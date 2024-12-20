<?php

namespace CryptAPI\Blocks;

require_once CRYPTAPI_PLUGIN_PATH . '/utils/Helper.php';

use CryptAPI\Controllers\WC_CryptAPI_Gateway;
use CryptAPI\Utils\Helper;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Exception;

class WC_CryptAPI_Payments extends AbstractPaymentMethodType {
    /**
     * @var WC_CryptAPI_Gateway
     */
    private $gateway;

    /**
     * @var string
     */
    protected $name = 'cryptapi';

    /**
     * @var array<string,mixed>
     */
    protected $settings = [];

    /**
     * @var string
     */
    private string $scriptId = '';

    /**
     * @return void
     */
    public function __construct()
    {
        add_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', [$this, 'register_style']);
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
        $this->gateway = WC()->payment_gateways->payment_gateways()[$this->name];
    }

    /**
     * @return bool
     */
    public function is_active() {
        return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * @return array<string,mixed>
     */
    public function get_payment_method_data(): array
    {
        $load_coins = \CryptAPI\Controllers\WC_CryptAPI_Gateway::load_coins();
        $output_coins = [];

        foreach ($this->get_setting('coins') as $coin) {
            $output_coins[] = [
                'ticker' => $coin,
                ...$load_coins[$coin]
            ];
        }

        return [
            'name'     => $this->name,
            'label'    => $this->get_setting('title'),
            'icons'    => $this->get_payment_method_icons(),
            'content'  => $this->get_setting('description'),
            'button'   => $this->get_setting('order_button_text'),
            'description'   => $this->get_setting('description'),
            'coins' => $output_coins,
            'show_crypto_logos' => $this-> get_setting('show_crypto_logos') === 'yes',
            'add_blockchain_fee' => $this-> get_setting('add_blockchain_fee') === 'yes',
            'fee_order_percentage' => (float) $this-> get_setting('fee_order_percentage'),
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'translations' => [
                'please_select_cryptocurrency' => __('Please select a Cryptocurrency', 'cryptapi'),
                'error_ocurred' => __('There was an error with the payment. Please try again.', 'cryptapi'),
                'cart_must_be_higher' => __('The cart total must be higher to use this cryptocurrency.', 'cryptapi')
            ],
        ];
    }

    /**
     * @return array<array<string,string>>
     */
    public function get_payment_method_icons(): array
    {
        return [
            [
                'id'  => $this->name,
                'alt' => $this->get_setting('title'),
                'src' => esc_url(CRYPTAPI_PLUGIN_URL) . 'static/files/200_logo_ca.png'
            ]
        ];
    }

    /**
     * @return array<string>
     */
    public function get_payment_method_script_handles(): array
    {
        if (!$this->is_active()) {
            return [];
        }

        $handle = 'cryptapi-' . str_replace(['.js', '_', '.'], ['', '-', '-'], 'blocks.js');

        $version = defined('CRYPTAPI_PLUGIN_VERSION') ? CRYPTAPI_PLUGIN_VERSION : false;

        wp_register_script($handle, CRYPTAPI_PLUGIN_URL . 'static/' . 'blocks.js', [
            'wc-blocks-registry',
            'wc-blocks-checkout',
            'wp-element',
            'wp-i18n',
            'wp-components',
            'wp-blocks',
            'wp-hooks',
            'wp-data',
            'wp-api-fetch'
        ], $version, true);
        wp_localize_script($handle, 'cryptapiData', [
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        return [
            $this->scriptId = $handle
        ];
    }

    /**
     * @return string
     */
    public function register_style(): string
    {
        $handle = 'cryptapi-' . str_replace(['.css', '_', '.'], ['', '-', '-'], 'blocks-styles.css');
        $version = defined('CRYPTAPI_PLUGIN_VERSION') ? CRYPTAPI_PLUGIN_VERSION : false;

        wp_register_style(
            $handle,
            CRYPTAPI_PLUGIN_URL . 'static/' . 'blocks-styles.css',
            [],
            $version
        );
        wp_enqueue_style($handle);

        return $handle;
    }
}
