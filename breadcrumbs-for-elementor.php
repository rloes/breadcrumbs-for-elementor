<?php
/**
 * Plugin Name: Breadcrumbs For Elementor
 * Description: A breadcrumb plugin for Elementor that utilizes the Singleton pattern.
 * Version: 1.0.0
 * Author: Robin - Westsite
 * Text Domain: breadcrumbs-for-elementor
 */

namespace BCFE;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Custom autoloader
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'BCFE\\';

    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/includes/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

class Plugin {

    private static $instance = null;

    private function __construct() {
        // Register the widget styles and scripts
        add_action('wp_enqueue_scripts', function () {
            wp_register_style('elementor-breadcrumbs-widget-style', plugins_url('assets/css/breadcrumbs-widget.css', __FILE__));
        });
        add_action('elementor/widgets/widgets_registered', [$this, 'register_widgets']);
    }

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_widgets() {
        // Check if Elementor is active
        if (did_action('elementor/loaded')) {
            require_once(__DIR__ . '/includes/widgets/Elementor_Breadcrumbs_Widget.php');
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new \BCFE\Widgets\Elementor_Breadcrumbs_Widget());
        }
    }
}

// Initialize the plugin
\BCFE\Plugin::instance();
