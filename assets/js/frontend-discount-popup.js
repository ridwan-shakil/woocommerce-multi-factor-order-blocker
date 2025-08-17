jQuery(document).ready(function ($) {
    let popupShown = false;

    const currentURL = window.location.href.toLowerCase();

    function isMatchingPage(urlParts = []) {
        return urlParts.some(part => currentURL.includes(part));
    }

    const isCartPage = isMatchingPage(['/cart']);
    const isCheckoutPage = isMatchingPage(['/checkout', 'step-checkout', 'cartflows_step']) || $('body').hasClass('cartflows-step');

    if (!isCartPage && !isCheckoutPage) return;
    if (sessionStorage.getItem('rs_coupon_popup_shown') === 'yes') return;

    function showPopup() {
        if (!popupShown) {
            $('#rs-popup-wrapper').fadeIn(200);
            sessionStorage.setItem('rs_coupon_popup_shown', 'yes');
            popupShown = true;
        }
    }

    $(document).on('mouseleave', function (e) {
        if (e.clientY < 50) {
            showPopup();
        }
    });

    $(document).on('click', '.rs-copy-btn', function () {
        const code = $('.rs-popup-code').text().trim();

        if (navigator.clipboard) {
            navigator.clipboard.writeText(code);
        } else {
            const temp = $('<input>');
            $('body').append(temp);
            temp.val(code).select();
            document.execCommand('copy');
            temp.remove();
        }

        $('#rs-popup-wrapper').fadeOut(200);
    });
});
