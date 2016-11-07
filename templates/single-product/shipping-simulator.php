<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
global $wpdb;
//get enable instance_id to identify method shipping;
$instance_id = $wpdb->get_results($wpdb->prepare("SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE is_enabled = %d AND method_id = %s ", 1, 'frenet'));
$instance_id = $instance_id[0]->instance_id;
$Options = get_option('woocommerce_frenet_' . $instance_id . '_settings');
$additional_time = $Options['additional_time'];
if ($Options['simulator'] === 'yes') {
    ?>

    <div id="shipping-simulator" style="<?php echo esc_attr($style); ?>"
         data-product-ids="<?php echo esc_attr($ids); ?>"
         data-product-type="<?php echo esc_attr($product->product_type); ?>">
        <form method="post" class="cart">

            <label for="shipping">Calcular Frete <br>
                <?php
                $current_user = get_current_user_id();
                $zipcode = get_user_meta($current_user, 'shipping_postcode', true);
                ?>
                <input required type="text" name="zipcode" id="zipcode" placeholder="000.000-00"
                       value="<?php echo $zipcode; ?>">
            </label>

            <input type="hidden" name="instance_id" id="instance_id" value="<?php echo $instance_id; ?>">
            <input type="hidden" name="additional_time" id="additional_time" value="<?php echo $additional_time; ?>">
            <input type="hidden" name="qty_simulator" id="qty_simulator" class="qty_simulator" value="1">
            <button name="idx-calc_shipping" id="idx-calc_shipping" value="1" class="button">Ok</button>
            <br class="clear"/>
            <br>
            <div id='loading_simulator' style='display:none'>
                <p>Aguarde...</p>
            </div>
            <div id="simulator-data"></div>
            <!--display data -->


        </form>

    </div>
<?php } else {
    return;
} ?>