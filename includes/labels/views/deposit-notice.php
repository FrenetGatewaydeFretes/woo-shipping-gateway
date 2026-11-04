<?php
/**
 * Result of "Adicionar saldo" when Mercado Pago sends the merchant back (?deposit=success|pending|failure),
 * or when Frenet did not start the payment (?deposit=error).
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_deposit = isset( $_GET['deposit'] ) ? sanitize_key( wp_unslash( $_GET['deposit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
$frenet_notices = array(
	'success' => array( 'success', __( 'Payment sent. The balance shows up as soon as Mercado Pago confirms it (Pix in a few minutes).', 'woo-shipping-gateway' ) ),
	'pending' => array( 'warning', __( 'Payment pending at Mercado Pago. The balance shows up when it is confirmed.', 'woo-shipping-gateway' ) ),
	'failure' => array( 'error', __( 'The payment was not completed. Nothing was added to the wallet.', 'woo-shipping-gateway' ) ),
	'error'   => array( 'error', __( 'Frenet did not start the payment. Nothing was charged; try again or add balance in the Frenet panel.', 'woo-shipping-gateway' ) ),
);
if ( 'error' === $frenet_deposit ) {
	// "Adicionar saldo" did not reach Mercado Pago: the reason was kept for this user (WC_Frenet_Labels_Admin::deposit()).
	$frenet_reason = get_transient( WC_Frenet_Labels_Admin::DEPOSIT_ERROR . get_current_user_id() );
	delete_transient( WC_Frenet_Labels_Admin::DEPOSIT_ERROR . get_current_user_id() );
	if ( is_string( $frenet_reason ) && '' !== $frenet_reason ) {
		$frenet_notices['error'] = array( 'error', $frenet_reason );
	}
}
?>
<?php if ( isset( $frenet_notices[ $frenet_deposit ] ) ) : ?>
	<div class="notice notice-<?php echo esc_attr( $frenet_notices[ $frenet_deposit ][0] ); ?> is-dismissible"><p><?php echo esc_html( $frenet_notices[ $frenet_deposit ][1] ); ?></p></div>
<?php endif; ?>
