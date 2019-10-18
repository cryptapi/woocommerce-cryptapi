<?php

class WC_CryptAPI_Gateway extends WC_Payment_Gateway
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
        $this->icon = CRYPTAPI_PLUGIN_URL . 'static/200_logo_ca.png';
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
        $this->ca_settings();

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

    private function ca_settings() {
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->coins = $this->get_option('coins');

        foreach(array_keys($this->coin_options) as $coin) {
            $this->{$coin . '_address'} = $this->get_option($coin . '_address');
        }
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
            'description' 	=> array(
                'title'       	=> __('Description', 'cryptapi'),
                'type'        	=> 'textarea',
                'default'     	=> __('Pay with cryptocurrency (BTC, BCH, LTC, ETH, XMR and IOTA)', 'cryptapi'),
                'description' 	=> __('Payment method description that the customer will see on your checkout', 'cryptapi' )
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
        if (empty($this->coins) || !is_array($this->coins)) return true;

        foreach ($this->coins as $val) {
            if (!empty($this->{$val . '_address'})) return false;
        }

        return true;
    }

    function payment_fields()
    { ?>

        <div class="form-row form-row-wide">
            <ul style="list-style: none outside;">
                <?php
                if (!empty($this->coins) && is_array($this->coins)) {
                    foreach ($this->coins as $val) {
                        $addr = $this->{$val . '_address'};
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
        return array_key_exists(sanitize_text_field($_POST['cryptapi_coin']), $this->coin_options);
    }

    function process_payment($order_id)
    {
        global $woocommerce;

        $selected = sanitize_text_field($_POST['cryptapi_coin']);
        $addr = $this->{$selected . '_address'};

        if (!empty($addr)) {

            $nonce = $this->generate_nonce();

            $callback_url = str_replace('https:', 'http:', add_query_arg(array(
                'wc-api' => 'WC_Gateway_CryptAPI',
                'order_id' => $order_id,
                'nonce' => $nonce,
            ), home_url('/')));


            try {
                $order = new WC_Order($order_id);
                $total = $order->get_total('edit');
                $currency = get_woocommerce_currency();

                $info = CryptAPI\Helper::get_info($selected);
                $min_tx = CryptAPI\Helper::convert_div($info->minimum_transaction, $selected);

                $price = floatval($info->prices->USD);
                if (isset($info->prices->{$currency})) {
                    $price = floatval($info->prices->{$currency});
                }

                $crypto_total = $this->round_sig($total / $price, 5);

                if ($crypto_total < $min_tx) {
                    wc_add_notice(__('Payment error:', 'woocommerce') . __('Value too low, minimum is', 'cryptapi') . ' ' . $min_tx . ' ' . strtoupper($selected), 'error');
                    return null;
                }

                $ca = new CryptAPI\Helper($selected, $addr, $callback_url, [], true);
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
        $data = CryptAPI\Helper::process_callback($_GET);
        $order = new WC_Order($data['order_id']);

        if ($order->is_paid() || $data['nonce'] != $order->get_meta('cryptapi_nonce')) die("*ok*");

        $value_convert = CryptAPI\Helper::convert_div($data['value'], $data['coin']);
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
        $order_id = sanitize_text_field($_REQUEST['order_id']);

        try {
            $order = new WC_Order($order_id);

            $data = [
                'is_paid' => $order->is_paid(),
                'is_pending' => boolval($order->get_meta('cryptapi_pending')),
            ];

            echo json_encode($data);
            die();

        } catch (Exception $e) {
            //
        }

        echo json_encode(['status' => 'error', 'error' => 'not a valid order_id']);
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

        $show_crypto_coin = $crypto_coin;
        if ($show_crypto_coin == 'iota') $show_crypto_coin = 'miota';

        $qr_value = $crypto_value;
        if (in_array($crypto_coin, array('eth', 'iota'))) $qr_value = CryptAPI\Helper::convert_mul($crypto_value, $crypto_coin);

        $ajax_url = add_query_arg(array(
            'action' => 'cryptapi_order_status',
            'order_id' => $order_id,
        ), home_url('/wp-admin/admin-ajax.php'));

        wp_enqueue_script('ca-jquery-qrcode', CRYPTAPI_PLUGIN_URL . 'static/jquery-qrcode-0.17.0.min.js', array('jquery'));
        wp_enqueue_script('ca-payment', CRYPTAPI_PLUGIN_URL . 'static/payment.js', array('ca-jquery-qrcode'), false, true);
        wp_add_inline_script('ca-payment', "function maybe_fill(){if(jQuery('.payment-panel').length>1){jQuery('.payment-panel')[1].remove();return}let ca_address='{$address_in}';let ca_value='{$qr_value}';let ca_coin='{$crypto_coin}';let ajax_url='{$ajax_url}';check_status(ajax_url);fill(ca_address,ca_value,ca_coin)}jQuery(function(){setTimeout(maybe_fill(),Math.floor(Math.random()*500))})");
        wp_enqueue_style('ca-loader-css', CRYPTAPI_PLUGIN_URL . 'static/loader.css');

        ?>

        <div class="payment-panel">
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
                <img width="100" style="margin: 0 auto;" src="<?php echo CRYPTAPI_PLUGIN_URL . 'static/check.png' ?>"/>
            </div>
            <div class="payment_details" style="width: 100%; text-align: center">
                <h4><?php echo __('Waiting for payment', 'cryptapi') ?></h4>
                <div style="width: 100%; text-align: center; margin: 2rem auto;">
                    <canvas height="300" class="qrcode"></canvas>
                </div>
                <div style="width: 100%; margin: 2rem auto; text-align: center;">
                    <?php echo __('In order to confirm your order, please send', 'cryptapi') ?>
                    <span style="font-weight: 500"><?php echo $crypto_value ?></span>
                    <span style="font-weight: 500"><?php echo strtoupper($show_crypto_coin) ?></span>
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