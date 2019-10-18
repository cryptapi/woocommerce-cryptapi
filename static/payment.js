function check_status(ajax_url) {
    let is_paid = false;

    function status_loop() {
        if (is_paid) return;

        jQuery.getJSON(ajax_url, function (data) {
            if (data.is_pending) {
                jQuery('.payment_details,.payment_complete').hide(200);
                jQuery('.payment_pending,.ca_loader').show(200);
            }

            if (data.is_paid) {
                jQuery('.ca_loader,.payment_pending,.payment_details').hide(200);
                jQuery('.payment_complete,.ca_check').show(200);

                is_paid = true;
            }
        });

        setTimeout(status_loop, 5000);
    }

    status_loop();
}

function fill(_addr, _value, _coin) {
    let _protocols = {
        btc: 'bitcoin:',
        bch: 'bitcoincash:',
        ltc: 'litecoin:',
        eth: 'ethereum:',
        xmr: 'monero:',
        iota: 'iota:'
    };

    let _keys = {
        btc: 'amount',
        bch: 'amount',
        ltc: 'amount',
        eth: 'value',
        xmr: 'tx_amount',
        iota: 'amount'
    };

    let _address;

    if (_addr.startsWith('bitcoincash:'))
        _address = _addr;
    else
        _address = _protocols[_coin] + _addr;

    let params = {};
    params[_keys[_coin]] = _value;

    _address += "?" + jQuery.param(params);

    let canvas = jQuery('.qrcode').get(0);
    let context = canvas.getContext('2d');
    context.clearRect(0, 0, canvas.width, canvas.height);

    jQuery('.qrcode').qrcode({'label': _address, 'text': _address, 'size': 300});
}