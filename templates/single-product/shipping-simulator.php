<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$has_shipping_class = false;
$product_shipping_class = (object) array('term_id' => 0);
$shipping_classes = WC()->shipping->get_shipping_classes();
$helper = new WC_Frenet_Helper();

foreach (WC()->shipping->get_shipping_classes() as $class) {
    if ($class->slug === $product->get_shipping_class()) {
        $product_shipping_class = $class;
    }

    if ($product->get_shipping_class() === '') {
        $product_shipping_class = new stdClass();
        $product_shipping_class->term_id = 0;
    }
}

foreach ($helper->get_instance_ids() as $object) {
    $frenet = new WC_Frenet($object->instance_id);
    $instance_id = $object->instance_id;
    $class_id = $product_shipping_class->term_id;
    $frenet_class_id = $frenet->get_option('shipping_class_id') ? (int)$frenet->get_option('shipping_class_id') : -1;

    if ($class_id === $frenet_class_id || (int)$frenet_class_id === -1) {
        $has_shipping_class = true;
    }
}

if (!$has_shipping_class) return;

?>

<div id="shipping-simulator" style="<?php echo esc_attr($style); ?>"
        data-product-id="<?php echo esc_attr($product->get_id()); ?>"
        data-product-ids="<?php echo esc_attr($ids); ?>"
        data-product-type="<?php echo esc_attr($product->get_type()); ?>">
    <form method="post" class="frenet-shipping-simulator-form">

        <label for="frenet-simulator-zipcode"><?php esc_html_e('Calculate shipping', 'woo-shipping-gateway'); ?> <br>
            <input required type="text" name="zipcode" id="frenet-simulator-zipcode" maxlength="9" placeholder="00000-000"
                    value="<?php echo esc_attr($zipcode); ?>">
        </label>

        <input type="hidden" name="instance_id" value="<?php echo esc_attr($instance_id); ?>">
        <input type="hidden" name="additional_time" value="<?php echo esc_attr($additional_time); ?>">
        <input type="hidden" name="qty_simulator" value="1">
        <button name="idx-calc_shipping" id="idx-calc_shipping" value="1" class="button"><?php esc_html_e('Calculate', 'woo-shipping-gateway'); ?></button>
        <br class="clear"/>
        <br>
        <div id='loading_simulator' style='display:none'>
            <p><?php esc_html_e('Please wait...', 'woo-shipping-gateway'); ?></p>
        </div>
        <div id="simulator-data"></div>

    </form>

</div>
