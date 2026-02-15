<?php
/**
 * Payment Health Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PaymentHealthWidget
 */
class PaymentHealthWidget extends AbstractWidget {

	public function get_id() {
		return 'payment_health_widget';
	}

	public function get_title() {
		return '💳 Salud de Pagos';
	}

	public function render( $range = 30, $compare = 'period' ) {
		$data = $this->get_payment_data( $range );

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
				<div class="gloto-metric-value"><?php echo number_format( $data['rate'], 1 ); ?>%</div>
				<div class="gloto-metric-subtext">Tasa de Fallo Global</div>
			</div>

			<div class="gloto-list-rows">
				<?php foreach ( $data['gateways'] as $method => $stats ) : ?>
					<div class="gloto-list-row">
						<span class="gloto-row-title"><?php echo esc_html( $method ); ?></span>
						<span class="gloto-row-val <?php echo $stats['rate'] > 10 ? 'gloto-text-danger' : ''; ?>">
							<?php echo number_format( $stats['rate'], 1 ); ?>%
						</span>
						<span class="gloto-row-sub">(<?php echo $stats['failed']; ?>/<?php echo $stats['total']; ?>)</span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $data['rate'] > 10 ) : ?>
				<div class="gloto-alert-box">
					🚨 Revisar pasarelas de pago
				</div>
			<?php endif; ?>
		</div>
		<style>
			.gloto-list-rows { margin-top: 15px; }
			.gloto-list-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--gloto-border); }
			.gloto-list-row:last-child { border-bottom: none; }
			.gloto-row-title { font-size: 13px; font-weight: 500; }
			.gloto-row-val { font-weight: bold; }
			.gloto-row-sub { font-size: 11px; color: var(--gloto-text-muted); margin-left: 4px; }
			.gloto-text-danger { color: var(--gloto-danger); }
			.gloto-alert-box { margin-top: 10px; color: var(--gloto-danger); font-weight: bold; text-align: center; }
		</style>
		<?php
		return ob_get_clean();
	}

	private function get_payment_data( $range ) {
		global $wpdb;

		// Calculate failure rate: (Failed / (Failed + Completed)) * 100
		$dates = $this->get_date_ranges( $range, 'period' );
		
		$sql = $wpdb->prepare( "
			SELECT 
				payment_method_title,
				status,
				COUNT(order_id) as count
			FROM {$wpdb->prefix}wc_order_stats
			WHERE date_created >= %s AND date_created <= %s
			GROUP BY payment_method_title, status
		", $dates['current']['start'], $dates['current']['end'] );

		$results = $wpdb->get_results( $sql );

		$gateways = [];
		$total_failed = 0;
		$total_attempts = 0;

		foreach ( $results as $row ) {
			if ( empty( $row->payment_method_title ) ) continue;
			
			if ( ! isset( $gateways[ $row->payment_method_title ] ) ) {
				$gateways[ $row->payment_method_title ] = [ 'failed' => 0, 'total' => 0 ];
			}

			$gateways[ $row->payment_method_title ]['total'] += $row->count;
			$total_attempts += $row->count;

			if ( in_array( $row->status, ['wc-failed', 'wc-cancelled'] ) ) {
				$gateways[ $row->payment_method_title ]['failed'] += $row->count;
				$total_failed += $row->count;
			}
		}

		// Calc Rates
		foreach ( $gateways as $method => &$stats ) {
			$stats['rate'] = $stats['total'] > 0 ? ( $stats['failed'] / $stats['total'] ) * 100 : 0;
		}

		$global_rate = $total_attempts > 0 ? ( $total_failed / $total_attempts ) * 100 : 0;

		return [
			'rate' => $global_rate,
			'gateways' => $gateways
		];
	}
}
