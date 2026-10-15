<?php
/**
 * "Em breve" pages (WhatsApp, Reversa, Comprovante, Torre).
 *
 * @package woo-shipping-gateway
 * @var array{0: string, 1: string, 2: string} $page
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="frenet-empty">
	<span class="frenet-pill is-wait"><?php esc_html_e( 'Coming soon', 'woo-shipping-gateway' ); ?></span>
	<h2><?php echo esc_html( $page[1] ); ?></h2>
	<p><?php esc_html_e( 'This part of the Frenet journey is not available in the plugin yet. Labels, printing and tracking already work.', 'woo-shipping-gateway' ); ?></p>
	<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG . '-labels' ) ); ?>"><?php esc_html_e( 'Go to labels', 'woo-shipping-gateway' ); ?></a>
</div>
