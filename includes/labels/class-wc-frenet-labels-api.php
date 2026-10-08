<?php
defined( 'ABSPATH' ) || exit;

/**
 * HTTP client for the Frenet APIs used by the labels screens.
 *
 * - WhiteLabel (labels, wallet, list): base URL from the settings, headers token + x-partner-token.
 *   Validated live (2026-09-30/10-01): both tokens are required together; responses are camelCase;
 *   errors carry .NET stack traces; label URLs carry the store token in base64 (p=) and are never logged.
 * - Public API (quote with every carrier, tracking): api.frenet.com.br with the store token.
 */
class WC_Frenet_Labels_Api {

	const PUBLIC_BASE = 'https://api.frenet.com.br';

	/**
	 * Label format for this request only ('' = the one in the settings). Set by the print screen.
	 *
	 * @var string
	 */
	public static $format = '';

	/**
	 * WhiteLabel call.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   Path after /v1.
	 * @param mixed|null $body   JSON body.
	 * @return array<int|string, mixed>|WP_Error
	 */
	public static function whitelabel( $method, $path, $body = null ) {
		$token   = WC_Frenet_Labels_Settings::store_token();
		$partner = WC_Frenet_Labels_Settings::get( 'partner_token' );
		if ( '' === $token || '' === $partner ) {
			return new WP_Error( 'frenet_labels_tokens', __( 'Frenet labels need the store token and the partner token. Check the Frenet settings.', 'woo-shipping-gateway' ) );
		}
		$format = '' !== self::$format ? self::$format : WC_Frenet_Labels_Settings::get( 'label_format' );
		return self::send(
			untrailingslashit( WC_Frenet_Labels_Settings::get( 'api_url' ) ) . $path,
			$method,
			array(
				'token'             => $token,
				'x-partner-token'   => $partner,
				// Frenet ignores "10x15" and prints A4; "A6" (105x148 mm) is its thermal 10x15 label.
				'x-printing-format' => '10x15' === $format ? 'A6' : 'A4',
			),
			$body,
			'GET' === $method ? 20 : 45
		);
	}

