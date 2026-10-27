<?php
/**
 * Wallet, inside the brand strip of every labels screen, with "Adicionar saldo" (Mercado Pago checkout of a
 * Frenet wallet deposit). "Atualizar saldo" reads the balance again.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_wallet = WC_Frenet_Labels_Service::wallet();
?>
<?php WC_Frenet_Labels_Admin::view( 'deposit-notice' ); ?>
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
	<?php if ( ! is_wp_error( $frenet_wallet ) && ! WC_Frenet_Labels_Settings::ready() ) : ?>
		<?php // Frenet refuses deposits without the partner token: the balance is added in its panel. ?>
		<a class="frenet-header__cta" href="<?php echo esc_url( WC_Frenet_Labels_Admin::PANEL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Add balance in the Frenet panel', 'woo-shipping-gateway' ); ?></a>
	<?php elseif ( ! is_wp_error( $frenet_wallet ) ) : ?>
	<form class="frenet-header__deposit" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="frenet_labels_deposit">
		<?php wp_nonce_field( WC_Frenet_Labels_Admin::NONCE ); ?>
		<label class="screen-reader-text" for="frenet-deposit-value"><?php esc_html_e( 'Amount to add (R$)', 'woo-shipping-gateway' ); ?></label>
		<input id="frenet-deposit-value" type="text" inputmode="decimal" name="value" value="50,00" required>
		<button type="submit" class="frenet-header__cta"><?php esc_html_e( 'Add balance', 'woo-shipping-gateway' ); ?></button>
	</form>
	<?php endif; ?>
	<button type="button" class="frenet-header__link" data-frenet-wallet-refresh title="<?php esc_attr_e( 'After paying, update the balance here.', 'woo-shipping-gateway' ); ?>"><?php esc_html_e( 'Update balance', 'woo-shipping-gateway' ); ?></button>
</div>
