<?php
/**
 * Upsell Machine Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UpsellMachineWidget
 */
class UpsellMachineWidget extends AbstractWidget
{

    public function get_id()
    {
        return 'upsell_machine_widget';
    }

    public function get_title()
    {
        return '🚀 Máquina de Upsell';
    }

    public function render($range = 30, $compare = 'period')
    {
        $data = $this->get_upsell_data($range);

        ob_start();
        ?>
		<div class="gloto-widget-card" id="<?php echo esc_attr($this->get_id()); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html($this->get_title()); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr($this->get_id()); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
			
			<h4 class="gloto-section-title">🔥 Combos Más Vendidos</h4>
			<div class="gloto-list-rows">
				<?php if (empty($data['combos'])) : ?>
					<div class="gloto-empty-state">No hay suficientes datos.</div>
				<?php else : ?>
					<?php foreach ($data['combos'] as $combo) : ?>
						<div class="gloto-list-row">
							<div class="gloto-prod-info">
								<span class="gloto-prod-name"><?php echo esc_html($combo['names']); ?></span>
							</div>
							<div class="gloto-stats">
								<span class="gloto-count"><?php echo $combo['count']; ?>x</span>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<h4 class="gloto-section-title" style="margin-top:20px">📈 Productos High-AOV</h4>
			<div class="gloto-list-rows">
				<?php if (empty($data['high_aov'])) : ?>
					<div class="gloto-empty-state">No hay suficientes datos.</div>
				<?php else : ?>
					<?php foreach ($data['high_aov'] as $prod) : ?>
						<div class="gloto-list-row">
							<span class="gloto-prod-name"><?php echo esc_html($prod['name']); ?></span>
							<span class="gloto-count"><?php echo wc_price($prod['avg_order']); ?> AOV</span>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
        return ob_get_clean();
    }

    private function get_upsell_data($range)
    {
        global $wpdb;
        $dates = $this->get_date_ranges($range, 'period');

        // wc_order_product_lookup columns: order_item_id, order_id, product_id, variation_id,
        // customer_id, date_created, product_qty, product_net_revenue, etc.
        // NOTE: It does NOT have 'order_item_name'. We must join woocommerce_order_items for name.

        // 1. Get orders with > 1 item
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT order_id 
            FROM {$wpdb->prefix}wc_order_product_lookup
            WHERE date_created >= %s
            GROUP BY order_id 
            HAVING COUNT(order_item_id) > 1
            LIMIT 100
        ", $dates['current']['start']));

        $combos = [];

        if (!empty($order_ids)) {
            $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
            
            // 2. Get product names by joining woocommerce_order_items
            $items = $wpdb->get_results($wpdb->prepare("
                SELECT opl.order_id, opl.product_id, oi.order_item_name
                FROM {$wpdb->prefix}wc_order_product_lookup opl
                JOIN {$wpdb->prefix}woocommerce_order_items oi 
                    ON opl.order_item_id = oi.order_item_id
                WHERE opl.order_id IN ($placeholders)
            ", ...$order_ids));

            // 3. Build pairs
            $orders_mapped = [];
            foreach ($items as $item) {
                $orders_mapped[$item->order_id][] = $item->order_item_name;
            }

            $pairs = [];
            foreach ($orders_mapped as $oid => $names) {
                $names = array_unique($names);
                if (count($names) < 2) continue;
                $n = array_values($names);
                $key = $n[0] < $n[1] ? "{$n[0]} + {$n[1]}" : "{$n[1]} + {$n[0]}";
                if (!isset($pairs[$key])) $pairs[$key] = 0;
                $pairs[$key]++;
            }

            arsort($pairs);
            $i = 0;
            foreach ($pairs as $names => $count) {
                $combos[] = ['names' => $names, 'count' => $count];
                if (++$i >= 3) break;
            }
        }

        // 4. High AOV Products (Products appearing in orders with above-average totals)
        $high_aov = $wpdb->get_results($wpdb->prepare("
            SELECT 
                oi.order_item_name as name,
                AVG(os.net_total) as avg_order,
                COUNT(*) as times
            FROM {$wpdb->prefix}wc_order_product_lookup opl
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON opl.order_item_id = oi.order_item_id
            JOIN {$wpdb->prefix}wc_order_stats os ON opl.order_id = os.order_id
            WHERE opl.date_created >= %s
            AND os.status IN ('wc-completed', 'wc-processing')
            GROUP BY oi.order_item_name
            HAVING times >= 2
            ORDER BY avg_order DESC
            LIMIT 3
        ", $dates['current']['start']), ARRAY_A);

        return [
            'combos' => $combos,
            'high_aov' => $high_aov ?: []
        ];
    }
}
