/**
 * operations helpers for simulator
*/
var simulatorHelper = {

    /**
     * clean old data showed page by the simulator
     */
    simulatorClean: function () {
        jQuery('#shipping-simulator #simulator-data').empty();
    },

    variationId: '',

    field: function (name) {
        return jQuery('#shipping-simulator [name="' + name + '"]');
    },

    xhr: null,

    showMessage: function (text) {
        var message = document.createElement('p');

        message.innerText = text;
        jQuery('#shipping-simulator #simulator-data').empty().append(message);
    },

    deliveryTimeText: function (days) {
        var message = 1 === days ? shipping_simulator.delivery_time_singular : shipping_simulator.delivery_time_plural;

        return message.replace('%d', days);
    },

    isSimulatedProductForm: function ($form) {
        var productId = jQuery('#shipping-simulator').data('product-id');

        return !productId || String($form.data('product_id')) === String(productId);
    },

    isShippable: function (variation) {
        return variation.variation_is_visible !== false
            && variation.is_purchasable !== false
            && variation.is_in_stock !== false
            && variation.is_virtual !== true;
    },

    setVariation: function (variation) {
        var variationId = variation && variation.variation_id ? String(variation.variation_id) : '';

        if (variationId !== this.variationId) {
            this.simulatorClean();
        }
        this.variationId = variationId && this.isShippable(variation) ? variationId : '';

        if (this.variationId) {
            jQuery('#shipping-simulator').slideDown(200);
        } else {
            jQuery('#shipping-simulator').hide();
        }
    },

    productForm: function () {
        var productId = jQuery('#shipping-simulator').data('product-id');

        return productId ? jQuery('form').has('[name="add-to-cart"][value="' + productId + '"]').first() : jQuery();
    },

    getQuantity: function () {
        var quantity = parseFloat(this.productForm().find('input[name="quantity"]').first().val());

        return quantity > 0 ? quantity : (this.field('qty_simulator').val() || 1);
    },

    /**
     * product ids are depends with product type, now same mode for getting product ids in quotation will be applied in page load
     */
    getProductIds: function() {
        var product_id;
        var simulator = jQuery('#shipping-simulator');
        var type = simulator.data('product-type');

        product_id = simulator.data('product-ids');
        if ('variable' === type) {
            product_id = simulator.data('product-id') || jQuery('input[name="product_id"]').val();
        }

        // avoid error caused for product ids not found
        if (!product_id) product_id = "";

        return product_id;
    }
};

