<?php
/**
 * Payment Health Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PaymentHealthWidget
 */
class PaymentHealthWidget extends AbstractWidget
{

    public function get_id()
    {
        return 'payment_health_widget';
    }

    public function get_title()
    {
        return '💳 Salud de Pagos';
    }

    public function render($range = 30, $compare = 'period')
    {
        $data = $this->get_payment_data($range);

        ob_start();
        ?>
		<div class="gloto-widget-card" id="<?php echo esc_attr($this->get_id()); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html($this->get_title()); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr($this->get_id()); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
			
			<div class="gloto-metric-main">
				<div class="gloto-metric-value"><?php echo number_format($data['rate'], 1); ?>%</div>
				<div class="gloto-metric-subtext">Tasa de Fallo Global</div>
			</div>

			<div class="gloto-list-rows">
				<?php foreach ($data['gateways'] as $method => $stats) : ?>
					<div class="gloto-list-row">
						<span class="gloto-row-title"><?php echo esc_html($method); ?></span>
						<span class="gloto-row-val <?php echo $stats['rate'] > 10 ? 'gloto-text-danger' : ''; ?>">
							<?php echo number_format($stats['rate'], 1); ?>%
						</span>
						<span class="gloto-row-sub">(<?php echo $stats['failed']; ?>/<?php echo $stats['total']; ?>)</span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ($data['rate'] > 10) : ?>
				<div class="gloto-alert-box">
					🚨 Revisar pasarelas de pago
				</div>
			<?php endif; ?>
		</div>
		<?php
        return ob_get_clean();
    }

    private function get_payment_data($range)
    {
        global $wpdb;

        $dates = $this->get_date_ranges($range, 'period');

        // Use wc_order_stats which has: order_id, status, net_total, date_created, customer_id
        // BUT it does NOT have payment_method_title!
        // We need to join with wp_wc_orders (HPOS) or wp_postmeta for payment method
        
        // Check if HPOS table exists
        $hpos_table = $wpdb->prefix . 'wc_orders';
        $hpos_exists = $wpdb->get_var("SHOW TABLES LIKE '{$hpos_table}'");
        
        if ($hpos_exists) {
            // HPOS: wc_orders has payment_method column
            $sql = $wpdb->prepare("
                SELECT 
                    o.payment_method as gateway,
                    os.status,
                    COUNT(os.order_id) as count
                FROM {$wpdb->prefix}wc_order_stats os
                JOIN {$wpdb->prefix}wc_orders o ON os.order_id = o.id
                WHERE os.date_created >= %s AND os.date_created <= %s
                GROUP BY o.payment_method, os.status
            ", $dates['current']['start'], $dates['current']['end']);
        } else {
            // Legacy: use postmeta
            $sql = $wpdb->prepare("
                SELECT 
                    pm.meta_value as gateway,
                    os.status,
                    COUNT(os.order_id) as count
                FROM {$wpdb->prefix}wc_order_stats os
                JOIN {$wpdb->prefix}postmeta pm ON os.order_id = pm.post_id AND pm.meta_key = '_payment_method'
                WHERE os.date_created >= %s AND os.date_created <= %s
                GROUP BY pm.meta_value, os.status
            ", $dates['current']['start'], $dates['current']['end']);
        }

        $results = $wpdb->get_results($sql);

        $gateways = [];
        $total_failed = 0;
        $total_attempts = 0;

        foreach ($results as $row) {
            $gateway_name = $row->gateway ?: 'Desconocido';
            
            if (!isset($gateways[$gateway_name])) {
                $gateways[$gateway_name] = ['failed' => 0, 'total' => 0];
            }

            $gateways[$gateway_name]['total'] += $row->count;
            $total_attempts += $row->count;

            if (in_array($row->status, ['wc-failed', 'wc-cancelled'])) {
                $gateways[$gateway_name]['failed'] += $row->count;
                $total_failed += $row->count;
            }
        }

        foreach ($gateways as $method => &$stats) {
            $stats['rate'] = $stats['total'] > 0 ? ($stats['failed'] / $stats['total']) * 100 : 0;
        }

        $global_rate = $total_attempts > 0 ? ($total_failed / $total_attempts) * 100 : 0;

        return [
            'rate' => $global_rate,
            'gateways' => $gateways
        ];
    }
}
