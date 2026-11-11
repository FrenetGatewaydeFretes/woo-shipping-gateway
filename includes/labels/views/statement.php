<?php
/**
 * Extrato: the movements of the Frenet wallet (GET /wallet/demonstrative), newest first, a page at a time.
 *
 * @package woo-shipping-gateway
 */

defined( 'ABSPATH' ) || exit;

$frenet_per  = 30;
$frenet_p    = isset( $_GET['p'] ) ? max( 0, absint( $_GET['p'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
$frenet_list = WC_Frenet_Labels_Service::statement( $frenet_p, $frenet_per );
$frenet_slug = WC_Frenet_Labels_Admin::SLUG . '-statement';
?>
<p class="frenet-lead"><?php esc_html_e( 'Every movement of your Frenet wallet: deposits, label payments, refunds of cancelled labels and adjustments. Refunds usually arrive overnight.', 'woo-shipping-gateway' ); ?></p>

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

$frenet_pages = max( 1, (int) ceil( $frenet_list['total'] / $frenet_per ) );
?>
<?php if ( ! $frenet_list['operations'] ) : ?>
	<div class="frenet-empty frenet-empty--small"><p><?php esc_html_e( 'No movements in the wallet yet.', 'woo-shipping-gateway' ); ?></p></div>
<?php else : ?>
	<p class="frenet-muted frenet-small">
		<?php
		/* translators: 1: page, 2: pages, 3: total movements */
		echo esc_html( sprintf( __( 'Page %1$d of %2$d · %3$s movements in the account.', 'woo-shipping-gateway' ), $frenet_p + 1, $frenet_pages, number_format_i18n( $frenet_list['total'] ) ) );
		?>
	</p>
	<div class="frenet-table-wrap">
		<table class="frenet-table frenet-statement">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Date', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Movement', 'woo-shipping-gateway' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'woo-shipping-gateway' ); ?></th>
				<th scope="col" class="is-num"><?php esc_html_e( 'Amount', 'woo-shipping-gateway' ); ?></th>
				<th scope="col" class="is-num"><?php esc_html_e( 'Balance after', 'woo-shipping-gateway' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $frenet_list['operations'] as $frenet_op ) : ?>
				<?php
				// "29/10/2026 06:04:15" → date and time on two lines, like the labels table.
				$frenet_when = explode( ' ', $frenet_op['date'], 2 );
				$frenet_tone = array(
					'Aprovado'           => 'is-ok',
					'Credito ao cliente' => 'is-ok',
					'Liberado'           => 'is-off',
					'Bloqueado'          => 'is-wait',
					'Falhou'             => 'is-bad',
				);
				$frenet_sign = 1 === $frenet_op['sign'] ? '+ ' : ( -1 === $frenet_op['sign'] ? '− ' : '' );
				?>
				<tr>
					<td><?php echo esc_html( $frenet_when[0] ); ?><?php if ( isset( $frenet_when[1] ) ) : ?><span class="frenet-cell-sub"><?php echo esc_html( substr( $frenet_when[1], 0, 5 ) ); ?></span><?php endif; ?></td>
					<td class="frenet-statement__what"><?php echo esc_html( '' !== $frenet_op['description'] ? $frenet_op['description'] : '—' ); ?></td>
					<td><span class="frenet-pill <?php echo esc_attr( $frenet_tone[ $frenet_op['type'] ] ?? 'is-off' ); ?>"><?php echo esc_html( '' !== $frenet_op['type'] ? $frenet_op['type'] : '—' ); ?></span></td>
					<td class="is-num frenet-statement__value<?php echo 1 === $frenet_op['sign'] ? ' is-in' : ( -1 === $frenet_op['sign'] ? ' is-out' : ' is-info' ); ?>"><?php echo esc_html( $frenet_sign . WC_Frenet_Labels_Service::money( $frenet_op['value'] ) ); ?></td>
					<td class="is-num"><?php echo esc_html( WC_Frenet_Labels_Service::money( $frenet_op['balance'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="frenet-muted frenet-small"><?php esc_html_e( 'Amounts without a sign did not change the balance: they only record a step (a refund being processed, a payment on hold or a deposit that failed).', 'woo-shipping-gateway' ); ?></p>
<?php endif; ?>

<?php if ( $frenet_pages > 1 ) : ?>
	<nav class="frenet-pager" aria-label="<?php esc_attr_e( 'Pages', 'woo-shipping-gateway' ); ?>">
		<?php if ( $frenet_p > 0 ) : ?>
			<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( $frenet_slug, array_filter( array( 'p' => $frenet_p - 1 ) ) ) ); ?>"><?php esc_html_e( 'Newer', 'woo-shipping-gateway' ); ?></a>
		<?php endif; ?>
		<span><?php /* translators: 1: page, 2: pages */ echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'woo-shipping-gateway' ), $frenet_p + 1, $frenet_pages ) ); ?></span>
		<?php if ( $frenet_p + 1 < $frenet_pages ) : ?>
			<a class="button" href="<?php echo esc_url( WC_Frenet_Labels_Admin::url( $frenet_slug, array( 'p' => $frenet_p + 1 ) ) ); ?>"><?php esc_html_e( 'Older', 'woo-shipping-gateway' ); ?></a>
		<?php endif; ?>
	</nav>
<?php endif; ?>
