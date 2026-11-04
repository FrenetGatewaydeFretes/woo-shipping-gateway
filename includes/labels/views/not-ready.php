<?php
/**
 * Empty state of the label screens: no token yet, or the store token only (basic mode).
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_mode = WC_Frenet_Labels_Settings::mode();
?>
<div class="frenet-empty">
	<?php if ( 'basic' === $frenet_mode && '' !== WC_Frenet_Labels_Settings::get( 'partner_token' ) ) : ?>
		<h2><?php esc_html_e( 'Frenet labels are turned off', 'woo-shipping-gateway' ); ?></h2>
		<p><?php esc_html_e( 'The partner token is saved, but the label screens are off. Turn on "Enable Frenet labels" in Settings to create labels from the store.', 'woo-shipping-gateway' ); ?></p>
		<a class="button button-primary button-hero" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG, array( 'full' => 1 ) ) ); ?>"><?php esc_html_e( 'Open Settings', 'woo-shipping-gateway' ); ?></a>
	<?php elseif ( 'basic' === $frenet_mode ) : ?>
		<h2><?php esc_html_e( 'Buying labels from the store needs the partner token', 'woo-shipping-gateway' ); ?></h2>
		<p><?php esc_html_e( 'Your Frenet account is connected in basic mode. Already working: freight quotes at checkout, the simulator on the product page, tracking, the address by CEP and the wallet balance.', 'woo-shipping-gateway' ); ?></p>
		<p><?php esc_html_e( 'Creating, paying, printing and cancelling labels here needs the partner token, exclusive to the Frenet whitelabel service. Until you have it, buy the labels in the Frenet panel and paste the tracking code in the order: the store follows the tracking and tells the customer.', 'woo-shipping-gateway' ); ?></p>
		<div class="frenet-actions">
			<a class="button button-primary button-hero" href="<?php echo esc_url( WC_Frenet_Labels_Setup::url( 'account' ) ); ?>"><?php esc_html_e( 'Add the partner token', 'woo-shipping-gateway' ); ?></a>
			<a class="button button-hero" href="<?php echo esc_url( WC_Frenet_Labels_Admin::PANEL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Buy labels in the Frenet panel', 'woo-shipping-gateway' ); ?></a>
		</div>
	<?php else : ?>
		<h2><?php esc_html_e( 'Connect your Frenet account', 'woo-shipping-gateway' ); ?></h2>
		<p><?php esc_html_e( 'With the store token, the store quotes freight, tracks parcels and fills the address by CEP. With the partner token too (Frenet whitelabel service), it also buys and prints labels.', 'woo-shipping-gateway' ); ?></p>
		<a class="button button-primary button-hero" href="<?php echo esc_url( WC_Frenet_Labels_Setup::url() ); ?>"><?php esc_html_e( 'Connect the account', 'woo-shipping-gateway' ); ?></a>
	<?php endif; ?>
</div>
