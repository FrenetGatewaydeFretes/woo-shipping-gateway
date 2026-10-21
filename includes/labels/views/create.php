<?php
/**
 * Criar envio: pick orders, compare carriers (best price / fastest), review the totals and create.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_ids = isset( $_GET['orders'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['orders'] ) ) ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preselection.
if ( $frenet_ids ) {
	$frenet_orders = array_filter( array_map( 'wc_get_order', $frenet_ids ) );
} else {
	$frenet_orders = wc_get_orders(
		array(
			'limit'   => 50,
			'status'  => array( 'wc-processing', 'wc-on-hold' ),
			'orderby' => 'date',
			'order'   => 'DESC',
		)
	);
}
$frenet_rows = array();
foreach ( $frenet_orders as $frenet_order ) {
	if ( ! $frenet_order instanceof WC_Order || WC_Frenet_Labels_Service::has_shipment( $frenet_order ) ) {
		continue;
	}
	$frenet_rows[] = $frenet_order;
}
?>
<p class="frenet-lead"><?php esc_html_e( 'Choose the orders, calculate the freight to compare carriers and create the shipments. Nothing is charged before you confirm.', 'woo-shipping-gateway' ); ?></p>
<?php if ( WC_Frenet_Labels_Auto::enabled() ) : ?>
	<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Automation on:', 'woo-shipping-gateway' ); ?></strong> <?php /* translators: %s: rule */ printf( esc_html__( 'paid orders create their shipment on their own (%s). This screen is for the ones that did not go through or that you want to send by hand.', 'woo-shipping-gateway' ), esc_html( WC_Frenet_Labels_Auto::rule_label() ) ); ?></p></div>
<?php endif; ?>
<?php if ( ! WC_Frenet_Labels_Payload::sender_ready() ) : ?>
	<div class="notice notice-warning inline"><p><?php esc_html_e( 'Fill in the store address in WooCommerce > Settings > General before creating shipments: Frenet requires the sender.', 'woo-shipping-gateway' ); ?></p></div>
<?php endif; ?>