	/**
	 * Every usable carrier service for a package (POST /shipping/quote). "Sevices" is the server's typo.
	 *
	 * @param string                           $to_cep Recipient CEP.
	 * @param float                            $value  Declared value.
	 * @param array<int, array<string, mixed>> $items  ShippingItemArray.
	 * @return array<int, array<string, mixed>>|WP_Error ShippingSevicesArray.
	 */
	public static function quote( $to_cep, $value, array $items ) {
		$token = WC_Frenet_Labels_Settings::store_token();
		if ( '' === $token ) {
			return new WP_Error( 'frenet_labels_token', __( 'Frenet token not configured in the Frenet shipping method.', 'woo-shipping-gateway' ) );
		}
		$data = self::send(
			self::PUBLIC_BASE . '/shipping/quote',
			'POST',
			array( 'token' => $token ),
			array(
				'SellerCEP'            => WC_Frenet_Labels_Settings::origin_cep(),
				'RecipientCEP'         => preg_replace( '/\D/', '', (string) $to_cep ),
				'ShipmentInvoiceValue' => (float) $value,
				'RecipientCountry'     => 'BR',
				'ShippingItemArray'    => $items,
				'PlatformName'         => 'WOOCOMMERCE',
			),
			10
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array_values( (array) ( $data['ShippingSevicesArray'] ?? array() ) );
	}

	/**
	 * POST /tracking/trackinginfo ("ServiceDescrition" is the server's typo).
	 *
	 * @param string $service_code Frenet ServiceCode.
	 * @param string $number       Tracking number.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function tracking( $service_code, $number ) {
		return self::send(
			self::PUBLIC_BASE . '/tracking/trackinginfo',
			'POST',
			array( 'token' => WC_Frenet_Labels_Settings::store_token() ),
			array(
				'ShippingServiceCode' => (string) $service_code,
				'TrackingNumber'      => (string) $number,
			),
			10
		);
	}

	/**
	 * GET /CEP/Address/{cep}: street, district, city and state of a Brazilian postcode.
	 *
	 * @param string $cep Postcode, digits only.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function address( $cep ) {
		$token = WC_Frenet_Labels_Settings::store_token();
		if ( '' === $token ) {
			return new WP_Error( 'frenet_labels_token', __( 'Frenet token not configured in the Frenet shipping method.', 'woo-shipping-gateway' ) );
		}
		return self::send( self::PUBLIC_BASE . '/CEP/Address/' . rawurlencode( (string) $cep ), 'GET', array( 'token' => $token ), null, 5 );
	}

	/**
	 * Reads $key from a Frenet response in camelCase or PascalCase.
	 *
	 * @param array<int|string, mixed> $data Response fragment.
	 * @param int|string               $key  Key.
	 * @return mixed
	 */
	public static function field( array $data, $key ) {
		if ( array_key_exists( $key, $data ) ) {
			return $data[ $key ];
		}
		if ( is_string( $key ) && array_key_exists( ucfirst( $key ), $data ) ) {
			return $data[ ucfirst( $key ) ];
		}
		return null;
	}

	/**
	 * Keeps the human part of a Frenet error and explains the known ones.
	 *
	 * @param string $message Raw message.
	 * @return string
	 */
	public static function clean_error( $message ) {
		if ( false !== stripos( $message, 'acesso negado para o parceiro' ) ) {
			return __( 'Frenet refused the partner token. Check it in the Frenet settings. Nothing was charged.', 'woo-shipping-gateway' );
		}
		if ( false !== stripos( $message, 'acesso negado para o cliente' ) ) {
			return __( 'Frenet refused the store token. Check the token of the Frenet shipping method. Nothing was charged.', 'woo-shipping-gateway' );
		}
		$message = (string) preg_replace( '/\s*-->.*$/s', '', $message );
		$message = (string) preg_replace( '/(Detalhes\s+)?identificador:\s*[0-9a-f-]{36}\s*/i', '', $message );
		return trim( $message );
	}

	/**
	 * Hides the store token Frenet embeds in label and declaration URLs (?p=...).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function redact( $text ) {
		return (string) preg_replace( '/([?&]p=)[^&"\s]+/', '$1***', (string) $text );
	}

	/**
	 * Writes to the WooCommerce log (source "frenet-labels"), always redacted.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( self::redact( $message ), array( 'source' => 'frenet-labels' ) );
		}
	}

	/**
	 * HTTP request with JSON in and out.
	 *
	 * @param string               $url     URL.
	 * @param string               $method  Method.
	 * @param array<string, string> $headers Extra headers.
	 * @param mixed|null           $body    JSON body.
	 * @param int                  $timeout Seconds.
	 * @return array<int|string, mixed>|WP_Error
	 */
	private static function send( $url, $method, array $headers, $body, $timeout ) {
		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => $headers + array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res  = wp_remote_request( $url, $args );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( is_wp_error( $res ) ) {
			self::log( "Frenet {$method} {$path}: " . $res->get_error_message() );
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = '';
			if ( is_array( $data ) ) {
				$msg     = (string) ( self::field( $data, 'message' ) ?? '' );
				$details = (array) ( self::field( $data, 'details' ) ?? array() );
				if ( '' === $msg && $details && is_array( $details[0] ) ) {
					$msg = (string) ( self::field( $details[0], 'message' ) ?? '' );
				}
			}
			$msg = '' !== $msg ? self::clean_error( $msg ) : sprintf( /* translators: %d: HTTP status */ __( 'Frenet answered HTTP %d.', 'woo-shipping-gateway' ), $code );
			self::log( "Frenet {$method} {$path} HTTP {$code}: " . substr( $raw, 0, 400 ) );
			return new WP_Error( 'frenet_labels_http_' . $code, $msg, array( 'status' => $code ) );
		}
		return is_array( $data ) ? $data : array();
	}
}
