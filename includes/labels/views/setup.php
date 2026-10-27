<?php
/**
 * First steps: a stepper (account, sender, payment) and one step at a time.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_flash   = WC_Frenet_Labels_Setup::take_flash();
$frenet_errors  = $frenet_flash['errors'];
$frenet_typed   = $frenet_flash['input'];
$frenet_current = WC_Frenet_Labels_Setup::current();
$frenet_s       = WC_Frenet_Labels_Settings::all();
$frenet_steps   = WC_Frenet_Labels_Setup::titles();
$frenet_active  = WC_Frenet_Labels_Setup::steps();
$frenet_basic   = 1 === count( $frenet_active );

/**
 * Error message under a field, tied to it by id.
 *
 * @param array<string, string> $errors Field → message.
 * @param string                $key    Field.
 * @return void
 */
$frenet_error = static function ( array $errors, $key ) {
	if ( isset( $errors[ $key ] ) ) {
		echo '<span class="frenet-field-msg is-error" id="frenet-err-' . esc_attr( $key ) . '">' . esc_html( $errors[ $key ] ) . '</span>';
	}
};
/**
 * aria attributes of a field with an error.
 *
 * @param array<string, string> $errors Field → message.
 * @param string                $key    Field.
 * @return string
 */
$frenet_invalid = static function ( array $errors, $key ) {
	return isset( $errors[ $key ] ) ? ' aria-invalid="true" aria-describedby="frenet-err-' . esc_attr( $key ) . '"' : '';
};

WC_Frenet_Labels_Admin::view( 'deposit-notice' );
?>
<?php if ( '' !== $frenet_flash['message'] ) : ?>
	<div class="notice notice-<?php echo 'error' === $frenet_flash['type'] ? 'error' : 'success'; ?> is-dismissible" <?php echo 'error' === $frenet_flash['type'] ? 'role="alert"' : 'role="status"'; ?>><p><?php echo esc_html( $frenet_flash['message'] ); ?></p></div>
<?php endif; ?>

