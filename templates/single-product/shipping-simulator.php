<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$has_shipping_class = false;
$product_shipping_class = false;
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
        data-product-ids="<?php echo esc_attr($ids); ?>"
        data-product-type="<?php echo esc_attr($product->get_type()); ?>">
    <form method="post" class="cart">

        <label for="shipping">Calcular Frete <br>
            <input required type="text" name="zipcode" id="zipcode" maxlength="9" placeholder="00000-000"
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
