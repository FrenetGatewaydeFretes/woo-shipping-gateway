<?php
/**
 * Wallet, inside the brand strip of every labels screen. Balance is added in the Frenet panel (its own payment
 * gateway); back here, "Atualizar saldo" reads it again.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_wallet = WC_Frenet_Labels_Service::wallet();
?>
<div class="frenet-header__wallet" data-frenet-wallet>
	<span class="frenet-header__label"><?php esc_html_e( 'Wallet balance', 'woo-shipping-gateway' ); ?></span>
	<?php if ( is_wp_error( $frenet_wallet ) ) : ?>
		<strong class="frenet-header__value is-muted"><?php esc_html_e( 'Unavailable', 'woo-shipping-gateway' ); ?></strong>
		<span class="frenet-header__meta"><?php echo esc_html( $frenet_wallet->get_error_message() ); ?></span>
	<?php else : ?>
		<strong class="frenet-header__value" data-balance="<?php echo esc_attr( (string) $frenet_wallet['balance'] ); ?>"><?php echo esc_html( WC_Frenet_Labels_Service::money( $frenet_wallet['balance'] ) ); ?></strong>
		<span class="frenet-header__meta">
			<?php
			/* translators: %s: number of labels */
			echo esc_html( sprintf( _n( '%s label available', '%s labels available', $frenet_wallet['labels'], 'woo-shipping-gateway' ), number_format_i18n( $frenet_wallet['labels'] ) ) );
			?>
		</span>
	<?php endif; ?>
	<a class="frenet-header__cta" href="<?php echo esc_url( WC_Frenet_Labels_Admin::PANEL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Add balance in the Frenet panel', 'woo-shipping-gateway' ); ?></a>
	<button type="button" class="frenet-header__link" data-frenet-wallet-refresh title="<?php esc_attr_e( 'After paying in the panel, update the balance here.', 'woo-shipping-gateway' ); ?>"><?php esc_html_e( 'Update balance', 'woo-shipping-gateway' ); ?></button>
</div>
