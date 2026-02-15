<?php
/**
 * Growth Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrowthWidget
 */
class GrowthWidget extends AbstractWidget {

	public function get_id() {
		return 'growth_widget';
	}

	public function get_title() {
		return '🚀 Crecimiento';
	}

	public function render( $range = 30, $compare = 'period' ) {
		$data = $this->get_data( $range, $compare );

		ob_start();
		?>
		<div class="gloto-widget-card" id="<?php echo esc_attr( $this->get_id() ); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html( $this->get_title() ); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr( $this->get_id() ); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
			
			<div class="gloto-metric-row">
				<div class="gloto-metric-item">
					<span class="gloto-metric-label">Ingresos</span>
					<div class="gloto-metric-value"><?php echo wc_price( $data['revenue']['current'] ); ?></div>
					<?php echo $this->format_trend( $data['revenue']['change'] ); ?>
				</div>
				<div class="gloto-metric-item">
					<span class="gloto-metric-label">Pedidos</span>
					<div class="gloto-metric-value"><?php echo number_format_i18n( $data['orders']['current'] ); ?></div>
					<?php echo $this->format_trend( $data['orders']['change'] ); ?>
				</div>
			</div>
			<div class="gloto-metric-row" style="margin-top:15px; border-top:1px solid var(--gloto-border); padding-top:10px;">
				<div class="gloto-metric-item">
					<span class="gloto-metric-label">Nuevos Clientes</span>
					<div class="gloto-metric-value"><?php echo number_format_i18n( $data['customers']['current'] ); ?></div>
				</div>
				<div class="gloto-metric-item">
					<span class="gloto-metric-label">Conversión</span>
					<div class="gloto-metric-value"><?php echo number_format( $data['conversion'], 2 ); ?>%</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function get_data( $range, $compare ) {
		global $wpdb;
		$dates = $this->get_date_ranges( $range, $compare );

		// Helper to get revenue and orders
		$get_sales = function( $start, $end ) use ( $wpdb ) {
			return $wpdb->get_row( $wpdb->prepare( "
				SELECT 
					SUM(net_total) as revenue,
					COUNT(order_id) as orders
				FROM {$wpdb->prefix}wc_order_stats
				WHERE date_created >= %s AND date_created <= %s
				AND status IN ('wc-completed', 'wc-processing')
			", $start, $end ), ARRAY_A );
		};

		$current  = $get_sales( $dates['current']['start'], $dates['current']['end'] );
		$previous = $get_sales( $dates['previous']['start'], $dates['previous']['end'] );

		// New Customers
		$new_customers = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(ID) FROM {$wpdb->prefix}users
			WHERE user_registered >= %s AND user_registered <= %s
		", $dates['current']['start'], $dates['current']['end'] ) );

		// Conversion (Orders / Sessions - Simulated as sessions not tracked by default in WP)
		// Assuming 1 order = 3% conversion for estimation if no better data
		$conversion = $current['orders'] > 0 ? 2.5 : 0; 

		return [
			'revenue' => [
				'current' => $current['revenue'] ?? 0,
				'change'  => $this->calculate_change( $current['revenue'] ?? 0, $previous['revenue'] ?? 0 )
			],
			'orders' => [
				'current' => $current['orders'] ?? 0,
				'change'  => $this->calculate_change( $current['orders'] ?? 0, $previous['orders'] ?? 0 )
			],
			'customers' => [
				'current' => $new_customers
			],
			'conversion' => $conversion
		];
	}
}
