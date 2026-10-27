<?php
/**
 * Configuração (Figma: Geral, Envio, Pagamento).
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_s       = WC_Frenet_Labels_Settings::all();
$frenet_store   = WC_Frenet_Labels_Settings::store_token();
$frenet_saved   = isset( $_GET['saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
$frenet_partner = '' !== $frenet_s['partner_token'];
?>
<?php if ( $frenet_saved ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'woo-shipping-gateway' ); ?></p></div>
<?php endif; ?>
<?php
$frenet_left = WC_Frenet_Labels_Setup::remaining();
if ( $frenet_left ) :
	?>
	<div class="frenet-setup-banner">
		<p>
			<strong><?php esc_html_e( 'Finish the first steps', 'woo-shipping-gateway' ); ?></strong>
			<?php
			$frenet_todo   = array();
			$frenet_titles = WC_Frenet_Labels_Setup::titles();
			foreach ( WC_Frenet_Labels_Setup::steps() as $frenet_key ) {
				if ( ! WC_Frenet_Labels_Setup::done( $frenet_key ) ) {
					$frenet_todo[] = $frenet_titles[ $frenet_key ][0];
				}
			}
			/* translators: %s: list of steps, e.g. "Connect your account, Confirm the sender" */
			echo esc_html( sprintf( __( 'Still to do: %s.', 'woo-shipping-gateway' ), implode( ', ', $frenet_todo ) ) );
			?>
		</p>
		<a class="button button-primary" href="<?php echo esc_url( WC_Frenet_Labels_Setup::url() ); ?>"><?php esc_html_e( 'Continue setup', 'woo-shipping-gateway' ); ?></a>
	</div>
<?php elseif ( 'basic' === WC_Frenet_Labels_Settings::mode() ) : ?>
	<div class="frenet-setup-banner">
		<p>
			<strong><?php esc_html_e( 'Basic mode: no partner token', 'woo-shipping-gateway' ); ?></strong>
			<?php esc_html_e( 'Quotes, simulator, tracking, address by CEP and the balance work. Buying labels from the store needs the partner token; until then, buy them in the Frenet panel.', 'woo-shipping-gateway' ); ?>
		</p>
		<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Setup::url( 'account' ) ); ?>"><?php esc_html_e( 'Add the partner token', 'woo-shipping-gateway' ); ?></a>
	</div>
