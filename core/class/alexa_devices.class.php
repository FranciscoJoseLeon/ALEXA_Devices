<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class alexa_devices extends eqLogic {

    /* =========================================================================
     * GESTIÓN DEL DEMONIO (DAEMON)
     * ========================================================================= */

    public static function deamon_info() {
        $return = array();
        $return['log'] = 'alexa_devices_daemon';
        $return['state'] = 'nok';

        $pid_file = jeedom::getTmpFolder('alexa_devices') . '/daemon.pid';

        if (file_exists($pid_file)) {
            $pid = intval(trim(file_get_contents($pid_file)));
            if ($pid > 0 && posix_kill($pid, 0)) {
                $return['state'] = 'ok';
            } else {
                try {
                    $health = self::callDaemon('/api/health', array(), 'GET');
                    if (isset($health['status']) && $health['status'] == 'ok') {
                        $return['state'] = 'ok';
                    } else {
                        unlink($pid_file);
                    }
                } catch (Exception $e) {
                    unlink($pid_file);
                }
            }
        } else {
            try {
                $health = self::callDaemon('/api/health', array(), 'GET');
                if (isset($health['status']) && $health['status'] == 'ok') {
                    $return['state'] = 'ok';
                }
            } catch (Exception $e) {
                // Demonio inactivo
            }
        }

        $return['launchable'] = 'ok';
        $return['auto'] = (config::byKey('deamonAutoMode', 'alexa_devices', 1) == 1) ? 1 : 0;
        return $return;
    }

    public static function cron5() {
        $deamon_info = self::deamon_info();
        if ($deamon_info['auto'] == 1 && $deamon_info['state'] != 'ok') {
            log::add('alexa_devices', 'info', 'Watchdog: Daemon is stopped, restarting...');
            self::deamon_start();
        }
    }

    public static function deamon_start() {
        self::deamon_stop();
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Please verify plugin configuration and dependencies.', __FILE__));
        }

        $port = config::byKey('daemon_port', 'alexa_devices', 55065);
        $poll_interval = config::byKey('poll_interval', 'alexa_devices', 300);
        $token = config::byKey('daemon_token', 'alexa_devices');
        if (empty($token)) {
            $token = bin2hex(random_bytes(16));
            config::save('daemon_token', $token, 'alexa_devices');
        }

        $internal_ip = config::byKey('internalAddr');
        if (empty($internal_ip)) {
            $internal_ip = '127.0.0.1';
        }
        $jeedom_url = 'http://127.0.0.1/plugins/alexa_devices/core/php/jeeAlexaDevices.php';

        $path = realpath(dirname(__FILE__) . '/../../resources');
        $python_bin = $path . '/demond/venv/bin/python3';
        if (!file_exists($python_bin)) {
            $python_bin = 'python3';
        }

        $daemon_script = $path . '/demond/alexa_devices_daemon.py';
        $storage_dir = $path . '/data/storage';

        $cmd = $python_bin . ' ' . $daemon_script;
        $cmd .= ' --port ' . intval($port);
        $cmd .= ' --jeedom-url ' . escapeshellarg($jeedom_url);
        $cmd .= ' --token ' . escapeshellarg($token);
        $cmd .= ' --storage-dir ' . escapeshellarg($storage_dir);
        $cmd .= ' --poll-interval ' . intval($poll_interval);

        $log_level = log::getLogLevel('alexa_devices');
        $log_str = 'info';
        if ($log_level <= 100) $log_str = 'debug';
        elseif ($log_level >= 400) $log_str = 'error';
        $cmd .= ' --loglevel ' . $log_str;

        $log_file = log::getPathToLog('alexa_devices_daemon');
        $pid_file = jeedom::getTmpFolder('alexa_devices') . '/daemon.pid';
        $full_cmd = $cmd . ' >> ' . $log_file . ' 2>&1 & echo $! > ' . $pid_file;

        log::add('alexa_devices', 'info', 'Starting Alexa Devices daemon: ' . $full_cmd);
        exec($full_cmd);

        $i = 0;
        while ($i < 10) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'ok') {
                break;
            }
            sleep(1);
            $i++;
        }

        if ($i >= 10) {
            log::add('alexa_devices', 'error', 'Could not start Alexa Devices daemon');
            return false;
        }

        return true;
    }

    public static function deamon_stop() {
        $pid_file = jeedom::getTmpFolder('alexa_devices') . '/daemon.pid';
        if (file_exists($pid_file)) {
            $pid = intval(trim(file_get_contents($pid_file)));
            if ($pid > 0) {
                posix_kill($pid, SIGTERM);
                sleep(1);
                if (posix_kill($pid, 0)) {
                    posix_kill($pid, SIGKILL);
                }
            }
            unlink($pid_file);
        }

        // Asegurar que no quede ningún proceso huérfano bloqueando el puerto
        $port = config::byKey('daemon_port', 'alexa_devices', 55065);
        if (function_exists('exec')) {
            $sudo = (class_exists('system') && method_exists('system', 'getCmdSudo')) ? system::getCmdSudo() : 'sudo ';
            exec($sudo . 'fuser -k ' . intval($port) . '/tcp 2>/dev/null');
            exec($sudo . 'pkill -f "alexa_devices_daemon.py" 2>/dev/null');
        }

        log::add('alexa_devices', 'info', 'Alexa Devices daemon stopped.');
    }

    public static function dependancy_info() {
        $return = array();
        $return['log'] = log::getPathToLog('alexa_devices_dep');
        $return['progress_file'] = '/tmp/jeedom_install_in_progress_alexa_devices';
        $return['state'] = 'ok';

        $venv_py = dirname(__FILE__) . '/../../resources/demond/venv/bin/python3';
        $req_file = dirname(__FILE__) . '/../../resources/requirements.txt';

        if (!file_exists($venv_py)) {
            $return['state'] = 'nok';
            return $return;
        }

        // Obtener versión requerida en requirements.txt
        $target_version = '17.2.7';
        if (file_exists($req_file)) {
            $content = file_get_contents($req_file);
            if (preg_match('/aioamazondevices[>=~]=?([0-9.]+)/', $content, $m)) {
                $target_version = $m[1];
            }
        }

        // Comprobar versión instalada en el venv de Python
        $installed_version = trim(shell_exec($venv_py . ' -c "import importlib.metadata; print(importlib.metadata.version(\'aioamazondevices\'))" 2>/dev/null'));
        if (empty($installed_version) || version_compare($installed_version, $target_version, '<')) {
            $return['state'] = 'nok';
            log::add('alexa_devices', 'warning', "Dependencias desactualizadas: aioamazondevices instalada (" . ($installed_version ?: 'ninguna') . ") < requerida ({$target_version}). Reinstalación necesaria.");
        }

        if (file_exists($return['progress_file'])) {
            $return['state'] = 'in_progress';
        }

        return $return;
    }

    public static function dependancy_install() {
        log::remove('alexa_devices_dep');
        return array('script' => dirname(__FILE__) . '/../../resources/install_apt.sh', 'log' => log::getPathToLog('alexa_devices_dep'));
    }

    /* =========================================================================
     * COMUNICACIÓN CON EL DEMONIO LOCAL (REST API)
     * ========================================================================= */

    public static function callDaemon($endpoint, $data = array(), $method = 'POST') {
        $port = config::byKey('daemon_port', 'alexa_devices', 55065);
        $url = 'http://127.0.0.1:' . $port . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("Error de conexión con el demonio: " . $err);
        }

        $res = json_decode($response, true);
        if ($http_code >= 400) {
            $msg = isset($res['error']) ? $res['error'] : 'HTTP ' . $http_code;
            throw new Exception("Error del demonio: " . $msg);
        }

        return $res;
    }

    /* =========================================================================
     * SINCRONIZACIÓN Y CREACIÓN DE DISPOSITIVOS
     * ========================================================================= */

    public static function syncDevices() {
        $res = self::callDaemon('/api/sync', array(), 'POST');
        return $res;
    }

    public static function createOrUpdateDevice($device) {
        $serial = $device['serial_number'];
        $name = !empty($device['account_name']) ? $device['account_name'] : $serial;

        $eqLogic = self::byLogicalId($serial, 'alexa_devices');
        if (!is_object($eqLogic)) {
            $eqLogic = new self();
            $eqLogic->setLogicalId($serial);
            $eqLogic->setEqType_name('alexa_devices');
            $eqLogic->setIsEnable(1);
            $eqLogic->setIsVisible(1);
        }

        $eqLogic->setName($name);
        $eqLogic->setConfiguration('device_family', $device['device_family']);
        $eqLogic->setConfiguration('device_type', $device['device_type']);
        $eqLogic->setConfiguration('software_version', $device['software_version']);
        $eqLogic->save();

        // Crear/actualizar comandos por defecto para este dispositivo Echo
        $eqLogic->createDefaultCommands($device);

        // Actualizar estado online
        if (isset($device['online'])) {
            $eqLogic->checkAndUpdateCmd('status', $device['online'] ? 1 : 0);
        }

        return $eqLogic;
    }

    public function toHtml($_version = 'dashboard') {
        $replace = $this->preToHtml($_version);
        if (!is_array($replace)) {
            return $replace;
        }
        $version = jeedom::versionAlias($_version);

        $status = $this->getCmd(null, 'status');
        $isOnline = (is_object($status) && $status->execCmd() == 1);
        $replace['#status_display#'] = $isOnline
            ? '<span class="label label-success" style="font-size:10px;"><i class="fas fa-wifi"></i> ' . __('Online', __FILE__) . '</span>'
            : '<span class="label label-danger" style="font-size:10px;"><i class="fas fa-times-circle"></i> ' . __('Offline', __FILE__) . '</span>';

        $dnd = $this->getCmd(null, 'dnd_status');
        $isDnd = (is_object($dnd) && $dnd->execCmd() == 1);
        $replace['#dnd_display#'] = $isDnd
            ? '<span class="label label-warning" style="font-size:10px;"><i class="fas fa-moon"></i> DND</span>'
            : '';

        $vol = $this->getCmd(null, 'volume');
        $replace['#volume#'] = is_object($vol) ? intval($vol->execCmd()) : 50;

        $set_vol = $this->getCmd(null, 'set_volume');
        $replace['#set_volume_id#'] = is_object($set_vol) ? $set_vol->getId() : '';

        $media = $this->getCmd(null, 'media_state');
        $mState = is_object($media) ? $media->execCmd() : '';
        $replace['#media_state#'] = !empty($mState) ? htmlspecialchars($mState) : __('Idle', __FILE__);

        $last = $this->getCmd(null, 'last_command');
        $lastVal = is_object($last) ? $last->execCmd() : '';
        $replace['#last_command#'] = !empty($lastVal) ? htmlspecialchars($lastVal) : __('No recent commands', __FILE__);

        $actionCmds = array('play', 'pause', 'next', 'previous', 'text_command', 'speak');
        foreach ($actionCmds as $c) {
            $cmdObj = $this->getCmd(null, $c);
            $replace['#' . $c . '_id#'] = is_object($cmdObj) ? $cmdObj->getId() : '';
        }

        return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'alexa_devices', 'alexa_devices')));
    }

    public function createDefaultCommands($device = null) {
        // 1. Info: Online Status
        $this->getOrCreateCmd('status', 'info', 'binary', __('Online Status', __FILE__), false);

        // 2. Info: Current Volume
        $this->getOrCreateCmd('volume', 'info', 'numeric', __('Current Volume', __FILE__), false, '#slider#');

        // 3. Info: Media State (PLAYING, PAUSED, IDLE)
        $this->getOrCreateCmd('media_state', 'info', 'string', __('Media State', __FILE__), false);

        // 4. Info: Do Not Disturb
        $this->getOrCreateCmd('dnd_status', 'info', 'binary', __('Do Not Disturb', __FILE__), false);

        // 5. Info: Last Voice Command
        $this->getOrCreateCmd('last_command', 'info', 'string', __('Last Voice Command', __FILE__), false);

        // 6. Action: TTS (Speak)
        $this->getOrCreateCmd('speak', 'action', 'message', __('Speak (TTS)', __FILE__), true);

        // 6b. Action: Whisper (SSML whispered TTS)
        $this->getOrCreateCmd('whisper', 'action', 'message', __('Whisper', __FILE__), true);

        // 7. Action: Announcement (Chime + Speech)
        $this->getOrCreateCmd('announcement', 'action', 'message', __('Announcement', __FILE__), true);

        // 8. Action: Text command (Voice command emulation)
        $this->getOrCreateCmd('text_command', 'action', 'message', __('Text Command', __FILE__), true);

        // 9. Action: Set Volume (Slider 0-100)
        $volCmd = $this->getOrCreateCmd('set_volume', 'action', 'slider', __('Set Volume', __FILE__), true);
        if (is_object($volCmd)) {
            $volCmd->setConfiguration('minValue', 0);
            $volCmd->setConfiguration('maxValue', 100);
            $volCmd->save();
        }

        // 10. Action: Play / Pause / Next / Previous
        $this->getOrCreateCmd('play', 'action', 'other', __('Play', __FILE__), true);
        $this->getOrCreateCmd('pause', 'action', 'other', __('Pause', __FILE__), true);
        $this->getOrCreateCmd('next', 'action', 'other', __('Next', __FILE__), true);
        $this->getOrCreateCmd('previous', 'action', 'other', __('Previous', __FILE__), true);

        // 11. Action: DND ON / OFF
        $this->getOrCreateCmd('dnd_on', 'action', 'other', __('Enable Do Not Disturb', __FILE__), true);
        $this->getOrCreateCmd('dnd_off', 'action', 'other', __('Disable Do Not Disturb', __FILE__), true);

        // 12. Action: Restart Echo device
        $this->getOrCreateCmd('restart', 'action', 'other', __('Restart Echo', __FILE__), false);
    }

    private function getOrCreateCmd($logicalId, $type, $subType, $name, $isVisible = true, $template = null) {
        $cmd = $this->getCmd(null, $logicalId);
        if (!is_object($cmd)) {
            $cmd = new alexa_devicesCmd();
            $cmd->setLogicalId($logicalId);
            $cmd->setEqLogic_id($this->getId());
            $cmd->setName($name);
            $cmd->setType($type);
            $cmd->setSubType($subType);
            $cmd->setIsVisible($isVisible ? 1 : 0);
            if ($template) {
                $cmd->setTemplate('dashboard', $template);
                $cmd->setTemplate('mobile', $template);
            }
            $cmd->save();
        }
        return $cmd;
    }
}

