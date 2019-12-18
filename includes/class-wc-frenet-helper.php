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
        $instance_id = $instance_id[0]->instance_id;

        return $instance_id;
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

        if ($options['simulator'] === 'yes') {
            return true;
        }

        return false;
    }

}