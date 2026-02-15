jQuery(document).ready(function ($) {
    /* ─── Config ─── */
    var container = $('#gloto-widgets-container');
    var rangeFilter = $('#gloto-range-filter');
    var widgetIds = glotoSettings.widgetIds || [];
    var baseUrl = glotoSettings.apiUrl;

    /**
     * Simple GET using fetch — NO nonce, NO extra headers.
     * Returns a Promise that resolves with text (HTML).
     */
    function apiGet(endpoint, params) {
        var url = baseUrl + endpoint;
        if (params) {
            var qs = Object.keys(params).map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            }).join('&');
            url += '?' + qs;
        }
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin' // send cookies but NO nonce header
        }).then(function (resp) {
            if (!resp.ok) throw { status: resp.status, resp: resp };
            return resp.text();
        });
    }

    /* ─── Load all widgets ─── */
    function loadWidgets() {
        container.empty();

        if (widgetIds.length === 0) {
            container.html('<div class="notice notice-warning"><p>No hay widgets registrados.</p></div>');
            return;
        }

        // Create skeleton placeholders
        for (var i = 0; i < widgetIds.length; i++) {
            container.append(
                '<div class="gloto-widget-card" id="' + widgetIds[i] + '" style="padding:30px;text-align:center;">' +
                '<span class="spinner is-active" style="float:none;"></span> Cargando ' + widgetIds[i] + '...' +
                '</div>'
            );
        }

        // Load sequentially
        loadNext(0);
    }

    function loadNext(index) {
        if (index >= widgetIds.length) return;

        var id = widgetIds[index];
        var card = $('#' + id);

        apiGet('/widgets/' + id, { range: rangeFilter.val() })
            .then(function (html) {
                console.log('✅ Widget ' + id + ' loaded');
                card.replaceWith(html);
            })
            .catch(function (err) {
                var status = err.status || '?';
                console.error('❌ Widget ' + id + ' failed:', status);

                // Try to get response body for debugging
                if (err.resp && err.resp.text) {
                    err.resp.text().then(function (body) {
                        card.html(
                            '<div style="padding:15px;color:#dc3232;">' +
                            '⚠️ Error en ' + id + ' (HTTP ' + status + ')' +
                            '<pre style="font-size:11px;max-height:100px;overflow:auto;margin-top:5px;">' + body.substring(0, 500) + '</pre>' +
                            '</div>'
                        );
                    });
                } else {
                    card.html(
                        '<div style="padding:15px;color:#dc3232;">' +
                        '⚠️ Error en ' + id + ' (HTTP ' + status + ')' +
                        '</div>'
                    );
                }
            })
            .finally(function () {
                loadNext(index + 1);
            });
    }

    /* ─── Start ─── */
    loadWidgets();

    /* ─── Refresh all ─── */
    $('#gloto-refresh-all').on('click', function () {
        loadWidgets();
    });

    rangeFilter.on('change', function () {
        loadWidgets();
    });

    /* ─── Refresh single widget ─── */
    $(document).on('click', '.gloto-widget-refresh', function () {
        var id = $(this).data('widget');
        var card = $(this).closest('.gloto-widget-card');
        card.html('<div style="padding:20px;text-align:center;"><span class="spinner is-active" style="float:none;"></span></div>');
        apiGet('/widgets/' + id, { range: rangeFilter.val() })
            .then(function (html) { card.replaceWith(html); })
            .catch(function () { card.html('<div style="padding:15px;color:#dc3232;">Error al recargar</div>'); });
    });
});
