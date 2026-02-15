<?php
/**
 * Lost Revenue Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LostRevenueWidget
 */
class LostRevenueWidget extends AbstractWidget
{

    public function get_id()
    {
        return 'lost_revenue_widget';
    }

    public function get_title()
    {
        return '💸 Ingresos Perdidos (Carritos)';
    }

    public function render($range = 30, $compare = 'period')
    {
        $data = $this->get_lost_revenue_data($range);

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
				<div class="gloto-metric-value"><?php echo wc_price($data['potential_revenue']); ?></div>
				<div class="gloto-metric-subtext"><?php echo $data['carts_count']; ?> carritos abandonados</div>
			</div>

			<div class="gloto-list-rows">
				<?php if (empty($data['recoverable'])) : ?>
					<div class="gloto-empty-state">No hay carritos recientes.</div>
				<?php else : ?>
					<?php foreach ($data['recoverable'] as $cart) : ?>
						<div class="gloto-list-row">
							<div class="gloto-cart-info">
								<span class="gloto-cart-email"><?php echo esc_html($cart['email']); ?></span>
								<span class="gloto-cart-date"><?php echo esc_html($cart['time_ago']); ?></span>
							</div>
							<div class="gloto-cart-action">
								<span class="gloto-cart-val"><?php echo wc_price($cart['total']); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
        return ob_get_clean();
    }

    private function get_lost_revenue_data($range)
    {
        global $wpdb;
        $dates = $this->get_date_ranges($range, 'period');

        // Use wc_order_stats which works with both HPOS and legacy
        // Look for pending/failed statuses as "abandoned"
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT order_id, date_created 
            FROM {$wpdb->prefix}wc_order_stats
            WHERE status IN ('wc-pending', 'wc-failed')
            AND date_created >= %s
            ORDER BY date_created DESC
            LIMIT 10
        ", $dates['current']['start']));

        $total = 0;
        $count = 0;
        $recoverable = [];

        foreach ($results as $row) {
            $order = wc_get_order($row->order_id);
            if (!$order) continue;

            $order_total = (float) $order->get_total();
            $total += $order_total;
            $count++;

            if (count($recoverable) < 5) {
                $recoverable[] = [
                    'email'   => $order->get_billing_email() ?: 'Visitante',
                    'total'   => $order_total,
                    'time_ago' => human_time_diff(strtotime($row->date_created), current_time('timestamp')) . ' atrás',
                ];
            }
        }

        return [
            'potential_revenue' => $total,
            'carts_count'       => $count,
            'recoverable'       => $recoverable
        ];
    }
}
