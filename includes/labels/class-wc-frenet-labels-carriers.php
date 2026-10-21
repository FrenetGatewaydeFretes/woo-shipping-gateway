<?php
/**
 * Carrier logos for the Frenet labels screens.
 *
 * Frenet's API sends no logo, so the official files are bundled in assets/images/carriers, one per Frenet carrier
 * code (Correios, Jadlog, J&T Express, Loggi, Total Express, LATAM Cargo, Azul Cargo Express). A carrier without a
 * file gets a monogram in its brand colour, so a new carrier never breaks the layout.
 *
 * @package WooCommerce_Frenet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logo (or monogram) of a carrier, for PHP views and for the page script.
 */
class WC_Frenet_Labels_Carriers {

	const DIR = 'assets/images/carriers/';

	/**
	 * Frenet carrier code => bundled file.
	 */
	const FILES = array(
		'COR' => 'cor.svg',
		'JAD' => 'jad.png',
		'JTE' => 'jte.svg',
		'LOG' => 'log.png',
		'TOT' => 'tot.svg',
		'LAT' => 'lat.svg',
		'AZU' => 'azu.png',
	);

	/**
	 * Brand colour of the monogram, for carriers whose file is missing or that have none.
	 */
	const COLORS = array(
		'COR' => '#005ca9',
		'JAD' => '#e4002b',
		'JTE' => '#d7262d',
		'LOG' => '#00a9e0',
		'TOT' => '#22315e',
		'LAT' => '#1b0088',
		'AZU' => '#0d2a5c',
		'BUS' => '#1f8a4c',
		'SEQ' => '#0e7c6b',
	);

	/**
	 * Lower-case needle found in the carrier name (or in an unknown code) => known code.
	 */
	const NAMES = array(
		'correios' => 'COR',
		'jadlog'   => 'JAD',
		'j&t'      => 'JTE',
		'jt exp'   => 'JTE',
		'loggi'    => 'LOG',
		'total'    => 'TOT',
		'latam'    => 'LAT',
		'azul'     => 'AZU',
		'buslog'   => 'BUS',
		'sequoia'  => 'SEQ',
	);

	/**
	 * Colours of the monogram for carriers nobody listed above (picked by the code, so it is stable).
	 */
	const PALETTE = array( '#0c005a', '#7b3ff3', '#0b6b47', '#7a4b00', '#a4161a', '#344054' );

	/**
	 * Known carrier code for a Frenet code and name ("JTE" for "J&T Express", also when the code is odd).
	 *
	 * @param string $code Frenet carrier code.
	 * @param string $name Carrier name.
	 * @return string
	 */
	public static function code( $code, $name = '' ) {
		$code = strtoupper( trim( (string) $code ) );
		if ( '' !== $code && isset( self::FILES[ $code ] ) ) {
			return $code;
		}
		$lower = mb_strtolower( (string) $name . ' ' . $code );
		foreach ( self::NAMES as $needle => $known ) {
			if ( false !== strpos( $lower, $needle ) ) {
				return $known;
			}
		}
		return $code;
	}

	/**
	 * URL of the bundled logo, or '' when the carrier has none.
	 *
	 * @param string $code Known code (see code()).
	 * @return string
	 */
	public static function url( $code ) {
		if ( ! isset( self::FILES[ $code ] ) ) {
			return '';
		}
		return plugins_url( self::DIR . self::FILES[ $code ], WOO_FRENET_PATH . 'woo-shipping-gateway.php' );
	}

	/**
	 * Colour of the monogram.
	 *
	 * @param string $code Known code.
	 * @return string
	 */
	public static function color( $code ) {
		if ( isset( self::COLORS[ $code ] ) ) {
			return self::COLORS[ $code ];
		}
		return self::PALETTE[ array_sum( array_map( 'ord', str_split( (string) $code ) ) ) % count( self::PALETTE ) ];
	}

	/**
	 * Two letters for the monogram: initials of the name ("J&T Express" => "JE"), else the start of the code.
	 *
	 * @param string $name Carrier name.
	 * @param string $code Known code.
	 * @return string
	 */
	public static function initials( $name, $code ) {
		$words = preg_split( '/[\s\-_]+/u', trim( (string) $name ) );
		if ( ! is_array( $words ) ) {
			$words = array();
		}
		$words = array_values(
			array_filter(
				$words,
				function ( $w ) {
					return '' !== preg_replace( '/[^\p{L}\p{N}]/u', '', $w );
				}
			)
		);
		if ( count( $words ) >= 2 ) {
			return mb_strtoupper( mb_substr( $words[0], 0, 1 ) . mb_substr( $words[1], 0, 1 ) );
		}
		$base = '' !== trim( (string) $name ) ? $name : $code;
		return mb_strtoupper( mb_substr( preg_replace( '/[^\p{L}\p{N}]/u', '', (string) $base ), 0, 2 ) );
	}

	/**
	 * Logo of the carrier as HTML (the name goes in the alt text), or its monogram with the name.
	 *
	 * Already escaped; print it through wp_kses() with kses().
	 *
	 * @param string $code Frenet carrier code.
	 * @param string $name Carrier name.
	 * @return string
	 */
	public static function html( $code, $name = '' ) {
		$known = self::code( $code, $name );
		$label = '' !== trim( (string) $name ) ? trim( (string) $name ) : $known;
		$url   = self::url( $known );
		if ( '' !== $url ) {
			return sprintf(
				'<span class="frenet-carrier"><img class="frenet-carrier__logo" src="%s" alt="%s" height="20" loading="lazy" decoding="async"></span>',
				esc_url( $url ),
				esc_attr( $label )
			);
		}
		return sprintf(
			'<span class="frenet-carrier"><span class="frenet-carrier__mark" style="background:%s" aria-hidden="true">%s</span><span class="frenet-carrier__name">%s</span></span>',
			esc_attr( self::color( $known ) ),
			esc_html( self::initials( $label, $known ) ),
			esc_html( $label )
		);
	}

	/**
	 * Tags html() uses, for wp_kses().
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function kses() {
		return array(
			'span' => array(
				'class'       => true,
				'style'       => true,
				'aria-hidden' => true,
			),
			'img'  => array(
				'class'    => true,
				'src'      => true,
				'alt'      => true,
				'height'   => true,
				'loading'  => true,
				'decoding' => true,
			),
		);
	}

	/**
	 * What the page script needs to draw the same thing: logo URLs, colours and name needles.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function js() {
		$logos = array();
		foreach ( array_keys( self::FILES ) as $code ) {
			$logos[ $code ] = self::url( $code );
		}
		return array(
			'logos'   => $logos,
			'colors'  => self::COLORS,
			'names'   => self::NAMES,
			'palette' => self::PALETTE,
		);
	}
}
