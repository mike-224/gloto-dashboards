<?php
/**
 * Upsell Machine Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UpsellMachineWidget
 */
class UpsellMachineWidget extends AbstractWidget {

	public function get_id() {
		return 'upsell_machine_widget';
	}

	public function get_title() {
		return '🚀 Máquina de Upsell';
	}

	public function render( $range = 30, $compare = 'period' ) {
		$data = $this->get_upsell_data( $range );

		ob_start();
		?>
		<div class="gloto-widget-card" id="<?php echo esc_attr( $this->get_id() ); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html( $this->get_title() ); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr( $this->get_id() ); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
			
			<h4 class="gloto-section-title">🔥 Combos Más Vendidos</h4>
			<div class="gloto-list-rows">
				<?php if ( empty( $data['combos'] ) ) : ?>
					<div class="gloto-empty-state">No hay suficientes datos.</div>
				<?php else : ?>
					<?php foreach ( $data['combos'] as $combo ) : ?>
						<div class="gloto-list-row">
							<div class="gloto-prod-info">
								<span class="gloto-prod-name"><?php echo esc_html( $combo['names'] ); ?></span>
							</div>
							<div class="gloto-stats">
								<span class="gloto-count"><?php echo $combo['count']; ?>x </span>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<h4 class="gloto-section-title" style="margin-top:20px">📈 Escalera de Valor (Ladders)</h4>
			<div class="gloto-list-rows">
				<?php if ( empty( $data['ladders'] ) ) : ?>
					<div class="gloto-empty-state">No se detectaron secuencias.</div>
				<?php else : ?>
					<?php foreach ( $data['ladders'] as $ladder ) : ?>
						<div class="gloto-list-row">
							<div class="gloto-prod-info">
								<span class="gloto-prod-name"><?php echo esc_html( $ladder['first'] ); ?> ➔ <?php echo esc_html( $ladder['second'] ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<style>
			.gloto-section-title { font-size: 12px; text-transform: uppercase; color: var(--gloto-text-muted); margin: 0 0 10px; border-bottom: 2px solid var(--gloto-bg); padding-bottom: 5px; }
		</style>
		<?php
		return ob_get_clean();
	}

	private function get_upsell_data( $range ) {
		global $wpdb;
		$dates = $this->get_date_ranges( $range, 'period' );
		
		// Find Combos: Orders with multiple products
		// Simplified query to find frequent pairs in same order_id
		// This is a heavy query, limiting to recent 100 orders for performance in this context
		
		// 1. Get Order IDs with > 1 item
		$order_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT order_id 
			FROM {$wpdb->prefix}wc_order_product_lookup
			WHERE date_created >= %s
			GROUP BY order_id 
			HAVING COUNT(order_item_id) > 1
			LIMIT 100
		", $dates['current']['start'] ) );

		if ( empty( $order_ids ) ) return [ 'combos' => [], 'ladders' => [] ];

		// 2. Fetch items for these orders
		$order_ids_str = implode(',', $order_ids);
		$items = $wpdb->get_results( "
			SELECT order_id, product_id, order_item_name
			FROM {$wpdb->prefix}wc_order_product_lookup
			WHERE order_id IN ($order_ids_str)
		" );

		// 3. Process into pairs (Basic implementation)
		$pairs = [];
		$orders_mapped = [];
		foreach ( $items as $item ) {
			$orders_mapped[$item->order_id][] = $item;
		}

		foreach ( $orders_mapped as $oid => $prods ) {
			$count = count($prods);
			if ( $count < 2 ) continue;
			// Just take the first 2 distinct products for simple pair
			$name1 = $prods[0]->order_item_name;
			$name2 = $prods[1]->order_item_name;
			if ( $name1 == $name2 ) continue;
			
			$key = $name1 < $name2 ? "$name1 + $name2" : "$name2 + $name1";
			if ( ! isset( $pairs[$key] ) ) $pairs[$key] = 0;
			$pairs[$key]++;
		}

		arsort($pairs);
		$top_combos = [];
		$i = 0;
		foreach ( $pairs as $names => $count ) {
			$top_combos[] = [ 'names' => $names, 'count' => $count ];
			if ( ++$i >= 3 ) break;
		}

		// Ladders (Mock logic for now as it requires complex order sequencing per user)
		$ladders = [
			[ 'first' => 'Producto Front-End', 'second' => 'Producto High-Ticket' ]
		];

		return [
			'combos' => $top_combos,
			'ladders' => $ladders
		];
	}
}
