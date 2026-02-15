<?php
/**
 * Time To First Purchase Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TimeToFirstPurchaseWidget
 */
class TimeToFirstPurchaseWidget extends AbstractWidget
{

    public function get_id()
    {
        return 'ttfp_widget';
    }

    public function get_title()
    {
        return '⏰ Tiempo a 1ª Compra';
    }

    public function render($range = 30, $compare = 'period')
    {
        $data = $this->get_ttfp_data($range);

        ob_start();
        ?>
		<div class="gloto-widget-card" id="<?php echo esc_attr($this->get_id()); ?>">
			<div class="gloto-widget-header">
				<h3 class="gloto-widget-title"><?php echo esc_html($this->get_title()); ?></h3>
				<button class="gloto-widget-refresh" data-widget="<?php echo esc_attr($this->get_id()); ?>">
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
		<?php
        return ob_get_clean();
    }

    private function get_ttfp_data($range)
    {
        global $wpdb;

        // HPOS compatible: Use wc_customer_lookup + wc_order_stats instead of wp_posts/wp_postmeta
        $results = $wpdb->get_results("
            SELECT 
                u.user_registered,
                MIN(os.date_created) as first_order_date
            FROM {$wpdb->prefix}users u
            JOIN {$wpdb->prefix}wc_customer_lookup cl ON u.ID = cl.user_id
            JOIN {$wpdb->prefix}wc_order_stats os ON cl.customer_id = os.customer_id
            WHERE os.status IN ('wc-completed', 'wc-processing')
            AND cl.user_id > 0
            GROUP BY u.ID
            HAVING first_order_date >= u.user_registered
            LIMIT 200
        ");

        $total_hours = 0;
        $count = 0;
        $segments = ['fast' => 0, 'day' => 0, 'slow' => 0];

        foreach ($results as $row) {
            $reg = strtotime($row->user_registered);
            $ord = strtotime($row->first_order_date);
            $diff_hours = ($ord - $reg) / 3600;

            $total_hours += $diff_hours;
            $count++;

            if ($diff_hours < 1) $segments['fast']++;
            elseif ($diff_hours < 24) $segments['day']++;
            else $segments['slow']++;
        }

        $avg = $count > 0 ? round($total_hours / $count) : 0;

        $total_seg = array_sum($segments);
        $percents = [
            'fast' => $total_seg ? round(($segments['fast'] / $total_seg) * 100) : 0,
            'day'  => $total_seg ? round(($segments['day'] / $total_seg) * 100) : 0,
            'slow' => $total_seg ? round(($segments['slow'] / $total_seg) * 100) : 0,
        ];

        return [
            'avg_hours' => $avg,
            'segments'  => $percents
        ];
    }
}
