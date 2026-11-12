<?php
/**
 * Ponto de postagem: default drop-off point of each carrier (the panel's map, as a searchable list).
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_carriers = WC_Frenet_Labels_Dropoff::carriers();
$frenet_cep      = preg_replace( '/\D/', '', WC_Frenet_Labels_Settings::origin_cep() );
$frenet_cep      = 8 === strlen( $frenet_cep ) ? substr( $frenet_cep, 0, 5 ) . '-' . substr( $frenet_cep, 5 ) : $frenet_cep;
?>
<p class="frenet-lead"><?php esc_html_e( 'Some carriers let you choose where you drop off your packages. Frenet uses the default point of each carrier for its next labels. To turn a default point off, use the Frenet panel.', 'woo-shipping-gateway' ); ?></p>

<?php if ( is_wp_error( $frenet_carriers ) ) : ?>
	<div class="notice notice-error inline"><p><?php echo esc_html( $frenet_carriers->get_error_message() ); ?></p></div>
	<?php
	return;
endif;
?>
<?php if ( ! $frenet_carriers ) : ?>
	<div class="frenet-empty">
		<h2><?php esc_html_e( 'No active carrier', 'woo-shipping-gateway' ); ?></h2>
		<p><?php esc_html_e( 'Activate carriers in the Frenet panel to choose their drop-off points here.', 'woo-shipping-gateway' ); ?></p>
		<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Admin::PANEL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the Frenet panel', 'woo-shipping-gateway' ); ?></a>
	</div>
	<?php
	return;
endif;
?>

<div class="frenet-dropoff" data-frenet-dropoff data-cep="<?php echo esc_attr( $frenet_cep ); ?>">
	<div class="frenet-table-wrap">
		<table class="frenet-table">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Carrier', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Default drop-off point', 'woo-shipping-gateway' ); ?></th>
				<th scope="col" class="is-action"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'woo-shipping-gateway' ); ?></span></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $frenet_carriers as $frenet_carrier ) : ?>
				<tr data-frenet-dropoff-row="<?php echo esc_attr( $frenet_carrier['code'] ); ?>" data-name="<?php echo esc_attr( $frenet_carrier['name'] ); ?>">
					<th scope="row">
						<?php echo wp_kses( WC_Frenet_Labels_Carriers::html( $frenet_carrier['code'], $frenet_carrier['name'] ), WC_Frenet_Labels_Carriers::kses() ); ?>
						<span class="frenet-muted frenet-block"><?php echo esc_html( implode( ', ', array_filter( $frenet_carrier['services'] ) ) ); ?></span>
					</th>
					<td data-frenet-dropoff-default><span class="frenet-muted"><?php esc_html_e( 'Loading…', 'woo-shipping-gateway' ); ?></span></td>
					<td class="is-action">
						<button type="button" class="button button-small" data-frenet-dropoff-edit disabled><?php esc_html_e( 'Change', 'woo-shipping-gateway' ); ?></button>
						<a class="frenet-small frenet-block" href="<?php echo esc_url( WC_Frenet_Labels_Admin::PANEL . '/Carrier/' . rawurlencode( $frenet_carrier['code'] ) . '/Services' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'In the Frenet panel', 'woo-shipping-gateway' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<section class="frenet-card frenet-dropoff__editor" data-frenet-dropoff-editor hidden aria-labelledby="frenet-dropoff-title">
		<h2 id="frenet-dropoff-title" data-frenet-dropoff-title></h2>
		<p class="frenet-muted" data-frenet-dropoff-current></p>

		<div class="frenet-dropoff__search">
			<div>
				<label class="frenet-field" for="frenet-dropoff-cep"><?php esc_html_e( 'Near the CEP', 'woo-shipping-gateway' ); ?></label>
				<input id="frenet-dropoff-cep" type="text" inputmode="numeric" maxlength="9" autocomplete="postal-code" value="<?php echo esc_attr( $frenet_cep ); ?>" data-frenet-dropoff-cep>
			</div>
			<button type="button" class="button" data-frenet-dropoff-search><?php esc_html_e( 'Search points', 'woo-shipping-gateway' ); ?></button>
		</div>

		<label class="frenet-field" for="frenet-dropoff-filter"><?php esc_html_e( 'Filter by name, neighborhood or city', 'woo-shipping-gateway' ); ?></label>
		<input id="frenet-dropoff-filter" type="search" autocomplete="off" data-frenet-dropoff-filter disabled aria-describedby="frenet-dropoff-count">
		<p id="frenet-dropoff-count" class="description" role="status" aria-live="polite" data-frenet-dropoff-count></p>

		<label class="screen-reader-text" for="frenet-dropoff-branch"><?php esc_html_e( 'Drop-off point', 'woo-shipping-gateway' ); ?></label>
		<select id="frenet-dropoff-branch" size="8" data-frenet-dropoff-branch disabled></select>
		<p class="frenet-dropoff__address" data-frenet-dropoff-address></p>

		<div class="frenet-actions">
			<button type="button" class="button button-primary" data-frenet-dropoff-save disabled><?php esc_html_e( 'Save default point', 'woo-shipping-gateway' ); ?></button>
			<button type="button" class="button" data-frenet-dropoff-close><?php esc_html_e( 'Cancel', 'woo-shipping-gateway' ); ?></button>
			<span class="frenet-status-line" role="status" aria-live="polite" data-frenet-status></span>
		</div>
	</section>
</div>
