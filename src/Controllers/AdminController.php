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
        ['jquery', 'wp-api', 'wp-element'],
            GLOTO_DASHBOARDS_VERSION,
            true
        );

        wp_localize_script('gloto-dashboards-admin', 'glotoSettings', [
            'nonce' => wp_create_nonce('gloto_dashboards_nonce'),
            'apiUrl' => rest_url('gloto-dashboards/v1')
        ]);
    }

    /**
     * Render Dashboard Page
     */
    public function render_dashboard()
    {
        require_once GLOTO_DASHBOARDS_PATH . 'templates/admin-dashboard.php';
    }
}