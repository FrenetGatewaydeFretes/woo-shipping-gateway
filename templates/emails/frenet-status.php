<?php
/**
 * Shipping status e-mail (HTML). Can be overridden in the theme: woocommerce/emails/frenet-status.php.
 *
 * @package woo-shipping-gateway
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $status_label
 * @var string   $tracking_code
 * @var string   $tracking_url
 * @var array    $events
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core e-mail hook.
$frenet_last = $events ? end( $events ) : null;
?>
<?php /* translators: %s: customer first name */ ?>
<p><?php printf( esc_html__( 'Hi %s,', 'woo-shipping-gateway' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<?php /* translators: 1: order number, 2: order status */ ?>
<p><?php printf( esc_html__( 'Order #%1$s: %2$s.', 'woo-shipping-gateway' ), esc_html( $order->get_order_number() ), esc_html( $status_label ) ); ?></p>
<?php if ( $frenet_last ) : ?>
	<p><strong><?php echo esc_html( $frenet_last['description'] ); ?></strong><br><?php echo esc_html( trim( $frenet_last['date'] . ' · ' . $frenet_last['location'], ' ·' ) ); ?></p>
<?php endif; ?>
<?php if ( $tracking_code ) : ?>
	<p><?php esc_html_e( 'Tracking code:', 'woo-shipping-gateway' ); ?> <strong><?php echo esc_html( $tracking_code ); ?></strong>
	<?php if ( $tracking_url ) : ?>
		· <a href="<?php echo esc_url( $tracking_url ); ?>"><?php esc_html_e( 'Track my order', 'woo-shipping-gateway' ); ?></a>
	<?php endif; ?>
	</p>
<?php endif; ?>
<?php
do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core e-mail hook.
