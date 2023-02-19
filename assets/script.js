jQuery(document).ready( function($) {
    $("form.woocommerce-checkout").on('submit', function(event) {
        if ( $('#payment_method_bingopay_gateway').is(':checked') ) {
            event.stopImmediatePropagation();
            event.stopPropagation();
            $('#exampleModal').modal('show');
            var form = $('form[name=checkout]');
            if (form.triggerHandler('checkout_place_order')  !== false ) {
                var elem = document.getElementsByName('checkout');
                var fd = new FormData(elem[0] ?? '');
                fd.append('action', 'bignopay_3ds_form');
                fd.append('_ajax_nonce', ajax.nonce);
                fd.append('amount', currentOrderTotal);
                jQuery.ajax({
                    url: ajax.url,
                    method: "POST",
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (response) {
                        if (response.data) {
                            if (response.data.redirect_url.length != 0) {
                                $('#bingopay-3ds-window').attr('src', response.data.redirect_url);
                                $('#exampleModal').show();
                            }
                        }
                    }
                });
            }
            return false;
        }
    });
});