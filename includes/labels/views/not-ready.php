<?php
/**
 * Empty state: labels not configured yet.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="frenet-empty">
	<h2><?php esc_html_e( 'Connect your Frenet account to create labels', 'woo-shipping-gateway' ); ?></h2>
	<p><?php esc_html_e( 'Creating labels from the store is exclusive to Frenet customers with the whitelabel service active. Each of them has its own partner token (x-partner-token): turn on Frenet labels and paste it in the settings. The store token comes from the Frenet shipping method.', 'woo-shipping-gateway' ); ?></p>
	<p class="frenet-muted"><?php esc_html_e( 'Do not have the whitelabel service? Talk to Frenet to activate it. Quotes and tracking keep working without it.', 'woo-shipping-gateway' ); ?></p>
	<a class="button button-primary" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG ) ); ?>"><?php esc_html_e( 'Open settings', 'woo-shipping-gateway' ); ?></a>
</div>
