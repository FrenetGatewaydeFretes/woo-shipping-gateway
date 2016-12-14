/* global shipping_simulator */
jQuery(document).ready(function ($) {
    jQuery(document).on('change', '.quantity .qty', function () {
        jQuery('.qty_simulator').attr('value', jQuery(this).val());
    });

    function simulatorClean() {
        $('#shipping-simulator #simulator-data').empty();
    }

    $('body').on('show_variation', function () {
        var ids = $('#shipping-simulator').data('product-ids').toString().split(','),
            variation_id = $('.cart input[name="variation_id"]').val().toString();

        if (-1 < $.inArray(variation_id, ids)) {
            $('#shipping-simulator').slideDown(200);
        }

        simulatorClean();
    });

    $('#shipping-simulator').on('click', '.button', function (e) {
        $('#loading_simulator').show();
        simulatorClean();
        e.preventDefault();
        var simulator = $('#shipping-simulator'),
            content = $('#shipping-simulator #simulator-data'),
            type = simulator.data('product-type');

        if ('simple' == type)
            product_id = simulator.data('product-ids');
        else
            product_id = $('input[name="product_id"]').val();

        additional_time = $('#additional_time').val();
        additional_time = parseInt(additional_time, 10);

        /*
        console.log('ID do produto: ' + product_id);
        console.log('ID da variacão (se for variavel): ' + $('.cart input[name="variation_id"]').val());
        console.log('CEP: ' + $('#shipping-simulator #zipcode').val());
        console.log('Additional Time: ' + additional_time);
        */

        $.ajax({
            type: 'POST',
            url: shipping_simulator.ajax_url,
            data: {
                action: 'ajax_simulator',
                zipcode: $('#shipping-simulator #zipcode').val(),
                product_id: product_id,
                variation_id: product_id,
                instance_id: $('#instance_id').val(),
                additional_time: $('#additional_time').val(),
                quantity: $('#qty_simulator').val()
            },
            success: function (response) {
                $('#loading_simulator').hide();
                var shipping = '<div>';
                console.log(response);
                response = jQuery.parseJSON(response);
                if (jQuery.isEmptyObject(response)) {
                    console.log('vazio: ' + response);
                    shipping += '<p>Não foi possível simular o frete, por favor tente adicionar o produto ao carrinho e prossiga para tentar obter o valor.</p>';
                }
                /*
                if (response.data.weight == undefined || response.data.weight == "") {
                    console.log('sem peso: ' + response);
                    shipping += '<p>Não foi possível simular o frete, por favor tente adicionar o produto ao carrinho e prossiga para tentar obter o valor.</p>';
                }
                */
                else {
                    shipping += '<ul id="shipping-rates">';
                    $.each(response, function (key, value) {
                        if (value.ServiceDescription !== undefined) {
                            var EstimatingDelivery = parseInt(value.DeliveryTime, 10) + parseInt(additional_time, 10);
                            console.log(EstimatingDelivery);
                            shipping += '<li class="li-frenet"><span class="span-frenet">' + value.ServiceDescription + '</span>: ' + value.ShippingPrice + ' (Entrega em ' + EstimatingDelivery + ' dias úteis)</li>';
                        }
                    });
                    shipping += '</ul>';
                }

                shipping += '</div>';
                content.prepend(shipping);
            },
            error: function () {
                console.log('error');
            }
        });

    });

});