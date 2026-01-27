jQuery(document).ready(function($) {
    alert('KEC admin script loaded.');

    $('#woocommerce_klarna_payments_kec_flow').on('change', function() {

        if ( 'one_step' === $(this).val() ) {
            $('.kec-webhook-section').css('display', 'flex').show();
            return;
        }

        $('.kec-webhook-section').hide();
    });
});