class alexa_devicesCmd extends cmd {

    public function execute($options = array()) {
        $eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic)) {
            throw new Exception(__('Device not found', __FILE__));
        }

        $serial = $eqLogic->getLogicalId();
        $logicalId = $this->getLogicalId();

        log::add('alexa_devices', 'debug', "Ejecutando comando {$logicalId} en dispositivo {$serial}");

        $action = '';
        $params = array();

        switch ($logicalId) {
            case 'speak':
                $action = 'speak';
                $params['text'] = isset($options['message']) ? $options['message'] : '';
                break;

            case 'whisper':
                $action = 'speak';
                $rawText = isset($options['message']) ? trim($options['message']) : '';
                if (stripos($rawText, '<speak>') === 0) {
                    $params['text'] = $rawText;
                } else {
                    $escaped = htmlspecialchars($rawText, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                    $params['text'] = '<speak><amazon:effect name="whispered">' . $escaped . '</amazon:effect></speak>';
                }
                break;

            case 'announcement':
                $action = 'announcement';
                $params['text'] = isset($options['message']) ? $options['message'] : '';
                $params['title'] = isset($options['title']) && !empty($options['title']) ? $options['title'] : 'Jeedom';
                break;

            case 'text_command':
                $action = 'text_command';
                $params['command'] = isset($options['message']) ? $options['message'] : '';
                break;

            case 'set_volume':
                $action = 'set_volume';
                $params['volume'] = isset($options['slider']) ? intval($options['slider']) : 50;
                break;

            case 'play':
            case 'pause':
            case 'next':
            case 'previous':
                $action = 'media_command';
                $params['command'] = $logicalId;
                break;

            case 'dnd_on':
                $action = 'dnd';
                $params['enable'] = true;
                break;

            case 'dnd_off':
                $action = 'dnd';
                $params['enable'] = false;
                break;

            case 'restart':
                $action = 'restart';
                break;

            default:
                throw new Exception("Comando no soportado: {$logicalId}");
        }

        $data = array(
            'serial_number' => $serial,
            'action' => $action,
            'params' => $params
        );

        return alexa_devices::callDaemon('/api/command', $data, 'POST');
    }
}
