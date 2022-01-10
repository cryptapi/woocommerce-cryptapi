<?php

class WC_CryptAPI_Gateway extends WC_Payment_Gateway {
	private static $HAS_TRIGGERED = false;
	private static $COIN_OPTIONS = [];

	function __construct() {
		$this->id                 = 'cryptapi';
		$this->icon               = CRYPTAPI_PLUGIN_URL . 'static/200_logo_ca.png';
		$this->has_fields         = true;
		$this->method_title       = 'CryptAPI';
		$this->method_description = 'CryptAPI allows customers to pay in cryptocurrency';

		$this->supports = array(
			'products',
			'tokenization',
			'add_payment_method',
		);

		$this->load_coins();

		$this->init_form_fields();
		$this->init_settings();
		$this->ca_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'validate_payment' ) );

		add_action( 'wp_ajax_nopriv_' . $this->id . '_order_status', array( $this, 'order_status' ) );
		add_action( 'wp_ajax_' . $this->id . '_order_status', array( $this, 'order_status' ) );

	}

	function load_coins() {
		if ( ! empty( WC_CryptAPI_Gateway::$COIN_OPTIONS ) ) {
			return;
		}

		$transient = get_transient( 'cryptapi_coins' );
		if ( ! empty( $transient ) ) {
			WC_CryptAPI_Gateway::$COIN_OPTIONS = $transient;

			return;
		}

		$coins = CryptAPI\Helper::get_supported_coins();
		set_transient( 'cryptapi_coins', $coins, 86400 );
		WC_CryptAPI_Gateway::$COIN_OPTIONS = $coins;
	}

	function admin_options() {
		parent::admin_options();
		echo "<div style='margin-top: 2rem;'>If you need any help or have any suggestion, contact us via the <b>live chat</b> on our <b><a href='https://cryptapi.io'>website</a></b></div>";
	}

	private function ca_settings() {
		$this->enabled            = $this->get_option( 'enabled' );
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->qrcode_size        = $this->get_option( 'qrcode_size' );
		$this->coins              = $this->get_option( 'coins' );
		$this->show_branding      = $this->get_option( 'show_branding' ) === 'yes';
		$this->disable_conversion = $this->get_option( 'disable_conversion' ) === 'yes';

		if ( ! $this->show_branding ) {
			$this->icon = '';
		}

		foreach ( array_keys( WC_CryptAPI_Gateway::$COIN_OPTIONS ) as $coin ) {
			$this->{$coin . '_address'} = $this->get_option( $coin . '_address' );
		}
	}

	function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enabled', 'cryptapi' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable CryptAPI Payments', 'cryptapi' ),
				'default' => 'yes'
			),
			'title'              => array(
				'title'       => __( 'Title', 'cryptapi' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'cryptapi' ),
				'default'     => __( 'Cryptocurrency', 'cryptapi' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description', 'cryptapi' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => __( 'Payment method description that the customer will see on your checkout', 'cryptapi' )
			),
			'show_branding'      => array(
				'title'   => __( 'Show CryptAPI branding', 'cryptapi' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show CryptAPI logo and credits below the QR code', 'cryptapi' ),
				'default' => 'yes'
			),
			'disable_conversion' => array(
				'title'   => __( 'Disable price conversion', 'cryptapi' ),
				'type'    => 'checkbox',
				'label'   => __( "<b>Attention: This option will disable the price conversion for ALL cryptocurrencies!</b><br/>If you check this, pricing will not be converted from the currency of your shop to the cryptocurrency selected by the user, and users will be requested to pay the same value as shown on your shop, regardless of the cryptocurrency selected", 'cryptapi' ),
				'default' => 'no'
			),
			'qrcode_size'        => array(
				'title'       => __( 'QR Code size', 'cryptapi' ),
				'type'        => 'number',
				'default'     => 300,
				'description' => __( 'QR code image size', 'cryptapi' )
			),
			'coins'              => array(
				'title'       => __( 'Accepted cryptocurrencies', 'cryptapi' ),
				'type'        => 'multiselect',
				'default'     => '',
				'css'         => 'height: 15em;',
				'options'     => WC_CryptAPI_Gateway::$COIN_OPTIONS,
				'description' => __( "Select which coins do you wish to accept. CTRL + click to select multiple", 'cryptapi' ),
			)
		);

		foreach ( WC_CryptAPI_Gateway::$COIN_OPTIONS as $ticker => $coin ) {
			$this->form_fields["{$ticker}_address"] = array(
				'title'       => __( "{$coin} Address", 'cryptapi' ),
				'type'        => 'text',
				'description' => __( "Insert your {$coin} address here. Leave blank if you want to skip this cryptocurrency", 'cryptapi' ),
				'desc_tip'    => true,
			);
		}
	}

	function needs_setup() {
		if ( empty( $this->coins ) || ! is_array( $this->coins ) ) {
			return true;
		}

		foreach ( $this->coins as $val ) {
			if ( ! empty( $this->{$val . '_address'} ) ) {
				return false;
			}
		}

		return true;
	}

	function payment_fields() { ?>
        <div class="form-row form-row-wide">
            <p><?php echo $this->description; ?></p>
            <ul style="list-style: none outside;">
				<?php
				if ( ! empty( $this->coins ) && is_array( $this->coins ) ) {
					foreach ( $this->coins as $val ) {
						$addr = $this->{$val . '_address'};
						if ( ! empty( $addr ) ) { ?>
                            <li>
                                <input id="payment_method_<?php echo $val ?>" type="radio" class="input-radio"
                                       name="cryptapi_coin" value="<?php echo $val ?>"/>
                                <label for="payment_method_<?php echo $val ?>"
                                       style="display: inline-block;"><?php echo __( 'Pay with', 'cryptapi' ) . ' ' . WC_CryptAPI_Gateway::$COIN_OPTIONS[ $val ] ?></label>
                            </li>
							<?php
						}
					}
				} ?>
            </ul>
        </div>
		<?php
	}

	function validate_fields() {
		return array_key_exists( sanitize_text_field( $_POST['cryptapi_coin'] ), WC_CryptAPI_Gateway::$COIN_OPTIONS );
	}

	function process_payment( $order_id ) {
		global $woocommerce;

		$selected = sanitize_text_field( $_POST['cryptapi_coin'] );
		$addr     = $this->{$selected . '_address'};

		if ( ! empty( $addr ) ) {

			$nonce = $this->generate_nonce();

			$callback_url = str_replace( 'https:', 'http:', add_query_arg( array(
				'wc-api'   => 'WC_Gateway_CryptAPI',
				'order_id' => $order_id,
				'nonce'    => $nonce,
			), home_url( '/' ) ) );


			try {
				$order    = new WC_Order( $order_id );
				$total    = $order->get_total( 'edit' );
				$currency = get_woocommerce_currency();

				$info   = CryptAPI\Helper::get_info( $selected );
				$min_tx = floatval( $info->minimum_transaction_coin );

				$crypto_total = CryptAPI\Helper::get_conversion( $selected, $total, $currency, $this->disable_conversion );

				if ( $crypto_total < $min_tx ) {
					wc_add_notice( __( 'Payment error:', 'woocommerce' ) . __( 'Value too low, minimum is', 'cryptapi' ) . ' ' . $min_tx . ' ' . strtoupper( $selected ), 'error' );

					return null;
				}

				$ca      = new CryptAPI\Helper( $selected, $addr, $callback_url, [], true );
				$addr_in = $ca->get_address();

				$qr_code_data = $ca->get_qrcode( $crypto_total, $this->qrcode_size );

				$order->add_meta_data( 'cryptapi_nonce', $nonce );
				$order->add_meta_data( 'cryptapi_address', $addr_in );
				$order->add_meta_data( 'cryptapi_total', $crypto_total );
				$order->add_meta_data( 'cryptapi_currency', $selected );
				$order->add_meta_data( 'cryptapi_qr_code', $qr_code_data['qr_code'] );
				$order->add_meta_data( 'cryptapi_uri', $qr_code_data['uri'] );
				$order->save_meta_data();

				$order->update_status( 'on-hold', __( 'Awaiting payment', 'cryptapi' ) . ': ' . WC_CryptAPI_Gateway::$COIN_OPTIONS[ $selected ] );
				$woocommerce->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);

			} catch ( Exception $e ) {
				wc_add_notice( __( 'Payment error:', 'cryptapi' ) . 'Unknown coin', 'error' );

				return null;
			}
		}

		wc_add_notice( __( 'Payment error:', 'woocommerce' ) . __( 'Payment could not be processed, please try again', 'cryptapi' ), 'error' );

		return null;
	}

	function validate_payment() {
		$data  = CryptAPI\Helper::process_callback( $_GET );
		$order = new WC_Order( $data['order_id'] );

		if ( $order->is_paid() || $data['nonce'] != $order->get_meta( 'cryptapi_nonce' ) ) {
			die( "*ok*" );
		}

		$paid        = floatval( $order->get_meta( 'cryptpi_paid' ) ) + floatval( $data['value_coin'] );
		$crypto_coin = strtoupper( $order->get_meta( 'cryptapi_currency' ) );

		if ( ! $data['pending'] ) {
			$order->add_meta_data( 'cryptapi_paid', $paid );
		}

		if ( $paid >= $order->get_meta( 'cryptapi_total' ) ) {
			if ( $data['pending'] ) {
				$order->add_meta_data( 'cryptapi_pending', "1" );
			} else {
				$order->delete_meta_data( 'cryptapi_pending' );
				$order->payment_complete( $data['address_in'] );
			}
		}

		$order->add_order_note(
			( $data['pending'] ? '[PENDING]' : '' ) .
			__( 'User sent a payment of', 'cryptapi' ) . ' ' .
			$data['value_coin'] . ' ' . $crypto_coin .
			'. TXID: ' . $data['txid_in']
		);
		$order->save_meta_data();
		die( "*ok*" );
	}

	function order_status() {
		$order_id = sanitize_text_field( $_REQUEST['order_id'] );

		try {
			$order = new WC_Order( $order_id );

			$data = [
				'is_paid'    => $order->is_paid(),
				'is_pending' => boolval( $order->get_meta( 'cryptapi_pending' ) ),
			];

			echo json_encode( $data );
			die();

		} catch ( Exception $e ) {
			//
		}

		echo json_encode( [ 'status' => 'error', 'error' => 'not a valid order_id' ] );
		die();
	}

	function thankyou_page( $order_id ) {
		if ( WC_CryptAPI_Gateway::$HAS_TRIGGERED ) {
			return;
		}
		WC_CryptAPI_Gateway::$HAS_TRIGGERED = true;

		$order           = new WC_Order( $order_id );
		$total           = $order->get_total();
		$currency_symbol = get_woocommerce_currency_symbol();
		$address_in      = $order->get_meta( 'cryptapi_address' );
		$crypto_value    = $order->get_meta( 'cryptapi_total' );
		$crypto_coin     = $order->get_meta( 'cryptapi_currency' );
		$qr_code_img     = $order->get_meta( 'cryptapi_qr_code' );
		$payment_uri     = $order->get_meta( 'cryptapi_uri' );

		$ajax_url = add_query_arg( array(
			'action'   => 'cryptapi_order_status',
			'order_id' => $order_id,
		), home_url( '/wp-admin/admin-ajax.php' ) );

		wp_enqueue_script( 'ca-payment', CRYPTAPI_PLUGIN_URL . 'static/payment.js', array(), false, true );
		wp_add_inline_script( 'ca-payment', "jQuery(function() {let ajax_url = '{$ajax_url}'; setTimeout(function(){check_status(ajax_url)}, 500)})" );
		wp_enqueue_style( 'ca-loader-css', CRYPTAPI_PLUGIN_URL . 'static/cryptapi.css' );

		?>
        <div class="payment-panel">
            <div class="ca_loader">
                <div>
                    <div class="lds-css ng-scope">
                        <div class="lds-dual-ring">
                            <div></div>
                            <div>
                                <div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ca_check">
                <img src="<?php echo CRYPTAPI_PLUGIN_URL . 'static/check.png' ?>"/>
            </div>
            <div class="payment_details">
                <h4><?php echo __( 'Waiting for payment', 'cryptapi' ) ?></h4>
                <div class="qrcode_wrapper">
                    <div class="inner-wrapper">
                        <a target="_blank" href="<?php echo $payment_uri ?>">
                            <img src="data:image/png;base64,<?php echo $qr_code_img; ?>"/>
                        </a>
                    </div>
					<?php if ( $this->show_branding ) {
						echo '<div class="cryptapi_branding">powered by <a href="https://cryptapi.io" target="_blank">CryptAPI</a></div>';
					} ?>
                </div>
                <div class="details_box">
					<?php echo __( 'In order to confirm your order, please send', 'cryptapi' ) ?>
                    <span><b><?php echo $crypto_value ?></b></span>
                    <span><b><?php echo strtoupper( $crypto_coin ) ?></b></span>
                    (<?php echo "{$currency_symbol} {$total}"; ?>)
					<?php echo __( 'to', 'cryptapi' ) ?>
                    <span><b><?php echo $address_in ?></b></span>
                </div>
            </div>
            <div class="payment_pending">
                <h4><?php echo __( 'Your payment has been received, awaiting confirmation', 'cryptapi' ) ?></h4>
            </div>
            <div class="payment_complete">
                <h4><?php echo __( 'Your payment has been confirmed!', 'cryptapi' ) ?></h4>
            </div>
        </div>
		<?php
		if ( $this->show_branding ) {
			echo '<style>.payment_details .qrcode_wrapper:before{padding-right: 114px !important}</style>';
		}
	}

	private function generate_nonce( $len = 32 ) {
		$data = str_split( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' );

		$nonce = [];
		for ( $i = 0; $i < $len; $i ++ ) {
			$nonce[] = $data[ mt_rand( 0, sizeof( $data ) - 1 ) ];
		}

		return implode( '', $nonce );
	}

	private function round_sig( $number, $sigdigs = 5 ) {
		$multiplier = 1;
		while ( $number < 0.1 ) {
			$number     *= 10;
			$multiplier /= 10;
		}
		while ( $number >= 1 ) {
			$number     /= 10;
			$multiplier *= 10;
		}

		return round( $number, $sigdigs ) * $multiplier;
	}
}