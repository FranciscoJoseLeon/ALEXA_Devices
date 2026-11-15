<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

if (!isConnect('admin')) {
    throw new Exception('{{401 - Unauthorized access}}');
}

$current_user = config::byKey('amazon_username', 'alexa_devices', '');
$current_domain = config::byKey('amazon_domain', 'alexa_devices', 'es');
$auth_token = sha1(config::byKey('amazon_token', 'alexa_devices', '') . microtime() . rand());
cache::set('alexa_devices_auth_token', $auth_token, 1800);
?>

<div class="row">
    <div class="col-xs-12">
        <div id="div_login_alert" style="display:none;" class="alert"></div>

        <div class="form-horizontal">
            <!-- Sélecteur de domaine / pays -->
            <div class="form-group">
                <label class="col-sm-3 control-label">{{Domain / Country}}</label>
                <div class="col-sm-9">
                    <select id="modal_domain" class="form-control">
                        <option value="es" <?php if ($current_domain == 'es') echo 'selected'; ?>>Amazon Spain (.es)</option>
                        <option value="fr" <?php if ($current_domain == 'fr') echo 'selected'; ?>>Amazon France (.fr)</option>
                        <option value="de" <?php if ($current_domain == 'de') echo 'selected'; ?>>Amazon Germany (.de)</option>
                        <option value="it" <?php if ($current_domain == 'it') echo 'selected'; ?>>Amazon Italy (.it)</option>
                        <option value="gb" <?php if ($current_domain == 'gb') echo 'selected'; ?>>Amazon United Kingdom (.co.uk)</option>
                        <option value="us" <?php if ($current_domain == 'us') echo 'selected'; ?>>Amazon United States (.com)</option>
                        <option value="ca" <?php if ($current_domain == 'ca') echo 'selected'; ?>>Amazon Canada (.ca)</option>
                        <option value="mx" <?php if ($current_domain == 'mx') echo 'selected'; ?>>Amazon Mexico (.com.mx)</option>
                    </select>
                </div>
            </div>

            <!-- CARTE RECOMMANDÉE : PROXY OFFICIEL AMAZON -->
            <div class="panel panel-primary" style="margin-top: 15px; border-color: #337ab7;">
                <div class="panel-heading" style="background-color: #337ab7;">
                    <h3 class="panel-title">
                        <i class="fab fa-amazon"></i> {{Official Amazon Authentication (Recommended)}}
                    </h3>
                </div>
                <div class="panel-body text-center" style="padding: 20px;">
                    <p style="font-size: 14px; margin-bottom: 15px;">
                        {{This method opens the official Amazon verification window and supports all security options:}}
                    </p>
                    <div style="margin-bottom: 20px;">
                        <span class="label label-success" style="font-size: 13px; margin: 3px; padding: 5px 10px; display: inline-block;">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </span>
                        <span class="label label-info" style="font-size: 13px; margin: 3px; padding: 5px 10px; display: inline-block;">
                            <i class="fas fa-sms"></i> SMS
                        </span>
                        <span class="label label-primary" style="font-size: 13px; margin: 3px; padding: 5px 10px; display: inline-block;">
                            <i class="fas fa-mobile-alt"></i> {{Amazon App Approval}}
                        </span>
                        <span class="label label-warning" style="font-size: 13px; margin: 3px; padding: 5px 10px; display: inline-block;">
                            <i class="fas fa-shield-alt"></i> {{Authenticator / Passkey}}
                        </span>
                    </div>

                    <button type="button" class="btn btn-success btn-lg" id="btn_open_amazon_proxy" style="font-weight: bold; padding: 12px 28px;">
                        <i class="fab fa-amazon"></i> {{Open Official Amazon Login Window}}
                    </button>

                    <div id="div_proxy_status" style="display:none; margin-top: 20px;" class="alert alert-info">
                        <i class="fas fa-spinner fa-spin fa-2x" style="vertical-align: middle; margin-right: 10px;"></i>
                        <span style="font-size: 14px;" id="txt_proxy_status">
                            {{Waiting for sign-in on Amazon window... Once completed, this dialog will update automatically.}}
                        </span>
                    </div>
                </div>
            </div>

            <!-- OPTIONS AVANCÉES / MANUELLES (ACCORDÉON) -->
            <div style="margin-top: 15px;">
                <a role="button" data-toggle="collapse" href="#collapseManualAuth" aria-expanded="false" aria-controls="collapseManualAuth" style="text-decoration: none; color: #777;">
                    <i class="fas fa-cog"></i> {{Advanced options / Manual sign-in}} <i class="fas fa-chevron-down"></i>
                </a>

                <div class="collapse" id="collapseManualAuth" style="margin-top: 15px;">
                    <div class="well">
                        <!-- Option 1: Coller code ou URL retour -->
                        <h4>{{Paste authorization code or return URL}}</h4>
                        <p class="text-muted" style="font-size: 12px;">
                            {{If the automatic window does not close or you completed sign-in in another browser, paste the final return URL or authorization code below:}}
                        </p>
                        <div class="input-group" style="margin-bottom: 20px;">
                            <input type="text" id="manual_auth_code" class="form-control" placeholder="https://www.amazon.../ap/maplanding?openid.oa2.authorization_code=..." />
                            <span class="input-group-btn">
                                <button class="btn btn-primary" type="button" id="btn_submit_manual_code">
                                    <i class="fas fa-check"></i> {{Validate}}
                                </button>
                            </span>
                        </div>

                        <!-- Option 2: Saisie directe classique TOTP -->
                        <h4>{{Direct credential login (for Authenticator App / TOTP)}}</h4>
                        <div class="form-group">
                            <label class="col-sm-4 control-label">{{Email address}}</label>
                            <div class="col-sm-8">
                                <input type="email" id="modal_username" class="form-control" value="<?php echo htmlspecialchars($current_user); ?>" placeholder="your_account@amazon.com" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-4 control-label">{{Password}}</label>
                            <div class="col-sm-8">
                                <input type="password" id="modal_password" class="form-control" placeholder="••••••••" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-4 control-label">{{OTP Code (2FA)}}</label>
                            <div class="col-sm-8">
                                <input type="text" id="modal_code" class="form-control" placeholder="123456" />
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-sm-offset-4 col-sm-8">
                                <button type="button" class="btn btn-default" id="btn_submit_direct_login">
                                    <i class="fas fa-sign-in-alt"></i> {{Submit credentials}}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var pollTimer = null;

    function stopAuthPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    // Ouvrir la fenêtre officielle Amazon (Proxy)
    $('#btn_open_amazon_proxy').on('click', function () {
        var domain = $('#modal_domain').val();
        var authToken = '<?php echo $auth_token; ?>';
        var proxyUrl = '/plugins/alexa_devices/core/php/proxy.php?action=start&domain=' + encodeURIComponent(domain) + '&auth_token=' + encodeURIComponent(authToken);

        var width = 600;
        var height = 750;
        var left = (screen.width - width) / 2;
        var top = (screen.height - height) / 2;

        var popup = window.open(
            proxyUrl,
            'AmazonAuthWindow',
            'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left + ',scrollbars=yes,resizable=yes'
        );

        if (!popup || popup.closed || typeof popup.closed == 'undefined') {
            $('#div_login_alert').removeClass('alert-success').addClass('alert-danger')
                .text("{{Pop-up blocked by your browser. Please allow pop-ups for this site or open manually: }} " + proxyUrl).show();
            return;
        }

        $('#div_login_alert').hide();
        $('#div_proxy_status').show();
        $('#txt_proxy_status').text("{{Waiting for sign-in on Amazon window... Once completed, this dialog will update automatically.}}");

        stopAuthPolling();
        pollTimer = setInterval(function () {
            $.ajax({
                type: 'POST',
                url: 'plugins/alexa_devices/core/ajax/alexa_devices.ajax.php',
                data: { action: 'getAuthStatus' },
                dataType: 'json',
                success: function (data) {
                    if (data.state == 'ok' && data.result && data.result.authenticated) {
                        stopAuthPolling();
                        try { if (popup && !popup.closed) popup.close(); } catch(e) {}

                        $('#div_proxy_status').removeClass('alert-info').addClass('alert-success');
                        $('#txt_proxy_status').html("<strong>{{Authentication successful!}}</strong> {{Discovering devices...}}");

                        setTimeout(function () {
                            $('#md_modal').dialog('close');
                            if (typeof checkAlexaAuthStatus === 'function') {
                                checkAlexaAuthStatus();
                            }
                            window.location.reload();
                        }, 2000);
                    }
                }
            });
        }, 1500);
    });

    // Validation du code ou URL manuelle
    $('#btn_submit_manual_code').on('click', function () {
        var code = $('#manual_auth_code').val().trim();
        var domain = $('#modal_domain').val();
        if (!code) return;

        $('#div_login_alert').hide();
        $.ajax({
            type: 'POST',
            url: 'plugins/alexa_devices/core/ajax/alexa_devices.ajax.php',
            data: {
                action: 'submitManualCode',
                code: code,
                domain: domain
            },
            dataType: 'json',
            success: function (data) {
                if (data.state == 'ok') {
                    $('#div_login_alert').removeClass('alert-danger').addClass('alert-success')
                        .text("{{Authentication validated successfully!}}").show();
                    setTimeout(function () {
                        $('#md_modal').dialog('close');
                        window.location.reload();
                    }, 1500);
                } else {
                    $('#div_login_alert').removeClass('alert-success').addClass('alert-danger').text(data.result).show();
                }
            }
        });
    });

    // Connexion classique par saisie directe
    $('#btn_submit_direct_login').on('click', function () {
        var username = $('#modal_username').val().trim();
        var password = $('#modal_password').val().trim();
        var code = $('#modal_code').val().trim();
        var domain = $('#modal_domain').val();

        if (!username || !password || !code) {
            $('#div_login_alert').removeClass('alert-success').addClass('alert-danger').text("{{Please complete all fields.}}").show();
            return;
        }

        $('#btn_submit_direct_login').prop('disabled', true);
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
                $('#btn_submit_direct_login').prop('disabled', false);
                $('#div_login_alert').removeClass('alert-success').addClass('alert-danger').text("{{Communication error: " + error + "}}").show();
            },
            success: function (data) {
                $('#btn_submit_direct_login').prop('disabled', false);
                if (data.state != 'ok') {
                    $('#div_login_alert').removeClass('alert-success').addClass('alert-danger').text(data.result).show();
                    return;
                }
                $('#div_login_alert').removeClass('alert-danger').addClass('alert-success').text("{{Connected successfully! Discovering devices...}}").show();
                setTimeout(function () {
                    $('#md_modal').dialog('close');
                    window.location.reload();
                }, 1500);
            }
        });
    });

    $('#md_modal').on('dialogclose', function () {
        stopAuthPolling();
    });
</script>
