<?php
/**
 * Empty state of the label screens: no token yet, or the label screens turned off (basic mode).
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_mode = WC_Frenet_Labels_Settings::mode();
?>
<div class="frenet-empty">
	<?php if ( 'basic' === $frenet_mode ) : ?>
		<h2><?php esc_html_e( 'Frenet labels are turned off', 'woo-shipping-gateway' ); ?></h2>
		<p><?php esc_html_e( 'The label screens are off. Turn on "Enable Frenet labels" in Settings to create labels from the store.', 'woo-shipping-gateway' ); ?></p>
		<a class="button button-primary button-hero" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG, array( 'full' => 1 ) ) ); ?>"><?php esc_html_e( 'Open Settings', 'woo-shipping-gateway' ); ?></a>
	<?php else : ?>
		<h2><?php esc_html_e( 'Connect your Frenet account', 'woo-shipping-gateway' ); ?></h2>
		<p><?php esc_html_e( 'With the store token, the store quotes freight, buys and prints labels, tracks parcels and fills the address by CEP.', 'woo-shipping-gateway' ); ?></p>
		<a class="button button-primary button-hero" href="<?php echo esc_url( WC_Frenet_Labels_Setup::url() ); ?>"><?php esc_html_e( 'Connect the account', 'woo-shipping-gateway' ); ?></a>
	<?php endif; ?>
</div>
