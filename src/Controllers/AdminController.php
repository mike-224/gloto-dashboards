<?php
/**
 * Admin Controller
 *
 * @package Gloto\Dashboards\Controllers
 */

namespace Gloto\Dashboards\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Controller Class
 */
class AdminController
{

    /**
     * Instance
     *
     * @var AdminController
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
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Register Admin Menu
     */
    public function register_menu()
    {
        add_menu_page(
            __('Gloto Dashboards', 'gloto-dashboards'),
            __('Gloto Dashboards', 'gloto-dashboards'),
            'manage_woocommerce',
            'gloto-dashboards',
        [$this, 'render_dashboard'],
            'dashicons-chart-area',
            2
        );
    }

    /**
     * Enqueue Assets
     */
    public function enqueue_assets($hook)
    {
        if ('toplevel_page_gloto-dashboards' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'gloto-dashboards-admin',
            GLOTO_DASHBOARDS_URL . 'assets/css/admin.css',
        [],
            GLOTO_DASHBOARDS_VERSION
        );

        wp_enqueue_script(
            'gloto-dashboards-admin',
            GLOTO_DASHBOARDS_URL . 'assets/js/admin.js',
        ['jquery'],
            GLOTO_DASHBOARDS_VERSION,
            true
        );

        $widgets = $this->get_widgets();
        $widget_ids = array_map(function ($w) {
            return $w->get_id();
        }, $widgets);

        wp_localize_script('gloto-dashboards-admin', 'glotoSettings', [
            'nonce' => wp_create_nonce('wp_rest'),
            'apiUrl' => rest_url('gloto-dashboards/v1'),
            'widgetIds' => $widget_ids
        ]);
    }

    /**
     * Render Dashboard Page
     */
    public function render_dashboard()
    {
        require_once GLOTO_DASHBOARDS_PATH . 'templates/admin-dashboard.php';
    }

    /**
     * Get Registered Widgets
     *
     * @return array
     */
    public function get_widgets()
    {
        return [
            new \Gloto\Dashboards\Widgets\GrowthWidget(),
            new \Gloto\Dashboards\Widgets\SalesPulseWidget(),
            new \Gloto\Dashboards\Widgets\LostRevenueWidget(),
            new \Gloto\Dashboards\Widgets\UpsellMachineWidget(),
            new \Gloto\Dashboards\Widgets\LTVWidget(),
            new \Gloto\Dashboards\Widgets\StockStrategyWidget(),
            new \Gloto\Dashboards\Widgets\ChurnRateWidget(),
            new \Gloto\Dashboards\Widgets\CACWidget(),
            new \Gloto\Dashboards\Widgets\TimeToFirstPurchaseWidget(),
            new \Gloto\Dashboards\Widgets\PaymentHealthWidget(),
            new \Gloto\Dashboards\Widgets\UrgencyWidget(),
        ];
    }
}