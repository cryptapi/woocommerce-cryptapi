function check_status(ajax_url) {
    let is_paid = false;

    function status_loop() {
        if (is_paid) return;

        jQuery.getJSON(ajax_url, function (data) {
            let waiting_payment = jQuery('.waiting_payment');
            let waiting_network = jQuery('.waiting_network');
            let payment_done = jQuery('.payment_done');

            if (data.is_pending) {
                waiting_payment.addClass('done');
                waiting_network.addClass('done');
            }

            if (data.is_paid) {
                waiting_payment.addClass('done');
                waiting_network.addClass('done');
                payment_done.addClass('done');
                jQuery('.ca_loader').hide();

                setTimeout(function () {
                    jQuery('.ca_payments_wrapper').slideToggle('400');
                    jQuery('.ca_payment_confirmed').slideToggle('400');
                }, 5000);

                is_paid = true;
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