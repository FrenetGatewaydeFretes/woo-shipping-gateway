<?php
/**
 * Tracking code e-mail (plain text).
 *
 * @package woo-shipping-gateway
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $tracking_code
 * @var string   $tracking_url
 */

defined( 'ABSPATH' ) || exit;

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";
/* translators: %s: order number */
printf( esc_html__( 'Your order #%s has been shipped.', 'woo-shipping-gateway' ) . "\n\n", esc_html( $order->get_order_number() ) );
echo esc_html__( 'Tracking code:', 'woo-shipping-gateway' ) . ' ' . esc_html( $tracking_code ) . "\n";
if ( $tracking_url ) {
	echo esc_url_raw( $tracking_url ) . "\n";
}
