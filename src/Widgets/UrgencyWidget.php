<?php
/**
 * Urgency Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UrgencyWidget
 */
class UrgencyWidget extends AbstractWidget {

	public function get_id() {
		return 'urgency_widget';
	}

	public function get_title() {
		return '⚡ Urgencia: Stock';
	}

	public function render( $range = 30, $compare = 'period' ) {
		$data = $this->get_urgency_data( $range );

		ob_start();
		?>
		<div class="gloto-widget-card" id="<?php echo esc_attr( $this->get_id() ); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html( $this->get_title() ); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr( $this->get_id() ); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
			
			<div class="gloto-list-rows">
				<?php if ( empty( $data ) ) : ?>
					<div class="gloto-empty-state">Todo el stock está correcto.</div>
				<?php else : ?>
					<?php foreach ( $data as $product ) : ?>
						<div class="gloto-list-row">
							<div class="gloto-prod-info">
								<span class="gloto-prod-name"><?php echo esc_html( $product['name'] ); ?></span>
								<span class="gloto-prod-velocity">Velocidad: <?php echo $product['velocity']; ?>/día</span>
							</div>
							<div class="gloto-stock-info">
								<span class="gloto-stock-val <?php echo $product['days_left'] < 7 ? 'gloto-text-danger' : 'gloto-text-warning'; ?>">
									<?php echo $product['stock']; ?> un.
								</span>
								<span class="gloto-days-left">
									<?php echo $product['days_left'] < 1 ? '¡HOY!' : $product['days_left'] . ' días'; ?>
								</span>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<style>
			.gloto-prod-info { display: flex; flex-direction: column; }
			.gloto-prod-name { font-weight: 500; font-size: 13px; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
			.gloto-prod-velocity { font-size: 10px; color: var(--gloto-text-muted); }
			.gloto-stock-info { text-align: right; }
			.gloto-stock-val { font-weight: bold; display: block; }
			.gloto-days-left { font-size: 10px; color: var(--gloto-text-muted); }
			.gloto-empty-state { text-align: center; padding: 20px; color: var(--gloto-text-muted); }
		</style>
		<?php
		return ob_get_clean();
	}

	private function get_urgency_data( $range ) {
		global $wpdb;
		
		// Find Fast Movers with Low Stock
		// 1. Calculate Velocity (Sales / Days) for products sold in range
		// 2. Check current Stock
		// 3. Estimate Days Left = Stock / Velocity
		
		$dates = $this->get_date_ranges( $range, 'period' );
		
		$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT 
				order_item_name as name,
				product_id,
				SUM(product_qty) as total_sold
			FROM {$wpdb->prefix}wc_order_product_lookup
			WHERE date_created >= %s AND date_created <= %s
			GROUP BY product_id
			ORDER BY total_sold DESC
			LIMIT 50
		", $dates['current']['start'], $dates['current']['end'] ) );

		$urgent_products = [];

		foreach ( $results as $row ) {
			$product = wc_get_product( $row->product_id );
			if ( ! $product || ! $product->managing_stock() ) continue;

			$stock = $product->get_stock_quantity();
			if ( $stock <= 0 ) continue; // Already OOS

			$velocity = $row->total_sold / $range; // avg per day
			if ( $velocity <= 0 ) continue;

			$days_left = $stock / $velocity;

			if ( $days_left <= 14 ) { // Alert if less than 2 weeks stock
				$urgent_products[] = [
					'name' => $product->get_name(),
					'velocity' => number_format( $velocity, 1 ),
					'stock' => $stock,
					'days_left' => round( $days_left )
				];
			}

			if ( count( $urgent_products ) >= 5 ) break; // Limit to Top 5 alerts
		}

		return $urgent_products;
	}
}
