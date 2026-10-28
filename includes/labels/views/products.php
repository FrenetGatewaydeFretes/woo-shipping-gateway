<?php
/**
 * Frenet › Produtos: weight, size and "Automatic shipment with" of every product that ships, editable in place,
 * with "apply to the selected" for bulk changes. Saves to the product itself.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
$frenet_f      = isset( $_GET['f'] ) ? sanitize_key( wp_unslash( $_GET['f'] ) ) : WC_Frenet_Labels_Products::FILTER_ALL; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
$frenet_p      = isset( $_GET['p'] ) ? max( 0, absint( $_GET['p'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
$frenet_saved  = isset( $_GET['saved'] ) ? absint( $_GET['saved'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
$frenet_f      = in_array( $frenet_f, array( WC_Frenet_Labels_Products::FILTER_NO_SIZE, WC_Frenet_Labels_Products::FILTER_RULE ), true ) ? $frenet_f : WC_Frenet_Labels_Products::FILTER_ALL;
$frenet_data   = WC_Frenet_Labels_Products::query( $frenet_q, $frenet_f, $frenet_p );
$frenet_slug   = WC_Frenet_Labels_Admin::SLUG . '-products';
$frenet_opts   = WC_Frenet_Labels_Auto::product_options();
$frenet_wunit  = get_option( 'woocommerce_weight_unit', 'kg' );
$frenet_dunit  = get_option( 'woocommerce_dimension_unit', 'cm' );
$frenet_quotes = WC_Frenet_Labels_Settings::quotes_at_checkout();
$frenet_args   = array_filter(
	array(
		'q' => $frenet_q,
		'f' => WC_Frenet_Labels_Products::FILTER_ALL !== $frenet_f ? $frenet_f : '',
	)
);
$frenet_url    = static function ( array $extra = array() ) use ( $frenet_slug, $frenet_args ) {
	return WC_Frenet_Labels_Admin::url( $frenet_slug, $extra + $frenet_args );
};
$frenet_cols   = array(
	'weight' => sprintf( '%s (%s)', __( 'Weight', 'woo-shipping-gateway' ), $frenet_wunit ),
	'length' => sprintf( '%s (%s)', __( 'Length', 'woo-shipping-gateway' ), $frenet_dunit ),
	'width'  => sprintf( '%s (%s)', __( 'Width', 'woo-shipping-gateway' ), $frenet_dunit ),
	'height' => sprintf( '%s (%s)', __( 'Height', 'woo-shipping-gateway' ), $frenet_dunit ),
);
?>
<?php if ( null !== $frenet_saved ) : ?>
	<div class="notice notice-success is-dismissible"><p>
		<?php
		/* translators: %d: products */
		echo esc_html( sprintf( _n( '%d product saved.', '%d products saved.', $frenet_saved, 'woo-shipping-gateway' ), $frenet_saved ) );
		?>
	</p></div>
<?php endif; ?>
<p class="frenet-lead"><?php esc_html_e( 'Weight, size and the carrier of the automatic shipment, product by product. What you save here also shows in the product form and can be imported by CSV.', 'woo-shipping-gateway' ); ?></p>
<div class="frenet-note" role="note">
	<?php if ( WC_Frenet_Labels_Auto::enabled() ) : ?>
		<strong><?php esc_html_e( 'Automatic shipments are on', 'woo-shipping-gateway' ); ?></strong>
		<?php /* translators: %s: rule */ echo esc_html( sprintf( __( '(rule: %s).', 'woo-shipping-gateway' ), WC_Frenet_Labels_Auto::rule_label() ) ); ?>
		<?php esc_html_e( 'A product that asks for a carrier or a rule wins over the general rule for the orders it is in.', 'woo-shipping-gateway' ); ?>
	<?php else : ?>
		<strong><?php esc_html_e( 'Automatic shipments are off.', 'woo-shipping-gateway' ); ?></strong>
		<?php esc_html_e( 'What you set here is used when you turn them on in Settings.', 'woo-shipping-gateway' ); ?>
	<?php endif; ?>
	<?php echo esc_html( $frenet_quotes ? __( 'Customers see the Frenet carriers at checkout.', 'woo-shipping-gateway' ) : __( 'Customers do not see Frenet at checkout: they pay your other shipping method and the carrier is decided here.', 'woo-shipping-gateway' ) ); ?>
	<a href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( WC_Frenet_Labels_Admin::SLUG ) ); ?>#frenet-auto-settings"><?php esc_html_e( 'Settings', 'woo-shipping-gateway' ); ?></a>
