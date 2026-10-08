<?php
defined( 'ABSPATH' ) || exit;

/**
 * Carrier comparison for "Criar envio". Pure logic (no WordPress), unit tested.
 *
 * Input: ShippingSevicesArray of POST api.frenet.com.br/shipping/quote ("Sevices" is the server's typo).
 * Each order gets its services normalized, the cheapest ("Melhor preço") and the fastest ("Melhor prazo")
 * marked, and a default selection: the service the customer chose, unless the store asked to pick the
 * best one automatically. For several orders, summarize() builds the per-carrier header of the screen.
 */
class WC_Frenet_Labels_Compare {

	const BEST_PRICE = 'best_price';
	const BEST_TIME  = 'best_time';

	/**
	 * Brazilian or dotted decimal ("12,34" / "12.34" / 12.34) → float.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public static function to_float( $value ) {
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}
		$value = (string) $value;
		if ( false !== strpos( $value, ',' ) ) {
			$value = str_replace( array( '.', ',' ), array( '', '.' ), $value );
		}
		return (float) $value;
	}

	/**
	 * Usable services (no error, price > 0), cheapest first.
	 *
	 * @param array<int, mixed> $raw ShippingSevicesArray (raw API data, items may be anything).
	 * @return array<int, array{code: string, name: string, carrier: string, carrier_code: string, price: float, days: int}>
	 */
	public static function normalize( array $raw ) {
		$out = array();
		foreach ( $raw as $s ) {
			if ( ! is_array( $s ) || ! empty( $s['Error'] ) || ! isset( $s['ServiceCode'] ) ) {
				continue;
			}
			$price = self::to_float( $s['ShippingPrice'] ?? 0 );
			if ( $price <= 0 ) {
				continue;
			}
			$out[] = array(
				'code'         => (string) $s['ServiceCode'],
				'name'         => (string) ( $s['ServiceDescription'] ?? $s['ServiceCode'] ),
				'carrier'      => (string) ( $s['Carrier'] ?? '' ),
				'carrier_code' => (string) ( $s['CarrierCode'] ?? '' ),
				'price'        => round( $price, 2 ),
				'days'         => (int) ( $s['DeliveryTime'] ?? 0 ),
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return array( $a['price'], $a['days'] ) <=> array( $b['price'], $b['days'] );
			}
		);
		return $out;
	}

	/**
	 * Cheapest and fastest service codes ('' when there is none). Ties: fastest wins on price, cheapest on time.
	 *
	 * @param array<int, array<string, mixed>> $services normalize() result.
	 * @return array{best_price: string, best_time: string}
	 */
	public static function best( array $services ) {
		$price = '';
		$time  = '';
		$p     = null;
		$t     = null;
		foreach ( $services as $s ) {
			if ( null === $p || array( $s['price'], $s['days'] ) < array( $p['price'], $p['days'] ) ) {
				$p = $s;
			}
			$days = $s['days'] > 0 ? $s['days'] : PHP_INT_MAX;
			$tday = null === $t ? PHP_INT_MAX : ( $t['days'] > 0 ? $t['days'] : PHP_INT_MAX );
			if ( null === $t || array( $days, $s['price'] ) < array( $tday, $t['price'] ) ) {
				$t = $s;
			}
		}
		if ( $p ) {
			$price = $p['code'];
		}
		if ( $t ) {
			$time = $t['code'];
		}
		return array(
			'best_price' => $price,
			'best_time'  => $time,
		);
	}

	/**
	 * Service selected by default for an order.
	 *
	 * @param array<int, array<string, mixed>> $services normalize() result.
	 * @param string                           $chosen   ServiceCode the customer chose ('' if none).
	 * @param bool                             $auto     "Selecionar automaticamente o melhor preço".
	 * @param string                           $rule     best_price | best_time ("Serviço padrão").
	 * @return string '' when there is nothing to select.
	 */
	public static function default_choice( array $services, $chosen, $auto, $rule ) {
		$codes = array_column( $services, 'code' );
		if ( ! $auto && '' !== (string) $chosen && in_array( (string) $chosen, $codes, true ) ) {
			return (string) $chosen;
		}
		$best = self::best( $services );
		return self::BEST_TIME === $rule ? $best['best_time'] : $best['best_price'];
	}

	/**
	 * Per-carrier header for several orders, like the Figma table:
	 * carrier · available in how many orders · total with its cheapest service · total with its fastest service;
	 * plus "Melhor opção (total)": the sum of each order's cheapest service of any carrier.
	 *
	 * @param array<int, array<int, array<string, mixed>>> $per_order normalize() result per order.
	 * @return array{carriers: array<int, array{carrier: string, available: int, orders: int, best_price: float, best_time: float}>, best_total: float, orders: int}
	 */
	public static function summarize( array $per_order ) {
		$orders   = count( $per_order );
		$carriers = array();
		$best     = 0.0;
		foreach ( $per_order as $services ) {
			if ( $services ) {
				$best += min( array_column( $services, 'price' ) );
			}
			$by = array();
			foreach ( $services as $s ) {
				$name          = '' !== $s['carrier'] ? $s['carrier'] : $s['carrier_code'];
				$by[ $name ][] = $s;
			}
			foreach ( $by as $name => $list ) {
				$picks = self::best( $list );
				$index = array_column( $list, null, 'code' );
				if ( ! isset( $carriers[ $name ] ) ) {
					$carriers[ $name ] = array(
						'carrier'    => $name,
						'available'  => 0,
						'orders'     => $orders,
						'best_price' => 0.0,
						'best_time'  => 0.0,
					);
				}
				++$carriers[ $name ]['available'];
				$carriers[ $name ]['best_price'] += $index[ $picks['best_price'] ]['price'];
				$carriers[ $name ]['best_time']  += $index[ $picks['best_time'] ]['price'];
			}
		}
		$carriers = array_values( $carriers );
		foreach ( $carriers as &$c ) {
			$c['best_price'] = round( $c['best_price'], 2 );
			$c['best_time']  = round( $c['best_time'], 2 );
		}
		unset( $c );
		usort(
			$carriers,
			static function ( $a, $b ) {
				return array( -$a['available'], $a['best_price'] ) <=> array( -$b['available'], $b['best_price'] );
			}
		);
		return array(
			'carriers'   => $carriers,
			'best_total' => round( $best, 2 ),
			'orders'     => $orders,
		);
	}
}
