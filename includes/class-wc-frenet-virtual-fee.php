<?php

/**
 * WC_Frenet_Virtual_Fee class.
 *
 * Charges the Frenet "Virtual Products Fee" as shipping on carts with virtual products.
 */
class WC_Frenet_Virtual_Fee
{
    /**
     * Frenet method with the fee enabled, per customer destination, for the current request.
     *
     * @var array
     */
    private static $fee_methods = array();

    /**
     * Makes a cart with only virtual products need shipping when the customer's zone charges the fee.
     *
     * @param bool $needs_shipping
     * @return bool
     */
    public static function cart_needs_shipping( $needs_shipping ) {
        if ( $needs_shipping || ! self::cart_has_virtual_items() ) {
            return $needs_shipping;
        }

        return null !== self::get_fee_method_for_customer();
    }

    /**
     * Flags the first shipping package of a cart with virtual products, so the fee is charged once per order.
     *
     * @param array $packages
     * @return array
     */
    public static function flag_virtual_fee_package( $packages ) {
        if ( empty( $packages ) || ! is_array( $packages ) || ! self::cart_has_virtual_items() ) {
            return $packages;
        }

        reset( $packages );
        $packages[ key( $packages ) ][ WC_Frenet::PACKAGE_VIRTUAL_FEE_KEY ] = true;

        return $packages;
    }

    /**
     * Keeps only the fee rate in a package with only virtual products, hiding the other carriers' rates.
     *
     * @param array $rates
     * @param array $package
     * @return array
     */
    public static function keep_only_virtual_fee_rate( $rates, $package ) {
        if ( ! is_array( $rates ) || empty( $package[ WC_Frenet::PACKAGE_VIRTUAL_FEE_KEY ] ) || WC_Frenet::package_has_shippable_items( $package ) ) {
            return $rates;
        }

        return array_filter( $rates, array( __CLASS__, 'is_virtual_fee_rate' ) );
    }

    /**
     * Restores the fee as the chosen shipping method, which WooCommerce drops from the session of carts without shippable products.
     *
     * @return void
     */
    public static function restore_chosen_virtual_fee_rate() {
        if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart || null !== WC()->session->get( 'chosen_shipping_methods' ) || ! WC()->cart->needs_shipping() ) {
            return;
        }

        $chosen = array();

        foreach ( WC()->shipping()->get_packages() as $key => $package ) {
            if ( isset( $package['rates'][ WC_Frenet::VIRTUAL_FEE_RATE_ID ] ) ) {
                $chosen[ $key ] = WC_Frenet::VIRTUAL_FEE_RATE_ID;
            }
        }

        if ( ! empty( $chosen ) ) {
            WC()->session->set( 'chosen_shipping_methods', $chosen );
        }
    }

    /**
     * @param WC_Shipping_Rate $rate
     * @return bool
     */
    public static function is_virtual_fee_rate( $rate ) {
        return $rate instanceof WC_Shipping_Rate && WC_Frenet::VIRTUAL_FEE_RATE_ID === $rate->get_id();
    }

    /**
     * @return bool
     */
    private static function cart_has_virtual_items() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }

        foreach ( WC()->cart->get_cart_contents() as $item ) {
            if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product && $item['quantity'] > 0 && $item['data']->is_virtual() ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Frenet method of the zone matching the customer's shipping address that charges the fee, if any.
     *
     * @return WC_Frenet|null
     */
    private static function get_fee_method_for_customer() {
        if ( ! WC()->customer || ! class_exists( 'WC_Shipping_Zones' ) ) {
            return null;
        }

        $destination = array(
            'country'  => WC()->customer->get_shipping_country(),
            'state'    => WC()->customer->get_shipping_state(),
            'postcode' => WC()->customer->get_shipping_postcode(),
        );
        $key = implode( '|', $destination );

        if ( ! array_key_exists( $key, self::$fee_methods ) ) {
            self::$fee_methods[ $key ] = null;
            $zone = WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => $destination ) );

            foreach ( $zone->get_shipping_methods( true ) as $method ) {
                if ( $method instanceof WC_Frenet && $method->get_virtual_fee_amount() > 0 ) {
                    self::$fee_methods[ $key ] = $method;
                    break;
                }
            }
        }

        return self::$fee_methods[ $key ];
    }
}
