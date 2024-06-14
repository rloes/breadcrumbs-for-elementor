<?php
/**
 * Plugin Name: Breadcrumbs For Elementor
 * Description: A breadcrumb plugin for Elementor that utilizes the Singleton pattern.
 * Version: 1.0.0
 * Author: Robin - Westsite
 * Text Domain: breadcrumbs-for-elementor
 */
namespace BCFE;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


class Plugin {

    private static $instance = null;

    private function __construct() {
        add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widgets' ] );
    }

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_widgets() {
        // Check if Elementor is active
        if ( did_action( 'elementor/loaded' ) ) {
            require_once( __DIR__ . '/widgets/breadcrumbs-widget.php' );
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_Breadcrumbs_Widget() );
        }
    }
}

// Initialize the plugin
\BCFE\Plugin::instance();