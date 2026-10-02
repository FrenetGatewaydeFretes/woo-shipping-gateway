<?php
/**
 * Frenet box on the order screen.
 *
 * @package woo-shipping-gateway
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;

$frenet_id      = (string) $order->get_meta( '_frenet_shipment_id' );
$frenet_status  = (int) $order->get_meta( '_frenet_shipment_status' );
$frenet_live    = WC_Frenet_Labels_Service::has_shipment( $order );
$frenet_nfe     = WC_Frenet_Labels_Nfe::parse( (string) $order->get_meta( WC_Frenet_Labels_Nfe::META ) );
$frenet_paid    = in_array( $frenet_status, WC_Frenet_Labels_Service::PAID_STATUSES, true );
$frenet_journey = (string) $order->get_meta( '_frenet_label_journey' );
?>
<div class="frenet-box" data-frenet-box data-order="<?php echo esc_attr( (string) $order->get_id() ); ?>">
	<?php if ( $frenet_live ) : ?>
		<p><span class="frenet-pill is-<?php echo esc_attr( WC_Frenet_Labels_Service::status_tone( $frenet_status ) ); ?>"><?php echo esc_html( WC_Frenet_Labels_Service::status_label( $frenet_status ) ); ?></span></p>
		<p class="frenet-muted">
			<?php
			/* translators: %s: shipment id */
			echo esc_html( sprintf( __( 'Shipment %s', 'woo-shipping-gateway' ), $frenet_id ) );
			$frenet_price = (float) $order->get_meta( '_frenet_shipment_price' );
			if ( $frenet_price > 0 ) {
				echo ' · ' . esc_html( WC_Frenet_Labels_Service::money( $frenet_price ) );
			}
			?>
		</p>
		<?php if ( $order->get_meta( '_frenet_tracking_code' ) ) : ?>
			<p><?php esc_html_e( 'Tracking code', 'woo-shipping-gateway' ); ?>: <strong class="frenet-code"><?php echo esc_html( (string) $order->get_meta( '_frenet_tracking_code' ) ); ?></strong></p>
		<?php endif; ?>
		<?php if ( 'panel' === $frenet_journey && ! $frenet_paid ) : ?>
			<p class="frenet-note"><?php esc_html_e( 'Sent to the Frenet panel: pay and print it there.', 'woo-shipping-gateway' ); ?></p>
		<?php endif; ?>
		<div class="frenet-actions">
			<?php if ( $frenet_paid ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( WC_Frenet_Labels_Admin::print_url( array( $frenet_id ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print label', 'woo-shipping-gateway' ); ?></a>
			<?php elseif ( 'panel' === $frenet_journey ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( WC_Frenet_Labels_Admin::PANEL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the Frenet panel', 'woo-shipping-gateway' ); ?></a>
			<?php else : ?>
				<a class="button button-primary" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG . '-labels', array( 'status' => $frenet_status ) ) ); ?>"><?php esc_html_e( 'Pay in Labels', 'woo-shipping-gateway' ); ?></a>
			<?php endif; ?>
			<button type="button" class="button frenet-danger" data-frenet-cancel="<?php echo esc_attr( $frenet_id ); ?>"><?php esc_html_e( 'Cancel', 'woo-shipping-gateway' ); ?></button>
		</div>
	<?php else : ?>
		<p class="frenet-muted"><?php esc_html_e( 'No Frenet shipment yet.', 'woo-shipping-gateway' ); ?></p>
		<a class="button button-primary" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG . '-create', array( 'orders' => $order->get_id() ) ) ); ?>"><?php esc_html_e( 'Create shipment', 'woo-shipping-gateway' ); ?></a>
	<?php endif; ?>
	<p class="frenet-muted frenet-box__nfe"><?php esc_html_e( 'NF-e', 'woo-shipping-gateway' ); ?>: <?php echo esc_html( $frenet_nfe['ok'] ? WC_Frenet_Labels_Nfe::summary( $frenet_nfe ) : __( 'none', 'woo-shipping-gateway' ) ); ?></p>
	<p class="frenet-status-line" role="status" aria-live="polite" data-frenet-status></p>
	<?php WC_Frenet_Labels_Admin::view( 'order-tracking', array( 'order' => $order ) ); ?>
</div>