<?php endif; ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="frenet-form">
	<input type="hidden" name="action" value="frenet_labels_save">
	<?php wp_nonce_field( WC_Frenet_Labels_Admin::NONCE ); ?>
	<?php // Two columns on wide screens: the whole form fits with less scrolling, and Save stays in the bar below. ?>
	<div class="frenet-grid">
	<section class="frenet-card">
		<h2><?php esc_html_e( 'General', 'woo-shipping-gateway' ); ?></h2>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[enabled]" value="1" <?php checked( 'yes', $frenet_s['enabled'] ); ?>> <span><?php esc_html_e( 'Enable Frenet labels', 'woo-shipping-gateway' ); ?></span></label>

		<label class="frenet-field" for="frenet-token"><?php esc_html_e( 'Client token', 'woo-shipping-gateway' ); ?></label>
		<input id="frenet-token" class="regular-text" type="text" name="frenet_labels[token]" value="<?php echo esc_attr( $frenet_s['token'] ); ?>" autocomplete="off" spellcheck="false" aria-describedby="frenet-token-help">
		<p id="frenet-token-help" class="description">
			<?php
			echo esc_html(
				'' !== $frenet_s['token'] || '' === $frenet_store
					? __( 'Leave empty to use the token of the Frenet shipping method.', 'woo-shipping-gateway' )
					: __( 'Empty: the token of the Frenet shipping method is used (found).', 'woo-shipping-gateway' )
			);
			?>
		</p>

		<label class="frenet-field" for="frenet-partner"><?php esc_html_e( 'Partner token (optional, for labels)', 'woo-shipping-gateway' ); ?></label>
		<input id="frenet-partner" class="regular-text" type="password" name="frenet_labels[partner_token]" value="" autocomplete="new-password" spellcheck="false"
			placeholder="<?php echo esc_attr( $frenet_partner ? __( 'Saved. Type a new one to replace it.', 'woo-shipping-gateway' ) : '' ); ?>" aria-describedby="frenet-partner-help">
		<p id="frenet-partner-help" class="description"><?php esc_html_e( 'Exclusive to Frenet customers with the whitelabel service: each store has its own (x-partner-token). Ask Frenet for it. Required to create, list, pay and cancel labels. Keep it secret like a password.', 'woo-shipping-gateway' ); ?></p>
		<?php if ( $frenet_partner ) : ?>
			<label class="frenet-inline"><input type="checkbox" name="frenet_labels[clear_partner_token]" value="1"> <?php esc_html_e( 'Remove the saved partner token', 'woo-shipping-gateway' ); ?></label>
		<?php endif; ?>

		<label class="frenet-field" for="frenet-url"><?php esc_html_e( 'API URL', 'woo-shipping-gateway' ); ?></label>
		<input id="frenet-url" class="regular-text code" type="url" name="frenet_labels[api_url]" value="<?php echo esc_attr( $frenet_s['api_url'] ); ?>" aria-describedby="frenet-url-help">
		<p id="frenet-url-help" class="description"><?php esc_html_e( 'Default: https://whitelabel.frenet.com.br/v1. Change it only for tests.', 'woo-shipping-gateway' ); ?></p>
	</section>

	<section class="frenet-card">
		<h2><?php esc_html_e( 'Shipping', 'woo-shipping-gateway' ); ?></h2>
		<fieldset>
			<legend class="frenet-field"><?php esc_html_e( 'Default service', 'woo-shipping-gateway' ); ?></legend>
			<label class="frenet-inline"><input type="radio" name="frenet_labels[default_service]" value="best_price" <?php checked( 'best_price', $frenet_s['default_service'] ); ?>> <?php esc_html_e( 'Best price', 'woo-shipping-gateway' ); ?></label>
			<label class="frenet-inline"><input type="radio" name="frenet_labels[default_service]" value="best_time" <?php checked( 'best_time', $frenet_s['default_service'] ); ?>> <?php esc_html_e( 'Fastest', 'woo-shipping-gateway' ); ?></label>
		</fieldset>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[auto_best]" value="1" <?php checked( 'yes', $frenet_s['auto_best'] ); ?>> <span><?php esc_html_e( 'Select the best option automatically', 'woo-shipping-gateway' ); ?></span></label>
		<p class="description"><?php esc_html_e( 'Off: each order starts with the carrier the customer chose at checkout. On: it starts with the best one by the default service. You can always change it before creating.', 'woo-shipping-gateway' ); ?></p>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[batch_print]" value="1" <?php checked( 'yes', $frenet_s['batch_print'] ); ?>> <span><?php esc_html_e( 'Batch label printing', 'woo-shipping-gateway' ); ?></span></label>
		<label class="frenet-field" for="frenet-format"><?php esc_html_e( 'Label format', 'woo-shipping-gateway' ); ?></label>
		<select id="frenet-format" name="frenet_labels[label_format]">
			<option value="A4" <?php selected( 'A4', $frenet_s['label_format'] ); ?>>A4</option>
			<option value="10x15" <?php selected( '10x15', $frenet_s['label_format'] ); ?>>10x15</option>
		</select>
	</section>

	<section class="frenet-card" id="frenet-sender-settings">
		<h2><?php esc_html_e( 'Sender', 'woo-shipping-gateway' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Filled in from the store settings (WooCommerce > Settings > General and the Frenet shipping method). Change only what is different on the labels; empty a field to use the store value again.', 'woo-shipping-gateway' ); ?></p>
		<div class="frenet-sender">
			<?php
			$frenet_sender = WC_Frenet_Labels_Payload::sender();
			$frenet_fields = array(
				'name'         => array( __( 'Name', 'woo-shipping-gateway' ), 'text', 'organization', 'frenet-sender__wide' ),
				'document'     => array( __( 'CPF/CNPJ', 'woo-shipping-gateway' ), 'numeric', 'off', '' ),
				'email'        => array( __( 'E-mail', 'woo-shipping-gateway' ), 'email', 'email', '' ),
				'postcode'     => array( __( 'CEP', 'woo-shipping-gateway' ), 'numeric', 'postal-code', '' ),
				'address_1'    => array( __( 'Street', 'woo-shipping-gateway' ), 'text', 'address-line1', 'frenet-sender__wide' ),
				'number'       => array( __( 'Number', 'woo-shipping-gateway' ), 'text', 'off', '' ),
				'address_2'    => array( __( 'Complement', 'woo-shipping-gateway' ), 'text', 'address-line2', '' ),
				'neighborhood' => array( __( 'Neighborhood', 'woo-shipping-gateway' ), 'text', 'address-level3', '' ),
				'city'         => array( __( 'City', 'woo-shipping-gateway' ), 'text', 'address-level2', '' ),
				'state'        => array( __( 'State (UF)', 'woo-shipping-gateway' ), 'text', 'address-level1', '' ),
			);
			foreach ( $frenet_fields as $frenet_key => $frenet_field ) :
				?>
				<p class="<?php echo esc_attr( $frenet_field[3] ); ?>">
					<label class="frenet-field" for="frenet-sender-<?php echo esc_attr( $frenet_key ); ?>"><?php echo esc_html( $frenet_field[0] ); ?></label>
					<input id="frenet-sender-<?php echo esc_attr( $frenet_key ); ?>" class="widefat" name="frenet_labels[sender_<?php echo esc_attr( $frenet_key ); ?>]"
						type="<?php echo 'email' === $frenet_field[1] ? 'email' : 'text'; ?>"<?php echo 'numeric' === $frenet_field[1] ? ' inputmode="numeric"' : ''; ?><?php echo 'state' === $frenet_key ? ' maxlength="2"' : ''; ?>
						autocomplete="<?php echo esc_attr( $frenet_field[2] ); ?>" value="<?php echo esc_attr( $frenet_sender[ $frenet_key ] ); ?>">
				</p>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="frenet-card">
		<h2><?php esc_html_e( 'Payment', 'woo-shipping-gateway' ); ?></h2>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[wallet_payment]" value="1" <?php checked( 'yes', $frenet_s['wallet_payment'] ); ?>> <span><?php esc_html_e( 'Pay with the wallet when creating', 'woo-shipping-gateway' ); ?></span></label>
		<p class="description"><?php esc_html_e( 'Off: labels bought here are created unpaid and wait in Labels until you pay them.', 'woo-shipping-gateway' ); ?></p>
	</section>

	<section class="frenet-card" id="frenet-auto-settings">
		<h2><?php esc_html_e( 'Automation', 'woo-shipping-gateway' ); ?></h2>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[auto_create]" value="1" <?php checked( 'yes', $frenet_s['auto_create'] ); ?> data-frenet-auto-on> <span><?php esc_html_e( 'Create the shipment automatically when an order is paid', 'woo-shipping-gateway' ); ?></span></label>
		<p class="description"><?php esc_html_e( 'A minute after a paid order with delivery in Brazil comes in, the store quotes it, picks the service by the rule below, creates the shipment and pays it with the wallet (when wallet payment is on). The result goes to the order history and to Create shipment > Notes.', 'woo-shipping-gateway' ); ?></p>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[auto_create_consent]" value="1" <?php checked( 'yes', $frenet_s['auto_create_consent'] ); ?> data-frenet-auto-consent> <span><?php esc_html_e( 'I authorise the store to buy labels and debit my Frenet wallet automatically, without confirming each order.', 'woo-shipping-gateway' ); ?></span></label>
		<p class="description"><?php esc_html_e( 'Without this authorisation the automation stays off. Orders without an NF-e key only go with carriers that accept the DC-e; when none does, nothing is created and the reason stays in Notes.', 'woo-shipping-gateway' ); ?></p>
		<?php $frenet_quotes = WC_Frenet_Labels_Settings::quotes_at_checkout(); ?>
		<fieldset>
			<legend class="frenet-field"><?php esc_html_e( 'Which service', 'woo-shipping-gateway' ); ?></legend>
			<?php foreach ( WC_Frenet_Labels_Auto::rule_options() as $frenet_rule => $frenet_rule_label ) : ?>
				<label class="frenet-inline"><input type="radio" name="frenet_labels[auto_create_rule]" value="<?php echo esc_attr( $frenet_rule ); ?>" <?php checked( $frenet_rule, isset( WC_Frenet_Labels_Auto::rule_options()[ $frenet_s['auto_create_rule'] ] ) ? $frenet_s['auto_create_rule'] : 'best_price' ); ?>> <?php echo esc_html( $frenet_rule_label ); ?></label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description">
		<?php
		echo esc_html(
			$frenet_quotes
			? __( 'Who decides, in this order: 1) the customer, when the rule is "the carrier the customer chose" and they paid for a Frenet service; 2) the products (Frenet > Products); 3) this rule.', 'woo-shipping-gateway' )
			: __( 'Frenet is not offered at checkout (no shipping zone has the Frenet method), so the customer never chooses. Who decides, in this order: 1) the products (Frenet > Products); 2) this rule.', 'woo-shipping-gateway' )
		);
		?>
			<a href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG . '-products' ) ); ?>"><?php esc_html_e( 'Products', 'woo-shipping-gateway' ); ?></a></p>
		<label class="frenet-field" for="frenet-fallback"><?php esc_html_e( 'If the carrier a product asks for does not quote', 'woo-shipping-gateway' ); ?></label>
		<select id="frenet-fallback" name="frenet_labels[auto_carrier_fallback]">
			<option value="best_price" <?php selected( 'best_price', $frenet_s['auto_carrier_fallback'] ); ?>><?php esc_html_e( 'Use the best price instead', 'woo-shipping-gateway' ); ?></option>
			<option value="skip" <?php selected( 'skip', $frenet_s['auto_carrier_fallback'] ); ?>><?php esc_html_e( 'Do not create the shipment (leave the reason in Notes)', 'woo-shipping-gateway' ); ?></option>
		</select>
	</section>

	<section class="frenet-card">
		<h2><?php esc_html_e( 'Checkout', 'woo-shipping-gateway' ); ?></h2>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[autofill]" value="1" <?php checked( 'yes', $frenet_s['autofill'] ); ?>> <span><?php esc_html_e( 'Fill the address from the CEP', 'woo-shipping-gateway' ); ?></span></label>
		<p class="description"><?php esc_html_e( 'Street, neighborhood, city and state appear when the customer types the CEP, in the classic checkout, the checkout block and My Account. Number and complement are never changed. Leave it off if another plugin already does this.', 'woo-shipping-gateway' ); ?></p>
	</section>

	<section class="frenet-card" id="frenet-tracking-settings">
		<h2><?php esc_html_e( 'Tracking', 'woo-shipping-gateway' ); ?></h2>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[tracking_sync]" value="1" <?php checked( 'yes', $frenet_s['tracking_sync'] ); ?>> <span><?php esc_html_e( 'Check the tracking automatically every hour', 'woo-shipping-gateway' ); ?></span></label>
		<p class="description"><?php esc_html_e( 'Orders with a tracking code (bought here or added by hand) are checked with Frenet until they are delivered.', 'woo-shipping-gateway' ); ?></p>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[tracking_status]" value="1" <?php checked( 'yes', $frenet_s['tracking_status'] ); ?>> <span><?php esc_html_e( 'Change the order status with the tracking', 'woo-shipping-gateway' ); ?></span></label>
		<p class="description"><?php esc_html_e( 'Adds the statuses In transit, Awaiting pickup, Delivered and Returning, and moves the order according to the latest event. Cancelled, refunded and completed orders are never changed.', 'woo-shipping-gateway' ); ?></p>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[tracking_email]" value="1" <?php checked( 'yes', $frenet_s['tracking_email'] ); ?>> <span><?php esc_html_e( 'E-mail the customer', 'woo-shipping-gateway' ); ?></span></label>
		<p class="description">
			<?php esc_html_e( 'The tracking code and each status change. Edit the texts or turn single e-mails off in', 'woo-shipping-gateway' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ); ?>"><?php esc_html_e( 'WooCommerce > Settings > Emails', 'woo-shipping-gateway' ); ?></a>.
		</p>
	</section>

	</div>
	<div class="frenet-bar">
		<span class="frenet-muted frenet-small"><?php esc_html_e( 'Changes apply to the next labels and quotes.', 'woo-shipping-gateway' ); ?></span>
		<div class="frenet-bar__actions">
			<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save changes', 'woo-shipping-gateway' ); ?></button>
		</div>
	</div>
