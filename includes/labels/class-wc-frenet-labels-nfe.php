<?php
defined( 'ABSPATH' ) || exit;

/**
 * NF-e access key (chave de acesso, 44 digits) of an order, for carriers that require an invoice
 * (validated live 2026-09-30: J&T answers "Chave da nota fiscal inválida" without it).
 *
 * Key layout (Manual de Orientação do Contribuinte, NF-e 4.0), 1-based positions:
 *   1-2 cUF · 3-6 AAMM · 7-20 CNPJ · 21-22 modelo · 23-25 série · 26-34 nNF · 35 tpEmis · 36-43 cNF · 44 cDV
 * cDV = mod 11 over the first 43 digits, weights 2..9 from the right; remainder 0 or 1 → 0.
 *
 * Order meta: _frenet_nfe_key (44 digits, only when valid).
 */
class WC_Frenet_Labels_Nfe {

	const META = '_frenet_nfe_key';

	/**
	 * Keeps only the digits of a pasted key ("3526 0911 2223..." or with dots).
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	public static function normalize( $key ) {
		return (string) preg_replace( '/\D/', '', (string) $key );
	}

	/**
	 * Check digit of the first 43 digits.
	 *
	 * @param string $first43 43 digits.
	 * @return int
	 */
	public static function check_digit( $first43 ) {
		$sum    = 0;
		$weight = 2;
		for ( $i = strlen( $first43 ) - 1; $i >= 0; $i-- ) {
			$sum   += (int) $first43[ $i ] * $weight;
			$weight = 9 === $weight ? 2 : $weight + 1;
		}
		$rest = $sum % 11;
		return $rest < 2 ? 0 : 11 - $rest;
	}

	/**
	 * Validates and reads a key. Pure (no WordPress): the caller translates the error code.
	 *
	 * @param string $key Raw key.
	 * @return array{ok: bool, error: string, key: string, number: string, series: string, model: string}
	 */
	public static function parse( $key ) {
		$digits = self::normalize( $key );
		$out    = array(
			'ok'     => false,
			'error'  => '',
			'key'    => $digits,
			'number' => '',
			'series' => '',
			'model'  => '',
		);
		if ( 44 !== strlen( $digits ) ) {
			$out['error'] = 'length';
			return $out;
		}
		if ( self::check_digit( substr( $digits, 0, 43 ) ) !== (int) $digits[43] ) {
			$out['error'] = 'check_digit';
			return $out;
		}
		$out['model']  = substr( $digits, 20, 2 );
		$out['series'] = (string) (int) substr( $digits, 22, 3 );
		$out['number'] = (string) (int) substr( $digits, 25, 9 );
		$out['ok']     = true;
		return $out;
	}

	/**
	 * Human message for a parse() error code.
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	public static function error_message( $code ) {
		if ( 'length' === $code ) {
			return __( 'The NF-e key has 44 digits. Copy it again from the invoice (DANFE).', 'woo-shipping-gateway' );
		}
		return __( 'This NF-e key is not valid: the last digit does not match. Check for a typo.', 'woo-shipping-gateway' );
	}

	/**
	 * Confirmation text for a valid key: "NF 12345, série 1".
	 *
	 * @param array{number: string, series: string} $parsed parse() result.
	 * @return string
	 */
	public static function summary( array $parsed ) {
		/* translators: 1: invoice number, 2: invoice series */
		return sprintf( __( 'NF %1$s, series %2$s', 'woo-shipping-gateway' ), $parsed['number'], $parsed['series'] );
	}

	/**
	 * True when a Frenet error means the service needs an invoice.
	 *
	 * @param string $message Frenet error message.
	 * @return bool
	 */
	public static function is_invoice_error( $message ) {
		$m = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $message ) : strtolower( (string) $message );
		return false !== strpos( $m, 'nota fiscal' ) || false !== strpos( $m, 'chave de acesso' ) || false !== strpos( $m, 'nf-e' );
	}
}
