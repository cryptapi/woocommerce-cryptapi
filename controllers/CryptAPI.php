<?php

class WC_Gateway_CryptAPI extends WC_Payment_Gateway
{

    private $coin_options = array(
        'btc' => 'Bitcoin',
        'bch' => 'Bitcoin Cash',
        'ltc' => 'Litecoin',
        'eth' => 'Ethereum',
        'xmr' => 'Monero',
        'iota' => 'IOTA',
    );

    function __construct()
    {
        $this->id = 'cryptapi';
        $this->icon = PLUGIN_CRYPTAPI_URL . 'static/200_logo_ca.png';
        $this->has_fields = true;
        $this->method_title = 'CryptAPI';
        $this->method_description = 'CryptAPI allows customers to pay in cryptocurrency';

        $this->supports = array(
            'products',
            'tokenization',
            'add_payment_method',
        );


        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'validate_payment'));

        add_action('wp_ajax_nopriv_' . $this->id . '_order_status', array($this, 'order_status'));
        add_action('wp_ajax_' . $this->id . '_order_status', array($this, 'order_status'));

    }

    function admin_options()
    {
        parent::admin_options();
        echo "<div style='margin-top: 2rem;'>If you need any help or have any suggestion, contact us via the <b>live chat</b> on our <b><a href='https://cryptapi.io'>website</a></b></div>";
    }

    function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enabled', 'cryptapi'),
                'type' => 'checkbox',
                'label' => __('Enable CryptAPI Payments', 'cryptapi'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'cryptapi'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'cryptapi'),
                'default' => __('Crypto', 'cryptapi'),
                'desc_tip' => true,
            ),
            'coins' => array(
                'title' => __('Accepted cryptocurrencies', 'cryptapi'),
                'type' => 'multiselect',
                'default' => '',
                'css' => 'height: 10rem;',
                'options' => $this->coin_options,
                'description' => __("Select which coins do you wish to accept. CTRL + click to select multiple", 'cryptapi'),
            ),
            'btc_address' => array(
                'title' => __('Bitcoin Address', 'cryptapi'),
                'type' => 'text',
                'description' => __("Insert your Bitcoin address here. Leave blank if you want to skip this cryptocurrency", 'cryptapi'),
                'desc_tip' => true,
            ),
            'bch_address' => array(
                'title' => __('Bitcoin Cash Address', 'cryptapi'),
                'type' => 'text',
                'description' => __("Insert your Bitcoin Cash address here. Leave blank if you want to skip this cryptocurrency", 'cryptapi'),
                'desc_tip' => true,
            ),
            'ltc_address' => array(
                'title' => __('Litecoin Address', 'cryptapi'),
                'type' => 'text',
                'description' => __("Insert your Litecoin address here. Leave blank if you want to skip this cryptocurrency", 'cryptapi'),
                'desc_tip' => true,
            ),
            'eth_address' => array(
                'title' => __('Ethereum Address', 'cryptapi'),
                'type' => 'text',
                'description' => __("Insert your Ethereum address here. Leave blank if you want to skip this cryptocurrency", 'cryptapi'),
                'desc_tip' => true,
            ),
            'xmr_address' => array(
                'title' => __('Monero Address', 'cryptapi'),
                'type' => 'text',
                'description' => __("Insert your Monero address here. Leave blank if you want to skip this cryptocurrency", 'cryptapi'),
                'desc_tip' => true,
            ),
            'iota_address' => array(
                'title' => __('IOTA Address', 'cryptapi'),
                'type' => 'text',
                'description' => __("Insert your IOTA address here. Leave blank if you want to skip this cryptocurrency", 'cryptapi'),
                'desc_tip' => true,
            ),
        );
    }

    function needs_setup()
    {
        $selected = $this->get_option('coins');

        if (empty($selected) || !is_array($selected)) return true;

        foreach ($selected as $val) {
            $addr = $this->get_option($val . '_address');
            if (!empty($addr)) return false;
        }

        return true;
    }

    function payment_fields()
    {
        $selected = $this->get_option('coins'); ?>

        <div class="form-row form-row-wide">
            <ul style="list-style: none outside;">
                <?php
                if (!empty($selected) && is_array($selected)) {
                    foreach ($selected as $val) {
                        $addr = $this->get_option($val . '_address');
                        if (!empty($addr)) { ?>
                            <li>
                                <input id="payment_method_<?php echo $val ?>" type="radio" class="input-radio"
                                       name="cryptapi_coin" value="<?php echo $val ?>"/>
                                <label for="payment_method_<?php echo $val ?>" style="display: inline-block;"><?php echo __('Pay with', 'cryptapi') . ' ' . $this->coin_options[$val] ?></label>
                            </li>
                            <?php
                        }
                    }
                } ?>
            </ul>
        </div>
        <?php
    }

    function validate_fields()
    {
        return array_key_exists($_POST['cryptapi_coin'], $this->coin_options);
    }

    function process_payment($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);

        $selected = $_POST['cryptapi_coin'];
        $addr = $this->get_option($selected . '_address');

        if (!empty($addr)) {

            $nonce = $this->generate_nonce();

            $callback_url = str_replace('https:', 'http:', add_query_arg(array(
                'wc-api' => 'WC_Gateway_CryptAPI',
                'order_id' => $order_id,
                'nonce' => $nonce,
            ), home_url('/')));

            $currency = get_woocommerce_currency();
            $total = $order->get_total('edit');

            try {
                $info = CryptAPI\CryptAPI::get_info($selected);
                $min_tx = CryptAPI\CryptAPI::convert($info->minimum_transaction, $selected);

                $price = floatval($info->prices->USD);
                if (isset($info->prices->{$currency})) {
                    $price = floatval($info->prices->{$currency});
                }

                $crypto_total = $this->round_sig($total / $price, 5);

                if ($crypto_total < $min_tx) {
                    wc_add_notice(__('Payment error:', 'woocommerce') . __('Value too low, minimum is', 'cryptapi') . ' ' . $min_tx . ' ' . strtoupper($selected), 'error');
                    return null;
                }

                $ca = new CryptAPI\CryptAPI($selected, $addr, $callback_url, [], true);
                $addr_in = $ca->get_address();

                $order->add_meta_data('cryptapi_nonce', $nonce);
                $order->add_meta_data('cryptapi_address', $addr_in);
                $order->add_meta_data('cryptapi_total', $crypto_total);
                $order->add_meta_data('cryptapi_currency', $selected);
                $order->save_meta_data();

                $order->update_status('on-hold', __('Awaiting payment', 'woothemes') . ': ' . $this->coin_options[$selected]);
                $woocommerce->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );

            } catch (Exception $e) {
                wc_add_notice(__('Payment error:', 'woothemes') . 'Unknown coin', 'error');
                return;
            }
        }

        wc_add_notice(__('Payment error:', 'woocommerce') . __('Payment could not be processed, please try again', 'woocommerce'), 'error');
        return null;
    }

    function validate_payment()
    {
        $data = CryptAPI\CryptAPI::process_callback($_GET);
        $order = new WC_Order($data['order_id']);

        if ($order->is_paid() || $data['nonce'] != $order->get_meta('cryptapi_nonce')) die("*ok*");

        $value_convert = CryptAPI\CryptAPI::convert($data['value'], $data['coin']);
        $paid = floatval($order->get_meta('cryptpi_paid')) + $value_convert;

        if (!$data['pending']) {
            $order->add_meta_data('cryptapi_paid', $paid);
        }

        if ($paid >= $order->get_meta('cryptapi_total')) {
            if ($data['pending']) {
                $order->add_meta_data('cryptapi_pending', "1");
            } else {
                $order->delete_meta_data('cryptapi_pending');
                $order->payment_complete($data['txid_in']);
            }
        }

        $order->save_meta_data();
        die("*ok*");
    }

    function order_status()
    {
        $order_id = $_REQUEST['order_id'];
        $order = new WC_Order($order_id);

        $data = [
            'is_paid' => $order->is_paid(),
            'is_pending' => boolval($order->get_meta('cryptapi_pending')),
        ];

        echo json_encode($data);
        die();
    }

    function thankyou_page($order_id)
    {
        $order = new WC_Order($order_id);
        $total = $order->get_total();
        $currency_symbol = get_woocommerce_currency_symbol();
        $address_in = $order->get_meta('cryptapi_address');
        $crypto_value = $order->get_meta('cryptapi_total');
        $crypto_coin = $order->get_meta('cryptapi_currency');

        $ajax_url = add_query_arg(array(
            'action' => 'cryptapi_order_status',
            'order_id' => $order_id,
        ), home_url('/wp-admin/admin-ajax.php'));

        ?>

        <style type="text/css">
            @keyframes lds-dual-ring {
                0% {
                    -webkit-transform: rotate(0);
                    transform: rotate(0);
                }
                100% {
                    -webkit-transform: rotate(360deg);
                    transform: rotate(360deg);
                }
            }

            @-webkit-keyframes lds-dual-ring {
                0% {
                    -webkit-transform: rotate(0);
                    transform: rotate(0);
                }
                100% {
                    -webkit-transform: rotate(360deg);
                    transform: rotate(360deg);
                }
            }

            .lds-dual-ring {
                position: relative;
            }

            .lds-dual-ring div {
                box-sizing: border-box;
            }

            .lds-dual-ring > div {
                position: absolute;
                width: 174px;
                height: 174px;
                top: 13px;
                left: 13px;
                border-radius: 50%;
                border: 14px solid #000;
                border-color: #0288d1 transparent #0288d1 transparent;
                -webkit-animation: lds-dual-ring 5s linear infinite;
                animation: lds-dual-ring 5s linear infinite;
            }

            .lds-dual-ring > div:nth-child(2) {
                border-color: transparent;
            }

            .lds-dual-ring > div:nth-child(2) div {
                position: absolute;
                width: 100%;
                height: 100%;
                -webkit-transform: rotate(45deg);
                transform: rotate(45deg);
            }

            .lds-dual-ring > div:nth-child(2) div:before,
            .lds-dual-ring > div:nth-child(2) div:after {
                content: "";
                display: block;
                position: absolute;
                width: 14px;
                height: 14px;
                top: -14px;
                left: 66px;
                background: #0288d1;
                border-radius: 50%;
                box-shadow: 0 160px 0 0 #0288d1;
            }

            .lds-dual-ring > div:nth-child(2) div:after {
                left: -14px;
                top: 66px;
                box-shadow: 160px 0 0 0 #0288d1;
            }

            .lds-dual-ring {
                width: 100px !important;
                height: 100px !important;
                -webkit-transform: translate(-50px, -50px) scale(0.5) translate(50px, 50px);
                transform: translate(-50px, -50px) scale(0.5) translate(50px, 50px);
            }
        </style>

        <div class="payment-panel">
            <script src="<?php echo PLUGIN_CRYPTAPI_URL . 'static/jquery-qrcode-0.17.0.min.js' ?>"></script>
            <div class="ca_loader" style="width: 100%; text-align: center; margin-bottom: 1rem;">
                <div style="width: 100px; margin: 0 auto">
                    <div class="lds-css ng-scope">
                        <div style="width:100%;height:100%" class="lds-dual-ring">
                            <div></div>
                            <div>
                                <div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ca_check" style="width: 100%; text-align: center; display: none;">
                <img width="100" style="margin: 0 auto;" src="<?php echo PLUGIN_CRYPTAPI_URL . 'static/check.png' ?>"/>
            </div>
            <div class="payment_details" style="width: 100%; text-align: center">
                <h4><?php echo __('Waiting for payment', 'cryptapi') ?></h4>
                <div style="width: 100%; text-align: center; margin: 2rem auto;">
                    <canvas height="300" class="qrcode"></canvas>
                </div>
                <div style="width: 100%; margin: 2rem auto; text-align: center;">
                    <?php echo __('Please send', 'cryptapi') ?>
                    <span style="font-weight: 500"><?php echo $crypto_value ?></span>
                    <span style="font-weight: 500"><?php echo strtoupper($crypto_coin) ?></span>
                    (<?php echo $currency_symbol . ' ' . $total; ?>)
                    <?php echo __('to', 'cryptapi') ?>
                    <span style="font-weight: 500"><?php echo $address_in ?></span>
                </div>
            </div>
            <div class="payment_pending" style="width: 100%; text-align: center; display: none;">
                <h4><?php echo __('Your payment has been received, awaiting confirmation', 'cryptapi') ?></h4>
            </div>
            <div class="payment_complete" style="width: 100%; text-align: center; display: none;">
                <h4><?php echo __('Your payment has been confirmed!', 'cryptapi') ?></h4>
            </div>
            <script>
                function check_status() {
                    let is_paid = false;
                    let ajax_url = '<?php echo $ajax_url ?>';

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

                function fill() {
                    if (jQuery('.payment-panel').length > 1) {
                        jQuery('.payment-panel')[1].remove();
                        return;
                    }

                    check_status();

                    let ca_address = '<?php echo $address_in; ?>';
                    let ca_value = '<?php echo $crypto_value; ?>';
                    let ca_coin = '<?php echo $crypto_coin; ?>';

                    generate_qr(ca_address, ca_value, ca_coin);

                    function generate_qr(_addr, _value, _coin) {
                        let _protocols = {
                            btc: 'bitcoin',
                            bch: 'bitcoincash',
                            ltc: 'litecoin',
                            eth: 'ethereum',
                            xmr: 'monero',
                            iota: 'iota'
                        };

                        let _address = _protocols[_coin] + ":" + _addr + "?amount=" + _value;

                        let canvas = jQuery('.qrcode').get(0);
                        let context = canvas.getContext('2d');
                        context.clearRect(0, 0, canvas.width, canvas.height);

                        jQuery('.qrcode').qrcode({'label': _address, 'text': _address, 'size': 300});
                    }
                }

                fill();
            </script>
        </div>
        <?php
    }

    private function generate_nonce($len = 32)
    {
        $data = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');

        $nonce = [];
        for ($i = 0; $i < $len; $i++) {
            $nonce[] = $data[mt_rand(0, sizeof($data) - 1)];
        }

        return implode('', $nonce);
    }

    private function round_sig($number, $sigdigs = 5)
    {
        $multiplier = 1;
        while ($number < 0.1) {
            $number *= 10;
            $multiplier /= 10;
        }
        while ($number >= 1) {
            $number /= 10;
            $multiplier *= 10;
        }
        return round($number, $sigdigs) * $multiplier;
    }
}