<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

if (!isConnect('admin')) {
    throw new Exception('{{401 - Acceso no autorizado}}');
}

$dep_info = alexa_devices::dependancy_info();
if ($dep_info['state'] == 'nok') {
    echo '<div class="alert alert-warning" style="margin: 15px 0;"><i class="fas fa-exclamation-triangle"></i> {{Las dependencias del plugin están desactualizadas o incompletas. Por favor, pulsa en "Reinstalar" en la sección de Dependencias para aplicar las últimas actualizaciones de Amazon Alexa.}}</div>';
}
?>

<form class="form-horizontal">
    <fieldset>
        <legend><i class="fas fa-user-shield"></i> {{Autenticación Amazon Alexa}}</legend>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Servidor Amazon / Dominio}}</label>
            <div class="col-sm-4">
                <select class="configKey form-control" data-l1key="amazon_domain">
                    <option value="es">{{Amazon España (amazon.es)}}</option>
                    <option value="us">{{Amazon Estados Unidos (amazon.com)}}</option>
                    <option value="fr">{{Amazon Francia (amazon.fr)}}</option>
                    <option value="de">{{Amazon Alemania (amazon.de)}}</option>
                    <option value="it">{{Amazon Italia (amazon.it)}}</option>
                    <option value="gb">{{Amazon Reino Unido (amazon.co.uk)}}</option>
                    <option value="mx">{{Amazon México (amazon.com.mx)}}</option>
                    <option value="ca">{{Amazon Canadá (amazon.ca)}}</option>
                    <option value="jp">{{Amazon Japón (amazon.co.jp)}}</option>
                    <option value="br">{{Amazon Brasil (amazon.com.br)}}</option>
                    <option value="au">{{Amazon Australia (amazon.com.au)}}</option>
                </select>
            </div>
            <div class="col-sm-5 help-block">
                {{Selecciona el dominio de la tienda Amazon donde están registrados tus dispositivos Echo.}}
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Correo / Cuenta Amazon}}</label>
            <div class="col-sm-4">
                <input type="text" class="configKey form-control" data-l1key="amazon_username" placeholder="usuario@ejemplo.com" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Estado de Sesión}}</label>
            <div class="col-sm-4">
                <span id="span_auth_status" class="label label-default" style="font-size: 14px; padding: 6px 12px;">{{Verificando...}}</span>
            </div>
            <div class="col-sm-5">
                <button type="button" class="btn btn-primary" id="btn_open_login_modal">
                    <i class="fas fa-sign-in-alt"></i> {{Iniciar Sesión / 2FA OTP}}
                </button>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><i class="fas fa-cogs"></i> {{Configuración del Demonio Local}}</legend>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Puerto del Demonio}}</label>
            <div class="col-sm-3">
                <input type="number" class="configKey form-control" data-l1key="daemon_port" placeholder="55065" />
            </div>
            <div class="col-sm-6 help-block">
                {{Puerto TCP local para la API del demonio Python (por defecto: 55065).}}
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Intervalo de Polling (segundos)}}</label>
            <div class="col-sm-3">
                <input type="number" class="configKey form-control" data-l1key="poll_interval" placeholder="300" />
            </div>
            <div class="col-sm-6 help-block">
                {{Frecuencia de sincronización de seguridad con los servidores de Amazon (por defecto: 300s). La mayoría de eventos se reciben en tiempo real por push HTTP/2.}}
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Token Interno del Demonio}}</label>
            <div class="col-sm-4">
                <input type="text" class="configKey form-control" data-l1key="daemon_token" placeholder="{{Generado automáticamente}}" readonly />
            </div>
            <div class="col-sm-2">
                <button type="button" class="btn btn-default" id="btn_generate_token">
                    <i class="fas fa-random"></i> {{Regenerar}}
                </button>
            </div>
        </div>
    </fieldset>
</form>

<script>
    $('#btn_generate_token').on('click', function () {
        var token = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        $('.configKey[data-l1key=daemon_token]').val(token);
    });

    function checkAlexaAuthStatus() {
        $.ajax({
            type: 'POST',
            url: 'plugins/alexa_devices/core/ajax/alexa_devices.ajax.php',
            data: {
                action: 'getAuthStatus'
            },
            dataType: 'json',
            error: function (request, status, error) {
                $('#span_auth_status').removeClass('label-success label-danger').addClass('label-warning').text('{{Error de consulta}}');
            },
            success: function (data) {
                if (data.state != 'ok') {
                    $('#span_auth_status').removeClass('label-success label-warning').addClass('label-danger').text('{{Desconectado}}');
                    return;
                }
                if (data.result.authenticated) {
                    $('#span_auth_status').removeClass('label-danger label-warning').addClass('label-success').text('{{Sesión Activa: ' + (data.result.user || 'OK') + '}}');
                } else {
                    $('#span_auth_status').removeClass('label-success label-warning').addClass('label-danger').text('{{No autenticado}}');
                }
            }
        });
    }

    $('#btn_open_login_modal').on('click', function () {
        $('#md_modal').dialog({
            title: "{{Conexión y Autenticación con Amazon Alexa}}",
            height: 480,
            width: 600
        });
        $('#md_modal').load('index.php?v=d&plugin=alexa_devices&modal=login.modal').dialog('open');
    });

    checkAlexaAuthStatus();
</script>
