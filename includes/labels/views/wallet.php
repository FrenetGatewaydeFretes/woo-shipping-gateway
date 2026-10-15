<?php
/**
 * Wallet card with "Adicionar saldo" (Mercado Pago checkout of a Frenet wallet deposit).
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_wallet  = WC_Frenet_Labels_Service::wallet();
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
<div class="frenet-wallet" data-frenet-wallet>
	<div>
		<span class="frenet-wallet__label"><?php esc_html_e( 'Wallet balance', 'woo-shipping-gateway' ); ?></span>
		<?php if ( is_wp_error( $frenet_wallet ) ) : ?>
			<strong class="frenet-wallet__value is-muted"><?php esc_html_e( 'Unavailable', 'woo-shipping-gateway' ); ?></strong>
			<span class="frenet-muted"><?php echo esc_html( $frenet_wallet->get_error_message() ); ?></span>
		<?php else : ?>
			<strong class="frenet-wallet__value" data-balance="<?php echo esc_attr( (string) $frenet_wallet['balance'] ); ?>"><?php echo esc_html( WC_Frenet_Labels_Service::money( $frenet_wallet['balance'] ) ); ?></strong>
			<span class="frenet-muted">
				<?php
				/* translators: %s: number of labels */
				echo esc_html( sprintf( _n( '%s label available', '%s labels available', $frenet_wallet['labels'], 'woo-shipping-gateway' ), number_format_i18n( $frenet_wallet['labels'] ) ) );
				?>
			</span>
		<?php endif; ?>
	</div>
	<form class="frenet-deposit" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="frenet_labels_deposit">
		<?php wp_nonce_field( WC_Frenet_Labels_Admin::NONCE ); ?>
		<label class="screen-reader-text" for="frenet-deposit-value"><?php esc_html_e( 'Amount to add (R$)', 'woo-shipping-gateway' ); ?></label>
		<input id="frenet-deposit-value" class="small-text" type="text" inputmode="decimal" name="value" value="50,00" required>
		<button type="submit" class="button"><?php esc_html_e( 'Add balance', 'woo-shipping-gateway' ); ?></button>
		<a class="frenet-muted" href="<?php echo esc_url( WC_Frenet_Labels_Admin::PANEL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'or in the Frenet panel', 'woo-shipping-gateway' ); ?></a>
	</form>
</div>
