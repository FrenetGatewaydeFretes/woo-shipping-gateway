<?php

/**
 * WC_Frenet_Helper class.
 */
class WC_Frenet_Helper
{

    /**
     * Retrieve frenet instance ID
     * @return int
     */
    public function get_instance_id(){
        global $wpdb;

        //get enable instance_id to identify method shipping;
        $instance_id = $wpdb->get_results($wpdb->prepare("SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE is_enabled = %d AND method_id = %s ", 1, 'frenet'));
        if (!empty($instance_id) && isset($instance_id[0]->instance_id)) {
            $instance_id = $instance_id[0]->instance_id;
        } else {
            $instance_id = null;
        }

        return $instance_id;
    }

    public function get_instance_ids(){
        global $wpdb;

        //get enable instance_id to identify method shipping;
        $instance_ids = $wpdb->get_results($wpdb->prepare("SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE is_enabled = %d AND method_id = %s ", 1, 'frenet'));

        if ($instance_ids && is_array($instance_ids)) {
            return $instance_ids;
        }

        return false;
    }

    /**
     * Retrieve shipping options
     * @return array
     */
    public function get_options(){

        $instance_id = $this->get_instance_id();
        $options = get_option('woocommerce_frenet_' . $instance_id . '_settings');

        return $options;
    }

    /**
     * Return if simulator is enabled
     * @return boolean
     */
    public function is_simulator_enabled(){

        $options = $this->get_options();

        if (is_array($options) && isset($options['simulator']) && $options['simulator'] === 'yes') {
            return true;
        }

        return false;
    }

    /**
     * Whether Correios Automático (Infixs) already shows its shipping calculator on the product page.
     * That calculator quotes every method of the shipping zone, Frenet included, so the Frenet simulator
     * would only print a second box with the same rates (reported by Frenet QA, 2026-11-04).
     * Filter "woocommerce_frenet_simulator_yields" to show both anyway.
     *
     * @return bool
     */
    public static function other_product_calculator() {
        $on = false;
        if ( defined( 'INFIXS_CORREIOS_AUTOMATICO_PLUGIN_NAME' ) ) {
            $settings = get_option( 'infixs_correios_automatico_settings', array() );
            // Correios Automático shows it unless it was turned off ("yes" by default).
            $value = is_array( $settings ) && isset( $settings['general']['calculate_shipping_product_page'] ) ? $settings['general']['calculate_shipping_product_page'] : 'yes';
            $on    = function_exists( 'wc_string_to_bool' ) ? wc_string_to_bool( $value ) : in_array( $value, array( true, 'yes', '1', 1 ), true );
        }
        return (bool) apply_filters( 'woocommerce_frenet_simulator_yields', $on );
    }

}
