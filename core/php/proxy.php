<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

$auth_token = init('auth_token', '');
$cached_token = cache::byKey('alexa_devices_auth_token')->getValue('');
$is_token_valid = (!empty($auth_token) && !empty($cached_token) && $auth_token === $cached_token);

if (!isConnect('admin') && !$is_token_valid) {
    http_response_code(401);
    die('401 - Accès non autorisé');
}

$daemon_port = config::byKey('daemon_port', 'alexa_devices', 55065);
$daemon_host = '127.0.0.1';

$action = init('action', '');
$target_url = init('url', '');
$domain = init('domain', 'es');

if (empty($target_url) && $action != 'start') {
    $domain_suffix = ($domain == 'us') ? 'com' : (($domain == 'gb') ? 'co.uk' : $domain);
    $query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    parse_str($query, $parsed);
    unset($parsed['auth_token']);
    unset($parsed['action']);
    unset($parsed['domain']);
    unset($parsed['url']);
    if (!empty($parsed)) {
        $target_url = "https://www.amazon.{$domain_suffix}/errors/validateCaptcha?" . http_build_query($parsed);
    }
}

$proxy_base = '/plugins/alexa_devices/core/php/proxy.php';
if (!empty($auth_token)) {
    $proxy_base .= '?auth_token=' . urlencode($auth_token);
}

if ($action == 'start') {
    $daemon_url = "http://{$daemon_host}:{$daemon_port}/proxy/start?domain=" . urlencode($domain) . "&proxy_base=" . urlencode($proxy_base);
} else {
    $daemon_url = "http://{$daemon_host}:{$daemon_port}/proxy/relay?url=" . urlencode($target_url);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $daemon_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$method = $_SERVER['REQUEST_METHOD'];
if ($method == 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    $input = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
}

$headers = array();
if (isset($_SERVER['CONTENT_TYPE'])) {
    $headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
}
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $headers[] = 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'];
}
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $headers[] = 'Accept-Language: ' . $_SERVER['HTTP_ACCEPT_LANGUAGE'];
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(502);
    $err = curl_error($ch);
    curl_close($ch);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Démon Alexa non démarré</title>';
    echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f4f6f9;color:#333;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;box-sizing:border-box;}';
    echo '.card{background:#fff;border-radius:10px;padding:30px;max-width:540px;box-shadow:0 6px 20px rgba(0,0,0,0.12);text-align:center;}';
    echo 'h2{color:#d9534f;margin-top:0;}p{line-height:1.6;color:#555;}ol{text-align:left;color:#444;line-height:1.9;background:#fdf2f2;border-left:4px solid #d9534f;padding:15px 15px 15px 35px;border-radius:4px;}';
    echo '.btn{display:inline-block;margin-top:15px;padding:10px 20px;background:#337ab7;color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;cursor:pointer;}';
    echo '</style></head><body>';
    echo '<div class="card">';
    echo '<h2>⚠️ Demonio de Alexa no iniciado</h2>';
    echo '<p>Para autenticarte con Amazon mediante el proxy oficial, el servicio local del plugin debe estar en funcionamiento en Jeedom.</p>';
    echo '<ol>';
    echo '<li>En Jeedom, ve a <b>Plugins &rarr; Gestión de plugins &rarr; ALEXA Devices</b>.</li>';
    echo '<li>Comprueba que las <b>Dependencias</b> estén en estado <b>OK</b> (si no, pulsa en <i>Relanzar</i>).</li>';
    echo '<li>En el bloque <b>Demonio</b>, pulsa el botón verde <b>(Re)Démarrer / Iniciar</b>.</li>';
    echo '<li>Una vez que el Demonio esté en verde (OK), vuelve a abrir esta ventana.</li>';
    echo '</ol>';
    echo '<p style="font-size:12px;color:#999;margin-top:20px;">Detalle de conexión: ' . htmlspecialchars($err) . '</p>';
    echo '<button class="btn" onclick="window.location.reload();">Reintentar</button>';
    echo '</div></body></html>';
    exit;
}

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$header_text = substr($response, 0, $header_size);
$body = substr($response, $header_size);

http_response_code($http_code);

foreach (explode("\r\n", $header_text) as $i => $line) {
    if ($i === 0 || empty($line)) continue;
    $lower = strtolower($line);
    if (strpos($lower, 'transfer-encoding:') === 0 || strpos($lower, 'content-length:') === 0) {
        continue;
    }
    if (strpos($lower, 'location:') === 0 && !empty($auth_token)) {
        if (strpos($line, 'auth_token=') === false) {
            $line .= (strpos($line, '?') !== false ? '&' : '?') . 'auth_token=' . urlencode($auth_token);
        }
    }
    header($line, false);
}

echo $body;
