<?php

use Cryptapi\Helper;

#[AllowDynamicProperties]
class WC_CryptAPI_Gateway extends WC_Payment_Gateway
{
    private static $HAS_TRIGGERED = false;

    function __construct()
    {
        $this->id = 'cryptapi';
        $this->icon = CRYPTAPI_PLUGIN_URL . 'static/files/200_logo_ca.png';
        $this->has_fields = true;
        $this->method_title = 'CryptAPI';
        $this->method_description = esc_attr(__('CryptAPI allows customers to pay in cryptocurrency', 'cryptapi'));

        $this->supports = array(
            'products',
            'tokenization',
            'add_payment_method',
            'subscriptions',
            'subscription_cancellation',
            'subscription_amount_changes',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_date_changes',
            'multiple_subscriptions',
        );
        $this->init_form_fields();
        $this->init_settings();
        $this->ca_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'validate_payment'));

        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_mail'), 10, 2);

        add_action('wcs_create_pending_renewal', array($this, 'subscription_send_email'));

        add_action('wp_ajax_nopriv_' . $this->id . '_order_status', array($this, 'order_status'));
        add_action('wp_ajax_' . $this->id . '_order_status', array($this, 'order_status'));

        add_action('wp_ajax_' . $this->id . '_validate_logs', array($this, 'validate_logs'));

        add_action('cryptapi_cronjob', array($this, 'ca_cronjob'), 10, 3);

        add_action('woocommerce_cart_calculate_fees', array($this, 'handling_fee'));

        add_action('woocommerce_checkout_update_order_review', array($this, 'chosen_currency_value_to_wc_session'));

        add_action('wp_footer', array($this, 'refresh_checkout'));

        add_action('woocommerce_email_order_details', array($this, 'add_email_link'), 2, 4);

        add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_order_link'), 10, 2);

        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'order_detail_validate_logs'));
    }

    function reset_load_coins() {
        delete_transient('cryptapi_coins');
        $this->load_coins();
    }

    function load_coins()
    {
        $transient = get_transient('cryptapi_coins');

        if (!empty($transient)) {
            $coins = $transient;
        } else {
            $coins = CryptAPI\Helper::get_supported_coins();
            set_transient('cryptapi_coins', $coins, 86400);

            if (empty($coins)) {
                throw new Exception(__('No cryptocurrencies available at the moment. Please choose a different payment method or try again later.', 'cryptapi'));
            }
        }

        # Disabling XMR since it is not supported anymore.
        unset($coins['xmr']);

        return $coins;
    }

    function admin_options()
    {
        parent::admin_options();
        ?>
        <div style='margin-top: 2rem;'>
            <?php echo __("If you need any help or have any suggestion, contact us via the <b>live chat</b> on our <b><a href='https://cryptapi.io' target='_blank'>website</a></b> or join our <b><a href='https://discord.gg/cryptapi' target='_blank'>Discord server</a></b>", "cryptapi"); ?>
        </div>
        <div style='margin-top: .5rem;'>
            <?php echo __("If you enjoy this plugin please <b><a href='https://wordpress.org/support/plugin/cryptapi-payment-gateway-for-woocommerce/reviews/#new-post' target='_blank'>rate and review it</a></b>!", "cryptapi") ?>
        </div>
        <div style="margin-top: 1.5rem">
            <a href="https://uk.trustpilot.com/review/cryptapi.io" target="_blank">
                <svg width="145" viewBox="0 0 200 39" xmlns="http://www.w3.org/2000/svg"
                     xmlns:xlink="http://www.w3.org/1999/xlink"
                     style="fill-rule:evenodd;clip-rule:evenodd;stroke-linecap:square;stroke-linejoin:round;stroke-miterlimit:1.5;">
                    <g id="Trustpilot" transform="matrix(1,0,0,0.065,0,0)">
                        <rect x="0" y="0" width="200" height="600" style="fill:none;"></rect>
                        <g transform="matrix(0.98251,0,0,66.8611,-599.243,-59226.5)">
                            <g>
                                <g transform="matrix(1,0,0,1,487.904,8.98364)">
                                    <g transform="matrix(0.695702,0,0,0.695702,-619.165,278.271)">
                                        <g transform="matrix(1,0,0,0.226074,1213.4,863.302)">
                                            <path d="M33.064,11.07L45.818,11.07L45.818,13.434L40.807,13.434L40.807,26.725L38.052,26.725L38.052,13.434L33.064,13.434L33.064,11.07ZM45.274,15.39L47.629,15.39L47.629,17.577L47.673,17.577C47.751,17.268 47.896,16.969 48.107,16.682C48.318,16.395 48.573,16.119 48.873,15.887C49.173,15.644 49.507,15.456 49.873,15.301C50.24,15.158 50.618,15.08 50.995,15.08C51.284,15.08 51.495,15.091 51.606,15.102C51.718,15.113 51.829,15.136 51.951,15.147L51.951,17.555C51.773,17.522 51.595,17.5 51.406,17.478C51.218,17.456 51.04,17.445 50.862,17.445C50.44,17.445 50.04,17.533 49.662,17.699C49.284,17.864 48.962,18.118 48.684,18.439C48.407,18.77 48.185,19.168 48.018,19.654C47.851,20.14 47.773,20.693 47.773,21.322L47.773,26.714L45.263,26.714L45.263,15.39L45.274,15.39ZM63.494,26.725L61.028,26.725L61.028,25.145L60.983,25.145C60.672,25.719 60.217,26.172 59.606,26.515C58.995,26.857 58.372,27.034 57.739,27.034C56.239,27.034 55.151,26.669 54.484,25.929C53.817,25.189 53.484,24.073 53.484,22.582L53.484,15.39L55.995,15.39L55.995,22.339C55.995,23.333 56.184,24.04 56.573,24.449C56.95,24.858 57.495,25.067 58.184,25.067C58.717,25.067 59.15,24.99 59.506,24.824C59.861,24.659 60.15,24.449 60.361,24.173C60.583,23.907 60.739,23.576 60.839,23.2C60.939,22.825 60.983,22.416 60.983,21.974L60.983,15.401L63.494,15.401L63.494,26.725ZM67.772,23.09C67.849,23.819 68.127,24.327 68.605,24.626C69.094,24.913 69.671,25.067 70.349,25.067C70.582,25.067 70.849,25.045 71.149,25.012C71.449,24.979 71.738,24.902 71.993,24.802C72.26,24.703 72.471,24.548 72.649,24.349C72.816,24.15 72.893,23.896 72.882,23.576C72.871,23.256 72.749,22.99 72.527,22.792C72.305,22.582 72.027,22.427 71.682,22.294C71.338,22.173 70.949,22.062 70.505,21.974C70.06,21.886 69.616,21.786 69.16,21.687C68.694,21.587 68.238,21.455 67.805,21.311C67.372,21.168 66.983,20.969 66.638,20.715C66.294,20.472 66.016,20.151 65.816,19.765C65.605,19.378 65.505,18.903 65.505,18.328C65.505,17.71 65.661,17.201 65.961,16.782C66.261,16.362 66.65,16.03 67.105,15.776C67.572,15.522 68.083,15.345 68.649,15.235C69.216,15.136 69.76,15.08 70.271,15.08C70.86,15.08 71.427,15.147 71.96,15.268C72.493,15.39 72.982,15.589 73.416,15.876C73.849,16.152 74.204,16.517 74.493,16.958C74.782,17.4 74.96,17.942 75.038,18.571L72.416,18.571C72.293,17.975 72.027,17.566 71.593,17.367C71.16,17.157 70.66,17.058 70.105,17.058C69.927,17.058 69.716,17.069 69.471,17.102C69.227,17.135 69.005,17.19 68.783,17.268C68.572,17.345 68.394,17.467 68.238,17.621C68.094,17.776 68.016,17.975 68.016,18.229C68.016,18.538 68.127,18.781 68.338,18.969C68.549,19.157 68.827,19.312 69.171,19.444C69.516,19.566 69.905,19.676 70.349,19.765C70.794,19.853 71.249,19.952 71.716,20.052C72.171,20.151 72.616,20.284 73.06,20.427C73.504,20.571 73.893,20.77 74.238,21.024C74.582,21.278 74.86,21.587 75.071,21.963C75.282,22.339 75.393,22.814 75.393,23.366C75.393,24.04 75.238,24.603 74.927,25.078C74.615,25.542 74.215,25.929 73.727,26.216C73.238,26.504 72.682,26.725 72.082,26.857C71.482,26.99 70.882,27.056 70.294,27.056C69.571,27.056 68.905,26.979 68.294,26.813C67.683,26.647 67.149,26.404 66.705,26.084C66.261,25.752 65.905,25.344 65.65,24.858C65.394,24.371 65.261,23.786 65.239,23.112L67.772,23.112L67.772,23.09ZM76.06,15.39L77.96,15.39L77.96,11.987L80.47,11.987L80.47,15.39L82.737,15.39L82.737,17.257L80.47,17.257L80.47,23.311C80.47,23.576 80.482,23.797 80.504,23.996C80.526,24.184 80.582,24.349 80.659,24.482C80.737,24.614 80.859,24.714 81.026,24.78C81.193,24.846 81.404,24.88 81.693,24.88C81.87,24.88 82.048,24.88 82.226,24.869C82.404,24.858 82.581,24.835 82.759,24.791L82.759,26.725C82.481,26.758 82.204,26.78 81.948,26.813C81.681,26.846 81.415,26.857 81.137,26.857C80.47,26.857 79.937,26.791 79.537,26.669C79.137,26.548 78.815,26.36 78.593,26.117C78.36,25.874 78.215,25.576 78.126,25.211C78.048,24.846 77.993,24.427 77.982,23.963L77.982,17.279L76.082,17.279L76.082,15.39L76.06,15.39ZM84.515,15.39L86.892,15.39L86.892,16.925L86.937,16.925C87.292,16.262 87.781,15.798 88.414,15.511C89.047,15.224 89.725,15.08 90.47,15.08C91.369,15.08 92.147,15.235 92.814,15.555C93.48,15.865 94.036,16.296 94.48,16.848C94.925,17.4 95.247,18.041 95.469,18.77C95.691,19.499 95.802,20.284 95.802,21.112C95.802,21.875 95.702,22.615 95.502,23.322C95.302,24.04 95.002,24.67 94.603,25.222C94.203,25.774 93.691,26.205 93.069,26.537C92.447,26.868 91.725,27.034 90.881,27.034C90.514,27.034 90.147,27.001 89.781,26.934C89.414,26.868 89.059,26.758 88.725,26.614C88.392,26.47 88.07,26.283 87.792,26.051C87.503,25.819 87.27,25.554 87.07,25.255L87.025,25.255L87.025,30.912L84.515,30.912L84.515,15.39ZM93.292,21.068C93.292,20.56 93.225,20.063 93.092,19.577C92.958,19.091 92.758,18.671 92.492,18.295C92.225,17.92 91.892,17.621 91.503,17.4C91.103,17.179 90.647,17.058 90.136,17.058C89.081,17.058 88.281,17.422 87.748,18.152C87.214,18.881 86.948,19.853 86.948,21.068C86.948,21.643 87.014,22.173 87.159,22.659C87.303,23.145 87.503,23.565 87.792,23.918C88.07,24.272 88.403,24.548 88.792,24.747C89.181,24.957 89.636,25.056 90.147,25.056C90.725,25.056 91.203,24.935 91.603,24.703C92.003,24.471 92.325,24.162 92.58,23.797C92.836,23.421 93.025,23.002 93.136,22.526C93.236,22.051 93.292,21.565 93.292,21.068ZM97.724,11.07L100.235,11.07L100.235,13.434L97.724,13.434L97.724,11.07ZM97.724,15.39L100.235,15.39L100.235,26.725L97.724,26.725L97.724,15.39ZM102.48,11.07L104.99,11.07L104.99,26.725L102.48,26.725L102.48,11.07ZM112.69,27.034C111.779,27.034 110.968,26.879 110.257,26.581C109.546,26.283 108.946,25.863 108.446,25.344C107.957,24.813 107.579,24.184 107.324,23.454C107.068,22.725 106.935,21.919 106.935,21.046C106.935,20.184 107.068,19.389 107.324,18.66C107.579,17.931 107.957,17.301 108.446,16.771C108.935,16.24 109.546,15.832 110.257,15.533C110.968,15.235 111.779,15.08 112.69,15.08C113.601,15.08 114.412,15.235 115.123,15.533C115.834,15.832 116.434,16.251 116.934,16.771C117.423,17.301 117.8,17.931 118.056,18.66C118.311,19.389 118.445,20.184 118.445,21.046C118.445,21.919 118.311,22.725 118.056,23.454C117.8,24.184 117.423,24.813 116.934,25.344C116.445,25.874 115.834,26.283 115.123,26.581C114.412,26.879 113.601,27.034 112.69,27.034ZM112.69,25.056C113.245,25.056 113.734,24.935 114.145,24.703C114.556,24.471 114.89,24.162 115.156,23.786C115.423,23.41 115.612,22.979 115.745,22.504C115.867,22.029 115.934,21.543 115.934,21.046C115.934,20.56 115.867,20.085 115.745,19.599C115.623,19.113 115.423,18.693 115.156,18.317C114.89,17.942 114.556,17.643 114.145,17.411C113.734,17.179 113.245,17.058 112.69,17.058C112.134,17.058 111.645,17.179 111.234,17.411C110.823,17.643 110.49,17.953 110.223,18.317C109.957,18.693 109.768,19.113 109.634,19.599C109.512,20.085 109.446,20.56 109.446,21.046C109.446,21.543 109.512,22.029 109.634,22.504C109.757,22.979 109.957,23.41 110.223,23.786C110.49,24.162 110.823,24.471 111.234,24.703C111.645,24.946 112.134,25.056 112.69,25.056ZM119.178,15.39L121.078,15.39L121.078,11.987L123.589,11.987L123.589,15.39L125.855,15.39L125.855,17.257L123.589,17.257L123.589,23.311C123.589,23.576 123.6,23.797 123.622,23.996C123.644,24.184 123.7,24.349 123.778,24.482C123.855,24.614 123.978,24.714 124.144,24.78C124.311,24.846 124.522,24.88 124.811,24.88C124.989,24.88 125.166,24.88 125.344,24.869C125.522,24.858 125.7,24.835 125.877,24.791L125.877,26.725C125.6,26.758 125.322,26.78 125.066,26.813C124.8,26.846 124.533,26.857 124.255,26.857C123.589,26.857 123.055,26.791 122.656,26.669C122.256,26.548 121.933,26.36 121.711,26.117C121.478,25.874 121.333,25.576 121.245,25.211C121.167,24.846 121.111,24.427 121.1,23.963L121.1,17.279L119.2,17.279L119.2,15.39L119.178,15.39Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(1,0,0,0.226074,1213.4,863.302)">
                                            <path d="M30.142,11.07L18.632,11.07L15.076,0.177L11.51,11.07L0,11.059L9.321,17.798L5.755,28.68L15.076,21.952L24.387,28.68L20.831,17.798L30.142,11.07L30.142,11.07Z"
                                                  style="fill:rgb(0,182,122);fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(1,0,0,0.226074,1213.4,863.302)">
                                            <path d="M21.631,20.262L20.831,17.798L15.076,21.952L21.631,20.262Z"
                                                  style="fill:rgb(0,81,40);fill-rule:nonzero;"></path>
                                        </g>
                                    </g>
                                    <g transform="matrix(1.12388,0,0,0.0893092,-1103.52,543.912)">
                                        <g transform="matrix(10.6773,0,0,30.3763,1102,3793.54)">
                                            <path d="M0.552,0L0.409,-0.205C0.403,-0.204 0.394,-0.204 0.382,-0.204L0.224,-0.204L0.224,0L0.094,0L0.094,-0.7L0.382,-0.7C0.443,-0.7 0.496,-0.69 0.541,-0.67C0.586,-0.65 0.62,-0.621 0.644,-0.584C0.668,-0.547 0.68,-0.502 0.68,-0.451C0.68,-0.398 0.667,-0.353 0.642,-0.315C0.616,-0.277 0.579,-0.249 0.531,-0.23L0.692,0L0.552,0ZM0.549,-0.451C0.549,-0.496 0.534,-0.53 0.505,-0.554C0.476,-0.578 0.433,-0.59 0.376,-0.59L0.224,-0.59L0.224,-0.311L0.376,-0.311C0.433,-0.311 0.476,-0.323 0.505,-0.347C0.534,-0.372 0.549,-0.406 0.549,-0.451Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(10.6773,0,0,30.3763,1109.81,3793.54)">
                                            <path d="M0.584,-0.264C0.584,-0.255 0.583,-0.243 0.582,-0.227L0.163,-0.227C0.17,-0.188 0.19,-0.157 0.221,-0.134C0.252,-0.111 0.29,-0.099 0.336,-0.099C0.395,-0.099 0.443,-0.118 0.481,-0.157L0.548,-0.08C0.524,-0.051 0.494,-0.03 0.457,-0.015C0.42,0 0.379,0.007 0.333,0.007C0.274,0.007 0.223,-0.005 0.178,-0.028C0.133,-0.051 0.099,-0.084 0.075,-0.126C0.05,-0.167 0.038,-0.214 0.038,-0.267C0.038,-0.319 0.05,-0.366 0.074,-0.407C0.097,-0.449 0.13,-0.482 0.172,-0.505C0.214,-0.528 0.261,-0.54 0.314,-0.54C0.366,-0.54 0.412,-0.529 0.454,-0.505C0.495,-0.483 0.527,-0.45 0.55,-0.408C0.573,-0.367 0.584,-0.319 0.584,-0.264ZM0.314,-0.44C0.274,-0.44 0.24,-0.428 0.213,-0.405C0.185,-0.381 0.168,-0.349 0.162,-0.31L0.465,-0.31C0.46,-0.349 0.443,-0.38 0.416,-0.404C0.389,-0.428 0.355,-0.44 0.314,-0.44Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(10.6773,0,0,30.3763,1116.34,3793.54)">
                                            <path d="M0.582,-0.534L0.353,0L0.224,0L-0.005,-0.534L0.125,-0.534L0.291,-0.138L0.462,-0.534L0.582,-0.534Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(10.6773,0,0,30.3763,1122.51,3793.54)">
                                            <path d="M0.082,-0.534L0.207,-0.534L0.207,0L0.082,0L0.082,-0.534ZM0.145,-0.622C0.122,-0.622 0.103,-0.629 0.088,-0.644C0.073,-0.658 0.065,-0.676 0.065,-0.697C0.065,-0.718 0.073,-0.736 0.088,-0.75C0.103,-0.765 0.122,-0.772 0.145,-0.772C0.168,-0.772 0.187,-0.765 0.202,-0.751C0.217,-0.738 0.225,-0.721 0.225,-0.7C0.225,-0.678 0.218,-0.66 0.203,-0.645C0.188,-0.629 0.168,-0.622 0.145,-0.622Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(10.6773,0,0,30.3763,1125.6,3793.54)">
                                            <path d="M0.584,-0.264C0.584,-0.255 0.583,-0.243 0.582,-0.227L0.163,-0.227C0.17,-0.188 0.19,-0.157 0.221,-0.134C0.252,-0.111 0.29,-0.099 0.336,-0.099C0.395,-0.099 0.443,-0.118 0.481,-0.157L0.548,-0.08C0.524,-0.051 0.494,-0.03 0.457,-0.015C0.42,0 0.379,0.007 0.333,0.007C0.274,0.007 0.223,-0.005 0.178,-0.028C0.133,-0.051 0.099,-0.084 0.075,-0.126C0.05,-0.167 0.038,-0.214 0.038,-0.267C0.038,-0.319 0.05,-0.366 0.074,-0.407C0.097,-0.449 0.13,-0.482 0.172,-0.505C0.214,-0.528 0.261,-0.54 0.314,-0.54C0.366,-0.54 0.412,-0.529 0.454,-0.505C0.495,-0.483 0.527,-0.45 0.55,-0.408C0.573,-0.367 0.584,-0.319 0.584,-0.264ZM0.314,-0.44C0.274,-0.44 0.24,-0.428 0.213,-0.405C0.185,-0.381 0.168,-0.349 0.162,-0.31L0.465,-0.31C0.46,-0.349 0.443,-0.38 0.416,-0.404C0.389,-0.428 0.355,-0.44 0.314,-0.44Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(10.6773,0,0,30.3763,1132.13,3793.54)">
                                            <path d="M0.915,-0.534L0.718,0L0.598,0L0.46,-0.368L0.32,0L0.2,0L0.004,-0.534L0.122,-0.534L0.263,-0.14L0.41,-0.534L0.515,-0.534L0.659,-0.138L0.804,-0.534L0.915,-0.534Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(10.6773,0,0,30.3763,1144.89,3793.54)">
                                            <path d="M0.599,-0.534L0.599,0L0.48,0L0.48,-0.068C0.46,-0.044 0.435,-0.025 0.405,-0.013C0.375,0 0.343,0.007 0.308,0.007C0.237,0.007 0.181,-0.013 0.14,-0.053C0.099,-0.092 0.078,-0.151 0.078,-0.229L0.078,-0.534L0.203,-0.534L0.203,-0.246C0.203,-0.198 0.214,-0.162 0.235,-0.139C0.257,-0.115 0.288,-0.103 0.328,-0.103C0.373,-0.103 0.408,-0.117 0.435,-0.145C0.461,-0.172 0.474,-0.212 0.474,-0.264L0.474,-0.534L0.599,-0.534Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(10.6773,0,0,30.3763,1152.16,3793.54)">
                                            <path d="M0.247,0.007C0.204,0.007 0.161,0.001 0.12,-0.01C0.079,-0.021 0.046,-0.036 0.021,-0.053L0.069,-0.148C0.093,-0.132 0.122,-0.119 0.156,-0.11C0.189,-0.1 0.222,-0.095 0.255,-0.095C0.33,-0.095 0.367,-0.115 0.367,-0.154C0.367,-0.173 0.358,-0.186 0.339,-0.193C0.32,-0.2 0.289,-0.207 0.247,-0.214C0.203,-0.221 0.167,-0.228 0.14,-0.237C0.112,-0.246 0.088,-0.261 0.068,-0.282C0.047,-0.304 0.037,-0.334 0.037,-0.373C0.037,-0.424 0.058,-0.464 0.101,-0.495C0.143,-0.525 0.2,-0.54 0.272,-0.54C0.309,-0.54 0.345,-0.536 0.382,-0.528C0.419,-0.519 0.449,-0.508 0.472,-0.494L0.424,-0.399C0.379,-0.426 0.328,-0.439 0.271,-0.439C0.234,-0.439 0.206,-0.434 0.188,-0.423C0.169,-0.411 0.159,-0.397 0.159,-0.379C0.159,-0.359 0.169,-0.345 0.19,-0.337C0.21,-0.328 0.241,-0.32 0.284,-0.313C0.327,-0.306 0.362,-0.299 0.389,-0.29C0.416,-0.281 0.44,-0.267 0.46,-0.246C0.479,-0.225 0.489,-0.196 0.489,-0.158C0.489,-0.108 0.467,-0.068 0.424,-0.038C0.381,-0.008 0.322,0.007 0.247,0.007Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(10.6773,0,0,30.3763,1160.61,3793.54)">
                                            <path d="M0.322,0.007C0.268,0.007 0.219,-0.005 0.176,-0.028C0.133,-0.051 0.099,-0.084 0.075,-0.126C0.05,-0.167 0.038,-0.214 0.038,-0.267C0.038,-0.32 0.05,-0.367 0.075,-0.408C0.099,-0.449 0.133,-0.482 0.176,-0.505C0.219,-0.528 0.268,-0.54 0.322,-0.54C0.377,-0.54 0.426,-0.528 0.469,-0.505C0.512,-0.482 0.546,-0.449 0.571,-0.408C0.595,-0.367 0.607,-0.32 0.607,-0.267C0.607,-0.214 0.595,-0.167 0.571,-0.126C0.546,-0.084 0.512,-0.051 0.469,-0.028C0.426,-0.005 0.377,0.007 0.322,0.007ZM0.322,-0.1C0.368,-0.1 0.406,-0.115 0.436,-0.146C0.466,-0.177 0.481,-0.217 0.481,-0.267C0.481,-0.317 0.466,-0.357 0.436,-0.388C0.406,-0.419 0.368,-0.434 0.322,-0.434C0.276,-0.434 0.238,-0.419 0.209,-0.388C0.179,-0.357 0.164,-0.317 0.164,-0.267C0.164,-0.217 0.179,-0.177 0.209,-0.146C0.238,-0.115 0.276,-0.1 0.322,-0.1Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                        <g transform="matrix(10.6773,0,0,30.3763,1167.49,3793.54)">
                                            <path d="M0.385,-0.54C0.452,-0.54 0.506,-0.52 0.547,-0.481C0.588,-0.442 0.608,-0.383 0.608,-0.306L0.608,0L0.483,0L0.483,-0.29C0.483,-0.337 0.472,-0.372 0.45,-0.396C0.428,-0.419 0.397,-0.431 0.356,-0.431C0.31,-0.431 0.274,-0.417 0.247,-0.39C0.22,-0.362 0.207,-0.322 0.207,-0.27L0.207,0L0.082,0L0.082,-0.534L0.201,-0.534L0.201,-0.465C0.222,-0.49 0.248,-0.508 0.279,-0.521C0.31,-0.534 0.346,-0.54 0.385,-0.54Z"
                                                  style="fill-rule:nonzero;"></path>
                                        </g>
                                    </g>
                                </g>
                                <g transform="matrix(1.21212,0,0,0.215332,142.599,49.6458)">
                                    <rect x="387" y="3885" width="165" height="38"
                                          style="fill:none;stroke:rgb(0,182,122);stroke-width:2px;"></rect>
                                </g>
                            </g>
                        </g>
                    </g>
                </svg>
            </a>
        </div>
        <div style="margin-top: .5rem">
            <a href="https://cryptwerk.com/company/cryptapi/" target="_blank" rel="noopener">
                <img src="https://widget.cryptwerk.com/cryptapi/?shape=rectangle" width="145"
                     alt="CryptAPI rating on Cryptwerk" border="0">
            </a>
        </div>
        <?php
    }

    private function ca_settings()
    {
        $load_coins = [];
        try {
            $load_coins = $this->load_coins();
        } catch (Exception $e) {
            //
        }

        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api_key');
        $this->qrcode_size = $this->get_option('qrcode_size');
        $this->qrcode_default = $this->get_option('qrcode_default') === 'yes';
        $this->qrcode_setting = $this->get_option('qrcode_setting');
        $this->coins = $this->get_option('coins');
        $this->show_branding = $this->get_option('show_branding') === 'yes';
        $this->show_crypto_logos = $this->get_option('show_crypto_logos') === 'yes';
        $this->color_scheme = $this->get_option('color_scheme');
        $this->refresh_value_interval = $this->get_option('refresh_value_interval');
        $this->order_cancelation_timeout = $this->get_option('order_cancelation_timeout');
        $this->add_blockchain_fee = $this->get_option('add_blockchain_fee') === 'yes';
        $this->fee_order_percentage = $this->get_option('fee_order_percentage');
        $this->virtual_complete = $this->get_option('virtual_complete') === 'yes';
        $this->disable_conversion = $this->get_option('disable_conversion') === 'yes';
        $this->icon = '';

        if (!empty($load_coins)) {
            foreach (array_keys($load_coins) as $coin) {
                $this->{$coin . '_address'} = $this->get_option($coin . '_address');
            }
        }
    }

    function init_form_fields()
    {
        $load_coins = [];
        try {
            $load_coins = $this->load_coins();
        } catch (Exception $e) {
            //
        }

        if (!empty($load_coins)) {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => esc_attr(__('Enabled', 'cryptapi')),
                    'type' => 'checkbox',
                    'label' => esc_attr(__('Enable CryptAPI Payments', 'cryptapi')),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => esc_attr(__('Title', 'cryptapi')),
                    'type' => 'text',
                    'description' => esc_attr(__('This controls the title which the user sees during checkout.', 'cryptapi')),
                    'default' => esc_attr(__('Cryptocurrency', 'cryptapi')),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => esc_attr(__('Description', 'cryptapi')),
                    'type' => 'textarea',
                    'default' => '',
                    'description' => esc_attr(__('Payment method description that the customer will see on your checkout', 'cryptapi'))
                ),
                'show_branding' => array(
                    'title' => esc_attr(__('Show CryptAPI branding', 'cryptapi')),
                    'type' => 'checkbox',
                    'label' => esc_attr(__('Show CryptAPI logo and credits below the QR code', 'cryptapi')),
                    'default' => 'yes'
                ),
                'show_crypto_logos' => array(
                    'title' => esc_attr(__('Show crypto logos in checkout', 'cryptapi')),
                    'type' => 'checkbox',
                    'label' => sprintf(esc_attr(__('Enable this to show the cryptocurrencies logos in the checkout %1$s %2$s Notice: %3$s It may break in some templates. Use at your own risk.', 'cryptapi')), '<br/>', '<strong>', '</strong>'),
                    'default' => 'no'
                ),
                'add_blockchain_fee' => array(
                    'title' => esc_attr(__('Add the blockchain fee to the order', 'cryptapi')),
                    'type' => 'checkbox',
                    'label' => esc_attr(__("This will add an estimation of the blockchain fee to the order value", 'cryptapi')),
                    'default' => 'no'
                ),
                'fee_order_percentage' => array(
                    'title' => esc_attr(__('Service fee manager', 'cryptapi')),
                    'type' => 'select',
                    'default' => 'none',
                    'options' => array(
                        '0.05' => '5%',
                        '0.048' => '4.8%',
                        '0.045' => '4.5%',
                        '0.042' => '4.2%',
                        '0.04' => '4%',
                        '0.038' => '3.8%',
                        '0.035' => '3.5%',
                        '0.032' => '3.2%',
                        '0.03' => '3%',
                        '0.028' => '2.8%',
                        '0.025' => '2.5%',
                        '0.022' => '2.2%',
                        '0.02' => '2%',
                        '0.018' => '1.8%',
                        '0.015' => '1.5%',
                        '0.012' => '1.2%',
                        '0.01' => '1%',
                        '0.0090' => '0.90%',
                        '0.0085' => '0.85%',
                        '0.0080' => '0.80%',
                        '0.0075' => '0.75%',
                        '0.0070' => '0.70%',
                        '0.0065' => '0.65%',
                        '0.0060' => '0.60%',
                        '0.0055' => '0.55%',
                        '0.0050' => '0.50%',
                        '0.0040' => '0.40%',
                        '0.0030' => '0.30%',
                        '0.0025' => '0.25%',
                        'none' => '0%',
                    ),
                    'description' => sprintf(esc_attr(__('Set the CryptAPI service fee you want to charge the costumer. %1$s %2$s Note: %3$s Fee you want to charge your costumers (to cover CryptAPI\'s fees fully or partially).', 'cryptapi')), '<br/>', '<strong>', '</strong>')
                ),
                'qrcode_default' => array(
                    'title' => esc_attr(__('QR Code by default', 'cryptapi')),
                    'type' => 'checkbox',
                    'label' => esc_attr(__('Show the QR Code by default', 'cryptapi')),
                    'default' => 'yes'
                ),
                'qrcode_size' => array(
                    'title' => esc_attr(__('QR Code size', 'cryptapi')),
                    'type' => 'number',
                    'default' => 300,
                    'description' => esc_attr(__('QR code image size', 'cryptapi'))
                ),
                'qrcode_setting' => array(
                    'title' => esc_attr(__('QR Code to show', 'cryptapi')),
                    'type' => 'select',
                    'default' => 'without_ammount',
                    'options' => array(
                        'without_ammount' => esc_attr(__('Default Without Amount', 'cryptapi')),
                        'ammount' => esc_attr(__('Default Amount', 'cryptapi')),
                        'hide_ammount' => esc_attr(__('Hide Amount', 'cryptapi')),
                        'hide_without_ammount' => esc_attr(__('Hide Without Amount', 'cryptapi')),
                    ),
                    'description' => esc_attr(__('Select how you want to show the QR Code to the user. Either select a default to show first, or hide one of them.', 'cryptapi'))
                ),
                'color_scheme' => array(
                    'title' => esc_attr(__('Color Scheme', 'cryptapi')),
                    'type' => 'select',
                    'default' => 'light',
                    'description' => esc_attr(__('Selects the color scheme of the plugin to match your website (Light, Dark and Auto to automatically detect it)', 'cryptapi')),
                    'options' => array(
                        'light' => esc_attr(__('Light', 'cryptapi')),
                        'dark' => esc_attr(__('Dark', 'cryptapi')),
                        'auto' => esc_attr(__('Auto', 'cryptapi')),
                    ),
                ),
                'refresh_value_interval' => array(
                    'title' => esc_attr(__('Refresh converted value', 'cryptapi')),
                    'type' => 'select',
                    'default' => '300',
                    'options' => array(
                        '0' => esc_attr(__('Never', 'cryptapi')),
                        '300' => esc_attr(__('Every 5 Minutes', 'cryptapi')),
                        '600' => esc_attr(__('Every 10 Minutes', 'cryptapi')),
                        '900' => esc_attr(__('Every 15 Minutes', 'cryptapi')),
                        '1800' => esc_attr(__('Every 30 Minutes', 'cryptapi')),
                        '2700' => esc_attr(__('Every 45 Minutes', 'cryptapi')),
                        '3600' => esc_attr(__('Every 60 Minutes', 'cryptapi')),
                    ),
                    'description' => sprintf(esc_attr(__('The system will automatically update the conversion value of the invoices (with real-time data), every X minutes. %1$s This feature is helpful whenever a customer takes long time to pay a generated invoice and the selected crypto a volatile coin/token (not stable coin). %1$s %4$s Warning: %3$s Setting this setting to none might create conversion issues, as we advise you to keep it at 5 minutes. %3$s', 'cryptapi')), '<br/>', '<strong>', '</strong>', '<strong style="color: #f44336;">'),
                ),
                'order_cancelation_timeout' => array(
                    'title' => esc_attr(__('Order cancelation timeout', 'cryptapi')),
                    'type' => 'select',
                    'default' => '0',
                    'options' => array(
                        '0' => esc_attr(__('Never', 'cryptapi')),
                        '900' => esc_attr(__('15 Minutes', 'cryptapi')),
                        '1800' => esc_attr(__('30 Minutes', 'cryptapi')),
                        '2700' => esc_attr(__('45 Minutes', 'cryptapi')),
                        '3600' => esc_attr(__('1 Hour', 'cryptapi')),
                        '21600' => esc_attr(__('6 Hours', 'cryptapi')),
                        '43200' => esc_attr(__('12 Hours', 'cryptapi')),
                        '64800' => esc_attr(__('18 Hours', 'cryptapi')),
                        '86400' => esc_attr(__('24 Hours', 'cryptapi')),
                    ),
                    'description' => sprintf(esc_attr(__('Selects the amount of time the user has to  pay for the order. %1$s When this time is over, order will be marked as "Cancelled" and every paid value will be ignored. %1$s %2$s Notice: %3$s If the user still sends money to the generated address, value will still be redirected to you. %1$s %4$s Warning: %3$s We do not advice more than 1 Hour.', 'cryptapi')), '<br/>', '<strong>', '</strong>', '<strong style="color: #f44336;">'),
                ),
                'virtual_complete' => array(
                    'title' => esc_attr(__('Completed status for virtual products', 'cryptapi')),
                    'type' => 'checkbox',
                    'label' => sprintf(__('When this setting is enabled, the plugin will mark the order as "completed" then payment is received. %1$s Only for virtual products %2$s.', 'cryptapi'), '<strong>', '</strong>'),
                    'default' => 'no'
                ),
                'disable_conversion' => array(
                    'title' => esc_attr(__('Disable price conversion', 'cryptapi')),
                    'type' => 'checkbox',
                    'label' => sprintf(__('%2$s Attention: This option will disable the price conversion for ALL cryptocurrencies! %3$s %1$s If you check this, pricing will not be converted from the currency of your shop to the cryptocurrency selected by the user, and users will be requested to pay the same value as shown on your shop, regardless of the cryptocurrency selected', 'cryptapi'), '<br/>', '<strong>', '</strong>'),
                    'default' => 'no'
                ),
                'api_key' => array(
                    'title' => esc_attr(__('API Key', 'cryptapi')),
                    'type' => 'text',
                    'default' => '',
                    'description' => sprintf(esc_attr(__('(Optional) Insert here your BlockBee API Key. You can get one here: %1$s', 'cryptapi')), '<a href="https://dash.blockbee.io/" target="_blank">https://dash.blockbee.io/</a>')
                ),
            );

            $coin_description = esc_attr(__('Insert your %s address here. Leave the checkbox unselected if you want to skip this cryptocurrency', 'cryptapi'));

            $c = 0;
            foreach ($load_coins as $ticker => $coin) {
                $this->form_fields["{$ticker}_address"] = array(
                    'title' => is_array($coin) ? $coin['name'] : $coin,
                    'type' => 'cryptocurrency',
                    'description' => sprintf($coin_description, is_array($coin) ? $coin['name'] : $coin),
                    'desc_tip' => true,
                    'custom_attributes' => array(
                        'counter' => $c++,
                    )
                );

            }

        }
    }

    function needs_setup()
    {
        if (empty($this->coins) || !is_array($this->coins)) {
            return true;
        }

        foreach ($this->coins as $val) {
            if (!empty($this->{$val . '_address'})) {
                return false;
            }
        }

        return true;
    }

    public function get_icon()
    {

        $icon = $this->show_branding ? '<img style="top: -5px; position:relative" width="120" src="' . esc_url(plugin_dir_url(dirname(__FILE__))) . 'static/files/200_logo_ca.png' . '" alt="' . esc_attr($this->get_title()) . '" />' : '';

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    function payment_fields()
    {
        try {
            $load_coins = $this->load_coins();
        } catch (Exception $e) {
            ?>
            <div class="woocommerce-error">
                <?php echo __('Sorry, there has been an error.', 'woocommerce'); ?>
            </div>
            <?php
            return;
        }
        ?>
        <div class="form-row form-row-wide">
            <p><?php echo esc_attr($this->description); ?></p>
            <ul style="margin-top: 7px; list-style: none outside;">
                <?php
                if (!empty($this->coins) && is_array($this->coins)) {
                    $selected = WC()->session->get('cryptapi_coin');
                    ?>
                    <li>
                        <select name="cryptapi_coin" id="payment_cryptapi_coin" class="input-control"
                                style="display:block; margin-top: 10px">
                            <option value="none"><?php echo esc_attr(__('Please select a Cryptocurrency', 'cryptapi')) ?></option>
                            <?php
                            foreach ($this->coins as $val) {
                                $addr = $this->{$val . '_address'};
                                $apikey = $this->api_key;
                                if (!empty($addr) || !empty($apikey)) { ?>
                                    <option data-image="<?php echo esc_url($load_coins[$val]['logo']); ?>"
                                            value="<?php echo esc_attr($val); ?>" <?php
                                    if (!empty($selected) && $selected === $val) {
                                        echo esc_attr("selected='true'");
                                    }
                                    $crypto_name = is_array($load_coins[$val]) ? $load_coins[$val]['name'] : $load_coins[$val];
                                    ?>> <?php echo esc_attr(__('Pay with', 'cryptapi') . ' ' . $crypto_name); ?></option>
                                    <?php
                                }
                            }
                            ?>
                        </select>
                    </li>
                    <?php
                } ?>
            </ul>
        </div>
        <?php
        if ($this->show_crypto_logos) {
            ?>
            <script>
                if (typeof jQuery.fn.selectWoo !== 'undefined') {
                    jQuery('#payment_cryptapi_coin').selectWoo({
                        minimumResultsForSearch: -1,
                        templateResult: formatState
                    })

                    function formatState(opt) {
                        if (!opt.id) {
                            return opt.text
                        }
                        let optImage = jQuery(opt.element).attr('data-image')
                        if (!optImage) {
                            return opt.text
                        } else {
                            return jQuery('<span style="display:flex; align-items:center;"><img style="margin-right: 8px" src="' + optImage + '" width="24px" alt="' + opt.text + '" /> ' + opt.text + '</span>')
                        }
                    }
                }
            </script>
            <?php
        }
    }

    function validate_fields()
    {
        $load_coins = $this->load_coins();
        return array_key_exists(sanitize_text_field($_POST['cryptapi_coin']), $load_coins);
    }

    function process_payment($order_id)
    {
        global $woocommerce;

        $selected = sanitize_text_field($_POST['cryptapi_coin']);

        if ($selected === 'none') {
            wc_add_notice(__('Payment error: ', 'woocommerce') . ' ' . __('Please choose a cryptocurrency', 'cryptapi'), 'error');

            return null;
        }

        $api_key = $this->api_key;
        $addr = $this->{$selected . '_address'};

        if (!empty($addr) || !empty($api_key)) {

            $nonce = $this->generate_nonce();

            $callback_url = str_replace('https:', 'http:', add_query_arg(array(
                'wc-api' => 'WC_Gateway_CryptAPI',
                'order_id' => $order_id,
                'nonce' => $nonce,
            ), home_url('/')));

            try {
                $order = new WC_Order($order_id);

                if (in_array('woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters('active_plugins', get_option('active_plugins')))) {

                    if (wcs_order_contains_subscription($order_id)) {

                        $sign_up_fee = (WC_Subscriptions_Order::get_sign_up_fee($order)) ? 0 : WC_Subscriptions_Order::get_sign_up_fee($order);
                        $initial_payment = (WC_Subscriptions_Order::get_total_initial_payment($order)) ? 0 : WC_Subscriptions_Order::get_total_initial_payment($order);
                        $price_per_period = (WC_Subscriptions_Order::get_recurring_total($order)) ? 0 : WC_Subscriptions_Order::get_recurring_total($order);

                        $total = $sign_up_fee + $initial_payment + $price_per_period + $order->get_total('edit');

                        if ($total == 0) {
                            $order->add_meta_data('cryptapi_currency', $selected);
                            $order->save_meta_data();
                            $order->payment_complete();
                            $woocommerce->cart->empty_cart();

                            return array(
                                'result' => 'success',
                                'redirect' => $this->get_return_url($order)
                            );
                        }
                    }
                }

                $total = $order->get_total('edit');

                $currency = get_woocommerce_currency();

                $info = CryptAPI\Helper::get_info($selected);
                $min_tx = CryptAPI\Helper::sig_fig($info->minimum_transaction_coin, 8);

                $crypto_total = CryptAPI\Helper::get_conversion($currency, $selected, $total, $this->disable_conversion);

                if ($crypto_total < $min_tx) {
                    wc_add_notice(__('Payment error:', 'woocommerce') . ' ' . __('Value too low, minimum is', 'cryptapi') . ' ' . $min_tx . ' ' . strtoupper($selected), 'error');

                    return null;
                }

                $ca = new CryptAPI\Helper($selected, $addr, $api_key, $callback_url, [], true);

                $addr_in = $ca->get_address();

                if (empty($addr_in)) {
                    wc_add_notice(__('Payment error:', 'woocommerce') . ' ' . __('There was an error with the payment. Please try again.', 'cryptapi'));

                    return null;
                }

                $qr_code_data_value = CryptAPI\Helper::get_static_qrcode($addr_in, $selected, $crypto_total, $this->qrcode_size);
                $qr_code_data = CryptAPI\Helper::get_static_qrcode($addr_in, $selected, '', $this->qrcode_size);

                $order->add_meta_data('cryptapi_version', CRYPTAPI_PLUGIN_VERSION);
                $order->add_meta_data('cryptapi_php_version', PHP_VERSION);
                $order->add_meta_data('cryptapi_nonce', $nonce);
                $order->add_meta_data('cryptapi_address', $addr_in);
                $order->add_meta_data('cryptapi_total', CryptAPI\Helper::sig_fig($crypto_total, 8));
                $order->add_meta_data('cryptapi_total_fiat', $total);
                $order->add_meta_data('cryptapi_currency', $selected);
                $order->add_meta_data('cryptapi_qr_code_value', $qr_code_data_value['qr_code']);
                $order->add_meta_data('cryptapi_qr_code', $qr_code_data['qr_code']);
                $order->add_meta_data('cryptapi_last_price_update', time());
                $order->add_meta_data('cryptapi_cancelled', '0');
                $order->add_meta_data('cryptapi_min', $min_tx);
                $order->add_meta_data('cryptapi_history', json_encode([]));
                $order->add_meta_data('cryptapi_callback_url', $callback_url);
                $order->add_meta_data('cryptapi_last_checked', $order->get_date_created()->getTimestamp());
                $order->save_meta_data();

                $load_coins = $this->load_coins();

                $order->update_status('on-hold', __('Awaiting payment', 'cryptapi') . ': ' . $load_coins[$selected]);
                $woocommerce->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );

            } catch (Exception $e) {
                wc_add_notice(__('Payment error:', 'cryptapi') . 'Unknown coin', 'error');

                return null;
            }
        }

        wc_add_notice(__('Payment error:', 'woocommerce') . __('Payment could not be processed, please try again', 'cryptapi'), 'error');

        return null;
    }

    function validate_payment()
    {
        $data = CryptAPI\Helper::process_callback($_GET);

        $order = new WC_Order($data['order_id']);

        if ($order->is_paid() || $order->get_status() === 'cancelled' || $data['nonce'] != $order->get_meta('cryptapi_nonce')) {
            die("*ok*");
        }

        $order->update_meta_data('cryptapi_last_checked', time());
        $order->save_meta_data();

        // Actually process the callback data
        $this->process_callback_data($data, $order);
    }

    function order_status()
    {
        $order_id = sanitize_text_field($_REQUEST['order_id']);

        try {
            $order = new WC_Order($order_id);
            $counter_calc = (int)$order->get_meta('cryptapi_last_price_update') + (int)$this->refresh_value_interval - time();

            if (!$order->is_paid()) {
                if ($counter_calc <= 0) {
                    $updated = $this->refresh_value($order);

                    if ($updated) {
                        $order = new WC_Order($order_id);
                        $counter_calc = (int)$order->get_meta('cryptapi_last_price_update') + (int)$this->refresh_value_interval - time();
                    }
                }
            }

            $showMinFee = '0';

            $history = json_decode($order->get_meta('cryptapi_history'), true);

            $cryptapi_total = $order->get_meta('cryptapi_total');
            $order_total = $order->get_total('edit');

            $calc = $this->calc_order($history, $cryptapi_total, $order_total);

            $already_paid = $calc['already_paid'];
            $already_paid_fiat = $calc['already_paid_fiat'];

            $min_tx = (float)$order->get_meta('cryptapi_min');

            $remaining_pending = $calc['remaining_pending'];
            $remaining_fiat = $calc['remaining_fiat'];

            $cryptapi_pending = 0;

            if ($remaining_pending <= 0 && !$order->is_paid()) {
                $cryptapi_pending = 1;
            }

            if ($remaining_pending <= $min_tx && $remaining_pending > 0) {
                $remaining_pending = $min_tx;
                $showMinFee = 1;
            }

            $data = [
                'is_paid' => $order->is_paid(),
                'is_pending' => $cryptapi_pending,
                'qr_code_value' => $order->get_meta('cryptapi_qr_code_value'),
                'cancelled' => (int)$order->get_meta('cryptapi_cancelled'),
                'coin' => strtoupper($order->get_meta('cryptapi_currency')),
                'show_min_fee' => $showMinFee,
                'order_history' => json_decode($order->get_meta('cryptapi_history'), true),
                'counter' => (string)$counter_calc,
                'crypto_total' => (float)$order->get_meta('cryptapi_total'),
                'already_paid' => $already_paid,
                'remaining' => (float)$remaining_pending <= 0 ? 0 : $remaining_pending,
                'fiat_remaining' => (float)$remaining_fiat <= 0 ? 0 : $remaining_fiat,
                'already_paid_fiat' => (float)$already_paid_fiat <= 0 ? 0 : $already_paid_fiat,
                'fiat_symbol' => get_woocommerce_currency_symbol(),
            ];

            echo json_encode($data);
            die();

        } catch (Exception $e) {
            //
        }

        echo json_encode(['status' => 'error', 'error' => 'Not a valid order_id']);
        die();
    }

    function validate_logs()
    {
        $order_id = sanitize_text_field($_REQUEST['order_id']);
        $order = new WC_Order($order_id);

        try {

            $callbacks = CryptAPI\Helper::check_logs($order->get_meta('cryptapi_callback_url'), $order->get_meta('cryptapi_currency'));

            $order->update_meta_data('cryptapi_last_checked', time());
            $order->save_meta_data();

            if ($callbacks) {
                foreach ($callbacks as $callback) {
                    $logs = $callback->logs;
                    $request_url = parse_url($logs[0]->request_url);
                    parse_str($request_url['query'], $data);

                    if (empty($history[$data->uuid]) || (!empty($history[$data->uuid]) && (int)$history[$data->uuid]['pending'] === 1 && (int)$data['pending'] === 0)) {
                        $this->process_callback_data($data, $order, true);
                    }
                }
            }
            die();
        } catch (Exception $e) {
            //
        }
        die();
    }

    function process_callback_data($data, $order, $validation = false)
    {
        $coin = $data['coin'];

        $saved_coin = $order->get_meta('cryptapi_currency');

        $paid = $data['value_coin'];

        $min_tx = (float)$order->get_meta('cryptapi_min');

        $crypto_coin = strtoupper($order->get_meta('cryptapi_currency'));

        $history = json_decode($order->get_meta('cryptapi_history'), true);

        if ($coin !== $saved_coin) {
            $order->add_order_note(
                '[MISSMATCHED PAYMENT] Registered a ' . $paid . ' ' . strtoupper($coin) . '. Order not confirmed because requested currency is ' . $crypto_coin . '. If you wish, you may confirm it manually. (Funds were already forwarded to you).'
            );

            die("*ok*");
        }

        if (!$data['uuid']) {
            if (!$validation) {
                die("*ok*");
            } else {
                return;
            }
        }

        if (empty($history[$data['uuid']])) {
            $conversion = json_decode(stripcslashes($data['value_coin_convert']), true);

            $history[$data['uuid']] = [
                'timestamp' => time(),
                'value_paid' => CryptAPI\Helper::sig_fig($paid, 8),
                'value_paid_fiat' => $conversion[strtoupper($order->get_currency())],
                'pending' => $data['pending']
            ];
        } else {
            $history[$data['uuid']]['pending'] = $data['pending'];
        }

        $order->update_meta_data('cryptapi_history', json_encode($history));
        $order->save_meta_data();

        $calc = $this->calc_order(json_decode($order->get_meta('cryptapi_history'), true), $order->get_meta('cryptapi_total'), $order->get_meta('cryptapi_total_fiat'));

        $remaining = $calc['remaining'];
        $remaining_pending = $calc['remaining_pending'];

        $order_notes = $this->get_private_order_notes($order);

        $has_pending = false;
        $has_confirmed = false;

        foreach ($order_notes as $note) {
            $note_content = $note['note_content'];

            if (strpos((string)$note_content, 'PENDING') && strpos((string)$note_content, $data['txid_in'])) {
                $has_pending = true;
            }

            if (strpos((string)$note_content, 'CONFIRMED') && strpos((string)$note_content, $data['txid_in'])) {
                $has_confirmed = true;
            }
        }

        if (!$has_pending) {
            $order->add_order_note(
                '[PENDING] ' .
                __('User sent a payment of', 'cryptapi') . ' ' .
                $paid . ' ' . $crypto_coin .
                '. TXID: ' . $data['txid_in']
            );
        }

        if (!$has_confirmed && (int)$data['pending'] === 0) {
            $order->add_order_note(
                '[CONFIRMED] ' . __('User sent a payment of', 'cryptapi') . ' ' .
                $paid . ' ' . $crypto_coin .
                '. TXID: ' . $data['txid_in']
            );

            if ($remaining > 0) {
                if ($remaining <= $min_tx) {
                    $order->add_order_note(__('Payment detected and confirmed. Customer still need to send', 'cryptapi') . ' ' . $min_tx . $crypto_coin, false);
                } else {
                    $order->add_order_note(__('Payment detected and confirmed. Customer still need to send', 'cryptapi') . ' ' . $remaining . $crypto_coin, false);
                }
            }
        }

        if ($remaining <= 0) {
            /**
             * Changes the order Status to Paid
             */
            $order->payment_complete($data['address_in']);

            if ($this->virtual_complete) {
                $count_products = count($order->get_items());
                $count_virtual = 0;
                foreach ($order->get_items() as $order_item) {
                    $item = wc_get_product($order_item->get_product_id());
                    $item_obj = $item->get_type() === 'variable' ? wc_get_product($order_item['variation_id']) : $item;

                    if ($item_obj->is_virtual()) {
                        $count_virtual += 1;
                    }
                }
                if ($count_virtual === $count_products) {
                    $order->update_status('completed');
                }
            }

            $order->save();

            if (!$validation) {
                die("*ok*");
            } else {
                return;
            }
        }

        /**
         * Refreshes the QR Code. If payment is marked as completed, it won't get here.
         */
        if ($remaining <= $min_tx) {
            $order->update_meta_data('cryptapi_qr_code_value', CryptAPI\Helper::get_static_qrcode($order->get_meta('cryptapi_address'), $order->get_meta('cryptapi_currency'), $min_tx, $this->qrcode_size)['qr_code']);
        } else {
            $order->update_meta_data('cryptapi_qr_code_value', CryptAPI\Helper::get_static_qrcode($order->get_meta('cryptapi_address'), $order->get_meta('cryptapi_currency'), $remaining_pending, $this->qrcode_size)['qr_code']);
        }

        $order->save();

        if (!$validation) {
            die("*ok*");
        }
    }

    function thankyou_page($order_id)
    {
        if (WC_CryptAPI_Gateway::$HAS_TRIGGERED) {
            return;
        }
        WC_CryptAPI_Gateway::$HAS_TRIGGERED = true;

        $order = new WC_Order($order_id);
        // run value conversion
        $updated = $this->refresh_value($order);

        if ($updated) {
            $order = new WC_Order($order_id);
        }

        $total = $order->get_total();
        $coins = $this->load_coins();
        $currency_symbol = get_woocommerce_currency_symbol();
        $address_in = $order->get_meta('cryptapi_address');
        $crypto_value = $order->get_meta('cryptapi_total');
        $crypto_coin = $order->get_meta('cryptapi_currency');
        $qr_code_img_value = $order->get_meta('cryptapi_qr_code_value');
        $qr_code_img = $order->get_meta('cryptapi_qr_code');
        $qr_code_setting = $this->get_option('qrcode_setting');
        $color_scheme = $this->get_option('color_scheme');
        $min_tx = $order->get_meta('cryptapi_min');

        $ajax_url = add_query_arg(array(
            'action' => 'cryptapi_order_status',
            'order_id' => $order_id,
        ), home_url('/wp-admin/admin-ajax.php'));

        wp_enqueue_script('ca-payment', CRYPTAPI_PLUGIN_URL . 'static/payment.js', array(), CRYPTAPI_PLUGIN_VERSION, true);
        wp_add_inline_script('ca-payment', "jQuery(function() {let ajax_url = '{$ajax_url}'; setTimeout(function(){check_status(ajax_url)}, 500)})");
        wp_enqueue_style('ca-loader-css', CRYPTAPI_PLUGIN_URL . 'static/cryptapi.css', false, CRYPTAPI_PLUGIN_VERSION);

        $allowed_to_value = array(
            'btc',
            'eth',
            'bch',
            'ltc',
            'miota',
            'xmr',
        );

        $crypto_allowed_value = false;

        $conversion_timer = ((int)$order->get_meta('cryptapi_last_price_update') + (int)$this->refresh_value_interval) - time();
        $cancel_timer = $order->get_date_created()->getTimestamp() + (int)$this->order_cancelation_timeout - time();

        if (in_array($crypto_coin, $allowed_to_value, true)) {
            $crypto_allowed_value = true;
        }

        ?>
        <div class="ca_payment-panel <?php echo esc_attr($color_scheme) ?>">
            <div class="ca_payment_details">
                <?php
                if ($total > 0) {
                    ?>
                    <div class="ca_payments_wrapper">
                        <div class="ca_qrcode_wrapper" style="<?php
                        if ($this->qrcode_default) {
                            echo 'display: block';
                        } else {
                            echo 'display: none';
                        }
                        ?>; width: <?php echo (int)$this->qrcode_size + 20; ?>px;">
                            <?php
                            if ($crypto_allowed_value == true) {
                                ?>
                                <div class="inner-wrapper">
                                    <figure>
                                        <?php
                                        if ($qr_code_setting != 'hide_ammount') {
                                            ?>
                                            <img class="ca_qrcode no_value" <?php
                                            if ($qr_code_setting == 'ammount') {
                                                echo 'style="display:none;"';
                                            }
                                            ?> src="data:image/png;base64,<?php echo $qr_code_img; ?>"
                                                 alt="<?php echo esc_attr(__('QR Code without value', 'cryptapi')); ?>"/>
                                            <?php
                                        }
                                        if ($qr_code_setting != 'hide_without_ammount') {
                                            ?>
                                            <img class="ca_qrcode value" <?php
                                            if ($qr_code_setting == 'without_ammount') {
                                                echo 'style="display:none;"';
                                            }
                                            ?> src="data:image/png;base64,<?php echo $qr_code_img_value; ?>"
                                                 alt="<?php echo esc_attr(__('QR Code with value', 'cryptapi')); ?>"/>
                                            <?php
                                        }
                                        ?>
                                        <div class="ca_qrcode_coin">
                                            <?php echo esc_attr(strtoupper($coins[$crypto_coin]['name'])); ?>
                                        </div>
                                    </figure>
                                    <?php
                                    if ($qr_code_setting != 'hide_ammount' && $qr_code_setting != 'hide_without_ammount') {
                                        ?>
                                        <div class="ca_qrcode_buttons">
                                        <?php
                                        if ($qr_code_setting != 'hide_without_ammount') {
                                            ?>
                                            <button class="ca_qrcode_btn no_value <?php
                                            if ($qr_code_setting == 'without_ammount') {
                                                echo " active";
                                            }
                                            ?>"
                                                    aria-label="<?php echo esc_attr(__('Show QR Code without value', 'cryptapi')); ?>">
                                                <?php echo esc_attr(__('ADDRESS', 'cryptapi')); ?>
                                            </button>
                                            <?php
                                        }
                                        if ($qr_code_setting != 'hide_ammount') {
                                            ?>
                                            <button class="ca_qrcode_btn value<?php
                                            if ($qr_code_setting == 'ammount') {
                                                echo " active";
                                            }
                                            ?>"
                                                    aria-label="<?php echo esc_attr(__('Show QR Code with value', 'cryptapi')); ?>">
                                                <?php echo esc_attr(__('WITH AMOUNT', 'cryptapi')); ?>
                                            </button>
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                </div>
                                <?php
                            } else {
                                ?>
                                <div class="inner-wrapper">
                                    <figure>
                                        <img class="ca_qrcode no_value"
                                             src="data:image/png;base64,<?php echo esc_attr($qr_code_img); ?>"
                                             alt="<?php echo esc_attr(__('QR Code without value', 'cryptapi')); ?>"/>
                                        <div class="ca_qrcode_coin">
                                            <?php echo esc_attr(strtoupper($coins[$crypto_coin]['name'])); ?>
                                        </div>
                                    </figure>
                                    <div class="ca_qrcode_buttons">
                                        <button class="ca_qrcode_btn no_value active"
                                                aria-label="<?php echo esc_attr(__('Show QR Code without value', 'cryptapi')); ?>">
                                            <?php echo esc_attr(__('ADDRESS', 'cryptapi')); ?>
                                        </button>
                                    </div>
                                </div>

                                <?php
                            }
                            ?>
                        </div>
                        <div class="ca_details_box">
                            <div class="ca_details_text">
                                <?php echo esc_attr(__('PLEASE SEND', 'cryptapi')) ?>
                                <button class="ca_copy ca_details_copy"
                                        data-tocopy="<?php echo esc_attr($crypto_value); ?>">
                                    <span><b class="ca_value"><?php echo esc_attr($crypto_value) ?></b></span>
                                    <span><b><?php echo strtoupper(esc_attr($crypto_coin)) ?></b></span>
                                    <span class="ca_tooltip ca_copy_icon_tooltip tip"><?php echo esc_attr(__('COPY', 'cryptapi')); ?></span>
                                    <span class="ca_tooltip ca_copy_icon_tooltip success"
                                          style="display: none"><?php echo esc_attr(__('COPIED!', 'cryptapi')); ?></span>
                                </button>
                                <strong>(<?php echo esc_attr($currency_symbol) . " <span class='ca_fiat_total'>" . esc_attr($total) . "</span>"; ?>
                                    )</strong>
                            </div>
                            <div class="ca_payment_notification ca_notification_payment_received"
                                 style="display: none;">
                                <?php echo sprintf(esc_attr(__('So far you sent %1s. Please send a new payment to complete the order, as requested above', 'cryptapi')),
                                    '<strong><span class="ca_notification_ammount"></span></strong>'
                                ); ?>
                            </div>
                            <div class="ca_payment_notification ca_notification_remaining" style="display: none">
                                <?php echo '<strong>' . esc_attr(__('Notice', 'cryptapi')) . '</strong>: ' . sprintf(esc_attr(__('For technical reasons, the minimum amount for each transaction is %1s, so we adjusted the value by adding the remaining to it.', 'cryptapi')),
                                        $min_tx . ' ' . esc_attr(strtoupper($coins[$crypto_coin]['name'])),
                                        '<span class="ca_notification_remaining"></span>'
                                    ); ?>
                            </div>
                            <?php
                            if ((int)$this->refresh_value_interval != 0) {
                                ?>
                                <div class="ca_time_refresh">
                                    <?php echo sprintf(esc_attr(__('The %1s conversion rate will be adjusted in', 'cryptapi')),
                                        esc_attr(strtoupper($coins[$crypto_coin]['name']))
                                    ); ?>
                                    <span class="ca_time_seconds_count"
                                          data-soon="<?php echo esc_attr(__('a moment', 'cryptapi')); ?>"
                                          data-seconds="<?php echo esc_attr($conversion_timer); ?>"><?php echo esc_attr(date('i:s', $conversion_timer)); ?></span>
                                </div>
                                <?php
                            }
                            ?>
                            <div class="ca_details_input">
                                <span><?php echo esc_attr($address_in) ?></span>
                                <button class="ca_copy ca_copy_icon" data-tocopy="<?php echo esc_attr($address_in); ?>">
                                    <span class="ca_tooltip ca_copy_icon_tooltip tip"><?php echo esc_attr(__('COPY', 'cryptapi')); ?></span>
                                    <span class="ca_tooltip ca_copy_icon_tooltip success"
                                          style="display: none"><?php echo esc_attr(__('COPIED!', 'cryptapi')); ?></span>
                                </button>
                                <div class="ca_loader"></div>
                            </div>
                        </div>
                        <?php
                        if ((int)$this->order_cancelation_timeout !== 0) {
                            ?>
                            <span class="ca_notification_cancel"
                                  data-text="<?php echo __('Order will be cancelled in less than a minute.', 'cryptapi'); ?>">
                                    <?php echo sprintf(esc_attr(__('This order will be valid for %s', 'cryptapi')), '<strong><span class="ca_cancel_timer" data-timestamp="' . $cancel_timer . '">' . date('H:i', $cancel_timer) . '</span></strong>'); ?>
                                </span>
                            <?php
                        }
                        ?>
                        <div class="ca_buttons_container">
                            <a class="ca_show_qr" href="#"
                               aria-label="<?php echo esc_attr(__('Show the QR code', 'cryptapi')); ?>">
                                <span class="ca_show_qr_open <?php
                                if (!$this->qrcode_default) {
                                    echo " active";
                                }
                                ?>"><?php echo __('Open QR CODE', 'cryptapi'); ?></span>
                                <span class="ca_show_qr_close <?php
                                if ($this->qrcode_default) {
                                    echo " active";
                                }
                                ?>"><?php echo esc_attr(__('Close QR CODE', 'cryptapi')); ?></span>
                            </a>
                        </div>
                        <?php
                        if ($this->show_branding) {
                            ?>
                            <div class="ca_branding">
                                <a href="https://cryptapi.io/" target="_blank">
                                    <span>Powered by</span>
                                    <img width="94" class="img-fluid"
                                         src="<?php echo esc_attr(CRYPTAPI_PLUGIN_URL . 'static/files/200_logo_ca.png') ?>"
                                         alt="Cryptapi Logo"/>
                                </a>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                }
                if ($total === 0) {
                    ?>
                    <style>
                        .ca_payment_confirmed {
                            display: block !important;
                            height: 100% !important;
                        }
                    </style>
                    <?php
                }
                ?>
                <div class="ca_payment_processing" style="display: none;">
                    <div class="ca_payment_processing_icon">
                        <div class="ca_loader_payment_processing"></div>
                    </div>
                    <h2><?php echo esc_attr(__('Your payment is being processed!', 'cryptapi')); ?></h2>
                    <h5><?php echo esc_attr(__('Processing can take some time depending on the blockchain.', 'cryptapi')); ?></h5>
                </div>

                <div class="ca_payment_confirmed" style="display: none;">
                    <div class="ca_payment_confirmed_icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path fill="#66BB6A"
                                  d="M504 256c0 136.967-111.033 248-248 248S8 392.967 8 256 119.033 8 256 8s248 111.033 248 248zM227.314 387.314l184-184c6.248-6.248 6.248-16.379 0-22.627l-22.627-22.627c-6.248-6.249-16.379-6.249-22.628 0L216 308.118l-70.059-70.059c-6.248-6.248-16.379-6.248-22.628 0l-22.627 22.627c-6.248 6.248-6.248 16.379 0 22.627l104 104c6.249 6.249 16.379 6.249 22.628.001z"></path>
                        </svg>
                    </div>
                    <h2><?php echo esc_attr(__('Your payment has been confirmed!', 'cryptapi')); ?></h2>
                </div>

                <div class="ca_payment_cancelled" style="display: none;">
                    <div class="ca_payment_cancelled_icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path fill="#c62828"
                                  d="M504 256c0 136.997-111.043 248-248 248S8 392.997 8 256C8 119.083 119.043 8 256 8s248 111.083 248 248zm-248 50c-25.405 0-46 20.595-46 46s20.595 46 46 46 46-20.595 46-46-20.595-46-46-46zm-43.673-165.346l7.418 136c.347 6.364 5.609 11.346 11.982 11.346h48.546c6.373 0 11.635-4.982 11.982-11.346l7.418-136c.375-6.874-5.098-12.654-11.982-12.654h-63.383c-6.884 0-12.356 5.78-11.981 12.654z"></path>
                        </svg>
                    </div>
                    <h2><?php echo esc_attr(__('Order has been cancelled due to lack of payment. Please don\'t send any payment to the address.', 'cryptapi')); ?></h2>
                </div>
                <div class="ca_history" style="display: none;">
                    <table class="ca_history_fill">
                        <tr class="ca_history_header">
                            <th><strong><?php echo esc_attr(__('Time', 'cryptapi')); ?></strong></th>
                            <th><strong><?php echo esc_attr(__('Value Paid', 'cryptapi')); ?></strong></th>
                            <th><strong><?php echo esc_attr(__('FIAT Value', 'cryptapi')); ?></strong></th>
                        </tr>
                    </table>
                </div>
                <?php
                if ($total > 0) {
                    ?>
                    <div class="ca_progress">
                        <div class="ca_progress_icon waiting_payment done">
                            <svg width="60" height="60" viewBox="0 0 50 50" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <path d="M49.2188 25C49.2188 38.3789 38.3789 49.2188 25 49.2188C11.6211 49.2188 0.78125 38.3789 0.78125 25C0.78125 11.6211 11.6211 0.78125 25 0.78125C38.3789 0.78125 49.2188 11.6211 49.2188 25ZM35.1953 22.1777L28.125 29.5508V11.7188C28.125 10.4199 27.0801 9.375 25.7812 9.375H24.2188C22.9199 9.375 21.875 10.4199 21.875 11.7188V29.5508L14.8047 22.1777C13.8965 21.2305 12.3828 21.2109 11.4551 22.1387L10.3906 23.2129C9.47266 24.1309 9.47266 25.6152 10.3906 26.5234L23.3398 39.4824C24.2578 40.4004 25.7422 40.4004 26.6504 39.4824L39.6094 26.5234C40.5273 25.6055 40.5273 24.1211 39.6094 23.2129L38.5449 22.1387C37.6172 21.2109 36.1035 21.2305 35.1953 22.1777V22.1777Z"
                                      fill="#0B4B70"/>
                            </svg>
                            <p><?php echo esc_attr(__('Waiting for payment', 'cryptapi')); ?></p>
                        </div>
                        <div class="ca_progress_icon waiting_network">
                            <svg width="60" height="60" viewBox="0 0 50 50" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <path d="M46.875 15.625H3.125C1.39912 15.625 0 14.2259 0 12.5V6.25C0 4.52412 1.39912 3.125 3.125 3.125H46.875C48.6009 3.125 50 4.52412 50 6.25V12.5C50 14.2259 48.6009 15.625 46.875 15.625ZM42.1875 7.03125C40.8931 7.03125 39.8438 8.08057 39.8438 9.375C39.8438 10.6694 40.8931 11.7188 42.1875 11.7188C43.4819 11.7188 44.5312 10.6694 44.5312 9.375C44.5312 8.08057 43.4819 7.03125 42.1875 7.03125ZM35.9375 7.03125C34.6431 7.03125 33.5938 8.08057 33.5938 9.375C33.5938 10.6694 34.6431 11.7188 35.9375 11.7188C37.2319 11.7188 38.2812 10.6694 38.2812 9.375C38.2812 8.08057 37.2319 7.03125 35.9375 7.03125ZM46.875 31.25H3.125C1.39912 31.25 0 29.8509 0 28.125V21.875C0 20.1491 1.39912 18.75 3.125 18.75H46.875C48.6009 18.75 50 20.1491 50 21.875V28.125C50 29.8509 48.6009 31.25 46.875 31.25ZM42.1875 22.6562C40.8931 22.6562 39.8438 23.7056 39.8438 25C39.8438 26.2944 40.8931 27.3438 42.1875 27.3438C43.4819 27.3438 44.5312 26.2944 44.5312 25C44.5312 23.7056 43.4819 22.6562 42.1875 22.6562ZM35.9375 22.6562C34.6431 22.6562 33.5938 23.7056 33.5938 25C33.5938 26.2944 34.6431 27.3438 35.9375 27.3438C37.2319 27.3438 38.2812 26.2944 38.2812 25C38.2812 23.7056 37.2319 22.6562 35.9375 22.6562ZM46.875 46.875H3.125C1.39912 46.875 0 45.4759 0 43.75V37.5C0 35.7741 1.39912 34.375 3.125 34.375H46.875C48.6009 34.375 50 35.7741 50 37.5V43.75C50 45.4759 48.6009 46.875 46.875 46.875ZM42.1875 38.2812C40.8931 38.2812 39.8438 39.3306 39.8438 40.625C39.8438 41.9194 40.8931 42.9688 42.1875 42.9688C43.4819 42.9688 44.5312 41.9194 44.5312 40.625C44.5312 39.3306 43.4819 38.2812 42.1875 38.2812ZM35.9375 38.2812C34.6431 38.2812 33.5938 39.3306 33.5938 40.625C33.5938 41.9194 34.6431 42.9688 35.9375 42.9688C37.2319 42.9688 38.2812 41.9194 38.2812 40.625C38.2812 39.3306 37.2319 38.2812 35.9375 38.2812Z"
                                      fill="#0B4B70"/>
                            </svg>
                            <p><?php echo esc_attr(__('Waiting for network confirmation', 'cryptapi')); ?></p>
                        </div>
                        <div class="ca_progress_icon payment_done">
                            <svg width="60" height="60" viewBox="0 0 50 50" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <path d="M45.0391 12.5H7.8125C6.94922 12.5 6.25 11.8008 6.25 10.9375C6.25 10.0742 6.94922 9.375 7.8125 9.375H45.3125C46.1758 9.375 46.875 8.67578 46.875 7.8125C46.875 5.22363 44.7764 3.125 42.1875 3.125H6.25C2.79785 3.125 0 5.92285 0 9.375V40.625C0 44.0771 2.79785 46.875 6.25 46.875H45.0391C47.7754 46.875 50 44.7725 50 42.1875V17.1875C50 14.6025 47.7754 12.5 45.0391 12.5ZM40.625 32.8125C38.8994 32.8125 37.5 31.4131 37.5 29.6875C37.5 27.9619 38.8994 26.5625 40.625 26.5625C42.3506 26.5625 43.75 27.9619 43.75 29.6875C43.75 31.4131 42.3506 32.8125 40.625 32.8125Z"
                                      fill="#0B4B70"/>
                            </svg>
                            <p><?php echo esc_attr(__('Payment confirmed', 'cryptapi')); ?></p>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     *  Cronjob
     */
    function ca_cronjob()
    {
        $order_timeout = (int)$this->order_cancelation_timeout;

        if ($order_timeout === 0) {
            return;
        }

        $orders = wc_get_orders(array(
            'status' => array('wc-on-hold'),
            'payment_method' => 'cryptapi',
            'date_created' => '<' . (time() - $order_timeout),
        ));

        if (empty($orders)) {
            return;
        }

        foreach ($orders as $order) {
            $order->update_status('cancelled', __('Order cancelled due to lack of payment.', 'cryptapi'));
            $order->update_meta_data('cryptapi_cancelled', '1');
            $order->save();
        }
    }

    function calc_order($history, $total, $total_fiat)
    {
        $already_paid = 0;
        $already_paid_fiat = 0;
        $remaining = $total;
        $remaining_pending = $total;
        $remaining_fiat = $total_fiat;

        if (!empty($history)) {
            foreach ($history as $uuid => $item) {
                if ((int)$item['pending'] === 0) {
                    $remaining = bcsub(CryptAPI\Helper::sig_fig($remaining, 8), $item['value_paid'], 8);
                }

                $remaining_pending = bcsub(CryptAPI\Helper::sig_fig($remaining_pending, 8), $item['value_paid'], 8);
                $remaining_fiat = bcsub(CryptAPI\Helper::sig_fig($remaining_fiat, 8), $item['value_paid_fiat'], 8);

                $already_paid = bcadd(CryptAPI\Helper::sig_fig($already_paid, 8), $item['value_paid'], 8);
                $already_paid_fiat = bcadd(CryptAPI\Helper::sig_fig($already_paid_fiat, 8), $item['value_paid_fiat'], 8);
            }
        }

        return [
            'already_paid' => (float)$already_paid,
            'already_paid_fiat' => (float)$already_paid_fiat,
            'remaining' => (float)$remaining,
            'remaining_pending' => (float)$remaining_pending,
            'remaining_fiat' => (float)$remaining_fiat
        ];
    }

    /**
     * WooCommerce Subscriptions Integration
     */
    function scheduled_subscription_mail($amount, $renewal_order)
    {

        $order = $renewal_order;

        $costumer_id = get_post_meta($order->get_id(), '_customer_user', true);
        $customer = new WC_Customer($costumer_id);

        if (empty($order->get_meta('cryptapi_paid'))) {
            $mailer = WC()->mailer();

            $recipient = $customer->get_email();

            $subject = sprintf('[%s] %s', get_bloginfo('name'), __('Please renew your subscription', 'cryptapi'));
            $headers = 'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>' . '\r\n';

            $content = wc_get_template_html('emails/renewal-email.php', array(
                'order' => $order,
                'email_heading' => get_bloginfo('name'),
                'sent_to_admin' => false,
                'plain_text' => false,
                'email' => $mailer
            ), plugin_dir_path(dirname(__FILE__)), plugin_dir_path(dirname(__FILE__)));

            $mailer->send($recipient, $subject, $content, $headers);

            $order->add_meta_data('cryptapi_paid', '1');
            $order->save_meta_data();
        }
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

    public function generate_cryptocurrency_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();

        $token = str_replace('_address', '', $key);
        $token_option = $this->get_option('coins');
        if (!empty($token_option)) {
            $token_search = array_search($token, $token_option);
        }

        if ($data['custom_attributes']['counter'] === 0) {
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc"></th>
                <td class="forminp forminp-<?php echo esc_attr($data['type']) ?>">
                    <p>
                        <strong>
                            <?php echo esc_attr(__('Addresses', 'cryptapi')); ?>
                        </strong><br/>
                        <?php echo sprintf(esc_attr(__('If you are using BlockBee you can choose if setting the receiving addresses here bellow or in your BlockBee settings page. %1$s - In order to set the addresses on plugin settings, you need to select “Address Override” while creating the API key. %1$s - In order to set the addresses on BlockBee settings, you need to NOT select “Address Override” while creating the API key.', 'cryptapi')), '<br/>'); ?>
                    </p>
                </td>
            </tr>
            <?php
        }
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <input style="display: inline-block; margin-bottom: -4px;" type="checkbox"
                       name="coins[]" id="<?php echo esc_attr('coins_' . $token); ?>"
                       value="<?php echo str_replace('_address', '', $key); ?>"
                    <?php if (!empty($token_option) && $this->get_option('coins')[$token_search] === $token) {
                        echo 'checked="true" ';
                    } ?> />
                <label style="display: inline-block; width: 80%;" for="<?php echo esc_attr('coins_' . $token); ?>">
                    <?php echo esc_html($data['title']); ?>
                    <span class="woocommerce-help-tip" data-tip="<?php echo esc_html($data['description']); ?>"></span>
                </label>
            </th>
            <td class="forminp forminp-<?php echo esc_attr($data['type']) ?>">
                <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="text"
                       name="<?php echo esc_attr($field_key); ?>"
                       id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>"
                       value="<?php echo $this->get_option($key); ?>"
                       placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.
                ?> />
            </td>
        </tr>

        <?php
        return ob_get_clean();
    }

    function handling_fee()
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $chosen_payment_id = WC()->session->get('chosen_payment_method');

        if ($chosen_payment_id != 'cryptapi') {
            return;
        }

        $total_fee = $this->get_option('fee_order_percentage') === 'none' ? 0 : (float)$this->get_option('fee_order_percentage');

        $fee_order = 0;

        if ($total_fee !== 0 || $this->add_blockchain_fee) {

            if ($total_fee !== 0) {
                $fee_order = (float)WC()->cart->subtotal * $total_fee;
            }

            $selected = WC()->session->get('cryptapi_coin');

            if ($selected === 'none') {
                return;
            }

            if (!empty($selected) && $selected != 'none' && $this->add_blockchain_fee) {
                $est = CryptAPI\Helper::get_estimate($selected);

                $fee_order += (float)$est->{get_woocommerce_currency()};
            }

            if (empty($fee_order)) {
                return;
            }

            WC()->cart->add_fee(__('Service Fee', 'cryptapi'), $fee_order, true);
        }
    }

    function refresh_checkout()
    {
        if (WC_CryptAPI_Gateway::$HAS_TRIGGERED) {
            return;
        }
        WC_CryptAPI_Gateway::$HAS_TRIGGERED = true;
        if (is_checkout()) {
            wp_register_script('cryptapi-checkout', '');
            wp_enqueue_script('cryptapi-checkout');
            wp_add_inline_script('cryptapi-checkout', "jQuery(function ($) { $('form.checkout').on('change', 'input[name=payment_method], #payment_cryptapi_coin', function () { $(document.body).trigger('update_checkout');});});");
        }
    }

    function chosen_currency_value_to_wc_session($posted_data)
    {
        parse_str($posted_data, $fields);

        if (isset($fields['cryptapi_coin'])) {
            WC()->session->set('cryptapi_coin', $fields['cryptapi_coin']);
        }
    }

    public function process_admin_options()
    {
        parent::update_option('coins', $_POST['coins']);
        parent::process_admin_options();
        $this->reset_load_coins();
    }

    function add_email_link($order, $sent_to_admin, $plain_text, $email)
    {
        if (WC_CryptAPI_Gateway::$HAS_TRIGGERED) {
            return;
        }

        if ($email->id == 'customer_on_hold_order') {
            WC_CryptAPI_Gateway::$HAS_TRIGGERED = true;
            echo '<a style="display:block;text-align:center;margin: 40px auto; font-size: 16px; font-weight: bold;" href="' . esc_url($this->get_return_url($order)) . '" target="_blank">' . __('Check your payment status', 'cryptapi') . '</a>';
        }
    }

    function add_order_link($actions, $order)
    {
        if ($order->has_status('on-hold')) {
            $action_slug = 'ca_payment_url';

            $actions[$action_slug] = array(
                'url' => $this->get_return_url($order),
                'name' => __('Pay', 'cryptapi'),
            );
        }

        return $actions;
    }

    function get_private_order_notes($order)
    {
        $results = wc_get_order_notes([
            'order_in' => $order->get_id(),
            'order__in' => $order->get_id()
        ]);

        foreach ($results as $note) {
            if (!$note->customer_note) {
                $order_note[] = array(
                    'note_id' => $note->id,
                    'note_date' => $note->date_created,
                    'note_content' => $note->content,
                );
            }
        }

        return $order_note;
    }

    function order_detail_validate_logs($order)
    {
        if (WC_CryptAPI_Gateway::$HAS_TRIGGERED) {
            return;
        }

        if ($order->is_paid()) {
            return;
        }

        if ($order->get_payment_method() !== 'cryptapi') {
            return;
        }

        $ajax_url = add_query_arg(array(
            'action' => 'cryptapi_validate_logs',
            'order_id' => $order->get_ID(),
        ), home_url('/wp-admin/admin-ajax.php'));
        ?>
        <p class="form-field form-field-wide wc-customer-user">
            <small style="display: block;">
                <?php echo sprintf(esc_attr(__('If the order is not being updated, your ISP is probably blocking our IPs (%1$s and %2$s): please try to get them whitelisted and feel free to contact us anytime to get support (link to our contact page). In the meantime you can refresh the status of any payment by clicking this button below:', 'cryptapi')), '145.239.119.223', '135.125.112.47'); ?>
            </small>
        </p>
        <a style="margin-top: 1rem;margin-bottom: 1rem;" id="validate_callbacks" class="button action" href="#">
            <?php echo esc_attr(__('Check for Callbacks', 'cryptapi')); ?>
        </a>
        <script>
            jQuery(function () {
                const validate_button = jQuery('#validate_callbacks')

                validate_button.on('click', function (e) {
                    e.preventDefault()
                    validate_callbacks()
                    validate_button.html('<?php echo esc_attr(__('Checking', 'cryptapi'));?>')
                })

                function validate_callbacks() {
                    jQuery.getJSON('<?php echo $ajax_url?>').always(function () {
                        window.location.reload()
                    })
                }
            })
        </script>
        <?php
        WC_CryptAPI_Gateway::$HAS_TRIGGERED = true;
    }

    function refresh_value($order)
    {
        $value_refresh = (int)$this->refresh_value_interval;

        if ($value_refresh === 0) {
            return false;
        }

        $woocommerce_currency = get_woocommerce_currency();
        $last_price_update = $order->get_meta('cryptapi_last_price_update');
        $min_tx = (float)$order->get_meta('cryptapi_min');
        $history = json_decode($order->get_meta('cryptapi_history'), true);
        $cryptapi_total = $order->get_meta('cryptapi_total');
        $order_total = $order->get_total('edit');

        $calc = $this->calc_order($history, $cryptapi_total, $order_total);
        $remaining = $calc['remaining'];
        $remaining_pending = $calc['remaining_pending'];

        if ((int)$last_price_update + $value_refresh < time() && !empty($last_price_update) && $remaining === $remaining_pending && $remaining_pending > 0) {
            $cryptapi_coin = $order->get_meta('cryptapi_currency');

            $crypto_conversion = (float)CryptAPI\Helper::get_conversion($woocommerce_currency, $cryptapi_coin, $order_total, $this->disable_conversion);
            $crypto_total = CryptAPI\Helper::sig_fig($crypto_conversion, 8);
            $order->update_meta_data('cryptapi_total', $crypto_total);

            $calc_cron = $this->calc_order($history, $crypto_total, $order_total);
            $crypto_remaining_total = $calc_cron['remaining_pending'];

            if ($remaining_pending <= $min_tx && !$remaining_pending <= 0) {
                $qr_code_data_value = CryptAPI\Helper::get_static_qrcode($order->get_meta('cryptapi_address'), $cryptapi_coin, $min_tx, $this->qrcode_size);
            } else {
                $qr_code_data_value = CryptAPI\Helper::get_static_qrcode($order->get_meta('cryptapi_address'), $cryptapi_coin, $crypto_remaining_total, $this->qrcode_size);
            }

            $order->update_meta_data('cryptapi_qr_code_value', $qr_code_data_value['qr_code']);

            $order->update_meta_data('cryptapi_last_price_update', time());
            $order->save_meta_data();

            return true;
        }

        return false;
    }
}
