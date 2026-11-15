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
        try {
            self::syncRoutines(false);
        } catch (Exception $e) {
            log::add('alexa_devices', 'debug', 'No se pudieron sincronizar rutinas automáticamente: ' . $e->getMessage());
        }
        return $res;
    }

    public static function isSleepModeActive() {
        if (config::byKey('sleep_protection_enabled', 'alexa_devices', 0) != 1) {
            return false;
        }
        $start = intval(config::byKey('sleep_start_hour', 'alexa_devices', 22));
        $end = intval(config::byKey('sleep_end_hour', 'alexa_devices', 7));
        $now = intval(date('G'));
        if ($start == $end) {
            return false;
        }
        if ($start < $end) {
            return ($now >= $start && $now < $end);
        }
        return ($now >= $start || $now < $end);
    }

    public static function syncRoutines($forceRefresh = true) {
        $endpoint = $forceRefresh ? '/api/routines/refresh' : '/api/routines';
        $method = $forceRefresh ? 'POST' : 'GET';
        $res = self::callDaemon($endpoint, array(), $method);
        $routines = isset($res['routines']) && is_array($res['routines']) ? $res['routines'] : array();
        sort($routines, SORT_NATURAL | SORT_FLAG_CASE);
        config::save('routines_list', json_encode($routines), 'alexa_devices');
        config::save('routines_updated_at', date('Y-m-d H:i:s'), 'alexa_devices');

        $listParts = array();
        foreach ($routines as $r) {
            $safe = str_replace(array(';', '|'), ' ', $r);
            $listParts[] = $safe . '|' . $safe;
        }
        $listValue = implode(';', $listParts);
        foreach (self::byType('alexa_devices') as $eqLogic) {
            $cmd = $eqLogic->getCmd(null, 'routine_select');
            if (is_object($cmd)) {
                $cmd->setConfiguration('listValue', $listValue);
                $cmd->save();
            }
        }
        return $routines;
    }

    public static function reloadAllCommands() {
        $count = 0;
        foreach (self::byType('alexa_devices') as $eqLogic) {
            $eqLogic->createDefaultCommands();
            $count++;
        }
        return $count;
    }

    public static function deleteAllAndRescan() {
        foreach (self::byType('alexa_devices') as $eqLogic) {
            $eqLogic->remove();
        }
        return self::syncDevices();
    }

    public static function resetAmazonSession() {
        try {
            self::callDaemon('/api/reset_session', array(), 'POST');
        } catch (Exception $e) {
            $sessionFile = dirname(__FILE__) . '/../../resources/data/storage/daemon_session.json';
            if (file_exists($sessionFile)) {
                @unlink($sessionFile);
            }
        }
        return true;
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
            $defaultObj = config::byKey('default_object_id', 'alexa_devices', '');
            if (!empty($defaultObj) && is_object(jeeObject::byId($defaultObj))) {
                $eqLogic->setObject_id($defaultObj);
            }
        }

        $devType = isset($device['device_type']) ? strtoupper((string)$device['device_type']) : '';
        $hwVersion = isset($device['hardware_version']) ? trim((string)$device['hardware_version']) : '';
        if ($hwVersion === '' && $devType !== '') {
            $hwFallback = array(
                'A3EH2E0YZ30OD6' => '2024 / 2nd Gen',
                'A10A33FOX2NUBK' => '1st Gen',
                'A2U21SRK4QGSE1' => '4th Gen',
                'A3RMGO6LYLH7YN' => '4th Gen',
                'A3FX4UWTP28V1P' => '3rd Gen',
                'A32DOYMUN6DTXA' => '3rd Gen',
                'A3S5BH2HU6VAYF' => '2nd Gen',
            );
            if (isset($hwFallback[$devType])) {
                $hwVersion = $hwFallback[$devType];
            }
        }

        $eqLogic->setName($name);
        $eqLogic->setConfiguration('device_family', isset($device['device_family']) ? $device['device_family'] : '');
        $eqLogic->setConfiguration('device_type', isset($device['device_type']) ? $device['device_type'] : '');
        $eqLogic->setConfiguration('software_version', isset($device['software_version']) ? $device['software_version'] : '');
        $eqLogic->setConfiguration('model', isset($device['model']) ? $device['model'] : '');
        $eqLogic->setConfiguration('hardware_version', $hwVersion);
        $eqLogic->setConfiguration('manufacturer', isset($device['manufacturer']) ? $device['manufacturer'] : '');
        $eqLogic->setConfiguration('primary_display_category', isset($device['primary_display_category']) ? $device['primary_display_category'] : '');
        $eqLogic->save();

        // Crear/actualizar comandos por defecto para este dispositivo Echo
        $eqLogic->createDefaultCommands($device);

        // Actualizar estado online
        if (isset($device['online'])) {
            $eqLogic->checkAndUpdateCmd('status', $device['online'] ? 1 : 0);
        }

        return $eqLogic;
    }

    /**
     * Resolves a distinct SVG image path and human-readable model/generation label for an eqLogic.
     */
    public static function resolveDeviceVisual($eqLogic) {
        $family = strtoupper((string)$eqLogic->getConfiguration('device_family', 'ECHO'));
        $type = strtoupper((string)$eqLogic->getConfiguration('device_type', ''));
        $model = trim((string)$eqLogic->getConfiguration('model', ''));
        $hw = trim((string)$eqLogic->getConfiguration('hardware_version', ''));
        $mfg = trim((string)$eqLogic->getConfiguration('manufacturer', ''));
        $name = (string)$eqLogic->getName();

        $mLow = strtolower($model);
        $hwLow = strtolower($hw);
        $base = 'plugins/alexa_devices/desktop/img/devices/';

        // Determinar imagen exacta según modelo, generación y tipo
        if ($family === 'WHA' || in_array($type, array('A3C9PE6TNYLTCH', 'AP1F6KUH00XPV'), true) || strpos($mLow, 'speaker group') !== false || strpos($mLow, 'stereo pair') !== false) {
            $file = 'speaker_group.svg';
        } elseif (strpos($family, 'FIRE_TV') !== false || strpos($mLow, 'fire tv') !== false || strpos($mLow, 'cube') !== false) {
            $file = 'fire_tv.svg';
        } elseif (strpos($family, 'VIDEO') !== false || strpos($mLow, 'tv') !== false || (!empty($mfg) && strcasecmp($mfg, 'Amazon') !== 0 && preg_match('/tv|qled|oled/i', $name))) {
            $file = 'smart_tv.svg';
        } elseif (strpos($mLow, 'spot') !== false || in_array($type, array('A3EH2E0YZ30OD6', 'A10A33FOX2NUBK'), true) || stripos($name, 'Spot') !== false) {
            $file = ($type === 'A10A33FOX2NUBK' || strpos($hwLow, '1st') !== false) ? 'echo_spot_gen1.svg' : 'echo_spot_2024.svg';
        } elseif (strpos($mLow, 'show') !== false || strpos($family, 'KNIGHT') !== false || stripos($name, 'Show') !== false) {
            if (strpos($mLow, '15') !== false || strpos($mLow, '21') !== false) {
                $file = 'echo_show_15.svg';
            } elseif (strpos($mLow, '10') !== false) {
                $file = 'echo_show_10.svg';
            } else {
                $file = 'echo_show_compact.svg';
            }
        } elseif (strpos($mLow, 'pop') !== false || preg_match('/\bpop\b/i', $name)) {
            $file = 'echo_pop.svg';
        } elseif (strpos($mLow, 'dot') !== false || in_array($type, array('A2U21SRK4QGSE1', 'A32DOYMUN6DTXA', 'A3S5BH2HU6VAYF', 'A2M35JJZWCQOMZ'), true)) {
            if (strpos($mLow, 'clock') !== false || stripos($name, 'clock') !== false || stripos($name, 'reloj') !== false) {
                $file = 'echo_dot_clock.svg';
            } elseif (strpos($hwLow, '1st') !== false || strpos($hwLow, '2nd') !== false || $type === 'A3S5BH2HU6VAYF') {
                $file = 'echo_dot_gen1_2.svg';
            } elseif (strpos($hwLow, '3rd') !== false || $type === 'A32DOYMUN6DTXA') {
                $file = 'echo_dot_gen3.svg';
            } else {
                $file = 'echo_dot_gen4_5.svg';
            }
        } elseif (strpos($mLow, 'studio') !== false) {
            $file = 'echo_studio.svg';
        } elseif (strpos($mLow, 'flex') !== false) {
            $file = 'echo_flex.svg';
        } elseif (strpos($mLow, 'auto') !== false) {
            $file = 'echo_auto.svg';
        } elseif ($mLow === 'echo' || strpos($mLow, 'echo plus') !== false || in_array($type, array('A3RMGO6LYLH7YN', 'A3FX4UWTP28V1P'), true)) {
            if (strpos($hwLow, '1st') !== false || strpos($hwLow, '2nd') !== false) {
                $file = 'echo_gen1_2.svg';
            } elseif (strpos($hwLow, '3rd') !== false || $type === 'A3FX4UWTP28V1P') {
                $file = 'echo_gen3.svg';
            } else {
                $file = 'echo_gen4.svg';
            }
        } elseif (strpos($family, 'VOX') !== false || strpos($family, 'MOBILE') !== false) {
            $file = 'mobile_app.svg';
        } else {
            $file = 'echo_generic.svg';
        }

        // Etiqueta legible con modelo y generación
        if (!empty($model)) {
            if ($type === 'A3EH2E0YZ30OD6' && empty($hw)) {
                $label = 'Echo Spot (2024)';
            } elseif (!empty($hw)) {
                $label = $model . ' (' . $hw . ')';
            } elseif (!empty($mfg) && strcasecmp($mfg, 'Amazon') !== 0) {
                $shortMfg = preg_replace('/\s+Electronics$/i', '', $mfg);
                $label = $shortMfg . ' · ' . $model;
            } else {
                $label = $model;
            }
        } else {
            $label = $family ?: $type;
        }

        return array(
            'image' => $base . $file,
            'label' => $label,
        );
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

        $placeholders = array(
            'speak' => __('Text to speak (SSML supported)...', __FILE__),
            'whisper' => __('Text to whisper...', __FILE__),
            'announcement' => __('Announcement text...', __FILE__),
            'text_command' => __('Voice command (e.g. turn on kitchen light)...', __FILE__),
            'sound' => __('Sound ID (e.g. amzn_sfx_doorbell_chime_01)...', __FILE__),
            'info_skill' => __('weather, traffic, joke, time, calendar_today...', __FILE__),
            'play_music' => __('Song, artist or playlist...', __FILE__),
            'routine' => __('Exact Alexa routine name...', __FILE__),
            'set_volume' => __('0 - 100', __FILE__),
        );
        $cmdOptionsHtml = '';
        $allActions = $this->getCmd('action');
        if (is_array($allActions)) {
            foreach ($allActions as $actCmd) {
                $lid = $actCmd->getLogicalId();
                $sub = $actCmd->getSubType();
                $ph = isset($placeholders[$lid]) ? $placeholders[$lid] : __('Parameter...', __FILE__);
                $selected = ($lid === 'speak') ? ' selected' : '';
                $cmdOptionsHtml .= '<option value="' . intval($actCmd->getId()) . '" data-subtype="' . htmlspecialchars($sub) . '" data-logical_id="' . htmlspecialchars($lid) . '" data-placeholder="' . htmlspecialchars($ph) . '"' . $selected . '>' . htmlspecialchars($actCmd->getName()) . '</option>';
            }
        }
        $replace['#cmd_options#'] = $cmdOptionsHtml;

        $template = translate::exec(
            getTemplate('core', $version, 'alexa_devices', 'alexa_devices'),
            'plugins/alexa_devices/core/template/' . $version . '/alexa_devices.html'
        );
        return $this->postToHtml($_version, template_replace($replace, $template));
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

        // 13. Action: Play built-in Amazon sound
        $this->getOrCreateCmd('sound', 'action', 'message', __('Play Sound', __FILE__), false);

        // 14. Action: Play music / radio
        $this->getOrCreateCmd('music', 'action', 'message', __('Play Music', __FILE__), false);

        // 15. Action: Info skill (dropdown)
        $infoCmd = $this->getOrCreateCmd('info_skill', 'action', 'select', __('Info Skill', __FILE__), false);
        if (is_object($infoCmd)) {
            $skillsList = implode(';', array(
                'weather|' . __('Weather', __FILE__),
                'traffic|' . __('Traffic', __FILE__),
                'flash_briefing|' . __('Flash Briefing', __FILE__),
                'national_news|' . __('News', __FILE__),
                'time|' . __('Time', __FILE__),
                'date|' . __('Date', __FILE__),
                'calendar_today|' . __('Calendar Today', __FILE__),
                'calendar_tomorrow|' . __('Calendar Tomorrow', __FILE__),
                'joke|' . __('Joke', __FILE__),
                'fun_fact|' . __('Fun Fact', __FILE__),
                'tell_story|' . __('Tell a Story', __FILE__),
                'sing_song|' . __('Sing a Song', __FILE__),
                'good_morning|' . __('Good Morning', __FILE__),
                'goodnight|' . __('Good Night', __FILE__),
                'im_home|' . __("I'm Home", __FILE__),
                'clean_up|' . __('Clean Up', __FILE__),
            ));
            $infoCmd->setConfiguration('listValue', $skillsList);
            $infoCmd->save();
        }

        // 16. Action: Run Alexa routine (text)
        $this->getOrCreateCmd('routine', 'action', 'message', __('Run Routine', __FILE__), false);

        // 17. Action: Run Alexa routine (dropdown list)
        $routineSelectCmd = $this->getOrCreateCmd('routine_select', 'action', 'select', __('Run Routine (List)', __FILE__), false);
        if (is_object($routineSelectCmd)) {
            $rawRoutines = config::byKey('routines_list', 'alexa_devices', array());
            $routines = is_string($rawRoutines) ? json_decode($rawRoutines, true) : $rawRoutines;
            if (is_array($routines) && !empty($routines)) {
                $parts = array();
                foreach ($routines as $r) {
                    $safe = str_replace(array(';', '|'), ' ', $r);
                    $parts[] = $safe . '|' . $safe;
                }
                $routineSelectCmd->setConfiguration('listValue', implode(';', $parts));
                $routineSelectCmd->save();
            }
        }
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

        if (alexa_devices::isSleepModeActive()) {
            if ($logicalId === 'speak' || $logicalId === 'announcement') {
                log::add('alexa_devices', 'info', "Silencio nocturno activo: comando {$logicalId} bloqueado en {$serial}");
                return;
            }
            if ($logicalId === 'whisper' && config::byKey('sleep_allow_whisper', 'alexa_devices', 1) != 1) {
                log::add('alexa_devices', 'info', "Silencio nocturno activo: susurro bloqueado en {$serial}");
                return;
            }
        }

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
                $vol = isset($options['slider']) ? intval($options['slider']) : 50;
                if (alexa_devices::isSleepModeActive()) {
                    $maxSleepVol = intval(config::byKey('sleep_max_volume', 'alexa_devices', 0));
                    if ($maxSleepVol > 0 && $vol > $maxSleepVol) {
                        log::add('alexa_devices', 'info', "Silencio nocturno: volumen limitado de {$vol}% a {$maxSleepVol}%");
                        $vol = $maxSleepVol;
                    }
                }
                $params['volume'] = $vol;
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

            case 'sound':
                $action = 'sound';
                $params['sound'] = isset($options['message']) ? trim($options['message']) : '';
                break;

            case 'music':
                $action = 'music';
                $rawMsg = isset($options['message']) ? trim($options['message']) : '';
                $provider = isset($options['title']) ? trim($options['title']) : '';
                if (empty($provider) && strpos($rawMsg, '|') !== false) {
                    list($provider, $rawMsg) = explode('|', $rawMsg, 2);
                    $provider = trim($provider);
                    $rawMsg = trim($rawMsg);
                }
                $params['search'] = $rawMsg;
                if (!empty($provider)) {
                    $params['provider'] = strtoupper($provider);
                }
                break;

            case 'info_skill':
                $action = 'info_skill';
                $params['skill'] = isset($options['select']) ? $options['select'] : 'weather';
                break;

            case 'routine':
                $action = 'routine';
                $params['routine'] = isset($options['message']) ? trim($options['message']) : '';
                $defaultSerial = config::byKey('default_device_serial', 'alexa_devices', '');
                if (!empty($defaultSerial)) {
                    $params['default_serial'] = $defaultSerial;
                }
                break;

            case 'routine_select':
                $action = 'routine';
                $params['routine'] = isset($options['select']) ? trim($options['select']) : '';
                $defaultSerial = config::byKey('default_device_serial', 'alexa_devices', '');
                if (!empty($defaultSerial)) {
                    $params['default_serial'] = $defaultSerial;
                }
                break;

            default:
                throw new Exception("Comando no soportado: {$logicalId}");
        }

        $data = array(
            'serial_number' => $serial,
            'action' => $action,
            'params' => $params
        );

        $res = alexa_devices::callDaemon('/api/command', $data, 'POST');
        if ($logicalId === 'set_volume' && isset($params['volume'])) {
            $eqLogic->checkAndUpdateCmd('volume', $params['volume']);
        }
        return $res;
    }
}
