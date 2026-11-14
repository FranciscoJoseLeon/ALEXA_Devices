/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

$('#bt_syncAlexaDevices').on('click', function () {
    $('#bt_syncAlexaDevices').find('i').addClass('fa-spin');
    $.ajax({
        type: 'POST',
        url: 'plugins/alexa_devices/core/ajax/alexa_devices.ajax.php',
        data: {
            action: 'syncDevices'
        },
        dataType: 'json',
        error: function (request, status, error) {
            $('#bt_syncAlexaDevices').find('i').removeClass('fa-spin');
            $('#div_alert').showAlert({message: error, level: 'danger'});
        },
        success: function (data) {
            $('#bt_syncAlexaDevices').find('i').removeClass('fa-spin');
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: '{{Devices synchronized successfully}}', level: 'success'});
            window.location.reload();
        }
    });
});

$('#in_searchEqlogic').off('keyup').keyup(function () {
    var search = $(this).val().toLowerCase();
    $('.eqLogicDisplayCard').each(function () {
        var text = $(this).find('.name').text().toLowerCase();
        if (text.indexOf(search) >= 0) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});

$('#bt_resetSearch').on('click', function () {
    $('#in_searchEqlogic').val('').keyup();
});

$('#bt_showPluginLog').on('click', function () {
    if (typeof jeedom.log.showLog === 'function') {
        jeedom.log.showLog({logfile: 'alexa_devices'});
    } else {
        window.location.href = 'index.php?v=d&p=log&logfile=alexa_devices';
    }
});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }

    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id" style="display:none;"></span>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Name}}">';
    tr += '<span class="label label-info cmdAttr" data-l1key="logicalId"></span>';
    tr += '</td>';

    tr += '<td>';
    tr += '<span class="type label label-default">' + init(_cmd.type) + ' / ' + init(_cmd.subType) + '</span>';
    tr += '</td>';

    tr += '<td>';
    if (_cmd.subType == 'slider') {
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" style="width: 45%; display: inline-block;"> ';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" style="width: 45%; display: inline-block;">';
    }
    tr += '</td>';

    tr += '<td>';
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Visible}}</label> ';
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized"/>{{History}}</label>';
    tr += '</td>';

    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Test}}</a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a>';
    }
    tr += '</td>';
    tr += '</tr>';

    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
}
