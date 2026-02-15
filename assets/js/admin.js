jQuery(document).ready(function ($) {
    const api = {
        get: function (endpoint, data = {}) {
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

    const Dashboard = {
        init: function () {
            this.range = $('#gloto-range-filter');
            this.compare = $('#gloto-compare-filter');
            this.container = $('#gloto-widgets-container');

            this.bindEvents();
            this.loadWidgets();
        },

        bindEvents: function () {
            const self = this;
            $('#gloto-refresh-all').on('click', function () {
                self.loadWidgets();
            });

            this.range.on('change', function () { self.loadWidgets(); });
            this.compare.on('change', function () { self.loadWidgets(); });

            $(document).on('click', '.gloto-widget-refresh', function () {
                const widgetId = $(this).data('widget');
                self.refreshWidget(widgetId, $(this).closest('.gloto-widget-card'));
            });
        },

        loadWidgets: function () {
            const self = this;
            this.container.html('<div class="gloto-loading">Cargando métricas...</div>');

            api.get('/widgets', {
                range: this.range.val(),
                compare: this.compare.val()
            }).done(function (response) {
                self.renderWidgets(response);
            }).fail(function () {
                self.container.html('<div class="error">Error cargando datos.</div>');
            });
        },

        renderWidgets: function (data) {
            this.container.empty();
            // TODO: Iterate and render widgets
            // This will be populated as we build the widget API
        },

        refreshWidget: function (id, card) {
            card.addClass('gloto-updating');
            api.get('/widgets/' + id, {
                range: this.range.val(),
                compare: this.compare.val()
            }).done(function (html) {
                card.replaceWith(html);
            }).always(function () {
                card.removeClass('gloto-updating');
            });
        }
    };

    Dashboard.init();
});
