<?php
/**
 * "Observações" cell: why the last label of the order failed (no balance, missing size, carrier refusal…).
 *
 * @package woo-shipping-gateway
 * @var array{message: string, at: string}|null $issue
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $issue ) ) {
	echo '<span class="frenet-muted">—</span>';
	return;
}
?>
<span class="frenet-error frenet-block"><?php echo esc_html( $issue['message'] ); ?></span>
<?php if ( '' !== $issue['at'] ) : ?>
	<span class="frenet-muted frenet-block"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $issue['at'] ) ); ?></span>
<?php endif; ?>
