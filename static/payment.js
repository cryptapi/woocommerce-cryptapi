function check_status(ajax_url) {
    let is_paid = false;

    function status_loop() {
        if (is_paid) return;

        jQuery.getJSON(ajax_url, function (data) {
            let waiting_payment = jQuery('.waiting_payment');
            let waiting_network = jQuery('.waiting_network');
            let payment_done = jQuery('.payment_done');

            jQuery('.ca_value').html(data.remaining);
            jQuery('.ca_fiat_total').html(data.fiat_remaining);
            jQuery('.ca_copy.ca_details_copy').attr('data-tocopy', data.remaining);

            if (data.cancelled === 1) {
                jQuery('.ca_loader').remove();
                jQuery('.ca_payments_wrapper').slideUp('200');
                jQuery('.ca_payment_cancelled').slideDown('200');
                jQuery('.ca_progress').slideUp('200');
                is_paid = true;
            }

            if (data.is_pending === 1) {
                waiting_payment.addClass('done');
                waiting_network.addClass('done');
                jQuery('.ca_loader').remove();
                jQuery('.ca_notification_refresh').remove();
                jQuery('.ca_notification_cancel').remove();

                setTimeout(function () {
                    jQuery('.ca_payments_wrapper').slideUp('200');
                    jQuery('.ca_payment_processing').slideDown('200');
                }, 300);
            }

            if (data.is_paid) {
                waiting_payment.addClass('done');
                waiting_network.addClass('done');
                payment_done.addClass('done');
                jQuery('.ca_loader').remove();
                jQuery('.ca_notification_refresh').remove();
                jQuery('.ca_notification_cancel').remove();

                setTimeout(function () {
                    jQuery('.ca_payments_wrapper').slideUp('200');
                    jQuery('.ca_payment_processing').slideUp('200');
                    jQuery('.ca_payment_confirmed').slideDown('200');
                }, 300);

                is_paid = true;
            }

            if (data.qr_code_value) {
                jQuery('.ca_qrcode.value').attr("src", "data:image/png;base64," + data.qr_code_value);
            }

            if (data.show_min_fee === 1) {
                jQuery('.ca_notification_remaining').show();
            } else {
                jQuery('.ca_notification_remaining').hide();
            }

            if (data.hide_refresh === 1) {
                jQuery('.ca_time_refresh').hide();
            } else {
                jQuery('.ca_time_refresh').show();
            }

            if (data.remaining !== data.crypto_total) {
                jQuery('.ca_notification_payment_received').show();
                jQuery('.ca_notification_cancel').remove();
                jQuery('.ca_notification_ammount').html(data.already_paid + ' ' + data.coin + ' (<strong>' + data.already_paid_fiat + ' ' + data.fiat_symbol + '<strong>)');
            }

            if (data.order_history) {
                let history = data.order_history;

                if (jQuery('.ca_history_fill tr').length < Object.entries(history).length + 1) {
                    jQuery('.ca_history').show();

                    jQuery('.ca_history_fill td:not(.ca_history_header)').remove();

                    Object.entries(history).forEach(([key, value]) => {
                        let time = new Date(value.timestamp * 1000).toLocaleTimeString(document.documentElement.lang);
                        let date = new Date(value.timestamp * 1000).toLocaleDateString(document.documentElement.lang);

                        jQuery('.ca_history_fill').append(
                            '<tr>' +
                            '<td>' + time + '<span class="ca_history_date">' + date + '</span></td>' +
                            '<td>' + value.value_paid + ' ' + data.coin + '</td>' +
                            '<td><strong>' + value.value_paid_fiat + ' ' + data.fiat_symbol + '</strong></td>' +
                            '</tr>'
                        )
                    });
                }
            }

            if (jQuery('.ca_time_refresh')[0]) {
                var timer = jQuery('.ca_time_seconds_count');

                if (timer.attr('data-seconds') <= 0) {
                    timer.attr('data-seconds', data.counter);
                }
            }
        });

        setTimeout(status_loop, 2000);
    }

    status_loop();
}

function copyToClipboard(text) {
    if (window.clipboardData && window.clipboardData.setData) {
        return clipboardData.setData("Text", text);

    } else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
        var textarea = document.createElement("textarea");
        textarea.textContent = text;
        textarea.style.position = "fixed";
        document.body.appendChild(textarea);
        textarea.select();
        try {
            return document.execCommand("copy");
        } catch (ex) {
            console.warn("Copy to clipboard failed.", ex);
            return false;
        } finally {
            document.body.removeChild(textarea);
        }
    }
}

jQuery(function ($) {

    if ($('.ca_time_refresh')[0] || $('.ca_notification_cancel')[0]) {
        setInterval(function () {

            if ($('.ca_time_refresh')[0]) {
                var refresh_time_span = $('.ca_time_seconds_count'),
                    refresh_time = refresh_time_span.attr('data-seconds') - 1;

                if (refresh_time <= 0) {
                    refresh_time_span.html('00:00');
                    refresh_time_span.attr('data-seconds', 0);
                    return;
                } else if (refresh_time <= 30) {
                    refresh_time_span.html(refresh_time_span.attr('data-soon'));
                }

                var refresh_minutes = Math.floor(refresh_time % 3600 / 60).toString().padStart(2, '0'),
                    refresh_seconds = Math.floor(refresh_time % 60).toString().padStart(2, '0');

                refresh_time_span.html(refresh_minutes + ':' + refresh_seconds);

                refresh_time_span.attr('data-seconds', refresh_time);
            }

            var ca_notification_cancel = $('.ca_notification_cancel');

            if (ca_notification_cancel[0]) {
                var cancel_time_span = $('.ca_cancel_timer'),
                    cancel_time = cancel_time_span.attr('data-timestamp') - 1;

                if (cancel_time <= 0) {
                    cancel_time_span.attr('data-timestamp', 0);
                    return;
                }

                var cancel_hours = Math.floor(cancel_time / 3600).toString().padStart(2, '0'),
                    cancel_minutes = Math.floor(cancel_time % 3600 / 60).toString().padStart(2, '0');

                if (cancel_time <= 60) {
                    ca_notification_cancel.html('<strong>' + ca_notification_cancel.attr('data-text') + '</strong>');
                } else {
                    cancel_time_span.html(cancel_hours + ':' + cancel_minutes);

                }
                cancel_time_span.attr('data-timestamp', cancel_time);
            }
        }, 1000);
    }


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

        let qr_code_close_text = $('.ca_show_qr_close');
        let qr_code_open_text = $('.ca_show_qr_open');

        if ($(this).hasClass('active')) {
            $('.ca_qrcode_wrapper').slideToggle(500);
            $(this).removeClass('active');
            qr_code_close_text.addClass('active');
            qr_code_open_text.removeClass('active');

        } else {
            $('.ca_qrcode_wrapper').slideToggle(500);
            $(this).addClass('active');
            qr_code_close_text.removeClass('active');
            qr_code_open_text.addClass('active');
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