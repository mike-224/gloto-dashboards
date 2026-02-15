<?php
/**
 * Sales Pulse Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SalesPulseWidget
 */
class SalesPulseWidget extends AbstractWidget {

	public function get_id() {
		return 'sales_pulse_widget';
	}

	public function get_title() {
		return '💓 Pulso de Ventas';
	}

	public function render( $range = 30, $compare = 'period' ) {
		// This widget always shows "Today vs Yesterday" and "This Month vs Last Month" for quick pulse
		// It ignores the global range filter for specific "Right Now" context
		$data = $this->get_pulse_data();

		ob_start();
		?>
		<div class="gloto-widget-card" id="<?php echo esc_attr( $this->get_id() ); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html( $this->get_title() ); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr( $this->get_id() ); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
			
			<table class="gloto-table">
				<thead>
					<tr>
						<th>Periodo</th>
						<th>Ventas</th>
						<th>Pedidos</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong>Hoy</strong></td>
						<td><?php echo wc_price( $data['today']['revenue'] ); ?></td>
						<td><?php echo number_format_i18n( $data['today']['orders'] ); ?></td>
					</tr>
					<tr>
						<td>Ayer</td>
						<td><?php echo wc_price( $data['yesterday']['revenue'] ); ?></td>
						<td><?php echo number_format_i18n( $data['yesterday']['orders'] ); ?></td>
					</tr>
					<tr style="border-top:2px solid var(--gloto-border)">
						<td><strong>Este Mes</strong></td>
						<td><?php echo wc_price( $data['month']['revenue'] ); ?></td>
						<td><?php echo number_format_i18n( $data['month']['orders'] ); ?></td>
					</tr>
					<tr>
						<td>Mes Pasado</td>
						<td><?php echo wc_price( $data['last_month']['revenue'] ); ?></td>
						<td><?php echo number_format_i18n( $data['last_month']['orders'] ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<style>
			.gloto-table { width: 100%; border-collapse: collapse; }
			.gloto-table th { text-align: left; padding: 8px; color: var(--gloto-text-muted); font-weight: 500; font-size: 13px; }
			.gloto-table td { padding: 8px; border-top: 1px solid var(--gloto-border); font-size: 14px; }
		</style>
		<?php
		return ob_get_clean();
	}

	private function get_pulse_data() {
		global $wpdb;

		$get_stats = function( $start, $end ) use ( $wpdb ) {
			return $wpdb->get_row( $wpdb->prepare( "
				SELECT 
					COALESCE(SUM(net_total), 0) as revenue,
					COUNT(order_id) as orders
				FROM {$wpdb->prefix}wc_order_stats
				WHERE date_created >= %s AND date_created <= %s
				AND status IN ('wc-completed', 'wc-processing')
			", $start, $end ), ARRAY_A );
		};

		// Today
		$today_start = current_time( 'Y-m-d 00:00:00' );
		$today_end   = current_time( 'Y-m-d 23:59:59' );
		
		// Yesterday
		$yesterday_start = date( 'Y-m-d 00:00:00', strtotime( '-1 day' ) );
		$yesterday_end   = date( 'Y-m-d 23:59:59', strtotime( '-1 day' ) );

		// This Month
		$month_start = date( 'Y-m-01 00:00:00' );
		$month_end   = current_time( 'Y-m-d 23:59:59' );

		// Last Month
		$last_month_start = date( 'Y-m-01 00:00:00', strtotime( 'first day of last month' ) );
		$last_month_end   = date( 'Y-m-t 23:59:59', strtotime( 'last month' ) );

		return [
			'today'      => $get_stats( $today_start, $today_end ),
			'yesterday'  => $get_stats( $yesterday_start, $yesterday_end ),
			'month'      => $get_stats( $month_start, $month_end ),
			'last_month' => $get_stats( $last_month_start, $last_month_end ),
		];
	}
}
