<?php
/**
 * Abstract Widget Class
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Class AbstractWidget
 */
abstract class AbstractWidget implements WidgetInterface
{

    /**
     * Get Date Ranges
     *
     * @param int    $range   Days to look back.
     * @param string $compare Comparison mode.
     * @return array Start and end dates for current and previous periods.
     */
    protected function get_date_ranges($range, $compare)
    {
        $current_end = current_time('Y-m-d 23:59:59');
        $current_start = date('Y-m-d 00:00:00', strtotime("-$range days", strtotime($current_end)));

        if ('year' === $compare) {
            $previous_end = date('Y-m-d 23:59:59', strtotime('-1 year', strtotime($current_end)));
            $previous_start = date('Y-m-d 00:00:00', strtotime('-1 year', strtotime($current_start)));
        }
        else {
            // Period comparison
            $previous_end = date('Y-m-d 23:59:59', strtotime('-1 day', strtotime($current_start)));
            $previous_start = date('Y-m-d 00:00:00', strtotime("-$range days", strtotime($previous_end)));
        }

        return [
            'current' => ['start' => $current_start, 'end' => $current_end],
            'previous' => ['start' => $previous_start, 'end' => $previous_end],
        ];
    }

    /**
     * Calculate Percentage Change
     *
     * @param float $current  Current value.
     * @param float $previous Previous value.
     * @return float Percentage change.
     */
    protected function calculate_change($current, $previous)
    {
        if (0 == $previous) {
            return 0 == $current ? 0 : 100;
        }
        return (($current - $previous) / $previous) * 100;
    }

    /**
     * Format Trend HTML
     *
     * @param float $change Percentage change.
     * @return string HTML for trend indicator.
     */
    protected function format_trend($change)
    {
        $class = $change >= 0 ? 'gloto-trend-up' : 'gloto-trend-down';
        $icon = $change >= 0 ? '▲' : '▼';
        $formatted = number_format(abs($change), 1) . '%';

        return sprintf(
            '<span class="gloto-metric-trend %s">%s %s</span>',
            esc_attr($class),
            esc_html($icon),
            esc_html($formatted)
        );
    }
}