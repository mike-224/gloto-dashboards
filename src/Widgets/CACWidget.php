<?php
/**
 * CAC Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CACWidget
 */
class CACWidget extends AbstractWidget {

	public function get_id() {
		return 'cac_widget';
	}

	public function get_title() {
		return '💰 CAC vs LTV';
	}

	public function render( $range = 30, $compare = 'period' ) {
		$data = $this->get_cac_data( $range );

		ob_start();
		?>
		<div class="gloto-widget-card" id="<?php echo esc_attr( $this->get_id() ); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html( $this->get_title() ); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr( $this->get_id() ); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
			
			<div class="gloto-cac-grid">
				<div class="gloto-cac-item">
					<span class="gloto-label">CAC (Est.)</span>
					<span class="gloto-value"><?php echo wc_price( $data['cac'] ); ?></span>
					<span class="gloto-sub">Marketing / Nuevos</span>
				</div>
				<div class="gloto-cac-item">
					<span class="gloto-label">LTV (Medio)</span>
					<span class="gloto-value"><?php echo wc_price( $data['ltv'] ); ?></span>
					<span class="gloto-sub">Valor de vida</span>
				</div>
			</div>

			<div class="gloto-ratio-box">
				<div class="gloto-ratio-title">Ratio LTV:CAC</div>
				<div class="gloto-ratio-val <?php echo $this->get_ratio_class( $data['ratio'] ); ?>">
					<?php echo number_format( $data['ratio'], 1 ); ?>:1
					<?php if ( $data['ratio'] >= 3 ) : ?>
						✅
					<?php elseif ( $data['ratio'] >= 1 ) : ?>
						⚠️
					<?php else : ?>
						❌
					<?php endif; ?>
				</div>
				<div class="gloto-ratio-desc">
					<?php echo $this->get_ratio_desc( $data['ratio'] ); ?>
				</div>
			</div>
		</div>
		<style>
			.gloto-cac-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
			.gloto-cac-item { text-align: center; padding: 10px; background: var(--gloto-bg); border-radius: 8px; }
			.gloto-label { display: block; font-size: 12px; color: var(--gloto-text-muted); }
			.gloto-value { display: block; font-size: 18px; font-weight: bold; margin: 4px 0; }
			.gloto-sub { display: block; font-size: 10px; color: var(--gloto-text-muted); }
			.gloto-ratio-box { border-top: 1px solid var(--gloto-border); pt: 10px; text-align: center; }
			.gloto-ratio-val { font-size: 24px; font-weight: 800; margin: 5px 0; }
			.gloto-ratio-good { color: var(--gloto-success); }
			.gloto-ratio-warn { color: var(--gloto-warning); }
			.gloto-ratio-bad { color: var(--gloto-danger); }
		</style>
		<?php
		return ob_get_clean();
	}

	private function get_ratio_class( $ratio ) {
		if ( $ratio >= 3 ) return 'gloto-ratio-good';
		if ( $ratio >= 1 ) return 'gloto-ratio-warn';
		return 'gloto-ratio-bad';
	}

	private function get_ratio_desc( $ratio ) {
		if ( $ratio >= 3 ) return 'Negocio Rentable';
		if ( $ratio >= 1 ) return 'Margen bajo o nulo';
		return 'Perdiendo dinero';
	}

	private function get_cac_data( $range ) {
		// NOTE: Marketing spend is not in WC by default. 
		// Ideally we would hook into a settings field or integration.
		// For now, we simulate a "Manual Input" or placeholder.
		$marketing_spend = 1000; // Placeholder: €1000/month
		
		global $wpdb;
		$dates = $this->get_date_ranges( $range, 'period' ); // Just get ranges

		// New Customers in period
		$new_customers = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(ID) FROM {$wpdb->prefix}users
			WHERE user_registered >= %s AND user_registered <= %s
		", $dates['current']['start'], $dates['current']['end'] ) );

		if ( ! $new_customers ) $new_customers = 1; // Avoid division by zero

		$cac = $marketing_spend / $new_customers;

		// Calculate LTV (simplified Average)
		$avg_ltv = $wpdb->get_var( "
			SELECT AVG(total_sales) FROM {$wpdb->prefix}wc_customer_lookup
		" );

		$ratio = $cac > 0 ? $avg_ltv / $cac : 0;

		return [
			'cac' => $cac,
			'ltv' => $avg_ltv,
			'ratio' => $ratio
		];
	}
}
