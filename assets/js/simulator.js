/* global shipping_simulator */
jQuery(document).ready(function ($) {

    $(document).on('change', '.quantity .qty', function () {
        $('.qty_simulator').attr('value', $(this).val());
    });

    function simulatorClean() {
        $('#shipping-simulator #simulator-data').empty();
    }

    $('body').on('show_variation', function () {

        var ids = $('#shipping-simulator').data('product-ids').toString().split(',');
        var variation_id = $('.cart input[name="variation_id"]').val();

        if (-1 < $.inArray(variation_id, ids)) {
            $('#shipping-simulator').slideDown(200);
        }

        simulatorClean();
    });

    $('#shipping-simulator').on('click', '.button', function (e) {

        e.preventDefault();

        $('#loading_simulator').show();
        simulatorClean();

        var simulator = $('#shipping-simulator');
        var content = $('#shipping-simulator #simulator-data');

        var type = simulator.data('product-type');
        var zipcode = $('#shipping-simulator #zipcode').val().trim(' ');
        var additional_time = $('#additional_time').val();
        var instance_id = $('#instance_id').val();
        var variation_id = $('.cart input[name="variation_id"]').val();
        var quantity = $('#qty_simulator').val();
        var product_id;

        if ('simple' == type) {
            product_id = simulator.data('product-ids');
        } else {
            product_id = $('input[name="product_id"]').val();
        }

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

        $.ajax({
            type: 'POST',
            url: shipping_simulator.ajax_url,
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
            success: function (response) {

                response = $.parseJSON(response);
                $('#loading_simulator').hide();

                var shipping = '<div>';

                if ($.isEmptyObject(response)) {
                    shipping += '<p>Não foi possível simular o frete, por favor tente adicionar o produto ao carrinho e prossiga para tentar obter o valor.</p>';
                } else {

                    // if (response.data.weight == undefined || response.data.weight == "") {
                    // shipping += '<p>Não foi possível simular o frete, por favor tente adicionar o produto ao carrinho e prossiga para tentar obter o valor.</p>';
                    // }else {

                    shipping += '<ul id="shipping-rates">';
                    $.each(response, function (key, value) {
                        if (value.ServiceDescription !== undefined) {
                            var EstimatingDelivery = parseInt(value.DeliveryTime, 10) + parseInt(additional_time, 10);
                            shipping += '<li class="li-frenet"><span class="span-frenet">' + value.ServiceDescription + '</span>: R$ ' + value.ShippingPrice + ' (Entrega em ' + EstimatingDelivery + ' dias úteis)</li>';
                        }
                    });
                    shipping += '</ul>';

                    // }

                }

                shipping += '</div>';
                content.prepend(shipping);
            }
        });

    });

});