</form>
<dialog class="frenet-dialog frenet-dialog--auto" data-frenet-auto-dialog aria-labelledby="frenet-auto-title">
	<form method="dialog">
		<h2 id="frenet-auto-title"><?php esc_html_e( 'Turn on automatic shipments?', 'woo-shipping-gateway' ); ?></h2>
		<div class="frenet-note frenet-note--warn" role="alert">
			<strong><?php esc_html_e( 'The store will spend your Frenet balance on its own.', 'woo-shipping-gateway' ); ?></strong>
			<p><?php esc_html_e( 'A minute after each paid order with delivery in Brazil, it quotes the freight, creates the shipment and pays it with the wallet, without asking anyone. Each label is charged exactly like one you create by hand.', 'woo-shipping-gateway' ); ?></p>
		</div>
		<ul class="frenet-list">
			<li><?php esc_html_e( 'Service:', 'woo-shipping-gateway' ); ?> <strong data-frenet-auto-rule></strong></li>
			<li><?php esc_html_e( 'Orders without an NF-e key only go with carriers that accept the DC-e; when none does, nothing is created and the reason stays in Notes.', 'woo-shipping-gateway' ); ?></li>
			<li><?php esc_html_e( 'A label that should not go out: cancel it in Labels and Frenet returns the amount to the wallet.', 'woo-shipping-gateway' ); ?></li>
			<li><?php esc_html_e( 'You can turn it off here at any time.', 'woo-shipping-gateway' ); ?></li>
		</ul>
		<div class="frenet-actions">
			<button value="cancel" class="button"><?php esc_html_e( 'Not now', 'woo-shipping-gateway' ); ?></button>
			<button value="confirm" class="button button-primary"><?php esc_html_e( 'I understand, turn it on', 'woo-shipping-gateway' ); ?></button>
		</div>
	</form>
</dialog>