</div>
<div class="frenet-toolbar">
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="frenet-filters" role="search">
		<input type="hidden" name="page" value="<?php echo esc_attr( $frenet_slug ); ?>">
		<label class="screen-reader-text" for="frenet-q"><?php esc_html_e( 'Search products', 'woo-shipping-gateway' ); ?></label>
		<input id="frenet-q" type="search" name="q" value="<?php echo esc_attr( $frenet_q ); ?>" placeholder="<?php esc_attr_e( 'Product name or SKU', 'woo-shipping-gateway' ); ?>">
		<label class="screen-reader-text" for="frenet-f"><?php esc_html_e( 'Show', 'woo-shipping-gateway' ); ?></label>
		<select id="frenet-f" name="f">
			<option value=""<?php selected( WC_Frenet_Labels_Products::FILTER_ALL, $frenet_f ); ?>><?php esc_html_e( 'All products', 'woo-shipping-gateway' ); ?></option>
			<option value="<?php echo esc_attr( WC_Frenet_Labels_Products::FILTER_NO_SIZE ); ?>"<?php selected( WC_Frenet_Labels_Products::FILTER_NO_SIZE, $frenet_f ); ?>><?php esc_html_e( 'Without weight or size', 'woo-shipping-gateway' ); ?></option>
			<option value="<?php echo esc_attr( WC_Frenet_Labels_Products::FILTER_RULE ); ?>"<?php selected( WC_Frenet_Labels_Products::FILTER_RULE, $frenet_f ); ?>><?php esc_html_e( 'With their own carrier or rule', 'woo-shipping-gateway' ); ?></option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Search', 'woo-shipping-gateway' ); ?></button>
		<span class="frenet-muted"><?php /* translators: %d: products */ echo esc_html( sprintf( _n( '%d product', '%d products', $frenet_data['total'], 'woo-shipping-gateway' ), $frenet_data['total'] ) ); ?></span>
	</form>
</div>
<?php if ( ! $frenet_data['rows'] ) : ?>
	<div class="frenet-empty frenet-empty--small">
		<h2><?php esc_html_e( 'No product found', 'woo-shipping-gateway' ); ?></h2>
		<p><?php esc_html_e( 'Simple products and variations are listed: a variation without its own carrier follows its parent product.', 'woo-shipping-gateway' ); ?></p>
	</div>
