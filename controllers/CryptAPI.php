<?php

class WC_CryptAPI_Gateway extends WC_Payment_Gateway {
	private static $HAS_TRIGGERED = false;
	private static $COIN_OPTIONS = [];

	function __construct() {
		$this->id                 = 'cryptapi';
		$this->icon               = CRYPTAPI_PLUGIN_URL . 'static/files/200_logo_ca.png';
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
		echo "<div style='margin-top: 2rem;'>If you need any help or have any suggestion, contact us via the <b>live chat</b> on our <b><a href='https://cryptapi.io' target='_blank'>website</a></b> or join our <b><a href='https://discord.gg/pQaJ32SGrR' target='_blank'>Discord server</a></b></div>";
		echo "<div style='margin-top: .5rem;'>If you enjoy this plugin please <b><a href='https://wordpress.org/support/plugin/cryptapi-payment-gateway-for-woocommerce/reviews/#new-post' target='_blank'>rate and review it</a></b>!</div>";
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

				$qr_code_data_value = $ca->get_qrcode( $crypto_total, $this->qrcode_size );
				$qr_code_data       = $ca->get_qrcode( '', $this->qrcode_size );

				$order->add_meta_data( 'cryptapi_nonce', $nonce );
				$order->add_meta_data( 'cryptapi_address', $addr_in );
				$order->add_meta_data( 'cryptapi_total', $crypto_total );
				$order->add_meta_data( 'cryptapi_currency', $selected );
				$order->add_meta_data( 'cryptapi_qr_code_value', $qr_code_data_value['qr_code'] );
				$order->add_meta_data( 'cryptapi_qr_code', $qr_code_data['qr_code'] );
				$order->add_meta_data( 'cryptapi_uri_value', $qr_code_data_value['uri'] );
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

		$order             = new WC_Order( $order_id );
		$total             = $order->get_total();
		$currency_symbol   = get_woocommerce_currency_symbol();
		$address_in        = $order->get_meta( 'cryptapi_address' );
		$crypto_value      = $order->get_meta( 'cryptapi_total' );
		$crypto_coin       = $order->get_meta( 'cryptapi_currency' );
		$qr_code_img_value = $order->get_meta( 'cryptapi_qr_code_value' );
		$qr_code_img       = $order->get_meta( 'cryptapi_qr_code' );
		$payment_uri_value = $order->get_meta( 'cryptapi_uri_value' );
		$payment_uri       = $order->get_meta( 'cryptapi_uri' );

		$ajax_url = add_query_arg( array(
			'action'   => 'cryptapi_order_status',
			'order_id' => $order_id,
		), home_url( '/wp-admin/admin-ajax.php' ) );

		wp_enqueue_script( 'ca-payment', CRYPTAPI_PLUGIN_URL . 'static/payment.js', array(), CRYPTAPI_PLUGIN_VERSION, true );
		wp_add_inline_script( 'ca-payment', "jQuery(function() {let ajax_url = '{$ajax_url}'; setTimeout(function(){check_status(ajax_url)}, 500)})" );
		wp_enqueue_style( 'ca-loader-css', CRYPTAPI_PLUGIN_URL . 'static/cryptapi.css', false, CRYPTAPI_PLUGIN_VERSION );

		?>
        <div class="ca_payment-panel">
            <div class="ca_payment_details">
                <div class="ca_payments_wrapper">
                    <div class="ca_qrcode_wrapper" style="display: none; width: <?php echo intval( $this->qrcode_size ) + 20; ?>px;">
                        <div class="inner-wrapper">
                            <figure>
                                <img class="ca_qrcode no_value" src="data:image/png;base64,<?php echo $qr_code_img; ?>" alt="<?php echo __( 'QR Code without value', 'cryptapi' ); ?>"/>
                                <img class="ca_qrcode value" style="display: none" src="data:image/png;base64,<?php echo $qr_code_img_value; ?>"
                                     alt="<?php echo __( 'QR Code with value', 'cryptapi' ); ?>"/>
                            </figure>
                            <div class="ca_qrcode_buttons">
                                <button class="ca_qrcode_btn no_value active" aria-label="<?php echo __( 'Show QR Code without value', 'cryptapi' ); ?>">
									<?php echo __( 'ADDRESS', 'cryptapi' ); ?>
                                </button>
                                <button class="ca_qrcode_btn value" aria-label="<?php echo __( 'Show QR Code with value', 'cryptapi' ); ?>">
									<?php echo __( 'WITH AMMOUNT', 'cryptapi' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="ca_details_box">
                        <div class="ca_details_text">
							<?php echo __( 'PLEASE SEND', 'cryptapi' ) ?>
                            <button class="ca_copy ca_details_copy" data-tocopy="<?php echo $crypto_value; ?>">
                                <span><b><?php echo $crypto_value ?></b></span>
                                <span><b><?php echo strtoupper( $crypto_coin ) ?></b></span>
                                <span class="ca_tooltip ca_copy_icon_tooltip tip"><?php echo __( 'COPY', 'cryptapi' ); ?></span>
                                <span class="ca_tooltip ca_copy_icon_tooltip success" style="display: none"><?php echo __( 'COPIED!', 'cryptapi' ); ?></span>
                            </button>
                            (<?php echo "{$currency_symbol} {$total}"; ?>)
                        </div>
                        <div class="ca_details_input">
                            <span><?php echo $address_in ?></span>
                            <button class="ca_copy ca_copy_icon" data-tocopy="<?php echo $address_in; ?>">
                                <span class="ca_tooltip ca_copy_icon_tooltip tip"><?php echo __( 'COPY', 'cryptapi' ); ?></span>
                                <span class="ca_tooltip ca_copy_icon_tooltip success" style="display: none"><?php echo __( 'COPIED!', 'cryptapi' ); ?></span>
                            </button>
                            <div class="ca_loader"></div>
                        </div>
                    </div>
                    <div class="ca_buttons_container">
                        <a href="<?php echo $payment_uri_value ?>" target="_blank">
							<?php echo __( 'WALLET', 'cryptapi' ); ?>
                        </a>
                        <a class="ca_show_qr" href="#" aria-label="<?php echo __( 'Show the QR code', 'cryptapi' ); ?>">
							<?php echo __( 'QR CODE', 'cryptapi' ); ?>
                        </a>
                    </div>
					<?php
					if ( $this->show_branding ) {
						?>
                        <div class="ca_branding">
                            <a href="https://cryptapi.io/" target="_blank">
                                <img width="122" class="img-fluid" src="<?php echo CRYPTAPI_PLUGIN_URL . 'static/files/200_logo_ca.png' ?>" alt="Cryptapi Logo"/>
                            </a>
                        </div>
						<?php
					}
					?>
                </div>
                <div class="ca_payment_confirmed" style="display: none">
                    <div class="ca_payment_confirmed_icon">
                        <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="check-circle" class="svg-inline--fa fa-check-circle fa-w-16" role="img"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path fill="#66BB6A"
                                  d="M504 256c0 136.967-111.033 248-248 248S8 392.967 8 256 119.033 8 256 8s248 111.033 248 248zM227.314 387.314l184-184c6.248-6.248 6.248-16.379 0-22.627l-22.627-22.627c-6.248-6.249-16.379-6.249-22.628 0L216 308.118l-70.059-70.059c-6.248-6.248-16.379-6.248-22.628 0l-22.627 22.627c-6.248 6.248-6.248 16.379 0 22.627l104 104c6.249 6.249 16.379 6.249 22.628.001z"></path>
                        </svg>
                    </div>
                    <h2><?php echo __( 'Your payment has been confirmed!', 'cryptapi' ); ?></h2>
                </div>
                <div class="ca_progress">
                    <div class="ca_progress_icon waiting_payment">
                        <svg width="60" height="60" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M49.2188 25C49.2188 38.3789 38.3789 49.2188 25 49.2188C11.6211 49.2188 0.78125 38.3789 0.78125 25C0.78125 11.6211 11.6211 0.78125 25 0.78125C38.3789 0.78125 49.2188 11.6211 49.2188 25ZM35.1953 22.1777L28.125 29.5508V11.7188C28.125 10.4199 27.0801 9.375 25.7812 9.375H24.2188C22.9199 9.375 21.875 10.4199 21.875 11.7188V29.5508L14.8047 22.1777C13.8965 21.2305 12.3828 21.2109 11.4551 22.1387L10.3906 23.2129C9.47266 24.1309 9.47266 25.6152 10.3906 26.5234L23.3398 39.4824C24.2578 40.4004 25.7422 40.4004 26.6504 39.4824L39.6094 26.5234C40.5273 25.6055 40.5273 24.1211 39.6094 23.2129L38.5449 22.1387C37.6172 21.2109 36.1035 21.2305 35.1953 22.1777V22.1777Z"
                                  fill="#0B4B70"/>
                        </svg>
                        <p><?php echo __( 'Waiting for payment', 'cryptapi' ); ?></p>
                    </div>
                    <div class="ca_progress_icon waiting_network">
                        <svg width="60" height="60" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M46.875 15.625H3.125C1.39912 15.625 0 14.2259 0 12.5V6.25C0 4.52412 1.39912 3.125 3.125 3.125H46.875C48.6009 3.125 50 4.52412 50 6.25V12.5C50 14.2259 48.6009 15.625 46.875 15.625ZM42.1875 7.03125C40.8931 7.03125 39.8438 8.08057 39.8438 9.375C39.8438 10.6694 40.8931 11.7188 42.1875 11.7188C43.4819 11.7188 44.5312 10.6694 44.5312 9.375C44.5312 8.08057 43.4819 7.03125 42.1875 7.03125ZM35.9375 7.03125C34.6431 7.03125 33.5938 8.08057 33.5938 9.375C33.5938 10.6694 34.6431 11.7188 35.9375 11.7188C37.2319 11.7188 38.2812 10.6694 38.2812 9.375C38.2812 8.08057 37.2319 7.03125 35.9375 7.03125ZM46.875 31.25H3.125C1.39912 31.25 0 29.8509 0 28.125V21.875C0 20.1491 1.39912 18.75 3.125 18.75H46.875C48.6009 18.75 50 20.1491 50 21.875V28.125C50 29.8509 48.6009 31.25 46.875 31.25ZM42.1875 22.6562C40.8931 22.6562 39.8438 23.7056 39.8438 25C39.8438 26.2944 40.8931 27.3438 42.1875 27.3438C43.4819 27.3438 44.5312 26.2944 44.5312 25C44.5312 23.7056 43.4819 22.6562 42.1875 22.6562ZM35.9375 22.6562C34.6431 22.6562 33.5938 23.7056 33.5938 25C33.5938 26.2944 34.6431 27.3438 35.9375 27.3438C37.2319 27.3438 38.2812 26.2944 38.2812 25C38.2812 23.7056 37.2319 22.6562 35.9375 22.6562ZM46.875 46.875H3.125C1.39912 46.875 0 45.4759 0 43.75V37.5C0 35.7741 1.39912 34.375 3.125 34.375H46.875C48.6009 34.375 50 35.7741 50 37.5V43.75C50 45.4759 48.6009 46.875 46.875 46.875ZM42.1875 38.2812C40.8931 38.2812 39.8438 39.3306 39.8438 40.625C39.8438 41.9194 40.8931 42.9688 42.1875 42.9688C43.4819 42.9688 44.5312 41.9194 44.5312 40.625C44.5312 39.3306 43.4819 38.2812 42.1875 38.2812ZM35.9375 38.2812C34.6431 38.2812 33.5938 39.3306 33.5938 40.625C33.5938 41.9194 34.6431 42.9688 35.9375 42.9688C37.2319 42.9688 38.2812 41.9194 38.2812 40.625C38.2812 39.3306 37.2319 38.2812 35.9375 38.2812Z"
                                  fill="#0B4B70"/>
                        </svg>
                        <p><?php echo __( 'Waiting for network confirmation', 'cryptapi' ); ?></p>
                    </div>
                    <div class="ca_progress_icon payment_done">
                        <svg width="60" height="60" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M45.0391 12.5H7.8125C6.94922 12.5 6.25 11.8008 6.25 10.9375C6.25 10.0742 6.94922 9.375 7.8125 9.375H45.3125C46.1758 9.375 46.875 8.67578 46.875 7.8125C46.875 5.22363 44.7764 3.125 42.1875 3.125H6.25C2.79785 3.125 0 5.92285 0 9.375V40.625C0 44.0771 2.79785 46.875 6.25 46.875H45.0391C47.7754 46.875 50 44.7725 50 42.1875V17.1875C50 14.6025 47.7754 12.5 45.0391 12.5ZM40.625 32.8125C38.8994 32.8125 37.5 31.4131 37.5 29.6875C37.5 27.9619 38.8994 26.5625 40.625 26.5625C42.3506 26.5625 43.75 27.9619 43.75 29.6875C43.75 31.4131 42.3506 32.8125 40.625 32.8125Z"
                                  fill="#0B4B70"/>
                        </svg>
                        <p><?php echo __( 'Payment confirmed', 'cryptapi' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
		<?php
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