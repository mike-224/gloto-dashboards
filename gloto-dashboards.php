<?php
/**
 * Plugin Name: Gloto Dashboards
 * Plugin URI:  https://glotomania.com
 * Description: Sistema modular de dashboards y analítica avanzada para WooCommerce.
 * Version:     2.0.0
 * Author:      Glotomania Tech
 * Author URI:  https://glotomania.com
 * License:     GPLv2 or later
 * Text Domain: gloto-dashboards
 * Domain Path: /languages
 * Requires PHP: 8.2
 */

namespace Gloto\Dashboards;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Main Plugin Class
 *
 * @since 1.0.0
 */
final class GlotoDashboards
{

    /**
     * Plugin Version
     *
     * @since 1.0.0
     * @var string
     */
    const VERSION = '2.0.0';

    /**
     * Instance
     *
     * @since 1.0.0
     * @var GlotoDashboards
     */
    private static $instance = null;

    /**
     * Instance
     *
     * Ensures only one instance of the class is loaded or can be loaded.
     *
     * @since 1.0.0
     * @return GlotoDashboards The instance.
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
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define Constants
     *
     * @since 1.0.0
     */
    private function define_constants()
    {
        define('GLOTO_DASHBOARDS_VERSION', self::VERSION);
        define('GLOTO_DASHBOARDS_FILE', __FILE__);
        define('GLOTO_DASHBOARDS_PATH', plugin_dir_path(__FILE__));
        define('GLOTO_DASHBOARDS_URL', plugin_dir_url(__FILE__));
    }

    /**
     * Include required core files used in admin and on the frontend.
     *
     * @since 1.0.0
     */
    private function includes()
    {
        // Autoloader logic could go here or via Composer
        require_once GLOTO_DASHBOARDS_PATH . 'includes/autoloader.php';
    }

    /**
     * Hook into actions and filters.
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        register_activation_hook(__FILE__, [$this, 'install']);
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
    }

    /**
     * Activation hook
     *
     * @since 1.0.0
     */
    public function install()
    {
    // Trigger install routines
    }

    /**
     * On plugins loaded
     *
     * @since 1.0.0
     */
    public function on_plugins_loaded()
    {
        // Check dependencies (WooCommerce)
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                echo '<div class="error"><p><strong>Gloto Dashboards</strong> requiere WooCommerce para funcionar.</p></div>';
            });
            return;
        }

        // Init Controllers
        \Gloto\Dashboards\Controllers\AdminController::instance();
        \Gloto\Dashboards\Controllers\AjaxController::instance();
    }
}

/**
 * Returns the main instance of GlotoDashboards.
 *
 * @since 1.0.0
 * @return GlotoDashboards
 */
function gloto_dashboards()
{
    return GlotoDashboards::instance();
}

// Global for backwards compatibility.
$GLOBALS['gloto_dashboards'] = gloto_dashboards();