<?php

namespace CryptAPI;

require_once 'blocks/CryptAPI.php';

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

use CryptAPI\Blocks\WC_CryptAPI_Gateway;

class Register {
    public function register() {
        // Register Payment Gateway for legacy WooCommerce
        add_filter('woocommerce_payment_gateways', [$this, 'register_payment_gateway']);

        // Register Payment Gateway for WooCommerce Blocks
        if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            add_action('woocommerce_blocks_payment_method_type_registration', [$this, 'register_blocks_payment_gateway']);
        }
    }

    public function register_payment_gateway($methods) {
        $methods[] = \CryptAPI\Controllers\WC_CryptAPI_Gateway::class;
        return $methods;
    }

    public function register_blocks_payment_gateway(PaymentMethodRegistry $registry) {
        $registry->register(new \CryptAPI\Blocks\WC_CryptAPI_Payments());
    }
}
