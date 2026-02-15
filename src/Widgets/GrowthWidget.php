<?php
/**
 * Growth Widget - SIMPLIFIED
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
        return '🚀 Crecimiento';
    }

    public function render($range = 30, $compare = 'period')
    {
        global $wpdb;

        // Simple query: revenue and orders in last X days
        $date_start = date('Y-m-d 00:00:00', strtotime("-{$range} days"));

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COALESCE(SUM(net_total), 0) as revenue,
                COUNT(order_id) as orders
            FROM {$wpdb->prefix}wc_order_stats
            WHERE date_created >= %s
            AND status IN ('wc-completed', 'wc-processing')
        ", $date_start), ARRAY_A);

        $revenue = (float) ($stats['revenue'] ?? 0);
        $orders = (int) ($stats['orders'] ?? 0);

        // New customers (users registered in period)
        $new_customers = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(customer_id) 
            FROM {$wpdb->prefix}wc_customer_lookup
            WHERE date_registered >= %s
        ", $date_start));

        ob_start();
        ?>
		<div class="gloto-widget-card" id="<?php echo esc_attr($this->get_id()); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html($this->get_title()); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr($this->get_id()); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
			
			<div class="gloto-metric-row">
				<div class="gloto-metric-item">
					<span class="gloto-metric-label">Ingresos</span>
					<div class="gloto-metric-value"><?php echo wc_price($revenue); ?></div>
				</div>
				<div class="gloto-metric-item">
					<span class="gloto-metric-label">Pedidos</span>
					<div class="gloto-metric-value"><?php echo number_format_i18n($orders); ?></div>
				</div>
			</div>
			<div class="gloto-metric-row" style="margin-top:15px; border-top:1px solid var(--gloto-border); padding-top:10px;">
				<div class="gloto-metric-item">
					<span class="gloto-metric-label">Nuevos Clientes</span>
					<div class="gloto-metric-value"><?php echo number_format_i18n($new_customers); ?></div>
				</div>
				<div class="gloto-metric-item">
					<span class="gloto-metric-label">AOV</span>
					<div class="gloto-metric-value"><?php echo $orders > 0 ? wc_price($revenue / $orders) : wc_price(0); ?></div>
				</div>
			</div>
		</div>
		<?php
        return ob_get_clean();
    }
}