<section class="frenet-summary" data-frenet-summary hidden>
	<h2><?php esc_html_e( 'Carriers for the selected orders', 'woo-shipping-gateway' ); ?></h2>
	<table class="frenet-table">
		<thead><tr>
			<th scope="col"><?php esc_html_e( 'Carrier', 'woo-shipping-gateway' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Available', 'woo-shipping-gateway' ); ?></th>
			<th scope="col" class="is-num"><?php esc_html_e( 'Best price', 'woo-shipping-gateway' ); ?></th>
			<th scope="col" class="is-num"><?php esc_html_e( 'Fastest', 'woo-shipping-gateway' ); ?></th>
			<th scope="col" class="is-action"><span class="screen-reader-text"><?php esc_html_e( 'Use for every order', 'woo-shipping-gateway' ); ?></span></th>
		</tr></thead>
		<tbody data-frenet-summary-rows></tbody>
		<tbody class="frenet-summary__best" data-frenet-summary-best></tbody>
	</table>
	<p class="frenet-muted frenet-summary__hint"><?php esc_html_e( 'Select a carrier to use it in every order where it is available, or the best price or the fastest option of each order, whatever the carrier.', 'woo-shipping-gateway' ); ?></p>
</section>

<?php if ( ! $frenet_rows ) : ?>
	<div class="frenet-empty">
		<h2><?php esc_html_e( 'No order waiting for a shipment', 'woo-shipping-gateway' ); ?></h2>
		<p><?php esc_html_e( 'Orders in processing or on hold without a Frenet shipment appear here. You can also select orders in WooCommerce > Orders and use the bulk action "Create Frenet shipment".', 'woo-shipping-gateway' ); ?></p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>"><?php esc_html_e( 'Go to orders', 'woo-shipping-gateway' ); ?></a>
	</div>
	<?php
	return;
endif;
?>

<form class="frenet-create" data-frenet-create>
	<div class="frenet-table-wrap">
		<table class="frenet-table">
			<thead><tr>
				<th scope="col" class="check-column"><input type="checkbox" data-frenet-all checked aria-label="<?php esc_attr_e( 'Select all', 'woo-shipping-gateway' ); ?>"></th>
				<th scope="col"><?php esc_html_e( 'Order', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Destination', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Package', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'NF-e', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Carrier', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Notes', 'woo-shipping-gateway' ); ?></th>
			</tr></thead>
			<tbody>
			<?php
			foreach ( $frenet_rows as $frenet_order ) :
				$frenet_to     = WC_Frenet_Labels_Payload::recipient( $frenet_order );
				$frenet_weight = 0.0;
				$frenet_count  = 0;
				foreach ( WC_Frenet_Labels_Payload::items( $frenet_order ) as $frenet_item ) {
					$frenet_weight += $frenet_item['weight'] * $frenet_item['qty'];
					$frenet_count  += $frenet_item['qty'];
				}
				$frenet_nfe   = WC_Frenet_Labels_Nfe::parse( (string) $frenet_order->get_meta( WC_Frenet_Labels_Nfe::META ) );
				$frenet_issue = WC_Frenet_Labels_Service::issue( $frenet_order );
				?>
				<tr<?php echo $frenet_issue ? ' class="is-failed"' : ''; ?> data-frenet-row data-order="<?php echo esc_attr( (string) $frenet_order->get_id() ); ?>">
					<th scope="row" class="check-column"><input type="checkbox" data-frenet-pick checked aria-label="<?php /* translators: %s: order number */ echo esc_attr( sprintf( __( 'Select order %s', 'woo-shipping-gateway' ), $frenet_order->get_order_number() ) ); ?>"></th>
					<td><a href="<?php echo esc_url( $frenet_order->get_edit_order_url() ); ?>">#<?php echo esc_html( $frenet_order->get_order_number() ); ?></a><br><span class="frenet-muted"><?php echo esc_html( $frenet_to['name'] ); ?></span></td>
					<td><?php echo esc_html( trim( $frenet_to['city'] . ' / ' . $frenet_to['state'], ' /' ) ); ?><br><span class="frenet-muted">CEP <?php echo esc_html( $frenet_to['postcode'] ); ?></span></td>
					<td><?php echo esc_html( wc_format_localized_decimal( (string) round( $frenet_weight, 3 ) ) ); ?> kg<br><span class="frenet-muted"><?php /* translators: %d: items */ echo esc_html( sprintf( _n( '%d item', '%d items', $frenet_count, 'woo-shipping-gateway' ), $frenet_count ) ); ?></span></td>
					<td class="frenet-nfe-cell" data-nfe="<?php echo $frenet_nfe['ok'] ? '1' : '0'; ?>">
						<span data-frenet-nfe-summary><?php echo esc_html( $frenet_nfe['ok'] ? WC_Frenet_Labels_Nfe::summary( $frenet_nfe ) : __( 'optional', 'woo-shipping-gateway' ) ); ?></span>
						<details>
							<summary><?php echo esc_html( $frenet_nfe['ok'] ? __( 'Change', 'woo-shipping-gateway' ) : __( 'Add NF-e key', 'woo-shipping-gateway' ) ); ?></summary>
							<input type="text" inputmode="numeric" maxlength="60" autocomplete="off" spellcheck="false" data-frenet-nfe value="<?php echo esc_attr( $frenet_nfe['ok'] ? $frenet_nfe['key'] : '' ); ?>" placeholder="<?php esc_attr_e( '44 digits from the DANFE', 'woo-shipping-gateway' ); ?>" aria-label="<?php esc_attr_e( 'NF-e access key', 'woo-shipping-gateway' ); ?>">
							<button type="button" class="button button-small" data-frenet-nfe-save><?php esc_html_e( 'Save', 'woo-shipping-gateway' ); ?></button>
							<span class="frenet-field-msg" role="status" aria-live="polite"></span>
						</details>
					</td>
					<td class="frenet-service-cell"><span class="frenet-muted" data-frenet-service><?php esc_html_e( 'Calculate the freight', 'woo-shipping-gateway' ); ?></span></td>
					<td data-frenet-result></td>
					<td class="frenet-issue-cell" data-frenet-issue><?php WC_Frenet_Labels_Admin::view( 'issue', array( 'issue' => $frenet_issue ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php // The bar sticks to the bottom of the screen: the actions stay in sight however long the table is. ?>
	<div class="frenet-bar" aria-live="polite">
		<div class="frenet-bar__facts">
			<span class="frenet-bar__fact"><?php esc_html_e( 'Selected orders', 'woo-shipping-gateway' ); ?><strong data-frenet-bar-count>0</strong></span>
			<span class="frenet-bar__fact" data-frenet-bar-total-wrap hidden><?php esc_html_e( 'Freight total', 'woo-shipping-gateway' ); ?><strong data-frenet-bar-total></strong></span>
			<span class="frenet-bar__fact" data-frenet-bar-after-wrap hidden><span data-frenet-bar-after-label><?php esc_html_e( 'Balance after', 'woo-shipping-gateway' ); ?></span><strong data-frenet-bar-after></strong></span>
		</div>
		<div class="frenet-bar__actions">
			<button type="button" class="button" data-frenet-clear disabled><?php esc_html_e( 'Clear quotes', 'woo-shipping-gateway' ); ?></button>
			<button type="button" class="button button-primary" data-frenet-calc><?php esc_html_e( 'Calculate freight', 'woo-shipping-gateway' ); ?></button>
			<button type="button" class="button button-primary" data-frenet-review disabled><?php esc_html_e( 'Create shipment', 'woo-shipping-gateway' ); ?></button>
		</div>
		<span class="frenet-status-line" role="status" aria-live="polite" data-frenet-status></span>
	</div>
</form>

<dialog class="frenet-dialog" data-frenet-dialog aria-labelledby="frenet-review-title">
	<form method="dialog">
		<h2 id="frenet-review-title"><?php esc_html_e( 'Review before creating', 'woo-shipping-gateway' ); ?></h2>
		<table class="frenet-table">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Carrier', 'woo-shipping-gateway' ); ?></th>
				<th scope="col" class="is-num"><?php esc_html_e( 'Labels', 'woo-shipping-gateway' ); ?></th>
				<th scope="col" class="is-num"><?php esc_html_e( 'Subtotal', 'woo-shipping-gateway' ); ?></th>
			</tr></thead>
			<tbody data-frenet-review-rows></tbody>
			<tfoot><tr><th scope="row" colspan="2"><?php esc_html_e( 'Total', 'woo-shipping-gateway' ); ?></th><td class="is-num" data-frenet-review-total></td></tr></tfoot>
		</table>
		<dl class="frenet-facts" data-frenet-review-balance>
			<div><dt><?php esc_html_e( 'Balance now', 'woo-shipping-gateway' ); ?></dt><dd data-frenet-balance-now></dd></div>
			<div><dt><?php esc_html_e( 'Balance after', 'woo-shipping-gateway' ); ?></dt><dd data-frenet-balance-after></dd></div>
		</dl>
		<p class="frenet-warning" data-frenet-review-warning hidden></p>
		<p class="frenet-warning" role="alert" data-frenet-review-nfe hidden></p>
		<div class="frenet-note" role="note" data-frenet-review-dce hidden>
			<strong data-frenet-review-dce-title></strong>
			<p><?php esc_html_e( 'Frenet generates the content declaration (DC-e) for every order without an NF-e key. If your store is required to issue an NF-e (it depends on its state registration), it cannot use the DC-e: go back and add the NF-e key of those orders.', 'woo-shipping-gateway' ); ?></p>
		</div>
		<p class="frenet-muted" data-frenet-review-note></p>
		<div class="frenet-actions">
			<button value="cancel" class="button"><?php esc_html_e( 'Back', 'woo-shipping-gateway' ); ?></button>
			<button value="confirm" class="button button-primary" data-frenet-confirm></button>
		</div>
	</form>
</dialog>
