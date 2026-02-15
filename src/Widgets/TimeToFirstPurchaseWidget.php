<?php
/**
 * Time To First Purchase Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TimeToFirstPurchaseWidget
 */
class TimeToFirstPurchaseWidget extends AbstractWidget {

	public function get_id() {
		return 'ttfp_widget';
	}

	public function get_title() {
		return '⏰ Tiempo a 1ª Compra';
	}

	public function render( $range = 30, $compare = 'period' ) {
		$data = $this->get_ttfp_data( $range );

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
				<div class="gloto-metric-value"><?php echo $data['avg_hours']; ?>h</div>
				<div class="gloto-metric-subtext">Promedio registro → compra</div>
			</div>

			<div class="gloto-segments-bar">
				<div class="gloto-segment" style="width: <?php echo $data['segments']['fast']; ?>%; background: #10b981;" title="<1h: <?php echo $data['segments']['fast']; ?>%"></div>
				<div class="gloto-segment" style="width: <?php echo $data['segments']['day']; ?>%; background: #3b82f6;" title="1-24h: <?php echo $data['segments']['day']; ?>%"></div>
				<div class="gloto-segment" style="width: <?php echo $data['segments']['slow']; ?>%; background: #f59e0b;" title=">24h: <?php echo $data['segments']['slow']; ?>%"></div>
			</div>
			<div class="gloto-legend">
				<span><i style="background:#10b981"></i> <1h</span>
				<span><i style="background:#3b82f6"></i> 1-24h</span>
				<span><i style="background:#f59e0b"></i> >24h</span>
			</div>
		</div>
		<style>
			.gloto-segments-bar { display: flex; height: 10px; border-radius: 5px; overflow: hidden; margin: 15px 0 10px; background: #eee; }
			.gloto-legend { display: flex; justify-content: space-between; font-size: 11px; color: var(--gloto-text-muted); }
			.gloto-legend i { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
		</style>
		<?php
		return ob_get_clean();
	}

	private function get_ttfp_data( $range ) {
		global $wpdb;

		// We need users who made their first order in the selected range
		// Query: Join Users table with First Order date
		
		// 1. Get User Registration ID and Date
		// 2. Get First Order Date for that User
		// 3. Diff
		
		// Optimization: This is heavy. Limit to last 100 first-orders for speed or cache.
		$results = $wpdb->get_results( "
			SELECT 
				u.user_registered,
				MIN(p.post_date) as first_order_date
			FROM {$wpdb->prefix}users u
			JOIN {$wpdb->prefix}postmeta pm ON u.ID = pm.meta_value AND pm.meta_key = '_customer_user'
			JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
			WHERE p.post_type = 'shop_order'
			AND p.post_status IN ('wc-completed', 'wc-processing')
			GROUP BY u.ID
			HAVING first_order_date >= u.user_registered
			LIMIT 200
		" );

		$total_hours = 0;
		$count = 0;
		$segments = [ 'fast' => 0, 'day' => 0, 'slow' => 0 ];

		foreach ( $results as $row ) {
			$reg = strtotime( $row->user_registered );
			$ord = strtotime( $row->first_order_date );
			$diff_hours = ( $ord - $reg ) / 3600;

			$total_hours += $diff_hours;
			$count++;

			if ( $diff_hours < 1 ) $segments['fast']++;
			elseif ( $diff_hours < 24 ) $segments['day']++;
			else $segments['slow']++;
		}

		$avg = $count > 0 ? round( $total_hours / $count ) : 0;
		
		// Percents
		$total_seg = array_sum( $segments );
		$percents = [
			'fast' => $total_seg ? round(($segments['fast']/$total_seg)*100) : 0,
			'day'  => $total_seg ? round(($segments['day']/$total_seg)*100) : 0,
			'slow' => $total_seg ? round(($segments['slow']/$total_seg)*100) : 0,
		];

		return [
			'avg_hours' => $avg,
			'segments' => $percents
		];
	}
}
