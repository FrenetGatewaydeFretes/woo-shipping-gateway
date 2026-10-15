<?php
/**
 * Impressão: one PDF with the paid labels, in A4 or 10x15.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_list = WC_Frenet_Labels_Service::shipments( 0, 100 );
$frenet_paid = array();
if ( ! is_wp_error( $frenet_list ) ) {
	foreach ( $frenet_list['shipments'] as $frenet_s ) {
		if ( in_array( (int) ( $frenet_s['shipmentStatus'] ?? 0 ), WC_Frenet_Labels_Service::PAID_STATUSES, true ) ) {
			$frenet_paid[] = $frenet_s;
		}
	}
}
$frenet_format = WC_Frenet_Labels_Settings::get( 'label_format' );
?>
<p class="frenet-lead"><?php esc_html_e( 'Print the paid labels in one file. Choose the paper format of your printer.', 'woo-shipping-gateway' ); ?></p>
<?php if ( is_wp_error( $frenet_list ) ) : ?>
	<div class="notice notice-error inline"><p><?php echo esc_html( $frenet_list->get_error_message() ); ?></p></div>
<?php elseif ( ! $frenet_paid ) : ?>
	<div class="frenet-empty frenet-empty--small"><p><?php esc_html_e( 'No paid label to print among the latest 100 shipments.', 'woo-shipping-gateway' ); ?></p>
		<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG . '-labels', array( 'status' => 2 ) ) ); ?>"><?php esc_html_e( 'See unpaid labels', 'woo-shipping-gateway' ); ?></a></div>
<?php else : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank" class="frenet-card" data-frenet-print-form>
	<input type="hidden" name="action" value="frenet_labels_print">
	<?php wp_nonce_field( WC_Frenet_Labels_Admin::NONCE ); ?>
	<fieldset class="frenet-format">
		<legend class="frenet-field"><?php esc_html_e( 'Paper format', 'woo-shipping-gateway' ); ?></legend>
		<label class="frenet-choice"><input type="radio" name="format" value="A4" <?php checked( 'A4', $frenet_format ); ?>> <span><strong>A4</strong> <?php esc_html_e( 'Common printer, several labels per page.', 'woo-shipping-gateway' ); ?></span></label>
		<label class="frenet-choice"><input type="radio" name="format" value="10x15" <?php checked( '10x15', $frenet_format ); ?>> <span><strong>10x15</strong> <?php esc_html_e( 'Thermal label printer.', 'woo-shipping-gateway' ); ?></span></label>
	</fieldset>
	<div class="frenet-table-wrap">
		<table class="frenet-table">
			<thead><tr>
				<th scope="col" class="check-column"><input type="checkbox" data-frenet-all checked aria-label="<?php esc_attr_e( 'Select all', 'woo-shipping-gateway' ); ?>"></th>
				<th scope="col"><?php esc_html_e( 'Shipment', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Order', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Service', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'woo-shipping-gateway' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $frenet_paid as $frenet_s ) : ?>
				<?php $frenet_q = (array) ( $frenet_s['quotation'] ?? array() ); ?>
				<tr>
					<th scope="row" class="check-column"><input type="checkbox" name="ids[]" value="<?php echo esc_attr( (string) $frenet_s['shipmentId'] ); ?>" data-frenet-pick checked></th>
					<td class="frenet-code"><?php echo esc_html( (string) $frenet_s['shipmentId'] ); ?></td>
					<td><?php echo esc_html( mb_strimwidth( (string) ( $frenet_s['orderId'] ?? '' ), 0, 14, '…' ) ); ?></td>
					<td><?php echo esc_html( trim( (string) ( $frenet_q['carrierCode'] ?? '' ) . ' ' . (string) ( $frenet_q['shippingServiceName'] ?? $frenet_q['shippingServiceCode'] ?? '' ) ) ); ?></td>
					<td><span class="frenet-pill is-ok"><?php echo esc_html( WC_Frenet_Labels_Service::status_label( (int) $frenet_s['shipmentStatus'] ) ); ?></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="frenet-actions">
		<button type="submit" class="button button-primary" data-frenet-print-submit><?php esc_html_e( 'Print selected labels', 'woo-shipping-gateway' ); ?></button>
		<span class="frenet-muted"><?php esc_html_e( 'Opens the PDF in a new tab.', 'woo-shipping-gateway' ); ?></span>
	</div>
</form>
<?php endif; ?>
