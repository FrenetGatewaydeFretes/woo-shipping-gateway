<?php
/**
 * Gerencie suas etiquetas: every shipment of the account, filters in the URL, cart to pay, print, cancel, track.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_per    = 50;
$frenet_p      = isset( $_GET['p'] ) ? max( 0, absint( $_GET['p'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
$frenet_filter = isset( $_GET['status'] ) ? absint( $_GET['status'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
$frenet_list   = WC_Frenet_Labels_Service::shipments( $frenet_p, $frenet_per );
$frenet_slug   = WC_Frenet_Labels_Admin::SLUG . '-labels';
?>
<p class="frenet-lead"><?php esc_html_e( 'Every shipment of your Frenet account, also the ones created in the Frenet panel. Select unpaid labels to pay them together, or paid ones to print them.', 'woo-shipping-gateway' ); ?></p>
<?php WC_Frenet_Labels_Admin::view( 'wallet' ); ?>

<?php if ( is_wp_error( $frenet_list ) ) : ?>
	<div class="notice notice-error inline"><p>
		<?php
		/* translators: %s: error */
		echo esc_html( sprintf( __( 'Frenet did not answer: %s. Reload the page in a moment.', 'woo-shipping-gateway' ), $frenet_list->get_error_message() ) );
		?>
	</p></div>
	<?php
	return;
endif;

$frenet_rows   = array();
$frenet_counts = array();
foreach ( $frenet_list['shipments'] as $frenet_s ) {
	$frenet_status                   = (int) ( $frenet_s['shipmentStatus'] ?? 0 );
	$frenet_counts[ $frenet_status ] = ( $frenet_counts[ $frenet_status ] ?? 0 ) + 1;
	if ( ! $frenet_filter || $frenet_filter === $frenet_status ) {
		$frenet_rows[] = $frenet_s;
	}
}
$frenet_pages = max( 1, (int) ceil( $frenet_list['total'] / $frenet_per ) );
?>
<nav class="frenet-filters" aria-label="<?php esc_attr_e( 'Filter by status', 'woo-shipping-gateway' ); ?>">
	<a class="frenet-chip<?php echo $frenet_filter ? '' : ' is-active'; ?>" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( $frenet_slug, array( 'p' => $frenet_p ) ) ); ?>">
		<?php esc_html_e( 'All', 'woo-shipping-gateway' ); ?> <span>(<?php echo (int) count( $frenet_list['shipments'] ); ?>)</span></a>
	<?php foreach ( $frenet_counts as $frenet_code => $frenet_n ) : ?>
		<a class="frenet-chip<?php echo $frenet_filter === $frenet_code ? ' is-active' : ''; ?>" href="
		<?php
		echo esc_url(
			WC_Frenet_Labels_Admin::url(
				$frenet_slug,
				array(
					'p'      => $frenet_p,
					'status' => $frenet_code,
				)
			)
		);
		?>
		">
			<?php echo esc_html( WC_Frenet_Labels_Service::status_label( $frenet_code ) ); ?> <span>(<?php echo (int) $frenet_n; ?>)</span></a>
	<?php endforeach; ?>
</nav>
<p class="frenet-muted">
	<?php
	/* translators: 1: page, 2: pages, 3: total shipments */
	echo esc_html( sprintf( __( 'Page %1$d of %2$d · %3$s shipments in the account. The filter applies to this page.', 'woo-shipping-gateway' ), $frenet_p + 1, $frenet_pages, number_format_i18n( $frenet_list['total'] ) ) );
	?>
</p>

<?php if ( ! $frenet_rows ) : ?>
	<div class="frenet-empty frenet-empty--small"><p><?php echo esc_html( $frenet_filter ? __( 'No label with this status on this page.', 'woo-shipping-gateway' ) : __( 'No Frenet labels yet. Create one in "Create shipment".', 'woo-shipping-gateway' ) ); ?></p></div>
