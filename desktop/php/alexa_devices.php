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
                $deviceIcon = 'fas fa-podcast';
                if (stripos($family, 'KNIGHT') !== false || stripos($family, 'SHOW') !== false) {
                    $deviceIcon = 'fas fa-tablet-alt';
                } elseif (stripos($family, 'FIRE_TV') !== false) {
                    $deviceIcon = 'fas fa-tv';
                }

                echo '<div class="eqLogicDisplayCard cursor alexa-device-card ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
                echo '<div class="alexa-card-icon-wrapper">';
                echo '<i class="' . $deviceIcon . ' alexa-device-icon"></i>';
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
