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

function alexaGetMessagePlaceholder(logicalId) {
    var map = {
        speak: '{{Text to speak (supports SSML)...}}',
        whisper: '{{Text to whisper...}}',
        announcement: '{{Announcement message...}}',
        text_command: '{{Voice command (e.g. turn off lights)...}}',
        music: '{{Song, artist or radio station...}}',
        sound: '{{Sound ID (e.g. amzn_sfx_doorbell_chime_01)...}}',
        routine: '{{Exact Amazon routine name...}}'
    };
    return map[logicalId] || '{{Value...}}';
}

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
    if (_cmd.type == 'info') {
        var stateVal = (_cmd.state !== undefined && _cmd.state !== null && _cmd.state !== '') ? String(_cmd.state) : '—';
        var unit = (_cmd.unite) ? ' ' + _cmd.unite : '';
        var dateTitle = (_cmd.valueDate) ? ' title="{{Last updated}}: ' + init(_cmd.valueDate) + '"' : '';
        if (_cmd.subType == 'binary') {
            var isOne = (stateVal === '1' || stateVal === 'true');
            var badgeCls = isOne ? 'label-success' : 'label-default';
            if (_cmd.logicalId === 'dnd_status') {
                badgeCls = isOne ? 'label-warning' : 'label-default';
                stateVal = isOne ? '{{ON}} (1)' : '{{OFF}} (0)';
            } else if (_cmd.logicalId === 'status') {
                stateVal = isOne ? '{{Online}} (1)' : '{{Offline}} (0)';
            }
            tr += '<span class="label ' + badgeCls + ' alexa-cmd-state"' + dateTitle + ' style="font-size:12px;">' + $('<span>').text(stateVal).html() + '</span>';
        } else {
            tr += '<span class="label label-primary alexa-cmd-state"' + dateTitle + ' style="font-size:12px; display:inline-block; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle;">' + $('<span>').text(stateVal + unit).html() + '</span>';
        }
    } else if (_cmd.subType == 'slider') {
        tr += '<div style="display:flex; gap:4px; align-items:center; flex-wrap:wrap;">';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" style="width:55px;" title="{{Min}}">';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" style="width:55px;" title="{{Max}}">';
        if (is_numeric(_cmd.id)) {
            tr += '<input type="number" class="form-control input-sm alexa-quick-val" data-subtype="slider" min="' + init(_cmd.configuration.minValue, 0) + '" max="' + init(_cmd.configuration.maxValue, 100) + '" placeholder="0-100" style="width:78px;">';
            tr += '<a class="btn btn-primary btn-xs btn-alexa-exec" title="{{Set value}}"><i class="fas fa-play"></i></a>';
        }
        tr += '</div>';
    } else if (_cmd.subType == 'select') {
        tr += '<input type="hidden" class="cmdAttr" data-l1key="configuration" data-l2key="listValue">';
        var listStr = init(_cmd.configuration.listValue);
        if (listStr) {
            tr += '<div style="display:flex; gap:4px; align-items:center;">';
            tr += '<select class="form-control input-sm alexa-quick-val" data-subtype="select" style="max-width:240px;">';
            var items = listStr.split(';');
            for (var i = 0; i < items.length; i++) {
                if (!items[i]) continue;
                var pair = items[i].split('|');
                var optVal = pair[0];
                var optLbl = (pair.length > 1) ? pair[1] : pair[0];
                tr += '<option value="' + $('<span>').text(optVal).html() + '">' + $('<span>').text(optLbl).html() + '</option>';
            }
            tr += '</select>';
            if (is_numeric(_cmd.id)) {
                tr += '<a class="btn btn-primary btn-xs btn-alexa-exec" title="{{Execute}}"><i class="fas fa-play"></i></a>';
            }
            tr += '</div>';
        } else {
            tr += '<span class="text-muted" style="font-size:11px;">{{No options synchronized yet}}</span>';
        }
    } else if (_cmd.subType == 'message') {
        if (is_numeric(_cmd.id)) {
            tr += '<div style="display:flex; gap:4px; align-items:center;">';
            tr += '<input type="text" class="form-control input-sm alexa-quick-val" data-subtype="message" placeholder="' + alexaGetMessagePlaceholder(init(_cmd.logicalId)) + '" style="min-width:180px;">';
            tr += '<a class="btn btn-primary btn-xs btn-alexa-exec" title="{{Execute}}"><i class="fas fa-play"></i></a>';
            tr += '</div>';
        }
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

$('#table_cmd').off('click', '.btn-alexa-exec').on('click', '.btn-alexa-exec', function () {
    var $tr = $(this).closest('tr.cmd');
    var cmdId = $tr.attr('data-cmd_id');
    var $input = $tr.find('.alexa-quick-val');
    var subType = $input.attr('data-subtype');
    var val = $input.val();
    var valueObj = {};
    if (subType === 'slider') {
        if (val === '' || val === null) return;
        valueObj = { slider: val };
    } else if (subType === 'select') {
        if (val === '' || val === null) return;
        valueObj = { select: val };
    } else if (subType === 'message') {
        if (!val) { $input.focus(); return; }
        valueObj = { title: '', message: val };
    }
    jeedom.cmd.execute({
        id: cmdId,
        value: valueObj,
        error: function (error) {
            $('#div_alert').showAlert({ message: error.message || error, level: 'danger' });
        },
        success: function () {
            $('#div_alert').showAlert({ message: '{{Command executed}}', level: 'success' });
        }
    });
});

$('#table_cmd').off('keypress', '.alexa-quick-val').on('keypress', '.alexa-quick-val', function (e) {
    if (e.which === 13) {
        e.preventDefault();
        $(this).closest('tr.cmd').find('.btn-alexa-exec').click();
    }
});
