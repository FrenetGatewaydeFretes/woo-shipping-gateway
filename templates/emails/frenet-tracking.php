<?php
/**
 * Tracking code e-mail (HTML). Can be overridden in the theme: woocommerce/emails/frenet-tracking.php.
 *
 * @package woo-shipping-gateway
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $tracking_code
 * @var string   $tracking_url
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core e-mail hook.
?>
<?php /* translators: %s: customer first name */ ?>
<p><?php printf( esc_html__( 'Hi %s,', 'woo-shipping-gateway' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<?php /* translators: %s: order number */ ?>
<p><?php printf( esc_html__( 'Your order #%s has been shipped.', 'woo-shipping-gateway' ), esc_html( $order->get_order_number() ) ); ?></p>
<p><?php esc_html_e( 'Tracking code:', 'woo-shipping-gateway' ); ?> <strong><?php echo esc_html( $tracking_code ); ?></strong>
<?php if ( $tracking_url ) : ?>
	· <a href="<?php echo esc_url( $tracking_url ); ?>"><?php esc_html_e( 'Track my order', 'woo-shipping-gateway' ); ?></a>
<?php endif; ?>
</p>
<?php
do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core e-mail hook.
