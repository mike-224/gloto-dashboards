<?php
/**
 * Widget Interface
 *
 * @package Gloto\Dashboards\Widgets
 */

namespace Gloto\Dashboards\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface WidgetInterface
 */
interface WidgetInterface
{

    /**
     * Get Widget ID
     *
     * @return string
     */
    public function get_id();

    /**
     * Get Widget Title
     *
     * @return string
     */
    public function get_title();

    /**
     * Render Widget
     *
     * @param int    $range   Date range in days.
     * @param string $compare Comparison mode (period/year).
     * @return string HTML content.
     */
    public function render($range = 30, $compare = 'period');
}