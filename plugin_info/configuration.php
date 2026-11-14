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
    throw new Exception('{{401 - Unauthorized access}}');
}

$dep_info = alexa_devices::dependancy_info();
if ($dep_info['state'] == 'nok') {
    echo '<div class="alert alert-warning" style="margin: 15px 0;"><i class="fas fa-exclamation-triangle"></i> {{Plugin dependencies are outdated or missing. Please click "Reinstall" in the Dependencies section to apply the latest Amazon Alexa updates.}}</div>';
}
?>

<form class="form-horizontal">
    <fieldset>
        <legend><i class="fas fa-user-shield"></i> {{Amazon Alexa Authentication}}</legend>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Amazon Server / Domain}}</label>
            <div class="col-sm-4">
                <select class="configKey form-control" data-l1key="amazon_domain">
                    <option value="es">{{Amazon Spain (amazon.es)}}</option>
                    <option value="us">{{Amazon United States (amazon.com)}}</option>
                    <option value="fr">{{Amazon France (amazon.fr)}}</option>
                    <option value="de">{{Amazon Germany (amazon.de)}}</option>
                    <option value="it">{{Amazon Italy (amazon.it)}}</option>
                    <option value="gb">{{Amazon United Kingdom (amazon.co.uk)}}</option>
                    <option value="mx">{{Amazon Mexico (amazon.com.mx)}}</option>
                    <option value="ca">{{Amazon Canada (amazon.ca)}}</option>
                    <option value="jp">{{Amazon Japan (amazon.co.jp)}}</option>
                    <option value="br">{{Amazon Brazil (amazon.com.br)}}</option>
                    <option value="au">{{Amazon Australia (amazon.com.au)}}</option>
                </select>
            </div>
            <div class="col-sm-5 help-block">
                {{Select the Amazon store domain where your Echo devices are registered.}}
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Amazon Account / Email}}</label>
            <div class="col-sm-4">
                <input type="text" class="configKey form-control" data-l1key="amazon_username" placeholder="user@example.com" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Session Status}}</label>
            <div class="col-sm-4">
                <span id="span_auth_status" class="label label-default" style="font-size: 14px; padding: 6px 12px;">{{Checking...}}</span>
            </div>
            <div class="col-sm-5">
                <button type="button" class="btn btn-primary" id="btn_open_login_modal">
                    <i class="fas fa-sign-in-alt"></i> {{Sign In / 2FA OTP}}
                </button>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><i class="fas fa-cogs"></i> {{Local Daemon Configuration}}</legend>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Daemon Port}}</label>
            <div class="col-sm-3">
                <input type="number" class="configKey form-control" data-l1key="daemon_port" placeholder="55065" />
            </div>
            <div class="col-sm-6 help-block">
                {{Local TCP port for the Python daemon REST API (default: 55065).}}
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Polling Interval (seconds)}}</label>
            <div class="col-sm-3">
                <input type="number" class="configKey form-control" data-l1key="poll_interval" placeholder="300" />
            </div>
            <div class="col-sm-6 help-block">
                {{Backup polling frequency with Amazon servers (default: 300s). Most events are received in real-time via HTTP/2 push.}}
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Internal Daemon Token}}</label>
            <div class="col-sm-4">
                <input type="text" class="configKey form-control" data-l1key="daemon_token" placeholder="{{Automatically generated}}" readonly />
            </div>
            <div class="col-sm-2">
                <button type="button" class="btn btn-default" id="btn_generate_token">
                    <i class="fas fa-random"></i> {{Regenerate}}
                </button>
            </div>
        </div>
    </fieldset>

    <div class="alert alert-info" style="margin-top: 20px; font-size: 11px;">
        <i class="fas fa-info-circle"></i>
        {{This plugin is an independent community development and is not affiliated with or endorsed by Amazon.com, Inc. The software is provided "AS IS", without warranty of any kind.}}
    </div>
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
                $('#span_auth_status').removeClass('label-success label-danger').addClass('label-warning').text('{{Connection Error}}');
            },
            success: function (data) {
                if (data.state != 'ok') {
                    $('#span_auth_status').removeClass('label-success label-warning').addClass('label-danger').text('{{Disconnected}}');
                    return;
                }
                if (data.result.authenticated) {
                    $('#span_auth_status').removeClass('label-danger label-warning').addClass('label-success').text('{{Active Session: ' + (data.result.user || 'OK') + '}}');
                } else {
                    $('#span_auth_status').removeClass('label-success label-warning').addClass('label-danger').text('{{Not Authenticated}}');
                }
            }
        });
    }

    $('#btn_open_login_modal').on('click', function () {
        $('#md_modal').dialog({
            title: "{{Amazon Alexa Connection & Authentication}}",
            height: 480,
            width: 600
        });
        $('#md_modal').load('index.php?v=d&plugin=alexa_devices&modal=login.modal').dialog('open');
    });

    checkAlexaAuthStatus();
</script>
