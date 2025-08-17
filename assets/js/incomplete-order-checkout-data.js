jQuery(function ($) {
    function captureFields() {
        const data = {
            email: $('input[name="billing_email"]').val() || $('input[type="email"]').first().val(),
            name: $('input[name="billing_first_name"]').val() || $('input[id="shipping-first_name"]').val() || $('input[id="billing-first_name"]').val() || $('input[name="first_name"]').val(),
            phone: $('input[name="billing_phone"]').val() || $('input[type="tel"]').val(),
        };

        if (!data.email && !data.name && !data.phone) return;

        $.ajax({
            url: rs_checkout_capture.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rs_capture_checkout_data',
                nonce: rs_checkout_capture.nonce,
                ...data
            },
            success: function (response) {
                console.log('Success:', response);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                console.log(jqXHR.responseText);
            }
        });
    }

    // Capture once on page load
    captureFields();

    // Retry once after 2 seconds in case fields load late
    setTimeout(captureFields, 2000);

    // Also capture on any input change
    $('body').on('change', 'form input', function () {
        captureFields();
    });
});
