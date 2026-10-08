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
	<p><?php esc_html_e( 'Turn on Frenet labels and paste the partner token (x-partner-token) that Frenet gave you. The store token comes from the Frenet shipping method.', 'woo-shipping-gateway' ); ?></p>
	<a class="button button-primary" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG ) ); ?>"><?php esc_html_e( 'Open settings', 'woo-shipping-gateway' ); ?></a>
</div>
