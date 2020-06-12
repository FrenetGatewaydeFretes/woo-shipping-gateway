<?php
/**
 * Plugin Name: WooCommerce Frenet
 * Plugin URI: https://github.com/FrenetGatewaydeFretes/woo-shipping-gateway
 * Description: Frenet para WooCommerce
 * Author: Rafael Mancini
 * Author URI: http://www.frenet.com.br
 * Version: 2.2.0
 * License: GPLv2 or later
 * Text Domain: woo-shipping-gateway
 * Domain Path: languages/
 */

define( 'WOO_FRENET_PATH', plugin_dir_path( __FILE__ ) );

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Frenet_Main' ) ) :

    /**
     * Frenet main class.
     */
    class WC_Frenet_Main {
        /**
         * Plugin version.
         *
         * @var string
         */
        const VERSION = '2.1.2';

        /**
         * Instance of this class.
         *
         * @var object
         */
        protected static $instance = null;

        /**
         * Initialize the plugin
         */
        private function __construct() {
            add_action( 'init', array( $this, 'load_plugin_textdomain' ), -1 );

            add_action( 'wp_ajax_ajax_simulator', array( 'WC_Frenet_Shipping_Simulator', 'ajax_simulator' ) );
            add_action( 'wp_ajax_nopriv_ajax_simulator', array( 'WC_Frenet_Shipping_Simulator', 'ajax_simulator' ) );

            // Checks with WooCommerce is installed.
            if ( class_exists( 'WC_Integration' ) ) {
                include_once WOO_FRENET_PATH . 'includes/class-wc-frenet.php';
                include_once WOO_FRENET_PATH . 'includes/class-wc-frenet-helper.php';
                include_once WOO_FRENET_PATH . 'includes/class-wc-frenet-shipping-simulator.php';

                add_filter( 'woocommerce_shipping_methods', array( $this, 'wcfrenet_add_method' ) );

            } else {
                add_action( 'admin_notices', array( $this, 'wcfrenet_woocommerce_fallback_notice' ) );
            }

            if ( ! class_exists( 'SimpleXmlElement' ) ) {
                add_action( 'admin_notices', 'wcfrenet_extensions_missing_notice' );
            }

            function create_delivery_custom_fields() {
                $args = array(
                    'id'            => 'shipping_additional_days',
                    'label'         => __( 'Tempo adicional (dias)', 'cfwc' ),
                    'class'					=> 'cfwc-custom-field',
                    'desc_tip'      => true,
                    'description'   => __( 'Insira o tempo adicional de processamento para entrega, em dias.', 'ctwc' ),
                );
                woocommerce_wp_text_input( $args );
            }
            add_action( 'woocommerce_product_options_shipping', 'create_delivery_custom_fields' );

            function save_delivery_custom_fields( $post_id ) {
                $product = wc_get_product( $post_id );
                
                $title = isset( $_POST['shipping_additional_days'] ) ? $_POST['shipping_additional_days'] : '';
                $product->update_meta_data( 'shipping_additional_days', sanitize_text_field( $title ) );
                
                $product->save();
            }
            add_action( 'woocommerce_process_product_meta', 'save_delivery_custom_fields' );
        }

        /**
         * Return an instance of this class.
         *
         * @return object A single instance of this class.
         */
        public static function get_instance() {
            // If the single instance hasn't been set, set it now.
            if ( null === self::$instance ) {
                self::$instance = new self;
            }

            return self::$instance;
        }

        /**
         * Load the plugin text domain for translation.
         */
        public function load_plugin_textdomain() {
            load_plugin_textdomain( 'woo-shipping-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        }

        /**
         * Get main file.
         *
         * @return string
         */
        public static function get_main_file() {
            return __FILE__;
        }

        /**
         * Get plugin path.
         *
         * @return string
         */
        public static function get_plugin_path() {
            return plugin_dir_path( __FILE__ );
        }

        /**
         * Get templates path.
         *
         * @return string
         */
        public static function get_templates_path() {
            return self::get_plugin_path() . 'templates/';
        }

        /**
         * Add the Frenet to shipping methods.
         *
         * @param array $methods
         *
         * @return array
         */
        function wcfrenet_add_method( $methods ) {
            $methods['frenet'] = 'WC_Frenet';

            return $methods;
        }

    }

    add_action( 'plugins_loaded', array( 'WC_Frenet_Main', 'get_instance' ) );

endif;
