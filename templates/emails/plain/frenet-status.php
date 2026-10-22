<?php
/**
 * Shipping status e-mail (plain text).
 *
 * @package woo-shipping-gateway
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $status_label
 * @var string   $tracking_code
 * @var string   $tracking_url
 * @var array    $events
 */

defined( 'ABSPATH' ) || exit;

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";
/* translators: 1: order number, 2: order status */
printf( esc_html__( 'Order #%1$s: %2$s.', 'woo-shipping-gateway' ) . "\n\n", esc_html( $order->get_order_number() ), esc_html( $status_label ) );
$frenet_last = $events ? end( $events ) : null;
if ( $frenet_last ) {
	echo esc_html( trim( $frenet_last['description'] . ' · ' . $frenet_last['date'] . ' ' . $frenet_last['location'], ' ·' ) ) . "\n\n";
}
if ( $tracking_code ) {
	echo esc_html__( 'Tracking code:', 'woo-shipping-gateway' ) . ' ' . esc_html( $tracking_code ) . "\n";
	if ( $tracking_url ) {
		echo esc_url_raw( $tracking_url ) . "\n";
	}
}
