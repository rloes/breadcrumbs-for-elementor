<?php
/**
 * Plugin Name: Breadcrumbs For Elementor
 * Description: A breadcrumb plugin for Elementor that uses the functions of The_SEO_Framework\
 * Version: 1.0.0
 * Author: Robin - Westsite
 * Author URI: https://westsite-webdesign.de/
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Text Domain: breadcrumbs-for-elementor
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  elementor
 */

namespace BCFE;

use function Sodium\add;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Plugin
{

    private static $instance = null;

    private function __construct()
    {
        // Register the widget styles and scripts
        $this->define_constants();
        $this->register_autoload();
        $this->load_textdomain();
        $this->add_hooks();
    }

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function define_constants()
    {
        define('BCFE_VERSION', '1.0.0');
    }

    public function add_hooks()
    {
        //TODO: add compatibility check for elementor see: https://developers.elementor.com/docs/addons/compatibility/
        add_action('init', [$this, "load_textdomain"]);
        add_action('wp_enqueue_scripts', [$this, "register_assets"]);
        add_action('elementor/widgets/widgets_registered', [$this, 'register_widgets']);
    }

    public function register_widgets()
    {
        require(__DIR__ . '/includes/widgets/Elementor_Breadcrumbs_Widget.php');
        \Elementor\Plugin::instance()->widgets_manager->register(new \BCFE\Widgets\Elementor_Breadcrumbs_Widget());
    }

    public function register_assets()
    {
        wp_register_style('elementor-breadcrumbs-widget-style', plugins_url('assets/css/breadcrumbs-widget.css', __FILE__), false, BCFE_VERSION);
    }

    public function register_autoload()
    {
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
    }

    public function load_textdomain()
    {
        load_plugin_textdomain("breadcrumbs-for-elementor", false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

function breadcrumbs_for_elementor_maybe_initialize()
{
    if (did_action('elementor/loaded')) {
        // Initialize the plugin
        \BCFE\Plugin::instance();
    } else {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning is-dismissible">
                    <p>' . esc_html__('Breadcrumbs for Elementor requires Elementor to be installed and activated.', 'breadcrumbs_for_elementor') . '</p>
                </div>';
        });
    }
}

add_action('plugins_loaded', "BCFE\\breadcrumbs_for_elementor_maybe_initialize", 20);

