jQuery(document).ready(function ($) {
    const api = {
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

    const Dashboard = {
        init: function () {
            this.range = $('#gloto-range-filter');
            this.compare = $('#gloto-compare-filter');
            this.container = $('#gloto-widgets-container');
            this.widgetIds = glotoSettings.widgetIds || [];

            this.bindEvents();
            this.initGrid();
        },

        bindEvents: function () {
            var self = this;
            $('#gloto-refresh-all').on('click', function () {
                self.initGrid();
            });

            this.range.on('change', function () { self.initGrid(); });
            this.compare.on('change', function () { self.initGrid(); });

            $(document).on('click', '.gloto-widget-refresh', function () {
                var widgetId = $(this).data('widget');
                var card = $(this).closest('.gloto-widget-card');
                self.refreshWidget(widgetId, card);
            });
        },

        initGrid: function () {
            this.container.empty();
            var self = this;

            if (this.widgetIds.length === 0) {
                this.container.html('<div class="notice notice-warning"><p>No se encontraron widgets.</p></div>');
                return;
            }

            // Create skeleton placeholders
            for (var i = 0; i < this.widgetIds.length; i++) {
                var id = this.widgetIds[i];
                var skeleton = '<div class="gloto-widget-card gloto-loading-skeleton" id="' + id + '">' +
                    '<div class="gloto-skeleton-body">' +
                    '<span class="spinner is-active" style="float:none;margin:0"></span> Cargando...' +
                    '</div></div>';
                this.container.append(skeleton);
            }

            // Load widgets one by one (sequential)
            this.loadNextWidget(0);
        },

        loadNextWidget: function (index) {
            if (index >= this.widgetIds.length) {
                return; // All done
            }

            var self = this;
            var widgetId = this.widgetIds[index];
            var card = $('#' + widgetId);

            api.get('/widgets/' + widgetId, {
                range: this.range.val(),
                compare: this.compare.val()
            }).done(function (html) {
                card.replaceWith(html);
            }).fail(function () {
                card.html('<div class="gloto-widget-error">Error cargando este widget.</div>');
            }).always(function () {
                self.loadNextWidget(index + 1);
            });
        },

        refreshWidget: function (id, card) {
            card.find('.gloto-widget-refresh .dashicons').addClass('spin');

            api.get('/widgets/' + id, {
                range: this.range.val(),
                compare: this.compare.val()
            }).done(function (html) {
                card.replaceWith(html);
            }).fail(function () {
                card.find('.dashicons').removeClass('spin');
                alert('Error actualizando widget');
            });
        }
    };

    Dashboard.init();
});
