<?php
/**
 * Churn Rate Widget
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ChurnRateWidget
 */
class ChurnRateWidget extends AbstractWidget
{

    public function get_id()
    {
        return 'churn_rate_widget';
    }

    public function get_title()
    {
        return '📉 Churn Rate (Abandono)';
    }

    public function render($range = 30, $compare = 'period')
    {
        $data = $this->get_churn_data($range, $compare);

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
				<div class="gloto-metric-value"><?php echo number_format($data['current']['rate'], 1); ?>%</div>
				<div class="gloto-metric-subtext">Clientes perdidos (>90 días sin comprar): <?php echo $data['current']['lost_count']; ?></div>
				
				<?php 
                $change = $data['current']['rate'] - $data['previous']['rate'];
                $class = $change <= 0 ? 'gloto-trend-up' : 'gloto-trend-down';
                $icon  = $change <= 0 ? '▼' : '▲';
                ?>
				<div class="gloto-metric-trend <?php echo esc_attr($class); ?>">
					<?php echo esc_html($icon); ?> <?php echo number_format(abs($change), 1); ?>% vs periodo anterior
				</div>
			</div>

			<div class="gloto-insight-box">
				<?php if ($data['current']['rate'] < 30) : ?>
					<span class="gloto-status-ok">✅ Excelente retención</span>
				<?php elseif ($data['current']['rate'] < 50) : ?>
					<span class="gloto-status-warning">⚠️ Normal (Vigilar)</span>
				<?php else : ?>
					<span class="gloto-status-critical">🚨 CRISIS: Actúa ya</span>
				<?php endif; ?>
			</div>
		</div>
		<?php
        return ob_get_clean();
    }

    private function get_churn_data($range, $compare)
    {
        global $wpdb;

        $churn_threshold_days = 90;
        $churn_date = date('Y-m-d H:i:s', strtotime("-{$churn_threshold_days} days"));

        // Use wc_customer_lookup for BOTH total and active customers (HPOS compatible)
        $total_customers = (int) $wpdb->get_var("
            SELECT COUNT(customer_id) 
            FROM {$wpdb->prefix}wc_customer_lookup
        ");

        if (!$total_customers) {
            return [
                'current'  => ['rate' => 0, 'lost_count' => 0],
                'previous' => ['rate' => 0]
            ];
        }

        $active_customers = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(customer_id)
            FROM {$wpdb->prefix}wc_customer_lookup
            WHERE date_last_active >= %s
        ", $churn_date));

        $lost_count = $total_customers - $active_customers;
        $churn_rate = ($lost_count / $total_customers) * 100;

        // Approximate previous period
        $prev_churn_date = date('Y-m-d H:i:s', strtotime("-" . ($churn_threshold_days + $range) . " days"));
        $prev_active = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(customer_id)
            FROM {$wpdb->prefix}wc_customer_lookup
            WHERE date_last_active >= %s AND date_last_active < %s
        ", $prev_churn_date, $churn_date));
        
        $prev_lost = $total_customers - $prev_active;
        $previous_rate = ($prev_lost / $total_customers) * 100;

        return [
            'current' => [
                'rate'       => $churn_rate,
                'lost_count' => $lost_count
            ],
            'previous' => [
                'rate' => $previous_rate
            ]
        ];
    }
}
