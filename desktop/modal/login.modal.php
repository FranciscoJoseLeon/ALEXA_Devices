<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

if (!isConnect('admin')) {
    throw new Exception('{{401 - Acceso no autorizado}}');
}

$current_user = config::byKey('amazon_username', 'alexa_devices', '');
$current_domain = config::byKey('amazon_domain', 'alexa_devices', 'es');
?>

<div class="row">
    <div class="col-xs-12">
        <div id="div_login_alert" style="display:none;" class="alert"></div>

        <form class="form-horizontal" id="form_alexa_login">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                {{Introduce tus credenciales de Amazon y el código de verificación en dos pasos (OTP de tu app autenticadora o SMS).}}
            </div>

            <div class="form-group">
                <label class="col-sm-4 control-label">{{Dominio / País}}</label>
                <div class="col-sm-8">
                    <select id="modal_domain" class="form-control">
                        <option value="es" <?php if ($current_domain == 'es') echo 'selected'; ?>>Amazon España (.es)</option>
                        <option value="us" <?php if ($current_domain == 'us') echo 'selected'; ?>>Amazon Estados Unidos (.com)</option>
                        <option value="fr" <?php if ($current_domain == 'fr') echo 'selected'; ?>>Amazon Francia (.fr)</option>
                        <option value="de" <?php if ($current_domain == 'de') echo 'selected'; ?>>Amazon Alemania (.de)</option>
                        <option value="it" <?php if ($current_domain == 'it') echo 'selected'; ?>>Amazon Italia (.it)</option>
                        <option value="gb" <?php if ($current_domain == 'gb') echo 'selected'; ?>>Amazon Reino Unido (.co.uk)</option>
                        <option value="mx" <?php if ($current_domain == 'mx') echo 'selected'; ?>>Amazon México (.com.mx)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-4 control-label">{{Correo electrónico}}</label>
                <div class="col-sm-8">
                    <input type="email" id="modal_username" class="form-control" value="<?php echo htmlspecialchars($current_user); ?>" placeholder="tu_cuenta@amazon.es" required />
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-4 control-label">{{Contraseña}}</label>
                <div class="col-sm-8">
                    <input type="password" id="modal_password" class="form-control" placeholder="••••••••" required />
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-4 control-label">{{Código OTP (2FA)}}</label>
                <div class="col-sm-8">
                    <input type="text" id="modal_code" class="form-control" placeholder="123456" autocomplete="off" required />
                    <span class="help-block">{{Código de 6 dígitos generado por tu aplicación de autenticación o recibido por SMS.}}</span>
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-offset-4 col-sm-8">
                    <button type="button" class="btn btn-success" id="btn_submit_login">
                        <i class="fas fa-key"></i> {{Conectar con Amazon}}
                    </button>
                    <span id="spinner_login" style="display:none; margin-left:10px;">
                        <i class="fas fa-spinner fa-spin"></i> {{Conectando con Amazon...}}
                    </span>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    $('#btn_submit_login').on('click', function () {
        var username = $('#modal_username').val().trim();
        var password = $('#modal_password').val().trim();
        var code = $('#modal_code').val().trim();
        var domain = $('#modal_domain').val();

        if (!username || !password || !code) {
            $('#div_login_alert').removeClass('alert-success').addClass('alert-danger').text("{{Por favor, completa todos los campos.}}").show();
            return;
        }

        $('#btn_submit_login').prop('disabled', true);
        $('#spinner_login').show();
        $('#div_login_alert').hide();

        $.ajax({
            type: 'POST',
            url: 'plugins/alexa_devices/core/ajax/alexa_devices.ajax.php',
            data: {
                action: 'loginAmazon',
                username: username,
                password: password,
                code: code,
                domain: domain
            },
            dataType: 'json',
            error: function (request, status, error) {
                $('#btn_submit_login').prop('disabled', false);
                $('#spinner_login').hide();
                $('#div_login_alert').removeClass('alert-success').addClass('alert-danger').text("{{Error de red o comunicación: " + error + "}}").show();
            },
            success: function (data) {
                $('#btn_submit_login').prop('disabled', false);
                $('#spinner_login').hide();

                if (data.state != 'ok') {
                    $('#div_login_alert').removeClass('alert-success').addClass('alert-danger').text(data.result).show();
                    return;
                }

                $('#div_login_alert').removeClass('alert-danger').addClass('alert-success').text("{{Conexión establecida con éxito. Descubriendo dispositivos...}}").show();

                setTimeout(function () {
                    $('#md_modal').dialog('close');
                    if (typeof checkAlexaAuthStatus === 'function') {
                        checkAlexaAuthStatus();
                    }
                    window.location.reload();
                }, 2000);
            }
        });
    });
</script>