<div class="frenet-setup">
	<nav aria-label="<?php esc_attr_e( 'Setup steps', 'woo-shipping-gateway' ); ?>">
		<ol class="frenet-steps">
			<?php
			$frenet_n = 0;
			foreach ( $frenet_steps as $frenet_key => $frenet_step ) :
				++$frenet_n;
				$frenet_locked = ! in_array( $frenet_key, $frenet_active, true );
				$frenet_done   = ! $frenet_locked && WC_Frenet_Labels_Setup::done( $frenet_key );
				$frenet_here   = $frenet_key === $frenet_current;
				$frenet_cls    = ( $frenet_done ? ' is-done' : '' ) . ( $frenet_here ? ' is-current' : '' ) . ( $frenet_locked ? ' is-locked' : '' );
				$frenet_tag    = $frenet_locked ? 'span' : 'a';
				?>
				<li class="frenet-steps__item<?php echo esc_attr( $frenet_cls ); ?>">
					<<?php echo esc_attr( $frenet_tag ); ?><?php echo $frenet_locked ? '' : ' href="' . esc_url( WC_Frenet_Labels_Setup::url( $frenet_key ) ) . '"'; ?><?php echo $frenet_here ? ' aria-current="step"' : ''; ?>>
						<span class="frenet-steps__dot" aria-hidden="true"><?php echo $frenet_done ? '&#10003;' : (int) $frenet_n; ?></span>
						<span class="frenet-steps__text">
							<strong><?php echo esc_html( $frenet_step[0] ); ?></strong>
							<span><?php echo esc_html( $frenet_locked ? __( 'Only with the partner token', 'woo-shipping-gateway' ) : ( $frenet_done ? __( 'Done', 'woo-shipping-gateway' ) : $frenet_step[1] ) ); ?></span>
						</span>
					</<?php echo esc_attr( $frenet_tag ); ?>>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>

	<section class="frenet-card frenet-setup__panel">
	<?php if ( 'account' === $frenet_current ) : ?>
		<?php
		$frenet_method_token = trim( (string) ( WC_Frenet_Labels_Settings::shipping_options()['token'] ?? '' ) );
		$frenet_token_value  = $frenet_typed['token'] ?? $frenet_s['token'];
		$frenet_has_partner  = '' !== $frenet_s['partner_token'];
		?>
		<p class="frenet-setup__kicker"><?php esc_html_e( 'Step 1 of 3', 'woo-shipping-gateway' ); ?></p>
		<h2><?php esc_html_e( 'Connect your Frenet account', 'woo-shipping-gateway' ); ?></h2>
		<p class="frenet-lead"><?php esc_html_e( 'With the store token, the store quotes freight, tracks parcels, fills the address by CEP and shows your wallet balance. To also buy and print labels here, add the partner token. We check both with Frenet before going on.', 'woo-shipping-gateway' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="frenet-setup__form" novalidate>
			<input type="hidden" name="action" value="<?php echo esc_attr( WC_Frenet_Labels_Setup::ACTION ); ?>">
			<input type="hidden" name="step" value="account">
			<?php wp_nonce_field( WC_Frenet_Labels_Admin::NONCE ); ?>

			<label class="frenet-field" for="frenet-setup-token"><?php esc_html_e( 'Store token', 'woo-shipping-gateway' ); ?></label>
			<input id="frenet-setup-token" class="regular-text" type="text" name="frenet_labels[token]" value="<?php echo esc_attr( $frenet_token_value ); ?>" autocomplete="off" spellcheck="false"
				placeholder="<?php echo esc_attr( '' !== $frenet_method_token ? __( 'Using the token of the Frenet shipping method', 'woo-shipping-gateway' ) : '' ); ?>"<?php echo $frenet_invalid( $frenet_errors, 'token' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>>
			<?php $frenet_error( $frenet_errors, 'token' ); ?>
			<p class="description">
				<?php
				echo esc_html(
					'' !== $frenet_method_token
						? __( 'Found in the Frenet shipping method: leave it empty to use that one.', 'woo-shipping-gateway' )
						: __( 'The same token you use to quote freight. It is in the Frenet panel, in Integrations.', 'woo-shipping-gateway' )
				);
				?>
				<a href="<?php echo esc_url( WC_Frenet_Labels_Admin::PANEL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the Frenet panel', 'woo-shipping-gateway' ); ?></a>
			</p>

			<label class="frenet-field" for="frenet-setup-partner"><?php esc_html_e( 'Partner token (optional, for labels)', 'woo-shipping-gateway' ); ?></label>
			<input id="frenet-setup-partner" class="regular-text" type="password" name="frenet_labels[partner_token]" value="" autocomplete="new-password" spellcheck="false"
				placeholder="<?php echo esc_attr( $frenet_has_partner ? __( 'Saved. Type a new one to replace it.', 'woo-shipping-gateway' ) : '' ); ?>"<?php echo $frenet_invalid( $frenet_errors, 'partner_token' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>>
			<?php $frenet_error( $frenet_errors, 'partner_token' ); ?>
			<p class="description"><?php esc_html_e( 'Exclusive to Frenet customers with the whitelabel service; each store has its own. Without it, leave it empty: everything else works and the labels are bought in the Frenet panel. Keep it secret like a password.', 'woo-shipping-gateway' ); ?></p>

			<div class="frenet-actions">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Check and continue', 'woo-shipping-gateway' ); ?></button>
			</div>
		</form>

	<?php elseif ( 'sender' === $frenet_current ) : ?>
		<?php
		$frenet_draft  = WC_Frenet_Labels_Setup::sender_draft();
		$frenet_sender = $frenet_typed ? array_merge( $frenet_draft['sender'], $frenet_typed ) : $frenet_draft['sender'];
		$frenet_fields = array(
			'name'         => array( __( 'Name or company name', 'woo-shipping-gateway' ), 'text', 'organization', 'frenet-sender__wide' ),
			'document'     => array( __( 'CPF/CNPJ', 'woo-shipping-gateway' ), 'text', 'off', '' ),
			'email'        => array( __( 'E-mail', 'woo-shipping-gateway' ), 'email', 'email', '' ),
			'postcode'     => array( __( 'CEP', 'woo-shipping-gateway' ), 'numeric', 'postal-code', '' ),
			'address_1'    => array( __( 'Street', 'woo-shipping-gateway' ), 'text', 'address-line1', 'frenet-sender__wide' ),
			'number'       => array( __( 'Number', 'woo-shipping-gateway' ), 'text', 'off', '' ),
			'address_2'    => array( __( 'Complement (optional)', 'woo-shipping-gateway' ), 'text', 'address-line2', '' ),
			'neighborhood' => array( __( 'Neighborhood', 'woo-shipping-gateway' ), 'text', 'address-level3', '' ),
			'city'         => array( __( 'City', 'woo-shipping-gateway' ), 'text', 'address-level2', '' ),
		);
		?>
		<p class="frenet-setup__kicker"><?php esc_html_e( 'Step 2 of 3', 'woo-shipping-gateway' ); ?></p>
		<h2><?php esc_html_e( 'Confirm the sender', 'woo-shipping-gateway' ); ?></h2>
		<p class="frenet-lead"><?php esc_html_e( 'This goes on every label as the sender. We filled it with your store data (WooCommerce > Settings > General); check each field and complete what is missing.', 'woo-shipping-gateway' ); ?></p>
		<?php if ( $frenet_draft['from_cep'] && ! $frenet_typed ) : ?>
			<div class="frenet-note"><?php esc_html_e( 'WooCommerce has no neighborhood field, so we looked it up by the CEP. Check that it is right.', 'woo-shipping-gateway' ); ?></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="frenet-setup__form" novalidate data-frenet-setup-sender>
			<input type="hidden" name="action" value="<?php echo esc_attr( WC_Frenet_Labels_Setup::ACTION ); ?>">
			<input type="hidden" name="step" value="sender">
			<?php wp_nonce_field( WC_Frenet_Labels_Admin::NONCE ); ?>
			<div class="frenet-sender">
				<?php foreach ( $frenet_fields as $frenet_key => $frenet_field ) : ?>
					<p class="<?php echo esc_attr( $frenet_field[3] ); ?>">
						<label class="frenet-field" for="frenet-sender-<?php echo esc_attr( $frenet_key ); ?>"><?php echo esc_html( $frenet_field[0] ); ?></label>
						<input id="frenet-sender-<?php echo esc_attr( $frenet_key ); ?>" class="widefat" name="frenet_labels[sender_<?php echo esc_attr( $frenet_key ); ?>]"
							type="<?php echo 'email' === $frenet_field[1] ? 'email' : 'text'; ?>"<?php echo 'numeric' === $frenet_field[1] ? ' inputmode="numeric"' : ''; ?>
							autocomplete="<?php echo esc_attr( $frenet_field[2] ); ?>" value="<?php echo esc_attr( (string) ( $frenet_sender[ $frenet_key ] ?? '' ) ); ?>"
							data-frenet-sender="<?php echo esc_attr( $frenet_key ); ?>"<?php echo $frenet_invalid( $frenet_errors, $frenet_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>>
						<?php $frenet_error( $frenet_errors, $frenet_key ); ?>
						<?php if ( 'document' === $frenet_key && ! isset( $frenet_errors['document'] ) ) : ?>
							<span class="frenet-field-msg frenet-muted"><?php esc_html_e( 'The one of the Frenet account that pays the labels.', 'woo-shipping-gateway' ); ?></span>
						<?php elseif ( 'postcode' === $frenet_key ) : ?>
							<span class="frenet-field-msg frenet-muted" data-frenet-cep-status aria-live="polite"></span>
						<?php endif; ?>
					</p>
				<?php endforeach; ?>
				<p>
					<label class="frenet-field" for="frenet-sender-state"><?php esc_html_e( 'State (UF)', 'woo-shipping-gateway' ); ?></label>
					<select id="frenet-sender-state" class="widefat" name="frenet_labels[sender_state]" autocomplete="address-level1" data-frenet-sender="state"<?php echo $frenet_invalid( $frenet_errors, 'state' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>>
						<option value=""><?php esc_html_e( 'Choose…', 'woo-shipping-gateway' ); ?></option>
						<?php foreach ( WC_Frenet_Labels_Setup::UFS as $frenet_uf ) : ?>
							<option value="<?php echo esc_attr( $frenet_uf ); ?>" <?php selected( $frenet_uf, strtoupper( (string) ( $frenet_sender['state'] ?? '' ) ) ); ?>><?php echo esc_html( $frenet_uf ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php $frenet_error( $frenet_errors, 'state' ); ?>
				</p>
			</div>
			<div class="frenet-actions">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Confirm sender', 'woo-shipping-gateway' ); ?></button>
				<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Setup::url( 'account' ) ); ?>"><?php esc_html_e( 'Back', 'woo-shipping-gateway' ); ?></a>
			</div>
		</form>

	<?php elseif ( 'payment' === $frenet_current ) : ?>
		<?php $frenet_wallet = WC_Frenet_Labels_Settings::ready() ? WC_Frenet_Labels_Service::wallet() : new WP_Error( 'frenet_setup_account', __( 'Connect your account in step 1 to see the balance.', 'woo-shipping-gateway' ) ); ?>
		<p class="frenet-setup__kicker"><?php esc_html_e( 'Step 3 of 3', 'woo-shipping-gateway' ); ?></p>
		<h2><?php esc_html_e( 'Balance and payment', 'woo-shipping-gateway' ); ?></h2>
		<p class="frenet-lead"><?php esc_html_e( 'Each label is paid with the wallet balance at the moment it is created. Add balance now or later; without balance, labels are not bought.', 'woo-shipping-gateway' ); ?></p>

		<div class="frenet-setup__wallet" data-frenet-wallet>
			<?php if ( is_wp_error( $frenet_wallet ) ) : ?>
				<div class="frenet-note frenet-note--warn"><?php echo esc_html( $frenet_wallet->get_error_message() ); ?> <a href="<?php echo esc_url( WC_Frenet_Labels_Setup::url( 'account' ) ); ?>"><?php esc_html_e( 'Go to step 1', 'woo-shipping-gateway' ); ?></a></div>
			<?php else : ?>
				<div>
					<span class="frenet-muted"><?php esc_html_e( 'Wallet balance', 'woo-shipping-gateway' ); ?></span>
					<strong class="frenet-setup__balance" data-balance="<?php echo esc_attr( (string) $frenet_wallet['balance'] ); ?>"><?php echo esc_html( WC_Frenet_Labels_Service::money( $frenet_wallet['balance'] ) ); ?></strong>
					<button type="button" class="button-link" data-frenet-wallet-refresh><?php esc_html_e( 'Update balance', 'woo-shipping-gateway' ); ?></button>
				</div>
				<form class="frenet-setup__deposit" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="frenet_labels_deposit">
					<?php wp_nonce_field( WC_Frenet_Labels_Admin::NONCE ); ?>
					<label class="frenet-field" for="frenet-setup-deposit"><?php esc_html_e( 'Amount to add (R$)', 'woo-shipping-gateway' ); ?></label>
					<span class="frenet-setup__deposit-row">
						<input id="frenet-setup-deposit" type="text" inputmode="decimal" name="value" value="50,00" required>
						<button type="submit" class="button"><?php esc_html_e( 'Add balance with Mercado Pago', 'woo-shipping-gateway' ); ?></button>
					</span>
					<span class="frenet-field-msg frenet-muted">
						<?php
						/* translators: %s: minimum amount, e.g. R$ 5,00 */
						echo esc_html( sprintf( __( 'Pix or card, from %s. You come back here when you finish paying.', 'woo-shipping-gateway' ), WC_Frenet_Labels_Service::money( WC_Frenet_Labels_Service::DEPOSIT_MIN ) ) );
						?>
					</span>
				</form>
			<?php endif; ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="frenet-setup__form">
			<input type="hidden" name="action" value="<?php echo esc_attr( WC_Frenet_Labels_Setup::ACTION ); ?>">
			<input type="hidden" name="step" value="payment">
			<?php wp_nonce_field( WC_Frenet_Labels_Admin::NONCE ); ?>
			<label class="frenet-switch"><input type="checkbox" name="frenet_labels[wallet_payment]" value="1" <?php checked( 'yes', $frenet_s['wallet_payment'] ); ?>> <span><?php esc_html_e( 'Pay with the wallet when creating', 'woo-shipping-gateway' ); ?></span></label>
			<p class="description"><?php esc_html_e( 'On (recommended): the label is paid and ready to print right away. Off: labels are created unpaid and wait in Labels until you pay them.', 'woo-shipping-gateway' ); ?></p>
			<div class="frenet-actions">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Finish setup', 'woo-shipping-gateway' ); ?></button>
				<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Setup::url( 'sender' ) ); ?>"><?php esc_html_e( 'Back', 'woo-shipping-gateway' ); ?></a>
			</div>
		</form>

	<?php else : ?>
		<?php
		$frenet_sender = WC_Frenet_Labels_Payload::sender();
		$frenet_left   = WC_Frenet_Labels_Setup::remaining();
		?>
		<?php if ( $frenet_left ) : ?>
			<h2><?php esc_html_e( 'Almost there', 'woo-shipping-gateway' ); ?></h2>
			<p class="frenet-lead">
				<?php
				/* translators: %d: number of steps */
				echo esc_html( sprintf( _n( '%d step is still missing. Labels cannot be bought until it is done.', '%d steps are still missing. Labels cannot be bought until they are done.', $frenet_left, 'woo-shipping-gateway' ), $frenet_left ) );
				?>
			</p>
			<div class="frenet-actions"><a class="button button-primary button-hero" href="<?php echo esc_url( WC_Frenet_Labels_Setup::url() ); ?>"><?php esc_html_e( 'Continue', 'woo-shipping-gateway' ); ?></a></div>
		<?php elseif ( $frenet_basic ) : ?>
			<h2><?php esc_html_e( 'Account connected in basic mode', 'woo-shipping-gateway' ); ?></h2>
			<p class="frenet-lead"><?php esc_html_e( 'With the store token, this already works:', 'woo-shipping-gateway' ); ?></p>
			<ul class="frenet-setup__summary">
				<li><strong><?php esc_html_e( 'Freight at checkout', 'woo-shipping-gateway' ); ?></strong> <?php esc_html_e( 'Quotes of the carriers of your Frenet account, and the simulator on the product page.', 'woo-shipping-gateway' ); ?></li>
				<li><strong><?php esc_html_e( 'Tracking', 'woo-shipping-gateway' ); ?></strong> <?php esc_html_e( 'Paste the tracking code in the order: the store follows it and can e-mail the customer.', 'woo-shipping-gateway' ); ?></li>
				<li><strong><?php esc_html_e( 'Address by CEP', 'woo-shipping-gateway' ); ?></strong> <?php esc_html_e( 'Street, neighborhood, city and state filled at checkout.', 'woo-shipping-gateway' ); ?></li>
				<li><strong><?php esc_html_e( 'Wallet balance', 'woo-shipping-gateway' ); ?></strong> <?php esc_html_e( 'At the top of the Frenet screens.', 'woo-shipping-gateway' ); ?></li>
			</ul>
			<div class="frenet-note"><?php esc_html_e( 'Buying, printing and cancelling labels from the store needs the partner token (Frenet whitelabel service). Until then, buy them in the Frenet panel.', 'woo-shipping-gateway' ); ?></div>
			<p class="description"><?php esc_html_e( 'Tracking e-mails and the address by CEP stay off until you turn them on in Settings.', 'woo-shipping-gateway' ); ?></p>
			<div class="frenet-actions">
				<a class="button button-primary button-hero" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG ) ); ?>"><?php esc_html_e( 'Review all settings', 'woo-shipping-gateway' ); ?></a>
				<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Setup::url( 'account' ) ); ?>"><?php esc_html_e( 'Add the partner token', 'woo-shipping-gateway' ); ?></a>
				<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Admin::PANEL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the Frenet panel', 'woo-shipping-gateway' ); ?></a>
			</div>
		<?php else : ?>
			<h2><?php esc_html_e( 'All set', 'woo-shipping-gateway' ); ?></h2>
			<p class="frenet-lead"><?php esc_html_e( 'The store can buy Frenet labels. This is what was configured:', 'woo-shipping-gateway' ); ?></p>
			<ul class="frenet-setup__summary">
				<li><strong><?php esc_html_e( 'Account', 'woo-shipping-gateway' ); ?></strong> <?php esc_html_e( 'Connected', 'woo-shipping-gateway' ); ?> <a href="<?php echo esc_url( WC_Frenet_Labels_Setup::url( 'account' ) ); ?>"><?php esc_html_e( 'Change', 'woo-shipping-gateway' ); ?></a></li>
				<li><strong><?php esc_html_e( 'Sender', 'woo-shipping-gateway' ); ?></strong> <?php echo esc_html( $frenet_sender['name'] . ' · ' . $frenet_sender['address_1'] . ', ' . $frenet_sender['number'] . ' · ' . $frenet_sender['city'] . '/' . $frenet_sender['state'] ); ?> <a href="<?php echo esc_url( WC_Frenet_Labels_Setup::url( 'sender' ) ); ?>"><?php esc_html_e( 'Change', 'woo-shipping-gateway' ); ?></a></li>
				<li><strong><?php esc_html_e( 'Payment', 'woo-shipping-gateway' ); ?></strong> <?php echo esc_html( WC_Frenet_Labels_Settings::on( 'wallet_payment' ) ? __( 'With the wallet, when creating', 'woo-shipping-gateway' ) : __( 'Later, in Labels', 'woo-shipping-gateway' ) ); ?> <a href="<?php echo esc_url( WC_Frenet_Labels_Setup::url( 'payment' ) ); ?>"><?php esc_html_e( 'Change', 'woo-shipping-gateway' ); ?></a></li>
			</ul>
			<p class="description"><?php esc_html_e( 'Automatic shipments, tracking e-mails and the address by CEP stay off until you turn them on in Settings.', 'woo-shipping-gateway' ); ?></p>
			<div class="frenet-actions">
				<a class="button button-primary button-hero" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG . '-create' ) ); ?>"><?php esc_html_e( 'Create my first shipment', 'woo-shipping-gateway' ); ?></a>
				<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG ) ); ?>"><?php esc_html_e( 'Review all settings', 'woo-shipping-gateway' ); ?></a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	</section>
</div>
