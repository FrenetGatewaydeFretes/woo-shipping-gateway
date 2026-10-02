<?php
/**
 * Rastreios: orders with a tracking code (latest event, update now) and the bulk import of codes.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_tab    = isset( $_GET['tab'] ) && 'import' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ? 'import' : 'orders'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab.
$frenet_auto   = WC_Frenet_Labels_Settings::on( 'tracking_sync' );
$frenet_status = WC_Frenet_Labels_Settings::on( 'tracking_status' );
$frenet_token  = '' !== WC_Frenet_Labels_Settings::store_token();
$frenet_base   = WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG . '-tracking' );
$frenet_cfg    = WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG ) . '#frenet-tracking-settings';
?>
<p class="frenet-lead"><?php esc_html_e( 'Follow every order shipped with a tracking code, from labels bought here or codes added by hand.', 'woo-shipping-gateway' ); ?></p>

<nav class="frenet-filters" aria-label="<?php esc_attr_e( 'Tracking sections', 'woo-shipping-gateway' ); ?>">
	<a class="frenet-chip<?php echo 'orders' === $frenet_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $frenet_base ); ?>" <?php echo 'orders' === $frenet_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Orders', 'woo-shipping-gateway' ); ?></a>
	<a class="frenet-chip<?php echo 'import' === $frenet_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'import', $frenet_base ) ); ?>" <?php echo 'import' === $frenet_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Import codes', 'woo-shipping-gateway' ); ?></a>
</nav>

<?php if ( ! $frenet_token ) : ?>
	<div class="frenet-empty">
		<p><?php esc_html_e( 'Tracking uses the token of the Frenet shipping method. Set it in WooCommerce > Settings > Shipping > Frenet.', 'woo-shipping-gateway' ); ?></p>
	</div>
	<?php return; ?>
<?php endif; ?>

<?php if ( 'import' === $frenet_tab ) : ?>
	<section class="frenet-card frenet-import" data-frenet-import>
		<h2><?php esc_html_e( 'Add tracking codes in bulk', 'woo-shipping-gateway' ); ?></h2>
		<p class="frenet-muted"><?php esc_html_e( 'One order per line: order number and tracking code, separated by ";", "," or a tab. You can paste two columns straight from a spreadsheet.', 'woo-shipping-gateway' ); ?></p>
		<label class="frenet-field" for="frenet-import-lines"><?php esc_html_e( 'Orders and codes', 'woo-shipping-gateway' ); ?></label>
		<textarea id="frenet-import-lines" class="frenet-import__lines" rows="8" spellcheck="false" placeholder="1234;AA123456789BR&#10;1235;AA987654321BR"></textarea>
		<label class="frenet-switch"><input type="checkbox" id="frenet-import-notify" checked> <span><?php esc_html_e( 'E-mail the tracking code to each customer', 'woo-shipping-gateway' ); ?></span></label>
		<div class="frenet-actions">
			<button type="button" class="button button-primary" data-frenet-import-preview><?php esc_html_e( 'Review before applying', 'woo-shipping-gateway' ); ?></button>
		</div>
		<p class="frenet-status-line" role="status" aria-live="polite" data-frenet-import-status></p>
		<div class="frenet-import__preview" data-frenet-import-preview-box hidden>
			<div class="frenet-table-wrap">
				<table class="frenet-table">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Line', 'woo-shipping-gateway' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Order', 'woo-shipping-gateway' ); ?></th>
						<th scope="col"><?php esc_html_e( 'New code', 'woo-shipping-gateway' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Current code', 'woo-shipping-gateway' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What happens', 'woo-shipping-gateway' ); ?></th>
					</tr></thead>
					<tbody data-frenet-import-rows></tbody>
				</table>
			</div>
			<div class="frenet-actions">
				<button type="button" class="button button-primary" data-frenet-import-apply disabled></button>
				<button type="button" class="button" data-frenet-import-back><?php esc_html_e( 'Edit lines', 'woo-shipping-gateway' ); ?></button>
			</div>
		</div>
	</section>
	<?php return; ?>
<?php endif; ?>

<?php
$frenet_orders = wc_get_orders(
	array(
		'limit'        => 50,
		'meta_key'     => '_frenet_tracking_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- orders with a code.
		'meta_compare' => 'EXISTS',
		'orderby'      => 'date',
		'order'        => 'DESC',
	)
);
?>
<div class="frenet-summary frenet-tracking-head">
	<p class="frenet-muted">
		<span class="frenet-pill is-<?php echo $frenet_auto ? 'ok' : 'off'; ?>"><?php echo $frenet_auto ? esc_html__( 'Automatic tracking on', 'woo-shipping-gateway' ) : esc_html__( 'Automatic tracking off', 'woo-shipping-gateway' ); ?></span>
		<?php if ( $frenet_auto ) : ?>
			<?php echo $frenet_status ? esc_html__( 'Checked every hour; the order status follows the latest event.', 'woo-shipping-gateway' ) : esc_html__( 'Checked every hour; the order status is not changed.', 'woo-shipping-gateway' ); ?>
		<?php else : ?>
			<a href="<?php echo esc_url( $frenet_cfg ); ?>"><?php esc_html_e( 'Turn it on in Settings', 'woo-shipping-gateway' ); ?></a>
		<?php endif; ?>
	</p>
	<?php if ( $frenet_orders ) : ?>
		<button type="button" class="button button-primary" data-frenet-track-all><?php esc_html_e( 'Update all now', 'woo-shipping-gateway' ); ?></button>
	<?php endif; ?>
</div>
<p class="frenet-status-line" role="status" aria-live="polite" data-frenet-track-all-status></p>

<?php if ( ! $frenet_orders ) : ?>
	<div class="frenet-empty frenet-empty--small">
		<p><?php esc_html_e( 'No order has a tracking code yet. Codes arrive when a label is paid, or add them on the order screen or in Import codes.', 'woo-shipping-gateway' ); ?></p>
		<a class="button" href="<?php echo esc_url( add_query_arg( 'tab', 'import', $frenet_base ) ); ?>"><?php esc_html_e( 'Import codes', 'woo-shipping-gateway' ); ?></a>
	</div>
<?php else : ?>
	<div class="frenet-table-wrap">
		<table class="frenet-table">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Order', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Tracking code', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Order status', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Latest movement', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Checked', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'woo-shipping-gateway' ); ?></span></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $frenet_orders as $frenet_o ) : ?>
				<?php $frenet_sum = WC_Frenet_Tracking_Admin::summary( $frenet_o ); ?>
				<tr data-frenet-track-row="<?php echo esc_attr( (string) $frenet_o->get_id() ); ?>">
					<td><a href="<?php echo esc_url( $frenet_o->get_edit_order_url() ); ?>">#<?php echo esc_html( $frenet_o->get_order_number() ); ?></a><br><span class="frenet-muted"><?php echo esc_html( $frenet_o->get_formatted_billing_full_name() ); ?></span></td>
					<td>
						<?php if ( $frenet_sum['url'] ) : ?>
							<a class="frenet-code" href="<?php echo esc_url( $frenet_sum['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $frenet_sum['code'] ); ?></a>
						<?php else : ?>
							<span class="frenet-code"><?php echo esc_html( $frenet_sum['code'] ); ?></span>
						<?php endif; ?>
					</td>
					<td data-frenet-track-status><?php echo esc_html( $frenet_sum['status'] ); ?></td>
					<td data-frenet-track-event>
						<?php if ( $frenet_sum['description'] ) : ?>
							<strong><?php echo esc_html( $frenet_sum['description'] ); ?></strong><br><span class="frenet-muted"><?php echo esc_html( $frenet_sum['when'] ); ?></span>
						<?php else : ?>
							<span class="frenet-muted"><?php esc_html_e( 'No movement yet', 'woo-shipping-gateway' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="frenet-muted" data-frenet-track-synced><?php echo esc_html( $frenet_sum['synced'] ? $frenet_sum['synced'] : __( 'Never', 'woo-shipping-gateway' ) ); ?></td>
					<td><button type="button" class="button button-small" data-frenet-track-sync="<?php echo esc_attr( (string) $frenet_o->get_id() ); ?>"><?php esc_html_e( 'Update', 'woo-shipping-gateway' ); ?></button></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