<?php else : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="frenet-products" data-frenet-products>
	<input type="hidden" name="action" value="<?php echo esc_attr( WC_Frenet_Labels_Products::ACTION ); ?>">
	<?php wp_nonce_field( WC_Frenet_Labels_Products::ACTION ); ?>
	<input type="hidden" name="q" value="<?php echo esc_attr( $frenet_q ); ?>">
	<input type="hidden" name="f" value="<?php echo esc_attr( $frenet_f ); ?>">
	<input type="hidden" name="p" value="<?php echo (int) $frenet_p; ?>">
	<div class="frenet-table-wrap">
		<table class="frenet-table">
			<thead><tr>
				<th scope="col" class="check-column"><input type="checkbox" data-frenet-all aria-label="<?php esc_attr_e( 'Select all', 'woo-shipping-gateway' ); ?>"></th>
				<th scope="col"><?php esc_html_e( 'Product', 'woo-shipping-gateway' ); ?></th>
				<?php foreach ( $frenet_cols as $frenet_col ) : ?>
					<th scope="col" class="is-num"><?php echo esc_html( $frenet_col ); ?></th>
				<?php endforeach; ?>
				<th scope="col"><?php esc_html_e( 'Automatic shipment with', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $frenet_data['rows'] as $frenet_r ) : ?>
				<tr data-frenet-id="<?php echo (int) $frenet_r['id']; ?>">
					<th scope="row" class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int) $frenet_r['id']; ?>" data-frenet-pick aria-label="<?php echo esc_attr( $frenet_r['name'] ); ?>"></th>
					<td><?php echo esc_html( $frenet_r['name'] ); ?>
						<span class="frenet-muted frenet-block frenet-small"><?php echo esc_html( $frenet_r['sku'] ); ?>
						<?php
						if ( $frenet_r['variation'] ) :
							?>
							· <?php esc_html_e( 'variation', 'woo-shipping-gateway' ); ?><?php endif; ?>
						<?php
						if ( ! $frenet_r['complete'] ) :
							?>
							· <span class="frenet-danger"><?php esc_html_e( 'missing weight or size', 'woo-shipping-gateway' ); ?></span><?php endif; ?></span></td>
					<?php foreach ( array_keys( $frenet_cols ) as $frenet_field ) : ?>
						<td class="is-num"><input type="text" inputmode="decimal" name="rows[<?php echo (int) $frenet_r['id']; ?>][<?php echo esc_attr( $frenet_field ); ?>]" value="<?php echo esc_attr( $frenet_r[ $frenet_field ] ); ?>" data-frenet-field="<?php echo esc_attr( $frenet_field ); ?>" aria-label="<?php echo esc_attr( $frenet_cols[ $frenet_field ] ); ?>"></td>
					<?php endforeach; ?>
					<td><select name="rows[<?php echo (int) $frenet_r['id']; ?>][rule]" data-frenet-field="rule" aria-label="<?php esc_attr_e( 'Automatic shipment with', 'woo-shipping-gateway' ); ?>">
						<?php foreach ( $frenet_opts as $frenet_value => $frenet_label ) : ?>
							<option value="<?php echo esc_attr( $frenet_value ); ?>"<?php selected( $frenet_value, $frenet_r['rule'] ); ?>><?php echo esc_html( '' === $frenet_value && '' !== $frenet_r['inherits'] ? sprintf( /* translators: %s: what the parent product asks */ __( 'Like the parent product (%s)', 'woo-shipping-gateway' ), WC_Frenet_Labels_Auto::product_label( $frenet_r['inherits'] ) ) : $frenet_label ); ?></option>
						<?php endforeach; ?>
					</select></td>
					<td><a class="frenet-small" href="<?php echo esc_url( $frenet_r['edit_url'] ); ?>"><?php esc_html_e( 'Edit product', 'woo-shipping-gateway' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="frenet-bar frenet-bar--products">
		<div class="frenet-bulk" role="group" aria-label="<?php esc_attr_e( 'Apply to the selected products', 'woo-shipping-gateway' ); ?>">
			<?php /* translators: %d: selected products (replaced by the page script) */ $frenet_some = __( '%d product(s) selected', 'woo-shipping-gateway' ); ?>
			<span class="frenet-bulk__count" data-frenet-count data-zero="<?php esc_attr_e( 'Select products to change several at once.', 'woo-shipping-gateway' ); ?>" data-some="<?php echo esc_attr( $frenet_some ); ?>"><?php esc_html_e( 'Select products to change several at once.', 'woo-shipping-gateway' ); ?></span>
			<?php foreach ( $frenet_cols as $frenet_field => $frenet_col ) : ?>
				<input type="text" inputmode="decimal" data-frenet-bulk="<?php echo esc_attr( $frenet_field ); ?>" placeholder="<?php echo esc_attr( $frenet_col ); ?>" aria-label="<?php echo esc_attr( $frenet_col ); ?>">
			<?php endforeach; ?>
			<select data-frenet-bulk="rule" aria-label="<?php esc_attr_e( 'Automatic shipment with', 'woo-shipping-gateway' ); ?>">
				<option value=""><?php esc_html_e( 'Automatic shipment with…', 'woo-shipping-gateway' ); ?></option>
				<?php foreach ( $frenet_opts as $frenet_value => $frenet_label ) : ?>
					<option value="<?php echo esc_attr( '' === $frenet_value ? 'general' : $frenet_value ); ?>"><?php echo esc_html( $frenet_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button" data-frenet-apply disabled><?php esc_html_e( 'Apply to the selected', 'woo-shipping-gateway' ); ?></button>
		</div>
		<div class="frenet-bar__actions">
			<span class="frenet-muted"><?php esc_html_e( 'Unsaved changes:', 'woo-shipping-gateway' ); ?> <b data-frenet-dirty-count>0</b></span>
			<button type="submit" class="button button-primary button-hero" data-frenet-save disabled><?php esc_html_e( 'Save changes', 'woo-shipping-gateway' ); ?></button>
		</div>
	</div>
</form>
	<?php if ( $frenet_data['pages'] > 1 ) : ?>
	<nav class="frenet-pager" aria-label="<?php esc_attr_e( 'Pages', 'woo-shipping-gateway' ); ?>">
		<?php
		if ( $frenet_p > 0 ) :
			?>
			<a class="button" href="<?php echo esc_url( $frenet_url( array( 'p' => $frenet_p - 1 ) ) ); ?>"><?php esc_html_e( 'Previous', 'woo-shipping-gateway' ); ?></a><?php endif; ?>
		<span><?php /* translators: 1: page, 2: pages */ echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'woo-shipping-gateway' ), $frenet_p + 1, $frenet_data['pages'] ) ); ?></span>
		<?php
		if ( $frenet_p + 1 < $frenet_data['pages'] ) :
			?>
			<a class="button" href="<?php echo esc_url( $frenet_url( array( 'p' => $frenet_p + 1 ) ) ); ?>"><?php esc_html_e( 'Next', 'woo-shipping-gateway' ); ?></a><?php endif; ?>
	</nav>
	<?php endif; ?>
<?php endif; ?>
