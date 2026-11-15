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

$plugin = plugin::byId('alexa_devices');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

if (!function_exists('alexa_get_device_icon_svg')) {
    /**
     * Retourne une icône SVG géométrique adaptée au type d'appareil Alexa.
     */
    function alexa_get_device_icon_svg($family, $type, $name) {
        $f = strtoupper((string)$family);
        $t = strtoupper((string)$type);
        $n = (string)$name;
        $wrap = '<svg class="alexa-device-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">%s</svg>';

        // Smart TV / Fire TV / Vidéo
        if (strpos($f, 'FIRE_TV') !== false || strpos($f, 'VIDEO') !== false || preg_match('/\b(tv|fire\s*tv)\b/i', $n)) {
            return sprintf($wrap, '<rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>');
        }
        // Groupe multi-pièces (Whole Home Audio)
        if ($f === 'WHA' || $t === 'A3C9PE6TNYLTCH') {
            return sprintf($wrap, '<rect x="2" y="6" width="8" height="13" rx="2"/><circle cx="6" cy="14" r="1.5"/><rect x="14" y="6" width="8" height="13" rx="2"/><circle cx="18" cy="14" r="1.5"/><path d="M10 4c1.3-1 2.7-1 4 0"/>');
        }
        // Echo Show (écran)
        if (strpos($f, 'KNIGHT') !== false || strpos($f, 'SHOW') !== false || stripos($n, 'Show') !== false) {
            return sprintf($wrap, '<rect x="3" y="4" width="18" height="13" rx="2"/><circle cx="12" cy="6.5" r="0.6" fill="currentColor"/><path d="M7 20h10l-1.5-3h-7z"/>');
        }
        // Echo Spot (horloge / écran rond)
        if (in_array($t, array('A3EH2E0YZ30OD6', 'A10A33FOX2NUBK'), true) || stripos($n, 'Spot') !== false) {
            return sprintf($wrap, '<circle cx="12" cy="11" r="8"/><path d="M12 7v4l2.5 2M7 20h10"/>');
        }
        // Téléphone / Tablette
        if (strpos($f, 'VOX') !== false || strpos($f, 'MOBILE') !== false) {
            return sprintf($wrap, '<rect x="7" y="2" width="10" height="20" rx="2"/><line x1="11" y1="18" x2="13" y2="18"/>');
        }
        // Echo Dot / Pop (enceinte compacte)
        if (in_array($t, array('A2U21SRK4QGSE1', 'A3RMGO6LYLH7YN', 'A3FX4UWTP28V1P', 'A1RABV2TMNGY4C', 'A32DOYMUN6DTXA', 'A2M35JJZWCQOMZ'), true) || preg_match('/\b(dot|pop)\b/i', $n)) {
            return sprintf($wrap, '<path d="M4.5 14a7.5 7.5 0 0 1 15 0c0 2.8-2 5-4.5 5H9c-2.5 0-4.5-2.2-4.5-5z"/><line x1="6" y1="14.5" x2="18" y2="14.5"/>');
        }
        // Enceinte Echo standard (cylindre)
        return sprintf($wrap, '<rect x="7" y="3" width="10" height="18" rx="4"/><line x1="7" y1="7" x2="17" y2="7"/><circle cx="12" cy="14" r="2"/>');
    }
}
?>

