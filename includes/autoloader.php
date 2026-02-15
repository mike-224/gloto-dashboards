<?php
/**
 * Autoloader
 *
 * @package Gloto\Dashboards
 */

namespace Gloto\Dashboards;

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($class) {
    $prefix = 'Gloto\\Dashboards\\';
    $base_dir = GLOTO_DASHBOARDS_PATH . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});