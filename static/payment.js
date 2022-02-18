function check_status(ajax_url) {
    let is_paid = false;

    function status_loop() {
        if (is_paid) return;

        jQuery.getJSON(ajax_url, function (data) {
            let waiting_payment = jQuery('.waiting_payment');
            let waiting_network = jQuery('.waiting_network');
            let payment_done = jQuery('.payment_done');

            if (data.cryptapi_cancelled === '1') {
                jQuery('.ca_loader').slideUp();
                jQuery('.ca_payments_wrapper').slideUp('400');
                jQuery('.ca_payment_cancelled').slideUp('400');
                jQuery('.ca_progress').slideUp('400');
                is_paid = true;
            }

            if (data.is_pending) {
                waiting_payment.addClass('done');
                waiting_network.addClass('done');
                jQuery('.ca_loader').slideUp();
                jQuery('.ca_notification_refresh').remove();

                setTimeout(function () {
                    jQuery('.ca_payments_wrapper').slideUp('400');
                    jQuery('.ca_payment_processing').slideDown('400');
                }, 5000);
            }

            if (data.is_paid) {
                waiting_payment.addClass('done');
                waiting_network.addClass('done');
                payment_done.addClass('done');

                setTimeout(function () {
                    jQuery('.ca_payment_processing').slideUp('400');
                    jQuery('.ca_payment_confirmed').slideDown('400');
                }, 5000);

                is_paid = true;
            }

            if (data.crypto_total) {
                jQuery('.ca_value').html(data.crypto_total);
                jQuery('.ca_copy.ca_details_copy').attr('data-tocopy', data.crypto_total);
            }

            if (data.cryptapi_qr_code_value) {
                jQuery('.ca_qrcode.value').attr("src", "data:image/png;base64," + data.cryptapi_qr_code_value);


            }
        });

        setTimeout(status_loop, 5000);
    }

    status_loop();
}

function copyToClipboard(text) {
    if (window.clipboardData && window.clipboardData.setData) {
        // IE specific code path to prevent textarea being shown while dialog is visible.
        return clipboardData.setData("Text", text);

    } else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
        var textarea = document.createElement("textarea");
        textarea.textContent = text;
        textarea.style.position = "fixed";  // Prevent scrolling to bottom of page in MS Edge.
        document.body.appendChild(textarea);
        textarea.select();
        try {
            return document.execCommand("copy");  // Security exception may be thrown by some browsers.
        } catch (ex) {
            console.warn("Copy to clipboard failed.", ex);
            return false;
        } finally {
            document.body.removeChild(textarea);
        }
    }
}

jQuery(function ($) {
    $('.ca_qrcode_btn').on('click', function () {
        $('.ca_qrcode_btn').removeClass('active')
        $(this).addClass('active');

        if ($(this).hasClass('no_value')) {
            $('.ca_qrcode.no_value').show();
            $('.ca_qrcode.value').hide();
        } else {
            $('.ca_qrcode.value').show();
            $('.ca_qrcode.no_value').hide();
        }
    });

    $('.ca_show_qr').on('click', function (e) {
        e.preventDefault();

        if ($(this).hasClass('active')) {
            $('.ca_qrcode_wrapper').slideToggle(500);
            $(this).removeClass('active');
        } else {
            $('.ca_qrcode_wrapper').slideToggle(500);
            $(this).addClass('active');
        }
    });

    $('.ca_copy').on('click', function () {
        copyToClipboard($(this).attr('data-tocopy'));
        let tip = $(this).find('.ca_tooltip.tip');
        let success = $(this).find('.ca_tooltip.success');

        success.show();
        tip.hide();

        setTimeout(function () {
            success.hide();
            tip.show();
        }, 5000);
    })
})