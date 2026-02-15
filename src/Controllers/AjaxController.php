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
        register_rest_route('gloto-dashboards/v1', '/widgets', [
            'methods' => 'GET',
            'callback' => [$this, 'get_all_widgets'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('gloto-dashboards/v1', '/widgets/(?P<id>[a-zA-Z0-9_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_single_widget'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }

    public function check_permissions()
    {
        return current_user_can('manage_woocommerce');
    }

    public function get_all_widgets($request)
    {
        $controllers = AdminController::instance();
        $widgets = $controllers->get_widgets();
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
        $id = $request->get_param('id');
        $controllers = AdminController::instance();
        $widgets = $controllers->get_widgets();

        $range = $request->get_param('range') ? intval($request->get_param('range')) : 30;
        $compare = $request->get_param('compare') ? sanitize_text_field($request->get_param('compare')) : 'period';

        foreach ($widgets as $widget) {
            if ($widget->get_id() === $id) {
                return new \WP_REST_Response($this->safe_render($widget, $range, $compare), 200);
            }
        }

        return new \WP_REST_Response('Widget not found', 404);
    }

    /**
     * Safe Render: Wraps widget render in try/catch to prevent 502 errors
     */
    private function safe_render($widget, $range, $compare)
    {
        try {
            return $widget->render($range, $compare);
        }
        catch (\Throwable $e) {
            // Return an error card instead of crashing the entire request
            $id = $widget->get_id();
            $title = $widget->get_title();
            $error = esc_html($e->getMessage());
            return "<div class=\"gloto-widget-card\" id=\"{$id}\">
                <div class=\"gloto-widget-header\">
                    <h3 class=\"gloto-widget-title\">{$title}</h3>
                    <button class=\"gloto-widget-refresh\" data-widget=\"{$id}\">
                        <span class=\"dashicons dashicons-update\"></span>
                    </button>
                </div>
                <div class=\"gloto-widget-error\" style=\"padding:20px;text-align:center;color:#dc3232;\">
                    ⚠️ Error: {$error}
                </div>
            </div>";
        }
    }
}