<?php
/**
 * Tracking part of the Frenet box on the order screen: code (typed or from the label), latest event,
 * update now.
 *
 * @package woo-shipping-gateway
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;

$frenet_sum = WC_Frenet_Tracking_Admin::summary( $order );
?>
<div class="frenet-box__tracking" data-frenet-tracking-box data-order="<?php echo esc_attr( (string) $order->get_id() ); ?>">
	<h4><?php esc_html_e( 'Tracking', 'woo-shipping-gateway' ); ?></h4>
	<p class="frenet-muted" data-frenet-track-event>
		<?php if ( $frenet_sum['description'] ) : ?>
			<strong><?php echo esc_html( $frenet_sum['description'] ); ?></strong><br><?php echo esc_html( $frenet_sum['when'] ); ?>
		<?php elseif ( $frenet_sum['code'] ) : ?>
			<?php esc_html_e( 'No movement yet', 'woo-shipping-gateway' ); ?>
		<?php endif; ?>
	</p>
	<label class="frenet-field" for="frenet-track-code-<?php echo esc_attr( (string) $order->get_id() ); ?>"><?php esc_html_e( 'Tracking code', 'woo-shipping-gateway' ); ?></label>
	<input type="text" id="frenet-track-code-<?php echo esc_attr( (string) $order->get_id() ); ?>" class="widefat frenet-code" value="<?php echo esc_attr( $frenet_sum['code'] ); ?>" placeholder="AA123456789BR" spellcheck="false" autocomplete="off" data-frenet-track-code>
	<label class="frenet-inline"><input type="checkbox" checked data-frenet-track-notify> <?php esc_html_e( 'E-mail the customer', 'woo-shipping-gateway' ); ?></label>
	<div class="frenet-actions">
		<button type="button" class="button" data-frenet-track-save><?php esc_html_e( 'Save code', 'woo-shipping-gateway' ); ?></button>
		<?php if ( $frenet_sum['code'] ) : ?>
			<button type="button" class="button" data-frenet-track-sync="<?php echo esc_attr( (string) $order->get_id() ); ?>"><?php esc_html_e( 'Update now', 'woo-shipping-gateway' ); ?></button>
		<?php endif; ?>
	</div>
	<p class="frenet-status-line" role="status" aria-live="polite" data-frenet-track-msg></p>
</div>
