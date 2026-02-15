<?php
/**
 * Ajax Controller
 *
 * @package Gloto\Dashboards\Controllers
 */

namespace Gloto\Dashboards\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AjaxController
 */
class AjaxController
{

    private static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        // DEBUG: Ultra-simple test endpoint
        register_rest_route('gloto-dashboards/v1', '/test', [
            'methods' => 'GET',
            'callback' => function () {
            return new \WP_REST_Response(['status' => 'ok', 'php' => PHP_VERSION, 'time' => date('Y-m-d H:i:s')], 200);
        },
            'permission_callback' => '__return_true',
        ]);

        // DEBUG: Step-by-step diagnostic endpoint
        register_rest_route('gloto-dashboards/v1', '/debug', [
            'methods' => 'GET',
            'callback' => [$this, 'debug_widget'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('gloto-dashboards/v1', '/widgets', [
            'methods' => 'GET',
            'callback' => [$this, 'get_all_widgets'],
            'permission_callback' => '__return_true', // TEMP: public for debugging
        ]);

        register_rest_route('gloto-dashboards/v1', '/widgets/(?P<id>[a-zA-Z0-9_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_single_widget'],
            'permission_callback' => '__return_true', // TEMP: public for debugging
        ]);
    }

    /**
     * Debug endpoint: tries to instantiate and render widget step by step
     */
    public function debug_widget()
    {
        $steps = [];

        // Step 1: Can we get AdminController?
        try {
            $admin = AdminController::instance();
            $steps[] = '✅ Step 1: AdminController OK';
        }
        catch (\Throwable $e) {
            $steps[] = '❌ Step 1: AdminController FAILED: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            return new \WP_REST_Response(['steps' => $steps], 200);
        }

        // Step 2: Can we call get_widget()?
        try {
            $widget = $admin->get_widget('growth_widget');
            $steps[] = '✅ Step 2: get_widget() returned ' . ($widget ? get_class($widget) : 'null');
        }
        catch (\Throwable $e) {
            $steps[] = '❌ Step 2: get_widget() FAILED: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            return new \WP_REST_Response(['steps' => $steps], 200);
        }

        if (!$widget) {
            $steps[] = '❌ Step 2b: Widget is null — class not found or registry empty';
            return new \WP_REST_Response(['steps' => $steps], 200);
        }

        // Step 3: Can we call get_id()?
        try {
            $id = $widget->get_id();
            $steps[] = '✅ Step 3: get_id() = ' . $id;
        }
        catch (\Throwable $e) {
            $steps[] = '❌ Step 3: get_id() FAILED: ' . $e->getMessage();
        }

        // Step 4: Can we call render()?
        try {
            $html = $widget->render(30, 'period');
            $steps[] = '✅ Step 4: render() returned ' . strlen($html) . ' bytes of HTML';
        }
        catch (\Throwable $e) {
            $steps[] = '❌ Step 4: render() FAILED: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        }

        // Step 5: Check DB tables exist
        global $wpdb;
        try {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_order_stats'");
            $steps[] = $table_exists ? '✅ Step 5: wc_order_stats table EXISTS' : '❌ Step 5: wc_order_stats table NOT FOUND';

            $table2 = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_customer_lookup'");
            $steps[] = $table2 ? '✅ Step 5b: wc_customer_lookup table EXISTS' : '❌ Step 5b: wc_customer_lookup table NOT FOUND';
        }
        catch (\Throwable $e) {
            $steps[] = '❌ Step 5: DB check FAILED: ' . $e->getMessage();
        }

        return new \WP_REST_Response(['steps' => $steps], 200);
    }

    public function check_permissions()
    {
        return current_user_can('manage_woocommerce');
    }

    public function get_all_widgets($request)
    {
        $admin = AdminController::instance();
        $widgets = $admin->get_widgets();
        $html = '';

        $range = $request->get_param('range') ? intval($request->get_param('range')) : 30;
        $compare = $request->get_param('compare') ? sanitize_text_field($request->get_param('compare')) : 'period';

        foreach ($widgets as $widget) {
            $html .= $this->safe_render($widget, $range, $compare);
        }

        return new \WP_REST_Response($html, 200);
    }

    public function get_single_widget($request)
    {
        $id = sanitize_text_field($request->get_param('id'));
        $admin = AdminController::instance();

        $range = $request->get_param('range') ? intval($request->get_param('range')) : 30;
        $compare = $request->get_param('compare') ? sanitize_text_field($request->get_param('compare')) : 'period';

        $widget = $admin->get_widget($id);

        if (!$widget) {
            return new \WP_REST_Response(
                '<div class="gloto-widget-card"><div style="padding:20px;text-align:center;color:#dc3232;">Widget no encontrado: ' . htmlspecialchars($id) . '</div></div>',
                200
                );
        }

        return new \WP_REST_Response($this->safe_render($widget, $range, $compare), 200);
    }

    private function safe_render($widget, $range, $compare)
    {
        try {
            return $widget->render($range, $compare);
        }
        catch (\Throwable $e) {
            $id = method_exists($widget, 'get_id') ? $widget->get_id() : 'unknown';
            error_log("Gloto Dashboards: Widget '{$id}' error: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");

            return "<div class=\"gloto-widget-card\" id=\"{$id}\">
                <div style=\"padding:20px;text-align:center;color:#dc3232;\">
                    Error: " . htmlspecialchars($e->getMessage()) . "
                </div>
            </div>";
        }
    }
}