<div class="row row-overflow">
    <!-- SIDEBAR / DEVICE LIST -->
    <div class="col-xs-12 col-md-3 col-lg-2">
        <div class="bs-sidebar">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
                <li class="filter" style="margin-bottom: 5px;">
                    <input class="filter form-control input-sm" placeholder="{{Search...}}" style="width: 100%"/>
                </li>
                <?php
                foreach ($eqLogics as $eqLogic) {
                    $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                    echo '<li class="cursor li_eqLogic ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
                    echo '<a>' . $eqLogic->getHumanName(true) . '</a>';
                    echo '</li>';
                }
                ?>
            </ul>
        </div>
    </div>

    <!-- MAIN PANEL -->
    <div class="col-xs-12 col-md-9 col-lg-10 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
        <legend><i class="fas fa-cog"></i> {{Alexa Devices Management}}</legend>
        <div class="eqLogicThumbnailContainer">
            <div class="cursor eqLogicAction logoPrimary" id="bt_syncAlexaDevices">
                <i class="fas fa-sync"></i>
                <br>
                <span>{{Synchronize Devices}}</span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" onclick="window.location.href = 'index.php?v=d&p=plugin&id=alexa_devices';">
                <i class="fas fa-wrench"></i>
                <br>
                <span>{{Configuration}}</span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" id="bt_showPluginLog">
                <i class="fas fa-file-alt"></i>
                <br>
                <span>{{Logs}}</span>
            </div>
        </div>

        <legend><i class="fas fa-volume-up"></i> {{My Echo & Alexa Devices}}</legend>
        <div class="input-group" style="margin-bottom:5px;">
            <input class="form-control roundedLeft" placeholder="{{Filter devices...}}" id="in_searchEqlogic"/>
            <div class="input-group-btn">
                <a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>
            </div>
        </div>

        <div class="eqLogicThumbnailContainer">
            <?php
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                $online = $eqLogic->getCmd(null, 'status');
                $isOnline = (is_object($online) && $online->execCmd() == 1);
                $badge = $isOnline ? '<span class="label label-success">{{Online}}</span>' : '<span class="label label-danger">{{Offline}}</span>';

                $vol = $eqLogic->getCmd(null, 'volume');
                $volText = (is_object($vol)) ? '<span class="label label-info" style="font-size:9px;"><i class="fas fa-volume-up"></i> ' . intval($vol->execCmd()) . '%</span>' : '';

                $family = $eqLogic->getConfiguration('device_family', 'ECHO');
                $devType = $eqLogic->getConfiguration('device_type', '');
                $devName = $eqLogic->getName();

                echo '<div class="eqLogicDisplayCard cursor alexa-device-card ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
                echo '<div class="alexa-card-icon-wrapper">';
                echo alexa_get_device_icon_svg($family, $devType, $devName);
                echo '</div>';
                echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '<div class="alexa-card-badges">';
                echo $badge . ' ' . $volText;
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <!-- DEVICE EDIT PANEL -->
    <div class="col-xs-12 col-md-9 col-lg-10 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px; display: none;">
        <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fas fa-cogs"></i> {{Advanced Configuration}}</a>
        <a class="btn btn-default eqLogicAction pull-right" data-action="copy"><i class="fas fa-copy"></i> {{Duplicate}}</a>
        <a class="btn btn-sm btn-success eqLogicAction pull-right" data-action="save"><i class="fas fa-check-circle"></i> {{Save}}</a>
        <a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fas fa-minus-circle"></i> {{Delete}}</a>

        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Device}}</a></li>
            <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commands}}</a></li>
        </ul>

        <div class="tab-content" style="height:calc(100% - 50px); overflow:auto; overflow-x: hidden;">
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <br/>
                <form class="form-horizontal">
                    <fieldset>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Device Name}}</label>
                            <div class="col-sm-4">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                                <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Echo Name}}" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Parent Object}}</label>
                            <div class="col-sm-4">
                                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                    <option value="">{{None}}</option>
                                    <?php
                                    $options = '';
                                    foreach ((jeeObject::buildTree(null, false)) as $object) {
                                        $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                                    }
                                    echo $options;
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Category}}</label>
                            <div class="col-sm-8">
                                <?php
                                foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                                    echo '<label class="checkbox-inline">';
                                    echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                                    echo '</label>';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{State}}</label>
                            <div class="col-sm-9">
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Enable}}</label>
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Serial Number (Logical ID)}}</label>
                            <div class="col-sm-4">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="logicalId" readonly />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Device Family}}</label>
                            <div class="col-sm-4">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="device_family" readonly />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Device Type}}</label>
                            <div class="col-sm-4">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="device_type" readonly />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Software Version}}</label>
                            <div class="col-sm-4">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="software_version" readonly />
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>

            <div role="tabpanel" class="tab-pane" id="commandtab">
                <br/>
                <div class="panel panel-info">
                    <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#alexa_ssml_help">
                        <h4 class="panel-title" style="font-size:13px;">
                            <i class="fas fa-question-circle"></i> {{SSML Tags Help for Speak (TTS)}}
                            <i class="fas fa-chevron-down pull-right"></i>
                        </h4>
                    </div>
                    <div id="alexa_ssml_help" class="panel-collapse collapse">
                        <div class="panel-body" style="font-size:12px;">
                            <p>{{You can wrap your Speak (TTS) message in &lt;speak&gt;...&lt;/speak&gt; to customize Alexa's voice, or use the Whisper command directly:}}</p>
                            <table class="table table-condensed table-bordered" style="margin-bottom:0;">
                                <thead>
                                    <tr>
                                        <th>{{Effect}}</th>
                                        <th>{{SSML Tag Example}}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{Whispered voice}}</td>
                                        <td><code>&lt;speak&gt;&lt;amazon:effect name="whispered"&gt;{{Hello}}&lt;/amazon:effect&gt;&lt;/speak&gt;</code></td>
                                    </tr>
                                    <tr>
                                        <td>{{Pause}}</td>
                                        <td><code>&lt;speak&gt;{{One}} &lt;break time="1s"/&gt; {{Two}}&lt;/speak&gt;</code></td>
                                    </tr>
                                    <tr>
                                        <td>{{Volume (x-soft, soft, medium, loud, x-loud)}}</td>
                                        <td><code>&lt;speak&gt;&lt;prosody volume="soft"&gt;{{Hello}}&lt;/prosody&gt;&lt;/speak&gt;</code></td>
                                    </tr>
                                    <tr>
                                        <td>{{Speed (x-slow, slow, medium, fast, x-fast)}}</td>
                                        <td><code>&lt;speak&gt;&lt;prosody rate="slow"&gt;{{Hello}}&lt;/prosody&gt;&lt;/speak&gt;</code></td>
                                    </tr>
                                    <tr>
                                        <td>{{Pitch (x-low, low, medium, high, x-high)}}</td>
                                        <td><code>&lt;speak&gt;&lt;prosody pitch="low"&gt;{{Hello}}&lt;/prosody&gt;&lt;/speak&gt;</code></td>
                                    </tr>
                                    <tr>
                                        <td>{{Emphasis (strong, moderate, reduced)}}</td>
                                        <td><code>&lt;speak&gt;&lt;emphasis level="strong"&gt;{{Attention}}&lt;/emphasis&gt;&lt;/speak&gt;</code></td>
                                    </tr>
                                    <tr>
                                        <td>{{Read as digits / date / time}}</td>
                                        <td><code>&lt;speak&gt;&lt;say-as interpret-as="digits"&gt;1234&lt;/say-as&gt;&lt;/speak&gt;</code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <table id="table_cmd" class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th style="width: 250px;">{{Name}}</th>
                            <th style="width: 150px;">{{Type}}</th>
                            <th style="width: 200px;">{{Parameters}}</th>
                            <th style="width: 150px;">{{Options}}</th>
                            <th style="width: 100px;">{{Action}}</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_file('desktop', 'alexa_devices', 'css', 'alexa_devices'); ?>
<?php include_file('desktop', 'alexa_devices', 'js', 'alexa_devices'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
