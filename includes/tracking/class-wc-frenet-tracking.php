<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tracking of Frenet shipments: codes per order (from bought labels or typed by hand), hourly sync
 * with POST /tracking/trackinginfo, automatic order status from the latest event, customer e-mails
 * and bulk import of codes.
 *
 * Order meta (HPOS-safe, always through WC_Order):
 *   _frenet_tracking_code    tracking number (also written by the labels service)
 *   _frenet_service_code     Frenet ServiceCode (labels service, else the FRENET_<code> rate)
 *   _frenet_tracking_events  [ [date, location, description, type], ... ] oldest first
 *   _frenet_tracking_url     public tracking page returned by Frenet
 *   _frenet_tracking_synced  timestamp of the last sync that changed something
 *
 * The time of every check is kept apart (transient wc_frenet_tracking_checked_<order id>), so a check
 * that finds nothing new does not save the order.
 */
class WC_Frenet_Tracking {

	const CRON        = 'woocommerce_frenet_tracking_sync';
	const GROUP       = 'woo-shipping-gateway';
	const SCHEDULE    = 'woocommerce_frenet_tracking_schedule';
	const BATCH       = 50;
	const ADMIN_BATCH = 10;
	const OPEN        = array( 'processing', 'on-hold', 'frenet-transit', 'frenet-pickup' );
	const CURSOR      = 'woocommerce_frenet_tracking_cursor';
	const CHECKED     = 'wc_frenet_tracking_checked_';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'cron_sync' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'emails' ) );
		// Slugs only: this runs on plugins_loaded, and translating here would load the text domain too early (WP 6.7+).
		foreach ( WC_Frenet_Order_Statuses::slugs() as $slug ) {
			$status = substr( $slug, 3 );
			// WooCommerce loads the mailer on demand: load it when one of our statuses is set.
			add_action(
				'woocommerce_order_status_' . $status,
				function ( $order_id ) use ( $status ) {
					if ( WC_Frenet_Labels_Settings::on( 'tracking_email' ) ) {
						WC()->mailer();
						do_action( 'woocommerce_frenet_status_email_' . $status, $order_id );
					}
				}
			);
		}
	}

	/**
	 * Keeps the hourly run in line with the setting (created or removed): an Action Scheduler action when WooCommerce
	 * ships it, else a WP-Cron event. The Action Scheduler queue is checked at most once an hour or when the setting
	 * changes.
	 *
	 * @return void
	 */
	public static function schedule() {
		$on = WC_Frenet_Labels_Settings::on( 'tracking_sync' );
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			$next = wp_next_scheduled( self::CRON );
			if ( $on && ! $next ) {
				wp_schedule_event( time() + 300, 'hourly', self::CRON );
			} elseif ( ! $on && $next ) {
				wp_clear_scheduled_hook( self::CRON );
			}
			return;
		}
		if ( ! did_action( 'action_scheduler_init' ) ) {
			return;
		}
		$state = $on ? 'on' : 'off';
		$last  = get_option( self::SCHEDULE );
		if ( is_array( $last ) && ( $last['state'] ?? '' ) === $state && (int) ( $last['time'] ?? 0 ) > time() - HOUR_IN_SECONDS ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON ) ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		$has = as_has_scheduled_action( self::CRON, array(), self::GROUP );
		if ( $on && ! $has ) {
			as_schedule_recurring_action( time(), HOUR_IN_SECONDS, self::CRON, array(), self::GROUP );
		} elseif ( ! $on && $has ) {
			as_unschedule_all_actions( self::CRON, array(), self::GROUP );
		}
		update_option(
			self::SCHEDULE,
			array(
				'state' => $state,
				'time'  => time(),
			),
			true
		);
	}

	/**
	 * Registers the customer e-mails (editable in WooCommerce > Settings > Emails).
	 *
	 * @param array<string, WC_Email> $emails E-mails.
	 * @return array<string, WC_Email>
	 */
	public static function emails( $emails ) {
		require_once __DIR__ . '/emails/class-wc-frenet-email-tracking.php';
		require_once __DIR__ . '/emails/class-wc-frenet-email-status.php';
		$emails['WC_Frenet_Email_Tracking']         = new WC_Frenet_Email_Tracking();
		$emails['WC_Frenet_Email_Status_Transit']   = new WC_Frenet_Email_Status( 'frenet-transit', __( 'Order in transit (Frenet)', 'woo-shipping-gateway' ), __( 'Your order #{order_number} is on its way', 'woo-shipping-gateway' ), 'no' );
		$emails['WC_Frenet_Email_Status_Pickup']    = new WC_Frenet_Email_Status( 'frenet-pickup', __( 'Order awaiting pickup (Frenet)', 'woo-shipping-gateway' ), __( 'Your order #{order_number} is waiting for pickup', 'woo-shipping-gateway' ), 'yes' );
		$emails['WC_Frenet_Email_Status_Delivered'] = new WC_Frenet_Email_Status( 'frenet-delivered', __( 'Order delivered (Frenet)', 'woo-shipping-gateway' ), __( 'Your order #{order_number} was delivered', 'woo-shipping-gateway' ), 'yes' );
		return $emails;
	}

	/* ------------------------------------------------------------------ data */

	/**
	 * Frenet ServiceCode of the order's shipping line ("FRENET_03298" → "03298"), or ''.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function service_from_rate( WC_Order $order ) {
		foreach ( $order->get_shipping_methods() as $item ) {
			$id = (string) $item->get_meta( 'FRENET_ID' );
			if ( 0 === strpos( $id, 'FRENET_' ) ) {
				return substr( $id, 7 );
			}
		}
		return '';
	}

	/**
	 * Tracking data of an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array{code: string, service: string, events: array<int, array<string, string>>, url: string, synced: int}
	 */
	public static function get( WC_Order $order ) {
		$service = (string) $order->get_meta( '_frenet_service_code' );
		$events  = $order->get_meta( '_frenet_tracking_events' );
		return array(
			'code'    => (string) $order->get_meta( '_frenet_tracking_code' ),
			'service' => '' !== $service ? $service : self::service_from_rate( $order ),
			'events'  => is_array( $events ) ? $events : array(),
			'url'     => (string) $order->get_meta( '_frenet_tracking_url' ),
			'synced'  => self::checked_at( $order ),
		);
	}

	/**
	 * When the current tracking code of the order was last checked with Frenet.
	 *
	 * @param WC_Order $order Order.
	 * @return int Timestamp (0 if never).
	 */
	public static function checked_at( WC_Order $order ) {
		$checked = get_transient( self::CHECKED . $order->get_id() );
		$time    = is_array( $checked ) && (string) ( $checked['code'] ?? '' ) === (string) $order->get_meta( '_frenet_tracking_code' ) ? (int) ( $checked['time'] ?? 0 ) : 0;
		return max( $time, (int) $order->get_meta( '_frenet_tracking_synced' ) );
	}

	/**
	 * Normalizes a tracking code: letters and digits, upper case.
	 *
	 * @param string $code Raw code.
	 * @return string
	 */
	public static function clean_code( $code ) {
		return strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
	}

	/**
	 * Saves a tracking code typed by hand or imported. On a new code: clears old events, adds a note,
	 * e-mails the customer ($notify) and moves Processing orders to In transit (automatic status on).
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $code   Tracking code ('' removes it).
	 * @param bool     $notify E-mail the customer.
	 * @return string The saved code.
	 */
	public static function set_code( WC_Order $order, $code, $notify = true ) {
		$code = self::clean_code( $code );
		$old  = (string) $order->get_meta( '_frenet_tracking_code' );
		if ( '' === $code ) {
			foreach ( array( '_frenet_tracking_code', '_frenet_tracking_events', '_frenet_tracking_url', '_frenet_tracking_synced' ) as $key ) {
				$order->delete_meta_data( $key );
			}
			$order->save();
			delete_transient( self::CHECKED . $order->get_id() );
			return '';
		}
		if ( $old === $code ) {
			return $code;
		}
		$order->update_meta_data( '_frenet_tracking_code', $code );
		if ( '' === (string) $order->get_meta( '_frenet_service_code' ) ) {
			$service = self::service_from_rate( $order );
			if ( '' !== $service ) {
				$order->update_meta_data( '_frenet_service_code', $service );
			}
		}
		$order->delete_meta_data( '_frenet_tracking_events' );
		$order->delete_meta_data( '_frenet_tracking_url' );
		/* translators: %s: tracking code */
		$order->add_order_note( sprintf( __( 'Tracking code added: %s', 'woo-shipping-gateway' ), $code ) );
		$order->save();
		if ( $notify && WC_Frenet_Labels_Settings::on( 'tracking_email' ) ) {
			WC()->mailer();
			do_action( 'woocommerce_frenet_tracking_email', $order->get_id() );
		}
		if ( WC_Frenet_Labels_Settings::on( 'tracking_status' ) && $order->has_status( 'processing' ) ) {
			self::change_status( $order, 'frenet-transit', __( 'Tracking code added.', 'woo-shipping-gateway' ) );
		}
		return $code;
	}

	/**
	 * Fetches the events from Frenet, stores them and applies the automatic status.
	 *
	 * @param WC_Order $order Order.
	 * @return array{events: int, changed: bool, pending: bool, note: string}|WP_Error
	 */
	public static function sync( WC_Order $order ) {
		$t = self::get( $order );
		if ( '' === $t['code'] ) {
			return new WP_Error( 'frenet_no_code', __( 'This order has no tracking code.', 'woo-shipping-gateway' ) );
		}
		if ( '' === $t['service'] ) {
			return new WP_Error( 'frenet_no_service', __( 'This order was not shipped with a Frenet service, so Frenet cannot track it.', 'woo-shipping-gateway' ) );
		}
		$data = WC_Frenet_Labels_Api::tracking( $t['service'], $t['code'] );
		$now  = time();
		set_transient(
			self::CHECKED . $order->get_id(),
			array(
				'code' => $t['code'],
				'time' => $now,
			),
			MONTH_IN_SECONDS
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$events = self::events_from( $data );
		// Frenet answers HTTP 200 with "ErrorMessage" while the carrier has nothing yet ("não encontrado", "sem eventos"):
		// that is "no movement yet", not a failure. Any other message (wrong service code…) is kept as a note.
		$note  = $events ? '' : self::note_from( (string) ( WC_Frenet_Labels_Api::field( $data, 'ErrorMessage' ) ?? '' ) );
		$url   = esc_url_raw( (string) ( WC_Frenet_Labels_Api::field( $data, 'TrackingUrl' ) ?? '' ) );
		$dirty = $events !== $t['events'];
		if ( $dirty ) {
			$order->update_meta_data( '_frenet_tracking_events', $events );
		}
		if ( '' !== $url && $url !== $t['url'] ) {
			$order->update_meta_data( '_frenet_tracking_url', $url );
			$dirty = true;
		}
		if ( $dirty ) {
			$order->update_meta_data( '_frenet_tracking_synced', (string) $now );
			$order->save();
		}
		$changed = false;
		if ( count( $events ) > count( $t['events'] ) && WC_Frenet_Labels_Settings::on( 'tracking_status' ) ) {
			$changed = self::apply_status( $order, end( $events ) );
		}
		return array(
			'events'  => count( $events ),
			'changed' => $changed,
			'pending' => ! $events,
			'note'    => $note,
		);
	}

	/**
	 * Frenet "ErrorMessage" of a tracking answer without events: empty when it only says there is nothing yet.
	 * Pure: unit tested.
	 *
	 * @param string $message Frenet message.
	 * @return string Cleaned message worth showing, or ''.
	 */
	public static function note_from( $message ) {
		$message = trim( (string) $message );
		if ( '' === $message ) {
			return '';
		}
		foreach ( array( 'não encontrado', 'nao encontrado', 'not found', 'sem evento', 'nenhum evento', 'não há evento', 'aguardando postagem', 'ainda não' ) as $quiet ) {
			if ( false !== mb_stripos( $message, $quiet ) ) {
				return '';
			}
		}
		return WC_Frenet_Labels_Api::clean_error( $message );
	}

	/**
	 * Frenet TrackingEvents → stored events, oldest first. Pure: unit tested.
	 *
	 * @param array<string, mixed> $data Frenet answer.
	 * @return array<int, array{date: string, location: string, description: string, type: string}>
	 */
	public static function events_from( array $data ) {
		$events = array();
		foreach ( (array) ( $data['TrackingEvents'] ?? $data['trackingEvents'] ?? array() ) as $e ) {
			$e        = (array) $e;
			$events[] = array(
				'date'        => (string) ( $e['EventDateTime'] ?? $e['eventDateTime'] ?? '' ),
				'location'    => (string) ( $e['EventLocation'] ?? $e['eventLocation'] ?? '' ),
				'description' => (string) ( $e['EventDescription'] ?? $e['eventDescription'] ?? '' ),
				'type'        => (string) ( $e['EventType'] ?? $e['eventType'] ?? '' ),
			);
		}
		$keys = array();
		foreach ( $events as $i => $event ) {
			$keys[ $i ] = array( self::event_time( $event['date'] ), $i );
		}
		uksort(
			$events,
			function ( $a, $b ) use ( $keys ) {
				return $keys[ $a ] <=> $keys[ $b ];
			}
		);
		return array_values( $events );
	}

	/**
	 * Frenet dates come as "dd/mm/YYYY HH:ii", sometimes with seconds or without the time, or in ISO 8601.
	 *
	 * @param string $date Date.
	 * @return int Timestamp (0 if unknown).
	 */
	public static function event_time( $date ) {
		$date = trim( (string) $date );
		foreach ( array( '!d/m/Y H:i:s', '!d/m/Y H:i', '!d/m/Y' ) as $format ) {
			$d      = DateTime::createFromFormat( $format, $date );
			$errors = DateTime::getLastErrors();
			if ( $d && ( ! $errors || ! ( $errors['warning_count'] + $errors['error_count'] ) ) ) {
				return $d->getTimestamp();
			}
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?)?(Z|[+-]\d{2}:?\d{2})?$/', $date ) ) {
			try {
				return ( new DateTime( $date ) )->getTimestamp();
			} catch ( Exception $e ) {
				return 0;
			}
		}
		return 0;
	}

	/**
	 * Latest event → order status (without "wc-"). Pure: unit tested.
	 *
	 * Uses the Frenet EventType when present (9 delivered, 3 returned, 18 at the drop-off point,
	 * 0/1/2/5 moving) and Correios/carrier phrases otherwise. A package going back or delivered to
	 * the sender is returning, and "não (foi) entregue" is not a delivery. Anything unknown means in
	 * transit; '' (no change) while the label is not posted yet.
	 *
	 * @param array<string, string> $event Event.
	 * @return string
	 */
	public static function status_for_event( array $event ) {
		$type = trim( (string) ( $event['type'] ?? '' ) );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) ( $event['description'] ?? '' ) ) : strtolower( (string) ( $event['description'] ?? '' ) );
		if ( '3' === $type || self::mentions( $text, array( 'ao remetente', 'devolvido', 'devolução', 'devolucao' ) ) ) {
			return 'frenet-returning';
		}
		if ( '9' === $type ) {
			return 'frenet-delivered';
		}
		// Correios events before posting ("Etiqueta emitida - Aguardando postagem", "Etiqueta expirada") and of a cancelled
		// label ("Etiqueta cancelada pelo emissor", EventType 1 like a moving package, validated live 2026-10-28): no change.
		foreach ( array( 'etiqueta emitida', 'etiqueta expirada', 'etiqueta cancelada', 'aguardando postagem', 'prazo para postagem' ) as $needle ) {
			if ( false !== strpos( $text, $needle ) ) {
				return '';
			}
		}
		if ( false !== strpos( $text, 'entregue' ) && ! preg_match( '/n[ãa]o\s+(foi\s+)?entregue/iu', $text ) && false === strpos( $text, 'ponto de postagem' ) ) {
			return 'frenet-delivered';
		}
		if ( self::mentions( $text, array( 'aguardando retirada', 'disponível para retirada', 'disponivel para retirada' ) ) ) {
			return 'frenet-pickup';
		}
		return 'frenet-transit';
	}

	/**
	 * Whether a text contains one of the phrases.
	 *
	 * @param string   $text    Lower-case text.
	 * @param string[] $needles Phrases.
	 * @return bool
	 */
	private static function mentions( $text, array $needles ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $text, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Moves the order to the status of the latest event. Never touches finished or cancelled orders.
	 *
	 * @param WC_Order              $order Order.
	 * @param array<string, string> $event Latest event.
	 * @return bool Whether the status changed.
	 */
	private static function apply_status( WC_Order $order, array $event ) {
		if ( $order->has_status( array( 'cancelled', 'refunded', 'failed', 'completed', 'pending' ) ) ) {
			return false;
		}
		$status = self::status_for_event( $event );
		if ( '' === $status || $order->has_status( $status ) ) {
			return false;
		}
		/* translators: %s: tracking event description */
		self::change_status( $order, $status, sprintf( __( 'Tracking: %s', 'woo-shipping-gateway' ), $event['description'] ) );
		return true;
	}

	/**
	 * Changes the status, making sure the statuses are registered from now on.
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $status Status without "wc-".
	 * @param string   $note   Order note.
	 * @return void
	 */
	private static function change_status( WC_Order $order, $status, $note ) {
		if ( ! get_option( WC_Frenet_Order_Statuses::USED ) ) {
			update_option( WC_Frenet_Order_Statuses::USED, 1, false );
		}
		$order->update_status( $status, $note );
	}

	/**
	 * One page of the orders with a tracking code that are not finished yet, oldest first.
	 *
	 * @param int $limit  Max orders.
	 * @param int $offset Orders to skip.
	 * @return array{orders: array<int, WC_Order>, total: int}
	 */
	public static function open_orders( $limit = self::BATCH, $offset = 0 ) {
		$found = wc_get_orders(
			array(
				'limit'        => (int) $limit,
				'offset'       => (int) $offset,
				'paginate'     => true,
				'status'       => self::OPEN,
				'meta_key'     => '_frenet_tracking_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one indexed lookup of orders with a code.
				'meta_compare' => 'EXISTS',
				'orderby'      => 'date ID',
				'order'        => 'ASC',
			)
		);
		return array(
			'orders' => (array) $found->orders,
			'total'  => (int) $found->total,
		);
	}

	/**
	 * Syncs one page of open orders. The next offset leaves out the orders that are finished now, so the next
	 * page starts right after this one.
	 *
	 * @param int $offset Orders to skip.
	 * @param int $limit  Max orders.
	 * @return array{synced: int, changed: int, pending: int, errors: int, offset: int, total: int, remaining: int, done: bool}
	 */
	public static function sync_batch( $offset, $limit ) {
		$offset = max( 0, (int) $offset );
		$page   = self::open_orders( $limit, $offset );
		$out    = array(
			'synced'  => 0,
			'changed' => 0,
			'pending' => 0,
			'errors'  => 0,
		);
		$open   = 0;
		foreach ( $page['orders'] as $order ) {
			$r     = self::sync( $order );
			$open += $order->has_status( self::OPEN ) ? 1 : 0;
			if ( is_wp_error( $r ) ) {
				++$out['errors'];
				continue;
			}
			++$out['synced'];
			$out['changed'] += $r['changed'] ? 1 : 0;
			$out['pending'] += $r['pending'] ? 1 : 0;
		}
		$remaining = max( 0, $page['total'] - $offset - count( $page['orders'] ) );
		return $out + array(
			'offset'    => $offset + $open,
			'total'     => $page['total'],
			'remaining' => $remaining,
			'done'      => count( $page['orders'] ) < (int) $limit || 0 === $remaining,
		);
	}

	/**
	 * "Update all now", one short request at a time: up to ADMIN_BATCH orders from $offset. The answer says where the
	 * next request starts and whether anything is left.
	 *
	 * @param int $offset Orders to skip (the "offset" of the previous answer; 0 to start).
	 * @return array{synced: int, changed: int, pending: int, errors: int, offset: int, total: int, remaining: int, done: bool}
	 */
	public static function sync_now( $offset = 0 ) {
		return self::sync_batch( $offset, self::ADMIN_BATCH );
	}

	/**
	 * Hourly run: the next page of open orders, round-robin, so every order gets its turn however many there are.
	 *
	 * @return array{synced: int, changed: int, pending: int, errors: int, offset: int, total: int, remaining: int, done: bool}
	 */
	public static function cron_sync() {
		$offset = (int) get_option( self::CURSOR, 0 );
		$out    = self::sync_batch( $offset, self::BATCH );
		if ( $offset > 0 && 0 === $out['synced'] + $out['errors'] ) {
			$out = self::sync_batch( 0, self::BATCH );
		}
		update_option( self::CURSOR, $out['done'] ? 0 : $out['offset'], false );
		WC_Frenet_Labels_Api::log( sprintf( 'Tracking sync: %d synced, %d status changes, %d without events yet, %d errors.', $out['synced'], $out['changed'], $out['pending'], $out['errors'] ) );
		return $out;
	}

	/* ------------------------------------------------------------------ bulk import */

	/**
	 * Parses pasted lines "order;code" (also "," or tab). Pure: unit tested.
	 *
	 * @param string $text Pasted text.
	 * @return array{rows: array<int, array{line: int, order: string, code: string}>, invalid: array<int, int>}
	 */
	public static function parse_lines( $text ) {
		$rows    = array();
		$invalid = array();
		$lines   = preg_split( '/\r\n|\r|\n/', (string) $text );
		foreach ( (array) $lines as $i => $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = preg_split( '/\s*[;,\t]\s*/', $line );
			$order = ltrim( trim( (string) ( $parts[0] ?? '' ) ), '#' );
			$code  = self::clean_code( (string) ( $parts[1] ?? '' ) );
			if ( ! preg_match( '/^\d+$/', $order ) || strlen( $code ) < 8 || strlen( $code ) > 40 ) {
				if ( 0 === $i && ! preg_match( '/\d{3,}/', $line ) ) {
					continue; // Header row such as "pedido;codigo".
				}
				$invalid[] = $i + 1;
				continue;
			}
			$rows[] = array(
				'line'  => $i + 1,
				'order' => $order,
				'code'  => $code,
			);
		}
		return array(
			'rows'    => $rows,
			'invalid' => $invalid,
		);
	}

	/**
	 * What the import would do, line by line, without changing anything.
	 *
	 * @param string $text Pasted text.
	 * @return array{rows: array<int, array<string, mixed>>, invalid: array<int, int>, apply: int}
	 */
	public static function preview( $text ) {
		$parsed = self::parse_lines( $text );
		$out    = array();
		$apply  = 0;
		$seen   = array();
		foreach ( $parsed['rows'] as $row ) {
			$order  = wc_get_order( (int) $row['order'] );
			$result = 'add';
			if ( ! $order instanceof WC_Order ) {
				$result = 'not_found';
			} elseif ( isset( $seen[ $row['order'] ] ) ) {
				$result = 'duplicate';
			} elseif ( (string) $order->get_meta( '_frenet_tracking_code' ) === $row['code'] ) {
				$result = 'same';
			} elseif ( '' !== (string) $order->get_meta( '_frenet_tracking_code' ) ) {
				$result = 'replace';
			}
			$seen[ $row['order'] ] = true;
			if ( in_array( $result, array( 'add', 'replace' ), true ) ) {
				++$apply;
			}
			$out[] = $row + array(
				'result'  => $result,
				'current' => $order instanceof WC_Order ? (string) $order->get_meta( '_frenet_tracking_code' ) : '',
				'status'  => $order instanceof WC_Order ? wc_get_order_status_name( $order->get_status() ) : '',
				'url'     => $order instanceof WC_Order ? $order->get_edit_order_url() : '',
			);
		}
		return array(
			'rows'    => $out,
			'invalid' => $parsed['invalid'],
			'apply'   => $apply,
		);
	}

	/**
	 * Applies the import (only "add" and "replace" lines).
	 *
	 * @param string $text   Pasted text.
	 * @param bool   $notify E-mail each customer.
	 * @return array{applied: int, skipped: int}
	 */
	public static function import( $text, $notify ) {
		$preview = self::preview( $text );
		$applied = 0;
		foreach ( $preview['rows'] as $row ) {
			if ( ! in_array( $row['result'], array( 'add', 'replace' ), true ) ) {
				continue;
			}
			$order = wc_get_order( (int) $row['order'] );
			if ( $order instanceof WC_Order ) {
				self::set_code( $order, $row['code'], $notify );
				++$applied;
			}
		}
		return array(
			'applied' => $applied,
			'skipped' => count( $preview['rows'] ) - $applied,
		);
	}
}
