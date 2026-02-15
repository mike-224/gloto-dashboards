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

    /**
     * Instance
     *
     * @var AjaxController
     */
    private static $instance = null;

    /**
     * Instance
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST Routes
     */
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

    /**
     * Check Permissions
     */
    public function check_permissions()
    {
        return current_user_can('manage_woocommerce') && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest');
    }

    /**
     * Get All Widgets
     */
    public function get_all_widgets($request)
    {
        $controllers = AdminController::instance();
        $widgets = $controllers->get_widgets();
        $html = '';

        $range = $request->get_param('range') ? intval($request->get_param('range')) : 30;
        $compare = $request->get_param('compare') ? sanitize_text_field($request->get_param('compare')) : 'period';

        foreach ($widgets as $widget) {
            $html .= $widget->render($range, $compare);
        }

        return new \WP_REST_Response($html, 200);
    }

    /**
     * Get Single Widget
     */
    public function get_single_widget($request)
    {
        $id = $request->get_param('id');
        $controllers = AdminController::instance();
        $widgets = $controllers->get_widgets();

        $range = $request->get_param('range') ? intval($request->get_param('range')) : 30;
        $compare = $request->get_param('compare') ? sanitize_text_field($request->get_param('compare')) : 'period';

        foreach ($widgets as $widget) {
            if ($widget->get_id() === $id) {
                return new \WP_REST_Response($widget->render($range, $compare), 200);
            }
        }

        return new \WP_REST_Response('Widget not found', 404);
    }
}