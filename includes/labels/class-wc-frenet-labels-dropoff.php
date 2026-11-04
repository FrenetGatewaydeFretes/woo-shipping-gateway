<?php
defined( 'ABSPATH' ) || exit;

/**
 * "Ponto de postagem": the drop-off point where the store hands over the packages of each carrier, like
 * the panel's "Escolha onde postar os seus pedidos" (painel.frenet.com.br/Carrier/{code}/Services), without the map.
 *
 * Carriers come from the store's services (GET /carriers/services); the points near a CEP from
 * GET /carriers/{code}/zipcode/{cep}/branches; the default point is read and saved on the store profile
 * (GET/PUT /profile/{code}/defaultbranch). Frenet uses that default point for every new label of the carrier.
 */
class WC_Frenet_Labels_Dropoff {

	const CARRIERS = 'woocommerce_frenet_labels_carriers';

	/**
	 * Carriers with at least one enabled service, by name.
	 *
	 * @param bool $fresh Skip the one-hour cache.
	 * @return array<int, array{code: string, name: string, services: array<int, string>}>|WP_Error
	 */
	public static function carriers( $fresh = false ) {
		$cached = $fresh ? false : get_transient( self::CARRIERS );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = WC_Frenet_Labels_Api::configuration( 'GET', '/carriers/services' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$out = self::group( (array) ( WC_Frenet_Labels_Api::field( $res, 'shippingServices' ) ?? array() ) );
		set_transient( self::CARRIERS, $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Groups the services by carrier (pure, unit tested).
	 *
	 * @param array<int, mixed> $services shippingServices.
	 * @return array<int, array{code: string, name: string, services: array<int, string>}>
	 */
	public static function group( array $services ) {
		$by = array();
		foreach ( $services as $s ) {
			if ( ! is_array( $s ) || false === ( WC_Frenet_Labels_Api::field( $s, 'enabled' ) ?? true ) ) {
				continue;
			}
			$code = (string) ( WC_Frenet_Labels_Api::field( $s, 'carrierCode' ) ?? '' );
			if ( '' === $code ) {
				continue;
			}
			if ( ! isset( $by[ $code ] ) ) {
				$name        = (string) ( WC_Frenet_Labels_Api::field( $s, 'carrier' ) ?? '' );
				$by[ $code ] = array(
					'code'     => $code,
					'name'     => '' !== $name ? $name : $code,
					'services' => array(),
				);
			}
			$by[ $code ]['services'][] = (string) ( WC_Frenet_Labels_Api::field( $s, 'serviceDescription' ) ?? WC_Frenet_Labels_Api::field( $s, 'serviceCode' ) ?? '' );
		}
		$out = array_values( $by );
		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		return $out;
	}

	/**
	 * Drop-off points of a carrier near a CEP. A carrier without points (Frenet answers 404) gives an empty list.
	 *
	 * @param string $carrier Carrier code (JTE, LOG, TOT, JAD…).
	 * @param string $cep     CEP, any format.
	 * @return array<int, array{id: string, name: string, initials: string, address: string, type: string}>|WP_Error
	 */
	public static function branches( $carrier, $cep ) {
		$cep = preg_replace( '/\D/', '', (string) $cep );
		if ( 8 !== strlen( (string) $cep ) ) {
			return new WP_Error( 'frenet_labels_cep', __( 'Type a CEP with 8 digits.', 'woo-shipping-gateway' ) );
		}
		$res = WC_Frenet_Labels_Api::configuration( 'GET', '/carriers/' . rawurlencode( self::code( $carrier ) ) . '/zipcode/' . $cep . '/branches' );
		if ( self::not_found( $res ) ) {
			return array();
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$out = array();
		foreach ( $res as $b ) {
			if ( is_array( $b ) ) {
				$branch = self::branch( $b );
				if ( '' !== $branch['id'] ) {
					$out[] = $branch;
				}
			}
		}
		return $out;
	}

	/**
	 * Default drop-off point of the carrier, or null when the store has none.
	 *
	 * @param string $carrier Carrier code.
	 * @return array{id: string, name: string, initials: string, address: string, type: string}|null|WP_Error
	 */
	public static function default_branch( $carrier ) {
		$res = WC_Frenet_Labels_Api::configuration( 'GET', '/profile/' . rawurlencode( self::code( $carrier ) ) . '/defaultbranch' );
		if ( self::not_found( $res ) ) {
			return null;
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$branch = self::branch( $res );
		return '' !== $branch['id'] ? $branch : null;
	}

	/**
	 * Saves the default drop-off point of the carrier on the Frenet profile.
	 *
	 * @param string $carrier Carrier code.
	 * @param string $id      Point id from branches().
	 * @return true|WP_Error
	 */
	public static function set_default( $carrier, $id ) {
		$id = trim( (string) $id );
		if ( '' === $id ) {
			return new WP_Error( 'frenet_labels_branch', __( 'Choose a drop-off point.', 'woo-shipping-gateway' ) );
		}
		$res = WC_Frenet_Labels_Api::configuration( 'PUT', '/profile/' . rawurlencode( self::code( $carrier ) ) . '/defaultbranch', array( 'Id' => $id ) );
		// Live 2026-10-14: Jadlog saves (204); J&T, Loggi and Total answer 400 "Unidade não existe" even for the
		// point already saved on the profile. Tell the shopkeeper where it does work instead of a raw error.
		if ( is_wp_error( $res ) && false !== stripos( $res->get_error_message(), 'Unidade não existe' ) ) {
			return new WP_Error( 'frenet_labels_branch_refused', __( 'Frenet did not accept this point for this carrier ("Unidade não existe"). Nothing changed. Choose it in the Frenet panel, in Shipping methods > Carriers.', 'woo-shipping-gateway' ) );
		}
		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * One point as the screen needs it (pure, unit tested).
	 *
	 * @param array<int|string, mixed> $b Raw branch (camelCase or PascalCase).
	 * @return array{id: string, name: string, initials: string, address: string, type: string}
	 */
	public static function branch( array $b ) {
		$a     = (array) ( WC_Frenet_Labels_Api::field( $b, 'address' ) ?? array() );
		$get   = static function ( $key ) use ( $a ) {
			return trim( (string) ( WC_Frenet_Labels_Api::field( $a, $key ) ?? '' ) );
		};
		$cep   = preg_replace( '/\D/', '', $get( 'zipCode' ) );
		$cep   = '' !== $cep ? str_pad( (string) $cep, 8, '0', STR_PAD_LEFT ) : '';
		$place = trim( $get( 'city' ) . ( '' !== $get( 'addressState' ) ? '/' . $get( 'addressState' ) : '' ), '/' );
		$parts = array_filter( array( $get( 'address' ), $get( 'addressQuarter' ), $place, '' !== $cep ? 'CEP ' . substr( $cep, 0, 5 ) . '-' . substr( $cep, 5 ) : '' ) );
		return array(
			'id'       => (string) ( WC_Frenet_Labels_Api::field( $b, 'id' ) ?? '' ),
			'name'     => trim( (string) ( WC_Frenet_Labels_Api::field( $b, 'nickName' ) ?? '' ) ),
			'initials' => (string) ( WC_Frenet_Labels_Api::field( $b, 'initials' ) ?? '' ),
			'address'  => implode( ' · ', $parts ),
			'type'     => (string) ( WC_Frenet_Labels_Api::field( $b, 'type' ) ?? '' ),
		);
	}

	/**
	 * Carrier code as Frenet expects it (letters and digits, upper case).
	 *
	 * @param string $carrier Raw code.
	 * @return string
	 */
	private static function code( $carrier ) {
		return strtoupper( (string) preg_replace( '/[^A-Za-z0-9_]/', '', (string) $carrier ) );
	}

	/**
	 * Frenet answers 404 for "no points" and "no default point".
	 *
	 * @param mixed $res Response.
	 * @return bool
	 */
	private static function not_found( $res ) {
		return is_wp_error( $res ) && 'frenet_labels_http_404' === $res->get_error_code();
	}
}
