<?php
/**
 * Lost Revenue Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LostRevenueWidget
 */
class LostRevenueWidget extends AbstractWidget {

	public function get_id() {
		return 'lost_revenue_widget';
	}

	public function get_title() {
		return '💸 Ingresos Perdidos (Carritos)';
	}

	public function render( $range = 30, $compare = 'period' ) {
		$data = $this->get_lost_revenue_data( $range );

		ob_start();
		?>
		<div class="gloto-widget-card" id="<?php echo esc_attr( $this->get_id() ); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html( $this->get_title() ); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr( $this->get_id() ); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
			
			<div class="gloto-metric-main">
				<div class="gloto-metric-value"><?php echo wc_price( $data['potential_revenue'] ); ?></div>
				<div class="gloto-metric-subtext"><?php echo $data['carts_count']; ?> carritos abandonados</div>
			</div>

			<div class="gloto-list-rows">
				<?php if ( empty( $data['recoverable'] ) ) : ?>
					<div class="gloto-empty-state">No hay carritos recientes.</div>
				<?php else : ?>
					<?php foreach ( $data['recoverable'] as $cart ) : ?>
						<div class="gloto-list-row">
							<div class="gloto-cart-info">
								<span class="gloto-cart-email"><?php echo esc_html( $cart['email'] ); ?></span>
								<span class="gloto-cart-date"><?php echo $cart['time_ago']; ?></span>
							</div>
							<div class="gloto-cart-action">
								<span class="gloto-cart-val"><?php echo wc_price( $cart['total'] ); ?></span>
								<?php if ( $cart['has_coupon'] ) : ?>
									<span class="dashicons dashicons-tickets" title="Tiene cupón"></span>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<?php if ( $data['potential_revenue'] > 0 ) : ?>
				<div class="gloto-action-btn">
					<button class="button button-small">Recuperar Todo</button>
				</div>
			<?php endif; ?>
		</div>
		<style>
			.gloto-cart-info { display: flex; flex-direction: column; }
			.gloto-cart-email { font-size: 13px; font-weight: 500; }
			.gloto-cart-date { font-size: 11px; color: var(--gloto-text-muted); }
			.gloto-cart-action { text-align: right; }
			.gloto-cart-val { font-weight: bold; display: block; }
			.gloto-action-btn { margin-top: 15px; text-align: center; }
		</style>
		<?php
		return ob_get_clean();
	}

	private function get_lost_revenue_data( $range ) {
		global $wpdb;

		// Note: WooCommerce Sessions are stored in a serialized format in 'wp_woocommerce_sessions'
		// It's hard to query directly with SQL efficiently. 
		// For this implementation, we look for 'checkout-draft' orders or specific session keys if available.
		// A common pattern is to query 'wc-pending' orders older than 1 hour.

		$dates = $this->get_date_ranges( $range, 'period' );
		
		// Query stats for 'wc-pending' or 'wc-cancelled' (often used for abandoned)
		$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT ID, post_date 
			FROM {$wpdb->prefix}posts 
			WHERE post_type = 'shop_order' 
			AND post_status IN ('wc-pending', 'wc-failed')
			AND post_date >= %s
			ORDER BY post_date DESC
			LIMIT 10
		", $dates['current']['start'] ) );

		$total = 0;
		$count = 0;
		$recoverable = [];

		foreach ( $results as $order_post ) {
			$order = wc_get_order( $order_post->ID );
			if ( ! $order ) continue;

			$order_total = $order->get_total();
			$total += $order_total;
			$count++;

			if ( count( $recoverable ) < 5 ) {
				$recoverable[] = [
					'email' => $order->get_billing_email() ?: 'Visitante',
					'total' => $order_total,
					'time_ago' => human_time_diff( strtotime( $order_post->post_date ), current_time('timestamp') ) . ' atrás',
					'has_coupon' => count( $order->get_coupon_codes() ) > 0
				];
			}
		}

		return [
			'potential_revenue' => $total,
			'carts_count' => $count,
			'recoverable' => $recoverable
		];
	}
}
