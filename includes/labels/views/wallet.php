<?php
/**
 * Wallet, inside the brand strip of every labels screen. "Adicionar saldo" opens a small panel: amount (with
 * quick picks) and then the button to the Mercado Pago checkout of a Frenet wallet deposit. "Atualizar saldo"
 * reads the balance again.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_wallet = WC_Frenet_Labels_Service::wallet();
// Mercado Pago only returns to https pages: on http it returns to the Frenet panel, and the panel says so first.
$frenet_https = 'https' === wp_parse_url( admin_url(), PHP_URL_SCHEME );
$frenet_min   = WC_Frenet_Labels_Service::money( WC_Frenet_Labels_Service::DEPOSIT_MIN );
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
		<details class="frenet-refill" data-frenet-refill>
			<summary class="frenet-header__cta"><?php esc_html_e( 'Add balance', 'woo-shipping-gateway' ); ?></summary>
			<div class="frenet-refill__panel">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-frenet-refill-form data-min="<?php echo esc_attr( (string) WC_Frenet_Labels_Service::DEPOSIT_MIN ); ?>">
					<input type="hidden" name="action" value="frenet_labels_deposit">
					<?php wp_nonce_field( WC_Frenet_Labels_Admin::NONCE ); ?>
					<p class="frenet-refill__title"><?php esc_html_e( 'Add balance', 'woo-shipping-gateway' ); ?></p>
					<p class="frenet-refill__lead"><?php esc_html_e( 'The amount goes to your Frenet wallet and pays the next labels.', 'woo-shipping-gateway' ); ?></p>
					<label class="frenet-field" for="frenet-deposit-value"><?php esc_html_e( 'Amount', 'woo-shipping-gateway' ); ?></label>
					<span class="frenet-refill__money">
						<span aria-hidden="true">R$</span>
						<input id="frenet-deposit-value" type="text" inputmode="decimal" name="value" value="50,00" autocomplete="off" aria-describedby="frenet-deposit-help" data-frenet-refill-value>
					</span>
					<span class="frenet-refill__picks" role="group" aria-label="<?php esc_attr_e( 'Quick amounts', 'woo-shipping-gateway' ); ?>">
						<?php foreach ( array( 50, 100, 200 ) as $frenet_pick ) : ?>
							<button type="button" class="frenet-refill__pick" data-frenet-refill-pick="<?php echo esc_attr( (string) $frenet_pick ); ?>"><?php echo esc_html( WC_Frenet_Labels_Service::money( $frenet_pick ) ); ?></button>
						<?php endforeach; ?>
					</span>
					<span id="frenet-deposit-help" class="frenet-refill__help" aria-live="polite" data-frenet-refill-help>
						<?php
						/* translators: %s: minimum amount, e.g. R$ 5,00 */
						echo esc_html( sprintf( __( 'From %s. Pix, card or boleto at Mercado Pago; you come back here when you finish.', 'woo-shipping-gateway' ), $frenet_min ) );
						?>
					</span>
					<?php if ( ! $frenet_https ) : ?>
						<span class="frenet-refill__warn"><?php esc_html_e( 'This store has no HTTPS, so after paying Mercado Pago takes you to the Frenet panel. Come back here and update the balance.', 'woo-shipping-gateway' ); ?></span>
					<?php endif; ?>
					<span class="frenet-refill__actions">
						<button type="submit" class="button button-primary" data-frenet-refill-submit><?php esc_html_e( 'Continue to Mercado Pago', 'woo-shipping-gateway' ); ?></button>
						<button type="button" class="button-link" data-frenet-refill-close><?php esc_html_e( 'Cancel', 'woo-shipping-gateway' ); ?></button>
					</span>
				</form>
			</div>
		</details>
	<?php endif; ?>
	<button type="button" class="frenet-header__link" data-frenet-wallet-refresh title="<?php esc_attr_e( 'After paying, update the balance here.', 'woo-shipping-gateway' ); ?>"><?php esc_html_e( 'Update balance', 'woo-shipping-gateway' ); ?></button>
</div>
