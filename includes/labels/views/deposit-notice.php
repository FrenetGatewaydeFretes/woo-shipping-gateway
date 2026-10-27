<?php
/**
 * Result of "Adicionar saldo" when Mercado Pago sends the merchant back (?deposit=success|pending|failure).
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_deposit = isset( $_GET['deposit'] ) ? sanitize_key( wp_unslash( $_GET['deposit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
$frenet_notices = array(
	'success' => array( 'success', __( 'Payment sent. The balance shows up as soon as Mercado Pago confirms it (Pix in a few minutes).', 'woo-shipping-gateway' ) ),
	'pending' => array( 'warning', __( 'Payment pending at Mercado Pago. The balance shows up when it is confirmed.', 'woo-shipping-gateway' ) ),
	'failure' => array( 'error', __( 'The payment was not completed. Nothing was added to the wallet.', 'woo-shipping-gateway' ) ),
);
?>
<?php if ( isset( $frenet_notices[ $frenet_deposit ] ) ) : ?>
	<div class="notice notice-<?php echo esc_attr( $frenet_notices[ $frenet_deposit ][0] ); ?> is-dismissible"><p><?php echo esc_html( $frenet_notices[ $frenet_deposit ][1] ); ?></p></div>
<?php endif; ?>
