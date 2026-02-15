jQuery(document).ready(function ($) {
    var api = {
        get: function (endpoint, data) {
            data = data || {};
            return $.ajax({
                url: glotoSettings.apiUrl + endpoint,
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', glotoSettings.nonce);
                },
                data: data
            });
        }
    };

    var container = $('#gloto-widgets-container');
    var rangeFilter = $('#gloto-range-filter');
    var widgetIds = glotoSettings.widgetIds || [];

    // 1) First test the API itself
    container.html('<div style="padding:20px;text-align:center;"><span class="spinner is-active" style="float:none;"></span> Probando conexión API...</div>');

    api.get('/test').done(function (response) {
        console.log('✅ API Test OK:', response);
        // API works! Now load widgets
        loadWidgets();
    }).fail(function (xhr) {
        console.error('❌ API Test FAILED:', xhr.status, xhr.responseText);
        container.html(
            '<div class="notice notice-error" style="margin:0;padding:15px;">' +
            '<p><strong>Error de conexión API</strong></p>' +
            '<p>Status: ' + xhr.status + '</p>' +
            '<p>La REST API del plugin no responde. Revisa el log de PHP del servidor.</p>' +
            '<pre style="background:#f5f5f5;padding:10px;overflow:auto;max-height:200px;">' + (xhr.responseText || 'Sin respuesta') + '</pre>' +
            '</div>'
        );
    });

    function loadWidgets() {
        container.empty();

        if (widgetIds.length === 0) {
            container.html('<div class="notice notice-warning"><p>No hay widgets registrados.</p></div>');
            return;
        }

        // Create skeleton placeholders
        for (var i = 0; i < widgetIds.length; i++) {
            var skeleton = '<div class="gloto-widget-card" id="' + widgetIds[i] + '" style="padding:30px;text-align:center;">' +
                '<span class="spinner is-active" style="float:none;"></span> Cargando ' + widgetIds[i] + '...' +
                '</div>';
            container.append(skeleton);
        }

        // Load sequentially
        loadNext(0);
    }

    function loadNext(index) {
        if (index >= widgetIds.length) return;

        var id = widgetIds[index];
        var card = $('#' + id);

        api.get('/widgets/' + id, {
            range: rangeFilter.val()
        }).done(function (html) {
            console.log('✅ Widget ' + id + ' loaded');
            card.replaceWith(html);
        }).fail(function (xhr) {
            console.error('❌ Widget ' + id + ' failed:', xhr.status, xhr.responseText);
            card.html(
                '<div style="padding:15px;color:#dc3232;">' +
                '⚠️ Error en ' + id + ' (HTTP ' + xhr.status + ')' +
                '<pre style="font-size:11px;max-height:100px;overflow:auto;margin-top:5px;">' + (xhr.responseText || '') + '</pre>' +
                '</div>'
            );
        }).always(function () {
            loadNext(index + 1);
        });
    }

    // Refresh all button
    $('#gloto-refresh-all').on('click', function () {
        loadWidgets();
    });

    rangeFilter.on('change', function () {
        loadWidgets();
    });

    // Delegate refresh per widget
    $(document).on('click', '.gloto-widget-refresh', function () {
        var id = $(this).data('widget');
        var card = $(this).closest('.gloto-widget-card');
        card.html('<div style="padding:20px;text-align:center;"><span class="spinner is-active" style="float:none;"></span></div>');
        api.get('/widgets/' + id, { range: rangeFilter.val() })
            .done(function (html) { card.replaceWith(html); })
            .fail(function () { card.html('<div style="padding:15px;color:#dc3232;">Error al recargar</div>'); });
    });
});
