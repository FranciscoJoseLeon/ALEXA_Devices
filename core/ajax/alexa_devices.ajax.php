<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Acceso no autorizado', __FILE__));
    }

    $action = init('action');

    if ($action == 'getAuthStatus') {
        try {
            $health = alexa_devices::callDaemon('/api/health', array(), 'GET');
            ajax::success($health);
        } catch (Exception $e) {
            ajax::error($e->getMessage());
        }
    }

    if ($action == 'loginAmazon') {
        $username = init('username');
        $password = init('password');
        $code = init('code');
        $domain = init('domain', 'es');

        if (empty($username) || empty($password) || empty($code)) {
            throw new Exception(__('Debes proporcionar usuario, contraseña y código OTP.', __FILE__));
        }

        $domainUrls = array(
            'es' => 'https://www.amazon.es',
            'us' => 'https://www.amazon.com',
            'fr' => 'https://www.amazon.fr',
            'de' => 'https://www.amazon.de',
            'it' => 'https://www.amazon.it',
            'gb' => 'https://www.amazon.co.uk',
            'mx' => 'https://www.amazon.com.mx',
            'ca' => 'https://www.amazon.ca',
            'jp' => 'https://www.amazon.co.jp',
            'br' => 'https://www.amazon.com.br',
            'au' => 'https://www.amazon.com.au',
        );

        $site = isset($domainUrls[$domain]) ? $domainUrls[$domain] : 'https://www.amazon.' . $domain;

        $loginPayload = array(
            'username' => $username,
            'password' => $password,
            'code' => $code,
            'site' => $site
        );

        try {
            $result = alexa_devices::callDaemon('/api/login', $loginPayload, 'POST');
            config::save('amazon_username', $username, 'alexa_devices');
            config::save('amazon_domain', $domain, 'alexa_devices');
            ajax::success($result);
        } catch (Exception $e) {
            ajax::error($e->getMessage());
        }
    }

    if ($action == 'syncDevices') {
        try {
            $result = alexa_devices::syncDevices();
            ajax::success($result);
        } catch (Exception $e) {
            ajax::error($e->getMessage());
        }
    }

    throw new Exception(__('Ninguna acción válida proporcionada.', __FILE__));

} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
