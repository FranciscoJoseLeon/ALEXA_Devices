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
    echo "Erreur de communication avec le démon Alexa: " . curl_error($ch);
    curl_close($ch);
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
