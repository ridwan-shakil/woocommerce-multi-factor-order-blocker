; (function ($) {
    $(document).ready(function () {

        $(document).on('click', '.rs-close-btn', function (e) {
            e.preventDefault();
            $('.woocommerce-error, .rs-block-msg').fadeOut();
        });
    });
})(jQuery);