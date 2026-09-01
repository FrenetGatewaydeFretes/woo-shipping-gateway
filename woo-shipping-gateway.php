<?php
/**
 * Plugin Name: Frenet Shipping Gateway for WooCommerce
 * Plugin URI: https://github.com/FrenetGatewaydeFretes/woo-shipping-gateway
 * Description: Frenet para WooCommerce
 * Author: Rafael Mancini
 * Author URI: http://www.frenet.com.br
 * Version: 2.1.23
 * License: GPLv2 or later
 * Text Domain: woo-shipping-gateway
 * Domain Path: languages/
 * Tested up to: 6.9
 * Requires at least: 3.5
 * WC tested up to: 10.4.3
 * Tags: shipping, woocommerce, frete, gateway
 */

/**
 * Informs WooCommerce that the plugin is compatible with the custom order tables feature.
 */
 add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

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
        const VERSION = '2.1.23';

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

                // Blocks checkout while the last live Frenet quote failed (see method docblock).
                add_action( 'woocommerce_checkout_validate_order_before_payment', array( $this, 'block_checkout_if_frenet_quote_failed' ), 10, 2 );
                add_action( 'woocommerce_after_checkout_validation', array( $this, 'block_checkout_if_frenet_quote_failed' ), 10, 2 );

                // While a quote is flagged as failed, keep expiring WooCommerce's shipping-rate
                // cache so the next page load re-quotes Frenet instead of serving the stale
                // "no rates" result (see method docblock).
                add_action( 'woocommerce_before_calculate_totals', array( $this, 'expire_shipping_cache_after_frenet_failure' ), 5 );

            } else {
                add_action( 'admin_notices', array( $this, 'wcfrenet_woocommerce_fallback_notice' ) );
            }

            if ( ! class_exists( 'SimpleXmlElement' ) ) {
                add_action( 'admin_notices', 'wcfrenet_extensions_missing_notice' );
            }
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

        /**
         * Rejects checkout while WC_Frenet::mark_quote_result() flagged the last live quote as
         * failed, but only when the customer is actually shipping with Frenet. If every package
         * uses another carrier, the failed Frenet quote is irrelevant and the order proceeds.
         *
         * @param mixed     $order_or_data Order object (blocks) or posted data (classic).
         * @param \WP_Error $errors
         * @return void
         */
        public function block_checkout_if_frenet_quote_failed( $order_or_data, $errors ) {
            if ( ! function_exists( 'WC' ) || ! WC()->session ) {
                return;
            }

            if ( ! WC()->session->get( WC_Frenet::SESSION_KEY_QUOTE_FAILED ) ) {
                return;
            }

            $chosen_methods = (array) WC()->session->get( 'chosen_shipping_methods', array() );
            $shipping_with_frenet = false;

            foreach ( $chosen_methods as $chosen_method ) {
                if ( 0 === strpos( (string) $chosen_method, 'FRENET_' ) ) {
                    $shipping_with_frenet = true;
                    break;
                }
            }

            if ( ! $shipping_with_frenet ) {
                return;
            }

            $errors->add(
                'frenet_quote_failed',
                __( 'Unable to confirm the Frenet shipping quote. Refresh the page or re-enter the zip code, or choose another shipping method before placing the order.', 'woo-shipping-gateway' )
            );
        }

        /**
         * Expires WooCommerce's shipping-rate cache while the last Frenet quote is flagged as
         * failed, so an unchanged cart re-quotes on the next request instead of being stuck
         * with the cached "no Frenet rate" result until the cart or address changes.
         *
         * Throttled with a short transient: the shipping cache version is global, so bumping
         * it on every request during an outage would force every shopper to recompute
         * shipping on every page load.
         *
         * @return void
         */
        public function expire_shipping_cache_after_frenet_failure() {
            if ( ! function_exists( 'WC' ) || ! WC()->session || ! class_exists( 'WC_Cache_Helper' ) ) {
                return;
            }

            if ( ! WC()->session->get( WC_Frenet::SESSION_KEY_QUOTE_FAILED ) ) {
                return;
            }

            if ( false !== get_transient( 'wc_frenet_shipping_cache_expired' ) ) {
                return;
            }

            set_transient( 'wc_frenet_shipping_cache_expired', 1, 30 );
            WC_Cache_Helper::get_transient_version( 'shipping', true );
        }

        function wcfrenet_extensions_missing_notice() {
            ?>
            <div class="notice notice-error is-dismissible">
            <p><?php _e( 'FRENET: Você precisa ativar a extensão do php SimpleXmlElement' ); ?></p>
            </div>
            <?php
        }

        function wcfrenet_woocommerce_fallback_notice() {
            ?>
            <div class="notice notice-error is-dismissible">
            <p><?php _e( 'FRENET: Instale o woocomerce para poder usar esta extensão' ); ?></p>
            </div>
            <?php
        }

    }

    add_action( 'plugins_loaded', array( 'WC_Frenet_Main', 'get_instance' ) );

endif;
