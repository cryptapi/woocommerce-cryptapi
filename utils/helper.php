<?php

namespace CryptAPI;

use Exception;

class Helper {
	private static $base_url = "https://api.cryptapi.io";
	private $own_address = null;
	private $payment_address = null;
	private $callback_url = null;
	private $coin = null;
	private $pending = false;
	private $parameters = [];

	public function __construct( $coin, $own_address, $callback_url, $parameters = [], $pending = false ) {
		$this->own_address  = $own_address;
		$this->callback_url = $callback_url;
		$this->coin         = $coin;
		$this->pending      = $pending ? 1 : 0;
		$this->parameters   = $parameters;

	}

	public function get_address() {

		if ( empty( $this->own_address ) || empty( $this->coin ) || empty( $this->callback_url ) ) {
			return null;
		}

		$callback_url = $this->callback_url;
		if ( ! empty( $this->parameters ) ) {
			$req_parameters = http_build_query( $this->parameters );
			$callback_url   = "{$this->callback_url}?{$req_parameters}";
		}

		$ca_params = [
			'callback' => $callback_url,
			'address'  => $this->own_address,
			'pending'  => $this->pending,
		];

		$response = Helper::_request( $this->coin, 'create', $ca_params );

		if ( $response->status == 'success' ) {
			$this->payment_address = $response->address_in;

			return $response->address_in;
		}

		return null;
	}

	public function check_logs() {

		if ( empty( $this->coin ) || empty( $this->callback_url ) ) {
			return null;
		}

		$params = [
			'callback' => $this->callback_url,
		];

		$response = Helper::_request( $this->coin, 'logs', $params );

		if ( $response->status == 'success' ) {
			return $response;
		}

		return null;
	}

	public function get_qrcode( $value, $size = 300 ) {
		if ( empty( $this->coin ) ) {
			return null;
		}

		if ( ! empty( $value ) ) {
			$params = [
				'address' => $this->payment_address,
				'value'   => $value,
				'size'    => $size,
			];
		} else {
			$params = [
				'address' => $this->payment_address,
				'size'    => $size,
			];
		}

		$response = Helper::_request( $this->coin, 'qrcode', $params );

		if ( $response->status == 'success' ) {
			return [ 'qr_code' => $response->qr_code, 'uri' => $response->payment_uri ];
		}

		return null;
	}

	public static function get_supported_coins() {
		$info = Helper::get_info( null, true );

		if ( empty( $info ) ) {
			return null;
		}

		unset( $info['fee_tiers'] );

		$coins = [];

		foreach ( $info as $chain => $data ) {
			$is_base_coin = in_array( 'ticker', array_keys( $data ) );
			if ( $is_base_coin ) {
				$coins[ $chain ] = $data['coin'];
				continue;
			}

			$base_ticker = "{$chain}_";
			foreach ( $data as $token => $subdata ) {
				$chain_upper                    = strtoupper( $chain );
				$coins[ $base_ticker . $token ] = "{$subdata['coin']} ({$chain_upper})";
			}
		}

		return $coins;
	}

	public static function get_info( $coin = null, $assoc = false ) {

		$params = [];

		if ( empty( $coin ) ) {
			$params['prices'] = '0';
		}

		$response = Helper::_request( $coin, 'info', $params, $assoc );

		if ( empty( $coin ) || $response->status == 'success' ) {
			return $response;
		}

		return null;
	}

	public static function get_conversion( $coin, $total, $currency, $disable_conversion ) {

		if ( $disable_conversion ) {
			return $total;
		}

		$params = [
			'value' => $total,
			'from'  => $currency,
		];

		$response = Helper::_request( $coin, 'convert', $params );

		if ( $response->status == 'success' ) {
			return $response->value_coin;
		}

		return null;
	}

	public static function process_callback( $_get ) {
		$params = [
			'address_in'           => $_get['address_in'],
			'address_out'          => $_get['address_out'],
			'txid_in'              => $_get['txid_in'],
			'txid_out'             => isset( $_get['txid_out'] ) ? $_get['txid_out'] : null,
			'confirmations'        => $_get['confirmations'],
			'value'                => $_get['value'],
			'value_coin'           => $_get['value_coin'],
			'value_forwarded'      => isset( $_get['value_forwarded'] ) ? $_get['value_forwarded'] : null,
			'value_forwarded_coin' => isset( $_get['value_forwarded_coin'] ) ? $_get['value_forwarded_coin'] : null,
			'coin'                 => $_get['coin'],
			'pending'              => isset( $_get['pending'] ) ? $_get['pending'] : false,
		];

		foreach ( $_get as $k => $v ) {
			if ( isset( $params[ $k ] ) ) {
				continue;
			}
			$params[ $k ] = $_get[ $k ];
		}

		foreach ( $params as &$val ) {
			$val = sanitize_text_field( $val );
		}

		return $params;
	}

	private static function _request( $coin, $endpoint, $params = [], $assoc = false ) {

		$base_url = Helper::$base_url;
		if ( ! empty( $params ) ) {
			$data = http_build_query( $params );
		}

		if ( ! empty( $coin ) ) {
			$coin = str_replace( '_', '/', $coin );
			$url  = "{$base_url}/{$coin}/{$endpoint}/";
		} else {
			$url = "{$base_url}/{$endpoint}/";
		}

		if ( ! empty( $data ) ) {
			$url .= "?{$data}";
		}

		$response = wp_remote_retrieve_body( wp_remote_get( $url ) );

//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
//        $response = curl_exec($ch);
//        curl_close($ch);

		return json_decode( $response, $assoc );
	}
}