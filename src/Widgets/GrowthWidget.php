<?php
/**
 * Growth Widget - ULTRA MINIMAL for debugging
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

class GrowthWidget extends AbstractWidget
{

    public function get_id()
    {
        return 'growth_widget';
    }

    public function get_title()
    {
        return 'Crecimiento';
    }

    public function render($range = 30, $compare = 'period')
    {
        global $wpdb;

        $date_start = date('Y-m-d 00:00:00', strtotime("-{$range} days"));

        // Simple query
        $revenue = 0;
        $orders = 0;
        $new_customers = 0;

        $stats = $wpdb->get_row(
            $wpdb->prepare(
            "SELECT COALESCE(SUM(net_total), 0) as revenue, COUNT(order_id) as orders FROM {$wpdb->prefix}wc_order_stats WHERE date_created >= %s AND status IN ('wc-completed', 'wc-processing')",
            $date_start
        ),
            ARRAY_A
        );

        if ($stats) {
            $revenue = round((float)$stats['revenue'], 2);
            $orders = (int)$stats['orders'];
        }

        $new_customers = (int)$wpdb->get_var(
            $wpdb->prepare(
            "SELECT COUNT(customer_id) FROM {$wpdb->prefix}wc_customer_lookup WHERE date_registered >= %s",
            $date_start
        )
        );

        $aov = $orders > 0 ? round($revenue / $orders, 2) : 0;

        // Plain HTML — NO wc_price(), NO number_format_i18n(), NO esc_html()
        return '<div class="gloto-widget-card" id="growth_widget">
            <div class="gloto-widget-header">
                <h3 class="gloto-widget-title">🚀 Crecimiento</h3>
                <button class="gloto-widget-refresh" data-widget="growth_widget">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </div>
            <div class="gloto-metric-row">
                <div class="gloto-metric-item">
                    <span class="gloto-metric-label">Ingresos</span>
                    <div class="gloto-metric-value">' . number_format($revenue, 2) . ' &euro;</div>
                </div>
                <div class="gloto-metric-item">
                    <span class="gloto-metric-label">Pedidos</span>
                    <div class="gloto-metric-value">' . $orders . '</div>
                </div>
            </div>
            <div class="gloto-metric-row" style="margin-top:12px;border-top:1px solid #e0e0e0;padding-top:10px;">
                <div class="gloto-metric-item">
                    <span class="gloto-metric-label">Nuevos Clientes</span>
                    <div class="gloto-metric-value">' . $new_customers . '</div>
                </div>
                <div class="gloto-metric-item">
                    <span class="gloto-metric-label">AOV</span>
                    <div class="gloto-metric-value">' . number_format($aov, 2) . ' &euro;</div>
                </div>
            </div>
        </div>';
    }
}