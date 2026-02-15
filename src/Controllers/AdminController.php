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
     * Widget registry: ID => Class name
     * This avoids instantiating widgets during enqueue_assets()
     */
    private static $widget_registry = [
        'growth_widget' => 'Gloto\\Dashboards\\Widgets\\GrowthWidget',
        // DESACTIVADOS — se activan uno a uno cuando el anterior funcione
        // 'sales_pulse_widget' => 'Gloto\\Dashboards\\Widgets\\SalesPulseWidget',
        // 'lost_revenue_widget' => 'Gloto\\Dashboards\\Widgets\\LostRevenueWidget',
        // 'upsell_machine_widget' => 'Gloto\\Dashboards\\Widgets\\UpsellMachineWidget',
        // 'ltv_widget' => 'Gloto\\Dashboards\\Widgets\\LTVWidget',
        // 'stock_strategy_widget' => 'Gloto\\Dashboards\\Widgets\\StockStrategyWidget',
        // 'churn_rate_widget' => 'Gloto\\Dashboards\\Widgets\\ChurnRateWidget',
        // 'cac_widget' => 'Gloto\\Dashboards\\Widgets\\CACWidget',
        // 'ttfp_widget' => 'Gloto\\Dashboards\\Widgets\\TimeToFirstPurchaseWidget',
        // 'payment_health_widget' => 'Gloto\\Dashboards\\Widgets\\PaymentHealthWidget',
        // 'urgency_widget' => 'Gloto\\Dashboards\\Widgets\\UrgencyWidget',
    ];

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
     * NOTE: We do NOT instantiate widgets here. We use the static registry
     * to pass IDs to JS, avoiding fatal errors during page load.
     */
    public function enqueue_assets($hook)
    {
        if ('toplevel_page_gloto-dashboards' !== $hook) {
            return;
        }

        // Use filemtime for cache busting — guarantees fresh JS/CSS after every deploy
        $css_ver = file_exists(GLOTO_DASHBOARDS_PATH . 'assets/css/admin.css') ? filemtime(GLOTO_DASHBOARDS_PATH . 'assets/css/admin.css') : GLOTO_DASHBOARDS_VERSION;
        $js_ver = file_exists(GLOTO_DASHBOARDS_PATH . 'assets/js/admin.js') ? filemtime(GLOTO_DASHBOARDS_PATH . 'assets/js/admin.js') : GLOTO_DASHBOARDS_VERSION;

        wp_enqueue_style(
            'gloto-dashboards-admin',
            GLOTO_DASHBOARDS_URL . 'assets/css/admin.css',
        [],
            $css_ver
        );

        wp_enqueue_script(
            'gloto-dashboards-admin',
            GLOTO_DASHBOARDS_URL . 'assets/js/admin.js',
        ['jquery'],
            $js_ver,
            true
        );

        wp_localize_script('gloto-dashboards-admin', 'glotoSettings', [
            'nonce' => wp_create_nonce('wp_rest'),
            'apiUrl' => rest_url('gloto-dashboards/v1'),
            'widgetIds' => array_keys(self::$widget_registry)
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
     * Get Registered Widgets (instantiates on demand)
     * Called only from AjaxController during REST API requests
     *
     * @return array
     */
    public function get_widgets()
    {
        $widgets = [];
        foreach (self::$widget_registry as $id => $class) {
            try {
                if (class_exists($class)) {
                    $widgets[] = new $class();
                }
            }
            catch (\Throwable $e) {
                // Skip broken widgets silently during instantiation
                error_log('Gloto Dashboards: Failed to load widget ' . $id . ': ' . $e->getMessage());
            }
        }
        return $widgets;
    }

    /**
     * Get a single widget by ID
     *
     * @param string $id Widget ID
     * @return \Gloto\Dashboards\Widgets\WidgetInterface|null
     */
    public function get_widget($id)
    {
        if (!isset(self::$widget_registry[$id])) {
            return null;
        }

        $class = self::$widget_registry[$id];

        try {
            if (class_exists($class)) {
                return new $class();
            }
        }
        catch (\Throwable $e) {
            error_log('Gloto Dashboards: Failed to load widget ' . $id . ': ' . $e->getMessage());
        }

        return null;
    }
}