/* global shipping_simulator */
jQuery(document).ready(function ($) {

    jQuery(document).on('found_variation', '.variations_form', function (event, variation) {
        if (simulatorHelper.isSimulatedProductForm(jQuery(this))) {
            simulatorHelper.setVariation(variation);
        }
    });

    jQuery(document).on('reset_data', '.variations_form', function () {
        if (simulatorHelper.isSimulatedProductForm(jQuery(this))) {
            simulatorHelper.setVariation(null);
        }
    });

    jQuery('.variations_form').each(function () {
        var $form = jQuery(this);
        var variationId = $form.find('input[name="variation_id"]').val();

        if (variationId && '0' !== variationId && simulatorHelper.isSimulatedProductForm($form)) {
            simulatorHelper.setVariation({ variation_id: variationId });
        }
    });

    jQuery('#shipping-simulator').on('submit', 'form', function (e) {

        e.preventDefault();

        var zipcodeInput = simulatorHelper.field('zipcode');
        var zipcode = zipcodeInput.val().trim();

        if (!zipcode) {
            zipcodeInput[0].reportValidity();
            return;
        }

        if (simulatorHelper.xhr) {
            simulatorHelper.xhr.abort();
        }

        jQuery('#shipping-simulator #loading_simulator').show();
        simulatorHelper.simulatorClean();

        var simulator = jQuery('#shipping-simulator');
        var content = jQuery('#shipping-simulator #simulator-data');

        var type = simulator.data('product-type');
        var additional_time = simulatorHelper.field('additional_time').val();
        var instance_id = simulatorHelper.field('instance_id').val();
        var variation_id = simulatorHelper.variationId;
        var quantity = simulatorHelper.getQuantity();
        var product_id = simulatorHelper.getProductIds();

        if (!variation_id) {
            variation_id = product_id;
        }

        if (!additional_time) {
            additional_time = 0;
        } else {
            additional_time = parseInt(additional_time, 10);
        }

        /*
        console.log('ID do produto: ' + product_id);
        console.log('ID da variacão (se for variavel): ' + variation_id);
        console.log('CEP: ' + zipcode);
        console.log('Additional Time: ' + additional_time);
        */

        simulatorHelper.xhr = jQuery.ajax({
            type: 'POST',
            url: shipping_simulator.ajax_url,
            dataType: 'json',
            data: {
                action: 'ajax_simulator',
                type: type,
                zipcode: zipcode,
                product_id: product_id,
                variation_id: variation_id,
                instance_id: instance_id,
                additional_time: additional_time,
                quantity: quantity
            },
            error: function (xhr, status) {
                if ('abort' === status) {
                    return;
                }

                jQuery('#shipping-simulator #loading_simulator').hide();
                simulatorHelper.showMessage(429 === xhr.status ? shipping_simulator.rate_limit_message : shipping_simulator.error_message);
            },
            complete: function (xhr) {
                if (simulatorHelper.xhr === xhr) {
                    simulatorHelper.xhr = null;
                }
            },
            success: function (response) {

                jQuery('#shipping-simulator #loading_simulator').hide();

                if ('variable' === type && variation_id !== simulatorHelper.variationId) {
                    return;
                }

                const shippingDiv = document.createElement('div');

                if (jQuery.isEmptyObject(response)) {
                    const shippingErrorMessage = document.createElement('p');

                    shippingErrorMessage.innerText = shipping_simulator.error_message;
                    shippingDiv.appendChild(shippingErrorMessage)
                } else {

                    const shippingHeader = document.createElement('div');
                    shippingHeader.classList.add('frenet-quote-header');

                    const headerService = document.createElement('span');
                    headerService.classList.add('frenet-quote-col-service');
                    headerService.innerText = shipping_simulator.shipping_label;

                    const headerPrice = document.createElement('span');
                    headerPrice.classList.add('frenet-quote-col-price');
                    headerPrice.innerText = shipping_simulator.cost_label;

                    shippingHeader.appendChild(headerService);
                    shippingHeader.appendChild(headerPrice);

                    const shippingUl = document.createElement('ul');
                    shippingUl.setAttribute('id', 'shipping-rates');

                    jQuery.each(response, function (key, value) {
                        if (value.ServiceDescription !== undefined) {
                            let EstimatingDelivery = parseInt(value.DeliveryTime, 10) + parseInt(additional_time, 10);

                            const shippingLi = document.createElement('li');
                            shippingLi.classList.add('li-frenet');

                            const serviceCol = document.createElement('div');
                            serviceCol.classList.add('frenet-quote-service');

                            const shippingSpan = document.createElement('span');
                            shippingSpan.classList.add('span-frenet');
                            shippingSpan.innerText = value.ServiceDescription;
                            serviceCol.appendChild(shippingSpan);

                            if (response.display_date === true) {
                                const deliverySpan = document.createElement('span');
                                deliverySpan.classList.add('frenet-quote-delivery');
                                deliverySpan.innerText = simulatorHelper.deliveryTimeText(EstimatingDelivery);
                                serviceCol.appendChild(deliverySpan);
                            }

                            const priceSpan = document.createElement('span');
                            priceSpan.classList.add('frenet-quote-price');
                            priceSpan.innerText = value.ShippingPriceFormatted;

                            shippingLi.appendChild(serviceCol);
                            shippingLi.appendChild(priceSpan);

                            shippingUl.appendChild(shippingLi);
                        }
                    });

                    shippingDiv.appendChild(shippingHeader);
                    shippingDiv.appendChild(shippingUl);
                }

                content.prepend(shippingDiv);
            }
        });

    });

});
