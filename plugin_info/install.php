<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function alexa_devices_install() {
    log::add('alexa_devices', 'info', 'Instalando plugin Alexa Devices...');
}

function alexa_devices_update() {
    log::add('alexa_devices', 'info', 'Actualizando plugin Alexa Devices...');
}

function alexa_devices_remove() {
    log::add('alexa_devices', 'info', 'Desinstalando plugin Alexa Devices...');
    try {
        alexa_devices::deamon_stop();
    } catch (Exception $e) {
        log::add('alexa_devices', 'error', 'Error al detener demonio durante la desinstalación: ' . $e->getMessage());
    }
}
