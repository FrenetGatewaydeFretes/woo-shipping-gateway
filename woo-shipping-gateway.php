<?php
/**
 * Plugin Name: WooCommerce Frenet
 * Plugin URI: https://github.com/FrenetGatewaydeFretes/woo-shipping-gateway
 * Description: Frenet para WooCommerce
 * Author: Rafael Mancini
 * Author URI: http://www.frenet.com.br
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: woo-shipping-gateway
 * Domain Path: /languages/
 */

define( 'WOO_FRENET_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_FRENET_URL', plugin_dir_url( __FILE__ ) );

/**
 * WooCommerce fallback notice.
 */
function wcfrenet_woocommerce_fallback_notice() {
	echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Frenet depends on %s to work!', 'woo-shipping-gateway' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
}

/**
 * SimpleXML missing notice.
 */
function wcfrenet_extensions_missing_notice() {
	echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Frenet depends to %s to work!', 'woo-shipping-gateway' ), '<a href="http://php.net/manual/en/book.simplexml.php">SimpleXML</a>' ) . '</p></div>';
}

/**
 * Load functions.
 */
function wcfrenet_shipping_load() {

	if ( ! class_exists( 'WC_Shipping_Method' ) ) {
		add_action( 'admin_notices', 'wcfrenet_woocommerce_fallback_notice' );

		return;
	}

	if ( ! class_exists( 'SimpleXmlElement' ) ) {
		add_action( 'admin_notices', 'wcfrenet_extensions_missing_notice' );

		return;
	}

	/**
	 * Load textdomain.
	 */
	load_plugin_textdomain( 'woo-shipping-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	/**
	 * Add the Frenet to shipping methods.
	 *
	 * @param array $methods
	 *
	 * @return array
	 */
	function wcfrenet_add_method( $methods ) {
		$methods[] = 'WC_Frenet';

		return $methods;
	}

	add_filter( 'woocommerce_shipping_methods', 'wcfrenet_add_method' );

	// WC_Frenet class.
	include_once WOO_FRENET_PATH . 'includes/class-wc-frenet.php';
}

add_action( 'plugins_loaded', 'wcfrenet_shipping_load', 0 );
