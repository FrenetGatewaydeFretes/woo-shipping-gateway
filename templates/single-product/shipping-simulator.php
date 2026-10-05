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
            <span class="frenet-simulator-zipcode-field">
                <svg class="frenet-simulator-zipcode-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
                <input required type="text" name="zipcode" id="frenet-simulator-zipcode" class="input-text" maxlength="9" inputmode="numeric" autocomplete="postal-code"
                        placeholder="<?php esc_attr_e('Enter your postcode', 'woo-shipping-gateway'); ?>"
                        value="<?php echo esc_attr($zipcode); ?>">
            </span>
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
