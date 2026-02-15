<?php
/**
 * LTV Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LTVWidget
 */
class LTVWidget extends AbstractWidget {

	public function get_id() {
		return 'ltv_widget';
	}

	public function get_title() {
		return '💎 Customer Lifetime Value';
	}

	public function render( $range = 30, $compare = 'period' ) {
		$data = $this->get_ltv_data();

		ob_start();
		?>
		<div class="gloto-widget-card" id="<?php echo esc_attr( $this->get_id() ); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html( $this->get_title() ); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr( $this->get_id() ); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
			
			<div class="gloto-ltv-periods">
				<div class="gloto-ltv-col">
					<span class="gloto-ltv-label">30 Días</span>
					<span class="gloto-ltv-val"><?php echo wc_price( $data['30_days'] ); ?></span>
				</div>
				<div class="gloto-ltv-col">
					<span class="gloto-ltv-label">90 Días</span>
					<span class="gloto-ltv-val"><?php echo wc_price( $data['90_days'] ); ?></span>
				</div>
				<div class="gloto-ltv-col">
					<span class="gloto-ltv-label">1 Año</span>
					<span class="gloto-ltv-val"><?php echo wc_price( $data['365_days'] ); ?></span>
				</div>
			</div>

			<div class="gloto-segments-list">
				<div class="gloto-segment-row">
					<span class="gloto-seg-name">🏅 VIP (>€500)</span>
					<span class="gloto-seg-count"><?php echo $data['segments']['vip']; ?> clientes</span>
				</div>
				<div class="gloto-segment-row">
					<span class="gloto-seg-name">🥈 Oro (€200-500)</span>
					<span class="gloto-seg-count"><?php echo $data['segments']['gold']; ?> clientes</span>
				</div>
			</div>
		</div>
		<style>
			.gloto-ltv-periods { display: flex; justify-content: space-between; margin-bottom: 20px; }
			.gloto-ltv-col { text-align: center; }
			.gloto-ltv-label { display: block; font-size: 11px; color: var(--gloto-text-muted); text-transform: uppercase; }
			.gloto-ltv-val { display: block; font-size: 18px; font-weight: bold; margin-top: 5px; color: var(--gloto-primary); }
			.gloto-segments-list { border-top: 1px solid var(--gloto-border); pt: 15px; }
			.gloto-segment-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; }
			.gloto-seg-name { font-weight: 500; }
		</style>
		<?php
		return ob_get_clean();
	}

	private function get_ltv_data() {
		global $wpdb;

		// Calculate Average Spent per Customer for different cohorts
		// Logic:
		// 1. Get total spent by ALL customers in the last X days
		// 2. Divide by unique customers in that period
		
		$get_avg_ltv = function( $days ) use ( $wpdb ) {
			$date = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );
			return $wpdb->get_var( $wpdb->prepare( "
				SELECT AVG(total_sales) 
				FROM {$wpdb->prefix}wc_customer_lookup
				WHERE date_last_active >= %s
			", $date ) );
		};

		// Segments (Total spend lifetime)
		$vip_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_customer_lookup WHERE total_sales >= 500" );
		$gold_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_customer_lookup WHERE total_sales >= 200 AND total_sales < 500" );

		return [
			'30_days' => $get_avg_ltv( 30 ) ?: 0,
			'90_days' => $get_avg_ltv( 90 ) ?: 0,
			'365_days' => $get_avg_ltv( 365 ) ?: 0,
			'segments' => [
				'vip' => $vip_count,
				'gold' => $gold_count
			]
		];
	}
}