<?php else : ?>
<form data-frenet-labels>
	<div class="frenet-actions frenet-actions--top">
		<button type="button" class="button button-primary" data-frenet-pay disabled><?php esc_html_e( 'Pay selected', 'woo-shipping-gateway' ); ?></button>
		<?php if ( WC_Frenet_Labels_Settings::on( 'batch_print' ) ) : ?>
			<button type="button" class="button" data-frenet-print-selected disabled><?php esc_html_e( 'Print selected', 'woo-shipping-gateway' ); ?></button>
		<?php endif; ?>
		<span class="frenet-status-line" role="status" aria-live="polite" data-frenet-status></span>
	</div>
	<div class="frenet-table-wrap">
		<table class="frenet-table">
			<thead><tr>
				<th scope="col" class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'woo-shipping-gateway' ); ?></span></th>
				<th scope="col"><?php esc_html_e( 'Shipment', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Order', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Service', 'woo-shipping-gateway' ); ?></th>
				<th scope="col" class="is-num"><?php esc_html_e( 'Price', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Tracking code', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Updated', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'woo-shipping-gateway' ); ?></span></th>
			</tr></thead>
			<tbody>
			<?php
			foreach ( $frenet_rows as $frenet_s ) :
				$frenet_id     = (string) ( $frenet_s['shipmentId'] ?? '' );
				$frenet_status = (int) ( $frenet_s['shipmentStatus'] ?? 0 );
				$frenet_q      = (array) ( $frenet_s['quotation'] ?? array() );
				$frenet_price  = WC_Frenet_Labels_Compare::to_float( $frenet_q['shippingPrice'] ?? ( $frenet_q['platformShippingPrice'] ?? 0 ) );
				$frenet_order  = ctype_digit( (string) ( $frenet_s['orderId'] ?? '' ) ) ? wc_get_order( (int) $frenet_s['orderId'] ) : false;
				$frenet_mine   = $frenet_order instanceof WC_Order && (string) $frenet_order->get_meta( '_frenet_shipment_id' ) === $frenet_id;
				$frenet_vol    = (array) ( ( (array) ( $frenet_s['volumes'] ?? array() ) )[0] ?? array() );
				$frenet_track  = (string) ( $frenet_s['trackingUrl'] ?? '' );
				$frenet_unpaid = in_array( $frenet_status, WC_Frenet_Labels_Service::UNPAID_STATUSES, true );
				$frenet_paid   = in_array( $frenet_status, WC_Frenet_Labels_Service::PAID_STATUSES, true );
				?>
				<tr data-frenet-label="<?php echo esc_attr( $frenet_id ); ?>" data-price="<?php echo esc_attr( (string) $frenet_price ); ?>" data-unpaid="<?php echo $frenet_unpaid ? '1' : '0'; ?>" data-paid="<?php echo $frenet_paid ? '1' : '0'; ?>">
					<th scope="row" class="check-column">
						<?php if ( $frenet_unpaid || $frenet_paid ) : ?>
							<input type="checkbox" data-frenet-pick aria-label="<?php /* translators: %s: shipment */ echo esc_attr( sprintf( __( 'Select shipment %s', 'woo-shipping-gateway' ), $frenet_id ) ); ?>">
						<?php endif; ?>
					</th>
					<td class="frenet-code"><?php echo esc_html( $frenet_id ); ?></td>
					<td>
						<?php if ( $frenet_mine ) : ?>
							<a href="<?php echo esc_url( $frenet_order->get_edit_order_url() ); ?>">#<?php echo esc_html( $frenet_order->get_order_number() ); ?></a>
						<?php else : ?>
							<span class="frenet-muted" title="<?php esc_attr_e( 'Order of another store or created in the Frenet panel', 'woo-shipping-gateway' ); ?>"><?php echo esc_html( mb_strimwidth( (string) ( $frenet_s['orderId'] ?? '—' ), 0, 14, '…' ) ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php
						$frenet_service = trim( (string) ( $frenet_q['carrierCode'] ?? '' ) . ' ' . (string) ( $frenet_q['shippingServiceName'] ?? $frenet_q['shippingServiceCode'] ?? '' ) );
						echo esc_html( '' !== $frenet_service ? $frenet_service : '—' );
						?>
					</td>
					<td class="is-num"><?php echo $frenet_price > 0 ? esc_html( WC_Frenet_Labels_Service::money( $frenet_price ) ) : '—'; ?></td>
					<td><span class="frenet-pill is-<?php echo esc_attr( WC_Frenet_Labels_Service::status_tone( $frenet_status ) ); ?>" data-frenet-pill><?php echo esc_html( WC_Frenet_Labels_Service::status_label( $frenet_status ) ); ?></span></td>
					<td>
					<?php
					if ( '' !== $frenet_track ) :
						?>
						<a class="frenet-code" href="<?php echo esc_url( $frenet_track ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( basename( $frenet_track ) ); ?></a>
						<?php
else :
	?>
						<span class="frenet-muted">—</span><?php endif; ?></td>
					<td class="frenet-muted"><?php echo esc_html( substr( (string) ( $frenet_vol['modifiedOn'] ?? '' ), 0, 16 ) ); ?></td>
					<td class="frenet-row-actions">
						<?php if ( $frenet_paid ) : ?>
							<a class="button button-small" href="<?php echo esc_url( WC_Frenet_Labels_Admin::print_url( array( $frenet_id ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print', 'woo-shipping-gateway' ); ?></a>
						<?php endif; ?>
						<?php if ( $frenet_unpaid || $frenet_paid ) : ?>
							<button type="button" class="button button-small frenet-danger" data-frenet-cancel="<?php echo esc_attr( $frenet_id ); ?>"><?php esc_html_e( 'Cancel', 'woo-shipping-gateway' ); ?></button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</form>
<?php endif; ?>

<?php if ( $frenet_pages > 1 ) : ?>
	<nav class="frenet-pager" aria-label="<?php esc_attr_e( 'Pages', 'woo-shipping-gateway' ); ?>">
		<?php if ( $frenet_p > 0 ) : ?>
			<a class="button" href="
			<?php
			echo esc_url(
				WC_Frenet_Labels_Admin::url(
					$frenet_slug,
					array_filter(
						array(
							'p'      => $frenet_p - 1,
							'status' => $frenet_filter,
						)
					)
				)
			);
			?>
			"><?php esc_html_e( 'Newer', 'woo-shipping-gateway' ); ?></a>
		<?php endif; ?>
		<span><?php /* translators: 1: page, 2: pages */ echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'woo-shipping-gateway' ), $frenet_p + 1, $frenet_pages ) ); ?></span>
		<?php if ( $frenet_p + 1 < $frenet_pages ) : ?>
			<a class="button" href="
			<?php
			echo esc_url(
				WC_Frenet_Labels_Admin::url(
					$frenet_slug,
					array_filter(
						array(
							'p'      => $frenet_p + 1,
							'status' => $frenet_filter,
						)
					)
				)
			);
			?>
			"><?php esc_html_e( 'Older', 'woo-shipping-gateway' ); ?></a>
		<?php endif; ?>
	</nav>
<?php endif; ?>
<?php if ( WC_Frenet_Labels_Settings::on( 'batch_print' ) ) : ?>
	<input type="hidden" data-frenet-print-base value="<?php echo esc_url( WC_Frenet_Labels_Admin::print_url( array() ) ); ?>">
<?php endif; ?>
