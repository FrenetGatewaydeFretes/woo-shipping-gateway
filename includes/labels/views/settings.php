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
<?php if ( WC_Frenet_Labels_Settings::ready() ) : ?>
	<?php WC_Frenet_Labels_Admin::view( 'wallet' ); ?>
<?php endif; ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="frenet-form">
	<input type="hidden" name="action" value="frenet_labels_save">
	<?php wp_nonce_field( WC_Frenet_Labels_Admin::NONCE ); ?>

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

		<label class="frenet-field" for="frenet-partner"><?php esc_html_e( 'Partner token', 'woo-shipping-gateway' ); ?></label>
		<input id="frenet-partner" class="regular-text" type="password" name="frenet_labels[partner_token]" value="" autocomplete="new-password" spellcheck="false"
			placeholder="<?php echo esc_attr( $frenet_partner ? __( 'Saved. Type a new one to replace it.', 'woo-shipping-gateway' ) : '' ); ?>" aria-describedby="frenet-partner-help">
		<p id="frenet-partner-help" class="description"><?php esc_html_e( 'Given by Frenet to integration partners (x-partner-token). Required to create, list, pay and cancel labels. Keep it secret like a password.', 'woo-shipping-gateway' ); ?></p>
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
		<label class="frenet-field" for="frenet-doc"><?php esc_html_e( 'Sender CPF/CNPJ', 'woo-shipping-gateway' ); ?></label>
		<input id="frenet-doc" class="regular-text" type="text" inputmode="numeric" name="frenet_labels[sender_document]" value="<?php echo esc_attr( $frenet_s['sender_document'] ); ?>">
		<p class="description"><?php esc_html_e( 'The sender address comes from WooCommerce > Settings > General.', 'woo-shipping-gateway' ); ?></p>
	</section>

	<section class="frenet-card">
		<h2><?php esc_html_e( 'Payment', 'woo-shipping-gateway' ); ?></h2>
		<fieldset>
			<legend class="frenet-field"><?php esc_html_e( 'How labels are generated', 'woo-shipping-gateway' ); ?></legend>
			<label class="frenet-choice"><input type="radio" name="frenet_labels[journey]" value="here" <?php checked( 'here', $frenet_s['journey'] ); ?>>
				<span><strong><?php esc_html_e( 'Buy here', 'woo-shipping-gateway' ); ?></strong> <?php esc_html_e( 'The store creates, pays and prints the labels.', 'woo-shipping-gateway' ); ?></span></label>
			<label class="frenet-choice"><input type="radio" name="frenet_labels[journey]" value="panel" <?php checked( 'panel', $frenet_s['journey'] ); ?>>
				<span><strong><?php esc_html_e( 'Send to the Frenet panel', 'woo-shipping-gateway' ); ?></strong> <?php esc_html_e( 'The orders go to the Frenet panel, where you pay and print them.', 'woo-shipping-gateway' ); ?></span></label>
		</fieldset>
		<label class="frenet-switch"><input type="checkbox" name="frenet_labels[wallet_payment]" value="1" <?php checked( 'yes', $frenet_s['wallet_payment'] ); ?>> <span><?php esc_html_e( 'Pay with the wallet when creating', 'woo-shipping-gateway' ); ?></span></label>
		<p class="description"><?php esc_html_e( 'Off: labels bought here are created unpaid and wait in Labels until you pay them.', 'woo-shipping-gateway' ); ?></p>
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

	<p><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save changes', 'woo-shipping-gateway' ); ?></button></p>
</form>
