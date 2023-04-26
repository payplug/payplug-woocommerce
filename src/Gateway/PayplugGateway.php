<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Payplug\Authentication;
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\HttpException;
use Payplug\Exception\ForbiddenException;
use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Admin\Ajax;
use Payplug\PayplugWoocommerce\Controller\IntegratedPayment;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Resource\Payment as PaymentResource;
use Payplug\Resource\Refund as RefundResource;
use WC_Payment_Gateway_CC;
use WC_Payment_Tokens;

/**
 * PayPlug WooCommerce Gateway.
 *
 * @package Payplug\PayplugWoocommerce\Gateway
 */
class PayplugGateway extends WC_Payment_Gateway_CC
{
	const OPTION_NAME = "payplug_config";

	/**
     * @var PayplugGatewayRequirements
     */
    private $requirements;

    /**
     * @var PayplugPermissions
     */
    private $permissions;

    /**
     * @var PayplugResponse
     */
    public $response;

    /**
     * @var PayplugApi
     */
    public $api;

    /**
     * @var \WC_Logger
     */
    protected static $log;

    /**
     * @var bool
     */
    protected static $log_enabled;

    /**
     * @var float
     */
    const MIN_AMOUNT = 0.99;

	/**
	 * @var float
	 */
	const MAX_AMOUNT = 20000;

	/**
	 * @var string
	 */
	private $payplug_merchant_country = 'FR';

	protected $oney_response;
	public $min_oney_price, $oney_thresholds_min;
	public $max_oney_price, $oney_thresholds_max;

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log($message, $level = 'info')
    {
        if (!self::$log_enabled) {
            return;
        }

        if (empty(self::$log)) {
            self::$log = PayplugWoocommerceHelper::is_pre_30() ? new \WC_Logger() : wc_get_logger();
        }

        PayplugWoocommerceHelper::is_pre_30()
            ? self::$log->add('payplug_gateway', $message)
            : self::$log->log($level, $message, array('source' => 'payplug_gateway'));
    }

    /**
     * Construct method
     *
     * @return void
     */
    public function __construct()
    {
		$payplug_gateways = array('payplug', 'american_express', 'apple_pay', 'bancontact', 'oney_x3_with_fees', 'oney_x3_without_fees', 'oney_x4_with_fees', 'oney_x4_without_fees');

		if ((!empty($_GET['section'])) && (in_array($_GET['section'], $payplug_gateways))) {
			$GLOBALS['hide_save_button'] = true;
		}

		//TODO: this should be properties of the class and implemented an interface on all classes (set values)
        $this->id                 = 'payplug';
        $this->icon               = '';
        $this->has_fields         = false;
        $this->method_title       = _x('PayPlug', 'Gateway method title', 'payplug');
        $this->method_description = __('Enable PayPlug for your customers.', 'payplug');
        $this->supports           = array(
            'products',
            'refunds',
            'tokenization',
        );
        $this->new_method_label   = __('Pay with another credit card', 'payplug');

        $this->init_settings();
        $this->requirements = new PayplugGatewayRequirements($this);
        if ($this->user_logged_in()) {
            $this->init_payplug();
        }else{
			delete_option('woocommerce_payplug_settings');
			set_transient( PayplugWoocommerceHelper::get_transient_key(get_option('woocommerce_payplug_settings', [])), null );
		}


		if(is_checkout()){
			$options = get_option('woocommerce_payplug_settings', []);
			if( !$this->get_option('update_gateway') ){
				$this->activate_integrated_payments();
			}

			//refered to https://payplug-prod.atlassian.net/browse/WOOC-772
			if( !$this->get_option('can_use_integrated_payments') && $this->get_option('payment_method') === "integrated"){
				$options["payment_method"] = "redirect";
				update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options) );
			}
		}


        $this->title          = $this->get_option('title');
        $this->description    = $this->get_option('description');
        $this->mode           = 'yes' === $this->get_option('mode', 'no') ? 'live' : 'test';
        $this->debug          = 'yes' === $this->get_option('debug', 'no');
        $this->email          = $this->get_option('email');
        $this->payment_method = $this->get_option('payment_method');
        $this->oneclick       = (('yes' === $this->get_option('oneclick', 'no')) && (is_user_logged_in()));
		$this->oney_type      = $this->get_option('oney_type', 'with_fees');
	    $oney_range = PayplugWoocommerceHelper::get_min_max_oney();

		//TODO:: remove this properties from here and add them on Oney classes
	    $this->min_oney_price = (isset($oney_range['min'])) ? intval($oney_range['min']) : 100;
	    $this->max_oney_price = (isset($oney_range['max'])) ? intval($oney_range['max']) : 3000;
	    $this->oney_thresholds_min = $this->get_option('oney_thresholds_min', $this->min_oney_price );
	    $this->oney_thresholds_max = $this->get_option('oney_thresholds_max', $this->max_oney_price );
        $this->init_form_fields();
        $this->payplug_merchant_country = PayplugWoocommerceHelper::get_payplug_merchant_country();
        $this->oney_product_animation = $this->get_option('oney_product_animation');

        add_filter('woocommerce_get_customer_payment_tokens', [$this, 'filter_tokens'], 10, 3);

        self::$log_enabled = $this->debug;


        // Ensure the description is not empty to correctly display users's save cards
        if (empty($this->description) && 0 !== count($this->get_tokens()) && $this->oneclick_available()) {
            $this->description = ' ';
        }


        if ('test' === $this->mode) {
            $this->description .= " \n";
            $this->description .= __('You are in TEST MODE. In test mode you can use the card 4242424242424242 with any valid expiration date and CVC.', 'payplug');
            $this->description = trim($this->description);
        }

		//add fields of IP to the description
		if($this->payment_method === 'integrated'){
			$this->has_fields = true;
		}

        add_filter('woocommerce_get_order_item_totals', [$this, 'customize_gateway_title'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'scripts']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
		add_action('the_post', [$this, 'validate_payment']);
        add_action('woocommerce_available_payment_gateways', [$this, 'check_gateway']);
	}

	/**
	 * @param $option
	 * @param $value
	 * @return bool|void
	 */
	public function update_option($option, $value = ''){
		if ( $this->needs_setup() ) {
			wp_send_json_error( 'needs_setup' );
			wp_die();
		}

		parent::update_option($option, $value);
	}

	/**
	 * this payment gateway cannot be updated on the wooco payment settings
	 * @return bool
	 */
	public function needs_setup()
	{
		return true;
	}

	/**
     * Customize gateway title in emails.
     *
     * @param array $total_rows
     * @param \WC_Order $order
     *
     * @return array
     *
     * @author Clément Boirie
     */
    public function customize_gateway_title($total_rows, $order)
    {

		$get_payment_method = $this->id;
		if( method_exists($order, "get_payment_method") ) {
			$get_payment_method = $order->get_payment_method();
		}

		$payment_method = PayplugWoocommerceHelper::is_pre_30() ? $order->payment_method : $get_payment_method;
        if (
            $this->id !== $payment_method
            || !isset($total_rows['payment_method'])
        ) {
            return $total_rows;
        }

        $total_rows['payment_method']['value'] = __('Credit card', 'payplug');

        return $total_rows;
    }

    /**
     * Validate order payment when the user is redirected to the success confirmation page.
     *
     * @throws \WC_Data_Exception
     */
    public function validate_payment()
    {
        if (!is_wc_endpoint_url('order-received') || empty($_GET['key'])) {
            return;
        }

        $order_id = wc_get_order_id_by_order_key(wc_clean($_GET['key']));
        if (empty($order_id)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            return;
        }

        $payment_method = PayplugWoocommerceHelper::is_pre_30() ? $order->payment_method : $order->get_payment_method();
        if (!in_array($payment_method, ['payplug', 'oney_x3_with_fees', 'oney_x4_with_fees', 'oney_x3_without_fees', 'oney_x4_without_fees','bancontact', 'apple_pay', 'american_express'])) {
            return;
        }


        $transaction_id = PayplugWoocommerceHelper::is_pre_30() ? get_post_meta($order_id, '_transaction_id', true) : $order->get_transaction_id();
        if (empty($transaction_id)) {
            PayplugGateway::log(sprintf('Order #%s : Missing transaction id.', $order_id), 'error');

            return;
        }

		if($payment_method === $this->id) {

			try {
				$payment = $this->api->payment_retrieve($transaction_id);
			} catch (\Exception $e) {
				PayplugGateway::log(
					sprintf(
						'Order #%s : An error occurred while retrieving the payment data with the message : %s',
						$order_id,
						$e->getMessage()
					)
				);

				return;
			}

			//FIXME:: this is being runned 1 time for each gateway,
			// this comparisson is only needed to only run the process_method one time
			if($payment_method != $this->id){
				return;
			}

			$this->response->process_payment($payment);
		}
    }

    /**
     * Get payment icons.
     *
     * @return string
     */
    public function get_icon()
    {

        $src = ('it_IT' === get_locale())
            ? PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_PostePay.svg'
            : PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_CB.svg';

        $icons = apply_filters('payplug_payment_icons', [
            'payplug' => sprintf('<img src="%s" alt="Visa & Mastercard" class="payplug-payment-icon" />', esc_url($src)),
        ]);

        $icons_str = '';
        foreach ($icons as $icon) {
            $icons_str .= $icon;
        }

        return $icons_str;
    }

    /**
     * Check if this gateway is enabled
     */
    public function is_available()
    {
        if ('yes' === $this->enabled) {
            return $this->requirements->satisfy_requirements() && !empty($this->get_api_key($this->get_current_mode()));
        }

        return parent::is_available();
    }

    /**
     * Load gateway settings.
     */
    public function init_settings()
    {
        parent::init_settings();
        $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
    }

    /**
     * Register gateway settings.
     */
    public function init_form_fields()
    {

        $anchor = esc_html_x( __("More informations", 'payplug'), 'modal', 'payplug' );
		$domain = __( 'support.payplug.com/hc/fr/articles/4408142346002', 'payplug' );
		$link   = sprintf(  ' <a href="https://%s" target="_blank">%s</a>', $domain, $anchor );


		$anchor_bancontact = esc_html_x( __("payplug_bancontact_activation_request", 'payplug'), 'modal', 'payplug' );
		$domain_bancontact = __( 'payplug_bancontact_activation_url', 'payplug' );
		$bancontact_call_to_action = sprintf(  ' <a id="bancontact_call_to_action" href="https://%s" target="_blank">%s</a>', $domain_bancontact, $anchor_bancontact );

        $fields = [
            'enabled'                 => [
                'title'       => __('Enable/Disable', 'payplug'),
                'type'        => 'checkbox',
                'label'       => __('Enable PayPlug', 'payplug'),
                'description' => __('Only Euro payments can be processed with PayPlug.', 'payplug'),
                'default'     => 'no',
            ],
            'title'                   => [
                'title'       => __('Title', 'payplug'),
                'type'        => 'text',
                'description' => __('The payment solution title displayed to your customers during checkout', 'payplug'),
                'default'     => _x('Credit card checkout', 'Default gateway title', 'payplug'),
                'desc_tip'    => false,
            ],
            'description'             => [
                'title'       => __('Description', 'payplug'),
                'type'        => 'text',
                'description' => __('The payment solution description displayed to your customers during checkout', 'payplug'),
                'default'     => '',
                'desc_tip'    => false,
            ],
            'title_connexion'         => [
                'title' => __('Connection', 'payplug'),
                'type'  => 'title',
            ],
            'email'                   => [
                'type'    => 'hidden',
                'default' => '',
            ],
            'login'                   => [
                'type'    => 'login',
                'default' => '',
            ],
            'payplug_test_key'        => [
                'type'    => 'hidden',
                'default' => '',
            ],
            'payplug_live_key'        => [
                'type'    => 'hidden',
                'default' => '',
            ],
            'payplug_merchant_id'     => [
                'type'    => 'hidden',
                'default' => '',
            ],
            'title_testmode'          => [
                'title' => __('Mode', 'payplug'),
                'type'  => 'title',
            ],
            'mode'                    => [
                'title'       => '',
                'label'       => '',
                'type'        => 'yes_no',
                'yes'         => 'Live',
                'no'          => 'Test',
                'description' => __('In TEST mode, all payments will be simulations and will not generate real transactions.', 'payplug'),
                'default'     => 'no',
                'hide_label'  => true,
            ],
            'title_settings'          => [
                'title' => __('Settings', 'payplug'),
                'type'  => 'title',
            ],
            'payment_method'          => [
                'title'       => __('Payment page', 'payplug'),
                'type'        => 'radio',
				'options'     => array(
					'redirect' => __('Redirect', 'payplug'),
					'embedded' => __('Integrated', 'payplug'),
				),
                'description' => __('Customers will be redirected to a PayPlug payment page to finalize the transaction, or payments will be performed in an embeddable payment form on your website.', 'payplug'),
                'default'     => 'redirect',
                'desc_tip'    => false
            ],
            'debug'                   => [
                'title'       => __('Debug', 'payplug'),
                'type'        => 'checkbox',
                'description' => __('Debug mode saves additional information on your server for each operation done via the PayPlug plugin (Developer setting).', 'payplug'),
                'label'       => __('Activate debug mode', 'payplug'),
                'default'     => 'yes',
				'desc_tip'    => false
            ],
            'title_advanced_settings' => [
                'title'       => __('payplug_advanced_settings', 'payplug'),
                'description' => __(
                    'Your current offer does not allow this option. Try it on TEST mode. More information <a href="https://www.payplug.com/pricing" target="_blank">here.</a>',
                    'payplug'
                ),
                'type'        => 'title',
            ],
            'oneclick'                => [
                'title'       => __('One Click Payment', 'payplug'),
                'type'        => 'checkbox',
                'label'       => __('Activate', 'payplug'),
                'description' => __('Allow your customers to save their credit card information for later purchases.', 'payplug'),
                'default'     => 'no',
				'desc_tip'    => false
            ],
			'bancontact'                 => [
				'title'       => __('payplug_bancontact_activate_title', 'payplug'),
				'type'        => 'checkbox',
				'label'       => __('Activate', 'payplug'),
				'description' => '<p class="description" id="bancontact_test_mode_description"> '. __('payplug_bancontact_testmode_description', 'payplug') .' </p>' .
								 '<p class="description" id="bancontact_live_mode_description_disabled"> '. __('payplug_bancontact_livemode_description_disabled', 'payplug') .' </p>' .
								 $bancontact_call_to_action,
				'default'     => 'no',
			],
			'apple_pay'                 => [
				'title'       => __('payplug_apple_pay_activate_title', 'payplug'),
				'type'        => 'checkbox',
				'label'       => __('Activate', 'payplug'),
				'description' => '<p class="description" id="apple_pay_test_mode_description"> '. __('payplug_apple_pay_testmode_description', 'payplug') .' </p>' .
								 '<p class="description" id="apple_pay_live_mode_description"> '. __('payplug_apple_pay_livemode_description', 'payplug') .' </p>' ,
				'default'     => 'no',
			],
			'american_express'          => [
				'title'       => __('payplug_amex_title', 'payplug'),
				'type'        => 'checkbox',
				'label'       => __('payplug_amex_activate', 'payplug'),
				'description' => '<p class="description" id="amex_test_mode_description"> '. __('payplug_amex_testmode_description', 'payplug') .' </p>' .
								 '<p class="description" id="amex_live_mode_description"> '. __('payplug_amex_livemode_description', 'payplug') .' </p>' ,
				'default'     => 'no',
			],
			'oney'                => [
				'title'       => __('3x 4x Oney payments', 'payplug'),
				'type'        => 'checkbox',
				'label'       => __('Activate', 'payplug'),
				// TRAD
				'description' => sprintf(__('Allow your customers to split payments into 3 or 4 installments, for orders between %s€ and %s€', 'payplug'), $this->min_oney_price, $this->max_oney_price) . $link,
				'default'     => 'no',
				'desc_tip'    => false
			],
	        'oney_type'           => [
		        'title'       => '',
		        'type'        => 'oney_type',
		        'options'     => array(
			        'with_fees' => __('Oney with fees', 'payplug'),
			        'without_fees' => __('Oney without fees', 'payplug'),
		        ),
		        'descriptions'     => array(
			        'with_fees' => __('The fees are split between you and your customers', 'payplug'),
			        'without_fees' => __('You pay the fees', 'payplug'),
		        ),
		        'description' => '',
		        'default'     => 'with_fees',
		        'desc_tip'    => false
	        ],
	        'oney_thresholds'     => [
		        'title'       => '',
		        'type'        => 'oney_thresholds',
		        'description' => sprintf(__('I would like to offer guaranteed payment in installments for amounts between %s€ and %s€.', 'payplug'),
                    '<b class="min">' . $this->oney_thresholds_min . '</b>', '<b class="max">' . $this->oney_thresholds_max . '</b>'),
		        'desc_tip'    => false
	        ],
	        'oney_thresholds_min' => [
		        'title'       => '',
		        'type'        => 'hidden',
		        'label'       => '',
		        'description' => '',
		        'default'     => 'no',
	        ],
	        'oney_thresholds_max' => [
		        'title'       => '',
		        'type'        => 'hidden',
		        'label'       => '',
		        'description' => '',
		        'default'     => 'no',
	        ],
			'oney_product_animation' => [
				'title'       => __('oney_installments_pop_up', 'payplug'),
				'description' => __('display_the_oney_installments_pop_up_on_the_product_page', 'payplug'),
				'label'       => __('Activate', 'payplug'),
				'default'     => 'no',
				'desc_tip'    => false,
				'type' => 'oney_product_animation'
			],
        ];

        if ($this->user_logged_in()) {
            if ($this->permissions->has_permissions(PayplugPermissions::SAVE_CARD)) {
                unset($fields['title_advanced_settings']);
            } else if  ('live' === $this->get_current_mode()){
                $fields['oneclick']['disabled'] = true;
            }
        }

        /**
         * Filter PayPlug gateway settings.
         *
         * @param array $fields
         */
        $fields            = apply_filters('payplug_gateway_settings', $fields);
        $this->form_fields = $fields;
    }

    /**
     * Set global configuration for PayPlug instance.
     */
    public function init_payplug()
    {
        $this->api = new PayplugApi($this);
        $this->api->init();

        $this->permissions = new PayplugPermissions($this);
        $this->response    = new PayplugResponse($this);

        // Register IPN handler
        new PayplugIpnResponse($this);

    }

	public function integrated_payments_scripts(){

		$translations = array(
			"cardholder" =>  __('payplug_integrated_payment_cardholder', 'payplug'),
			"your_card" =>  __('payplug_integrated_payment_your_card', 'payplug'),
			"card_number" =>  __('payplug_integrated_payment_card_number', 'payplug'),
			"expiration_date" =>  __('payplug_integrated_payment_expiration_date', 'payplug'),
			"cvv" =>  __('payplug_integrated_payment_cvv', 'payplug'),
			"one_click" =>  __('payplug_integrated_payment_oneClick', 'payplug'),
			'ajax_url' => \WC_AJAX::get_endpoint('payplug_create_order'),
			'order_review_url' => \WC_AJAX::get_endpoint('payplug_order_review_url'),
			'nonce'    =>  wp_create_nonce('woocommerce-process_checkout'),
			'mode' => PayplugWoocommerceHelper::check_mode(), // true for TEST, false for LIVE
			'check_payment_url' => \WC_AJAX::get_endpoint('payplug_check_payment')
		);

		//TODO:: if integrated payment is active please active form and comment the one above
		/**x
		 * Integrated payments scripts
		 */
		wp_enqueue_style('payplugIP', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-integrated-payments.css', [], PAYPLUG_GATEWAY_VERSION);
		wp_enqueue_script('payplug-integrated-payments-api', 'https://cdn-qa.payplug.com/js/integrated-payment/v1@1/index.js', [], 'v1.1', true);

		wp_enqueue_script('payplug-integrated-payments', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-integrated-payments.js', ['jquery', 'payplug-integrated-payments-api'], 'v1.1', true);
		wp_localize_script( 'payplug-integrated-payments', 'payplug_integrated_payment_params', $translations);
	}

    /**
     * Embedded payment form scripts.
     *
     * Register scripts and additionnal data needed for the
     * embedded payment form.
     */
    public function scripts()
    {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page() && !isset($_GET['change_payment_method'])) {
            return;
        }

        // If PayPlug is not enabled bail.
        if ('no' === $this->enabled) {
            return;
        }

        // If keys are not set bail.
        if (empty($this->get_api_key($this->mode))) {
            PayplugGateway::log('Keys are not set correctly.');

            return;
        }

		// Register checkout styles.
		wp_register_style('payplug-checkout', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-checkout.css', [], PAYPLUG_GATEWAY_VERSION);
		wp_enqueue_style('payplug-checkout');

		//load Integrated Payment features
		if($this->payment_method === 'integrated'){
			$this->integrated_payments_scripts();

		}else{

			//load popup features
			//TODO:: if integrated payment is not active please active this and comment the one bellow
			wp_register_script('payplug', 'https://api.payplug.com/js/1/form.latest.js', [], null, true);
			wp_register_script('payplug-checkout', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-checkout.js', [
				'jquery',
				'payplug'
			], PAYPLUG_GATEWAY_VERSION, true);
			wp_localize_script('payplug-checkout', 'payplug_checkout_params', [
				'ajax_url' => \WC_AJAX::get_endpoint('payplug_create_order'),
				'order_review_url' => \WC_AJAX::get_endpoint('payplug_order_review_url'),
				'nonce'    => [
					'checkout' => wp_create_nonce('woocommerce-process_checkout'),
				],
				'is_embedded' => 'redirect' !== $this->payment_method
			]);

			wp_enqueue_script('payplug-checkout');
		}


    }

    /**
     * Filter saved tokens for the gateway.
     *
     * A token will be removed if :
     * - it doesn't match the current merchant logged in,
     * - or it doesn't match the current gateway mode,
     * - or it is expired.
     *
     * @param array $tokens
     * @param int $user_id
     * @param string $gateway_id
     *
     * @return array
     */
    public function filter_tokens($tokens, $user_id, $gateway_id)
    {

        if (!is_user_logged_in() || !class_exists('WC_Payment_Gateway_CC')) {
            return $tokens;
        }

        /* @var \WC_Payment_Token_CC $token */
        foreach ($tokens as $k => $token) {

            if ($this->id !== $token->get_gateway_id()) {
                continue;
            }

            // check if token is associated with a merchant id and if it match the current one
            $token_merchant_id = $token->get_meta('payplug_account', true);
            if (empty($token_merchant_id) || $this->get_merchant_id() !== $token_merchant_id) {
                unset($tokens[$k]);
                continue;
            }

            // check if token is available for the current gateway mode
            if ($this->mode !== $token->get_meta('mode', true)) {
                unset($tokens[$k]);
                continue;
            }

            // check if token is not expired
            $current_month = \absint(date('n'));
            $current_year  = \absint(date('Y'));
            if ($current_year > (int) $token->get_expiry_year()) {
                unset($tokens[$k]);
                continue;
            }

            if ($current_year === (int) $token->get_expiry_year() && $current_month > (int) $token->get_expiry_month()) {
                unset($tokens[$k]);
                continue;
            }
        }

        return $tokens;
    }

    public function payment_fields()
    {
        $description = $this->get_description();

		if (!empty($description)) {
			echo wpautop(wptexturize($description));
		}

		if(($this->payment_method === 'integrated') && ($this->id == 'payplug')){
				echo IntegratedPayment::template_form($this->oneclick);
		}

        if ($this->oneclick_available()) {
            $this->tokenization_script();
            $this->saved_payment_methods();
        }
    }

    /**
     * Handle admin display.
     */
    public function admin_options()
    {
		/************ VUE Code *************/

		wp_enqueue_script('chunk-vendors.js', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/dist/js/chunk-vendors-1.3.0.js', [], PAYPLUG_GATEWAY_VERSION);
		wp_enqueue_script('app.js', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/dist/js/app-1.3.0.js', [], PAYPLUG_GATEWAY_VERSION);
		wp_enqueue_style('app.css', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/dist/css/app.css', [], PAYPLUG_GATEWAY_VERSION);
		wp_localize_script('app.js', 'payplug_admin_config',
			array(
				"img_path"		=> esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/dist/'),
				'ajax_url'      => get_home_url() . DIRECTORY_SEPARATOR . 'index.php'
			));

		?>
		<script>window.get_data_url = "<?php echo rest_url('payplug/data'); ?>"</script>
		<script>window.set_data_url = "<?php echo rest_url('payplug/save_data'); ?>"</script>
		<div id="payplug_admin"></div>

		<?php

		/*********** End VUE Code ***********/

    }

    /**
     * Process admin options.
     *
     * @return bool
     */
    public function process_admin_options()
    {
        $data = $this->get_post_data();
        $oneclick_fieldkey = $this->get_field_key('oneclick');

        // Handle logout process
        if (
            isset($data['submit_logout'])
            && false !== check_admin_referer('payplug_user_logout', '_logoutaction')
        ) {

            if ($this->permissions) {
                $this->permissions->clear_permissions();
            }

            if(PayplugWoocommerceHelper::payplug_logout($this)) {
                \WC_Admin_Settings::add_message(__('Successfully logged out.', 'payplug'));
            }

            return true;
        }

        // Handle login process
        if (
            isset($data['payplug_email'])
            && false !== check_admin_referer('payplug_user_login', '_loginaction')
        ) {
            $email    = $data['payplug_email'];
            $password = wp_unslash($data['payplug_password']);
            $response = $this->retrieve_user_api_keys($email, $password);
            if (is_wp_error($response)) {
                \WC_Admin_Settings::add_error($response->get_error_message());

                return false;
            }

            // try to use the api keys to retrieve the merchant id
            $merchant_id = isset($response['test']) ? $this->retrieve_merchant_id($response['test']) : '';

            $this->init_form_fields();
            $fields = $this->get_form_fields();
            $data   = [];

            // Load existing values if the user is re-login.
            foreach ($fields as $key => $field) {
                if (in_array($field['type'], ['title', 'login'])) {
                    continue;
                }

                switch ($key) {
                    case 'enabled':
                        $val = 'yes';
                        break;
                    case 'mode':
                        $val = 'no';
                        break;
                    case 'payplug_test_key':
                        $val = !empty($response['test']) ? esc_attr($response['test']) : null;
                        break;
                    case 'payplug_live_key':
                        $val = !empty($response['live']) ? esc_attr($response['live']) : null;
                        break;
                    case 'payplug_merchant_id':
                        $val = esc_attr($merchant_id);
                        break;
                    case 'email':
                        $val = esc_html($email);
                        break;
                    default:
                        $val = $this->get_option($key);
                }

                $data[$key] = $val;
            }

            $this->set_post_data($data);
            update_option(
                $this->get_option_key(),
                apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $data)
            );
            if("payplug" === $this->id) {
                \WC_Admin_Settings::add_message(__('Successfully logged in.', 'payplug'));
            }

            return true;
        }

        // Don't let user without live key leave TEST mode.
        $mode_fieldkey     = $this->get_field_key('mode');
        $live_key_fieldkey = $this->get_field_key('payplug_live_key');
        if (isset($data[$mode_fieldkey]) && '1' === $data[$mode_fieldkey] && empty($data[$live_key_fieldkey])) {
            $data[$mode_fieldkey] = null;
            $this->set_post_data($data);
            \WC_Admin_Settings::add_error(__('Your account does not support LIVE mode at the moment, it must be validated first. If your account has already been validated, please log out and log in again.', 'payplug'));
        }

        // Check user permissions before activating one-click feature.
        $oneclick_fieldkey = $this->get_field_key('oneclick');
        if (
            isset($data[$oneclick_fieldkey])
            && '1' === $data[$oneclick_fieldkey]
            && '1' === $data[$mode_fieldkey]
            && (!$this->user_logged_in()
                || false === $this->permissions->has_permissions(PayplugPermissions::SAVE_CARD))
        ) {
            $data[$oneclick_fieldkey] = null;
            \WC_Admin_Settings::add_error(__('Only PREMIUM accounts can enable the One Click option in LIVE mode.', 'payplug'));
        }

        // Force getAccount to set transient data on live mode
        if (
            $mode_fieldkey === "woocommerce_payplug_mode" &&
            "1" === $data[$mode_fieldkey] &&
            !empty($data[$live_key_fieldkey])
        ) {
			try{
				$response = Authentication::getAccount(new Payplug($data[$live_key_fieldkey]));
			}  catch (ForbiddenException $e){
				PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
				\WC_Admin_Settings::add_error($e->getMessage());
				return false;
			}
            PayplugWoocommerceHelper::set_transient_data($response, [
                'mode' => 'yes'
            ]);
        }

        // Validate Oney thresholds
	    if($data['woocommerce_payplug_oney_thresholds_min'] < $this->min_oney_price || $data['woocommerce_payplug_oney_thresholds_max'] > $this->max_oney_price){
		    \WC_Admin_Settings::add_error(sprintf(__('The amount must be between %s€ and %s€.', 'payplug'), $this->min_oney_price, $this->max_oney_price));
		    return false;
	    }
	    if($data['woocommerce_payplug_oney_thresholds_min'] > $data['woocommerce_payplug_oney_thresholds_max']){
		    \WC_Admin_Settings::add_error(sprintf(__('Please note that the minimum amount entered is greater than the maximum amount entered.', 'payplug'), $this->min_oney_price, $this->max_oney_price));
		    return false;
	    }

        $this->data = $data;
        parent::process_admin_options();
    }

    /**
     * Process payment.
     *
     * @param int $order_id
     *
     * @return array
     * @throws \Exception
     */
    public function process_payment($order_id)
    {

        PayplugGateway::log(sprintf('Processing payment for order #%s', $order_id));

        $order       = wc_get_order($order_id);
        $customer_id = PayplugWoocommerceHelper::is_pre_30() ? $order->customer_user : $order->get_customer_id();
        $amount      = (int) PayplugWoocommerceHelper::get_payplug_amount($order->get_total());
        $amount      = $this->validate_order_amount($amount);

		if (is_wp_error($amount)) {
            PayplugGateway::log(sprintf('Invalid amount %s for the order.', $order->get_total()), 'error');
            throw new \Exception($amount->get_error_message());
        }

        $payment_token_id = (isset($_POST['wc-' . $this->id . '-payment-token']) && 'new' !== $_POST['wc-' . $this->id . '-payment-token'])
            ? wc_clean($_POST['wc-' . $this->id . '-payment-token'])
            : false;

        if ($payment_token_id && $this->oneclick_available() && (int) $customer_id > 0) {
            PayplugGateway::log(sprintf('Payment token found.', $amount));

            return $this->process_payment_with_token($order, $amount, $customer_id, $payment_token_id);
        }

        return $this->process_standard_payment($order, $amount, $customer_id);
    }

    /**
     * @param \WC_Order $order
     * @param int $amount
     * @param int $customer_id
     *
     * @return array
     * @throws \Exception
     */
    public function process_standard_payment($order, $amount, $customer_id)
    {

        $order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();

        try {
            $address_data = PayplugAddressData::from_order($order);

			$return_url = esc_url_raw($order->get_checkout_order_received_url());

			if (!(substr( $return_url, 0, 4 ) === "http")) {
				$return_url = get_site_url().$return_url;
			}

			$payment_data = [
                'amount'           => $amount,
                'currency'         => get_woocommerce_currency(),
                'allow_save_card'  => $this->oneclick_available() && (int) $customer_id > 0,
                'billing'          => $address_data->get_billing(),
                'shipping'         => $address_data->get_shipping(),
                'hosted_payment'   => [
                    'return_url' => $return_url,
                    'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),
                ],
                'notification_url' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
                'metadata'         => [
                    'order_id'    => $order_id,
                    'customer_id' => ((int) $customer_id > 0) ? $customer_id : 'guest',
                    'domain'      => $this->limit_length(esc_url_raw(home_url()), 500),
                ],
            ];

			if($this->payment_method === 'integrated'){
				$payment_data['initiator'] = 'PAYER';
				$payment_data['integration'] = 'INTEGRATED_PAYMENT';
				unset($payment_data['hosted_payment']['cancel_url']);
			}

            /**
             * Filter the payment data before it's used
             *
             * @param array $payment_data
             * @param int $order_id
             * @param array $customer_details
             * @param PayplugAddressData $address_data
             */
            $payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $order_id, [], $address_data);
            $payment      = $this->api->payment_create($payment_data);

            // Save transaction id for the order
            PayplugWoocommerceHelper::is_pre_30()
                ? update_post_meta($order_id, '_transaction_id', $payment->id)
                : $order->set_transaction_id($payment->id);

            if (is_callable([$order, 'save'])) {
                $order->save();
            }

            /**
             * Fires once a payment has been created.
             *
             * @param int $order_id Order ID
             * @param PaymentResource $payment Payment resource
             */
            \do_action('payplug_gateway_payment_created', $order_id, $payment);

            $metadata = PayplugWoocommerceHelper::extract_transaction_metadata($payment);
            PayplugWoocommerceHelper::save_transaction_metadata($order, $metadata);

            PayplugGateway::log(sprintf('Payment creation complete for order #%s', $order_id));

            return [
				'payment_id' => $payment->id,
				'result'   => 'success',
				'redirect' => !empty($payment->hosted_payment->payment_url) ? $payment->hosted_payment->payment_url : $return_url,
				'cancel'   => $payment->hosted_payment->cancel_url,
			];

        } catch (HttpException $e) {
            PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');
            throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
        } catch (\Exception $e) {
            PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, $e->getMessage()), 'error');
            throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
        }
    }

    /**
     * @param \WC_Order $order
     * @param int $amount
     * @param int $customer_id
     * @param string $token_id
     *
     * @return array
     * @throws \Exception
     */
    public function process_payment_with_token($order, $amount, $customer_id, $token_id)
    {

        $order_id      = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
        $payment_token = WC_Payment_Tokens::get($token_id);
        if (!$payment_token || (int) $customer_id !== (int) $payment_token->get_user_id()) {
            PayplugGateway::log('Could not find the payment token or the payment doesn\'t belong to the current user.', 'error');
            throw new \Exception(__('Invalid payment method.', 'payplug'));
        }

        try {
            $address_data = PayplugAddressData::from_order($order);

			$return_url = esc_url_raw($order->get_checkout_order_received_url());

			if (!(substr( $return_url, 0, 4 ) === "http")) {
				$return_url = get_site_url().$return_url;
			}

            $payment_data = [
                'amount'           => $amount,
                'currency'         => get_woocommerce_currency(),
                'payment_method'   => $payment_token->get_token(),
                'allow_save_card'  => false,
                'billing'          => $address_data->get_billing(),
                'shipping'         => $address_data->get_shipping(),
                'initiator'        => 'PAYER',
                'hosted_payment'   => [
                    'return_url' => $return_url,
                    'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),
                ],
                'notification_url' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
                'metadata'         => [
                    'order_id'    => $order_id,
                    'customer_id' => ((int) $customer_id > 0) ? $customer_id : 'guest',
                    'domain'      => $this->limit_length(esc_url_raw(home_url()), 500),
                ],
            ];

            /** This filter is documented in src/Gateway/PayplugGateway */
            $payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $order_id, [], $address_data);
            $payment      = $this->api->payment_create($payment_data);

            /** This action is documented in src/Gateway/PayplugGateway */
            \do_action('payplug_gateway_payment_created', $order_id, $payment);

            $this->response->process_payment($payment, true);

            PayplugGateway::log(sprintf('Payment process complete for order #%s', $order_id));

			if(($payment->__get('is_paid'))){
				$redirect =  $order->get_checkout_order_received_url();
			}else if(isset($payment->__get('hosted_payment')->payment_url)){
				$redirect = $payment->__get('hosted_payment')->payment_url;
			}else{
				$redirect = $return_url;
			}

            return [
				'payment_id' => $payment->id,
                'result'   => 'success',
                'is_paid'  => $payment->__get('is_paid'), // Use for path redirect before DSP2
                'redirect' => $redirect
            ];
        } catch (HttpException $e) {
            PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');
            throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
        } catch (\Exception $e) {
            PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, $e->getMessage()), 'error');
            throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
        }
    }

    /**
     * Process refund for an order paid with PayPlug gateway.
     *
     * @param int $order_id
     * @param null $amount
     * @param string $reason
     *
     * @return bool|\WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        PayplugGateway::log(sprintf('Processing refund for order #%s', $order_id));

		if( !$this->user_logged_in()){
			PayplugGateway::log(__('You must be logged in with your PayPlug account.', 'payplug'), 'error');
			return new \WP_Error('process_refund_error', __('You must be logged in with your PayPlug account.', 'payplug'));
		}

        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            PayplugGateway::log(sprintf('The order #%s does not exist.', $order_id), 'error');

            return new \WP_Error('process_refund_error', sprintf(__('The order %s does not exist.', 'payplug'), $order_id));
        }

        if ($order->get_status() === "cancelled") {
            PayplugGateway::log(sprintf('The order #%s cannot be refund.', $order_id), 'error');

            return new \WP_Error('process_refund_error', sprintf(__('The order %s cannot be refund.', 'payplug'), $order_id));
        }

        $transaction_id = PayplugWoocommerceHelper::is_pre_30() ? get_post_meta($order_id, '_transaction_id', true) : $order->get_transaction_id();
        if (empty($transaction_id)) {
            PayplugGateway::log(sprintf('The order #%s does not have PayPlug transaction ID associated with it.', $order_id), 'error');

            return new \WP_Error('process_refund_error', __('No PayPlug transaction was found for this order. The refund could not be processed.', 'payplug'));
        }

        $customer_id = PayplugWoocommerceHelper::is_pre_30() ? $order->customer_user : $order->get_customer_id();

        $data = [
            'metadata' => [
                'order_id'    => $order_id,
                'customer_id' => ((int) $customer_id > 0) ? $customer_id : 'guest',
                'refund_from' => 'woocommerce',
            ]
        ];

        if (!is_null($amount)) {
            $data['amount'] = PayplugWoocommerceHelper::get_payplug_amount($amount);
        }

        if (!empty($reason)) {
            $data['metadata']['reason'] = $reason;
        }

        /**
         * Filter the refund data before it's used.
         *
         * @param array $data
         * @param int $order_id
         * @param string $transaction_id
         */
        $data = apply_filters('payplug_gateway_refund_data', $data, $order_id, $transaction_id);

        try {
            $refund = $this->api->refund_create($transaction_id, $data);

            /**
             * Fires once a refund has been created.
             *
             * @param int $order_id Order ID
             * @param RefundResource $refund Refund resource
             * @param string $transaction_id Transaction id
             */
            \do_action('payplug_gateway_refund_created', $order_id, $refund, $transaction_id);

            $refund_meta_key = sprintf('_pr_%s', wc_clean($refund->id));
            if (PayplugWoocommerceHelper::is_pre_30()) {
                update_post_meta($order_id, $refund_meta_key, $refund->id);
            } else {
                $order->add_meta_data($refund_meta_key, $refund->id, true);
                $order->save();
            }

            $note = sprintf(__('Refund %s : Refunded %s', 'payplug'), wc_clean($refund->id), wc_price(((int) $refund->amount) / 100));
            if (!empty($refund->metadata['reason'])) {
                $note .= sprintf(' (%s)', esc_html($refund->metadata['reason']));
            }
            $order->add_order_note($note);

            try {
                $payment  = $this->api->payment_retrieve($transaction_id);
                $metadata = PayplugWoocommerceHelper::extract_transaction_metadata($payment);
                PayplugWoocommerceHelper::save_transaction_metadata($order, $metadata);
            } catch (\Exception $e) {
            }

            PayplugGateway::log('Refund process complete for the order.');

            return true;
        } catch (HttpException $e) {
            PayplugGateway::log(sprintf('Refund request error for the order %s from PayPlug API : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');

            return new \WP_Error('process_refund_error', __('The transaction could not be refunded. Please try again.', 'payplug'));
        } catch (\Exception $e) {
            PayplugGateway::log(sprintf('Refund request error for the order %s : %s', $order_id, wc_clean($e->getMessage())), 'error');

            return new \WP_Error('process_refund_error', __('The transaction could not be refunded. Please try again.', 'payplug'));
        }
    }

    /**
     * Check the order amount to ensure it's on the allowed range.
     *
     * @param int $amount
     *
     * @return int|\WP_Error
     */
    public function validate_order_amount($amount)
    {
        if (
            $amount < PayplugWoocommerceHelper::get_minimum_amount()
            || $amount > PayplugWoocommerceHelper::get_maximum_amount()
        ) {
            return new \WP_Error(
                'invalid order amount',
                sprintf(__('Payments for this amount (%s) are not authorised with this payment gateway.', 'payplug'), \wc_price($amount / 100))
            );
        }

        return $amount;
    }

    /**
     * Limit string length.
     *
     * @param string $value
     * @param int $maxlength
     *
     * @return string
     */
    public function limit_length($value, $maxlength = 100)
    {
        return (strlen($value) > $maxlength) ? substr($value, 0, $maxlength) : $value;
    }

    /**
     * Get user's keys.
     *
     * @param string $email
     * @param string $password
     *
     * @return array|\WP_Error
     */
    public function retrieve_user_api_keys($email, $password)
    {
        if (empty($email) || empty($password)) {
            return new \WP_Error('missing_login_data', __('Please fill all login fields', 'payplug'));
        }

        try {
            $response = Authentication::getKeysByLogin($email, $password);
            if (empty($response) || !isset($response['httpResponse']) && "payplug" === $this->id) {
                return new \WP_Error('invalid_credentials', __('Invalid credentials.', 'payplug'));
            }

            return $response['httpResponse']['secret_keys'];
        } catch (HttpException $e) {
            if("payplug" === $this->id) {
                return new \WP_Error('invalid_credentials', __('Invalid credentials.', 'payplug'));
            }
        }
    }

    /**
     * Get user merchant id.
     *
     * This method might be called during the login process before the global PayPlug
     * configuration is set. In that case you can pass a valid token to make the request.
     *
     * @param string|null $key
     *
     * @return string
     */
    public function retrieve_merchant_id($key = null)
    {
        try {
            $response    = !is_null($key) ? Authentication::getAccount(new Payplug($key)) : Authentication::getAccount();
            PayplugWoocommerceHelper::set_transient_data($response);
            $merchant_id = isset($response['httpResponse']['id']) ? $response['httpResponse']['id'] : '';
        } catch (ConfigurationException $e) {
            PayplugGateway::log(sprintf('Missing API key for PayPlug client : %s', wc_print_r($e->getMessage(), true)), 'error');

            $merchant_id = '';
        } catch (HttpException $e) {
            PayplugGateway::log(sprintf('Account request error from PayPlug API : %s', wc_print_r($e->getErrorObject(), true)), 'error');

            $merchant_id = '';
        } catch (\Exception $e) {
            PayplugGateway::log(sprintf('Account request error : %s', wc_clean($e->getMessage())), 'error');

            $merchant_id = '';
        }

        return $merchant_id;
    }

    /**
     * Generate Hidden HTML.
     *
     * @param string $key
     * @param array $data
     *
     * @return string
     */
    public function generate_hidden_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <input type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" />
    <?php

        return ob_get_clean();
    }

    /**
     * Generate Yes/No Input HTML.
     *
     * @param string $key
     * @param array $data
     *
     * @return string
     */
    public function generate_yes_no_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults  = array(
            'title'             => '',
            'no'                => 'No',
            'yes'               => 'Yes',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => [],
            'hide_label'        => false,
        );

        $data    = wp_parse_args($data, $defaults);
        $checked = 'yes' === $this->get_option($key) ? '1' : '0';

        ob_start();
    ?>
        <tr valign="top">
            <?php if (!$data['hide_label']) : ?>
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>">
                        <?php echo wp_kses_post($data['title']); ?>
                        <?php echo $this->get_tooltip_html($data); ?>
                    </label>
                </th>
            <?php endif; ?>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span>
                    </legend>
                    <div class="radio--custom">
                        <input class="radio radio-yes <?php echo esc_attr($data['class']); ?>" type="radio" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>-yes" value="1" <?php checked('1', $checked); ?> <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); ?>>
                        <label for="<?php echo esc_attr($field_key); ?>-yes"><?php echo esc_html($data['yes']); ?></label>
                    </div>
                    <div class="radio--custom">
                        <input class="radio radio-no <?php echo esc_attr($data['class']); ?>" type="radio" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>-no" value="0" <?php checked('0', $checked); ?> <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); ?>>
                        <label for="<?php echo esc_attr($field_key); ?>-no"><?php echo esc_html($data['no']); ?></label>
                    </div>
                    <div id="live-mode-test-p"><?php echo $this->get_description_html($data); ?></div>
                </fieldset>
            </td>
        </tr>
    <?php

        return ob_get_clean();
    }

    /**
     * Generate Radio Input HTML.
     *
     * @param string $key
     * @param array $data
     *
     * @return string
     */
    public function generate_radio_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => [],
            'options'           => [],
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
    ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>">
                    <?php echo wp_kses_post($data['title']); ?>
                    <?php echo $this->get_tooltip_html($data); ?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span>
                    </legend>
                    <?php foreach ($data['options'] as $option_key => $option_value) : ?>
                        <input class="radio <?php echo esc_attr($data['class']); ?>" type="radio" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>-<?php echo esc_attr($option_key); ?>" value="<?php echo esc_attr($option_key); ?>" <?php checked($option_key, $this->get_option($key)); ?> <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); ?>>
                        <label for="<?php echo esc_attr($field_key); ?>-<?php echo esc_attr($option_key); ?>"><?php echo esc_html($option_value); ?></label>
                    <?php endforeach; ?>
					<?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
    <?php

        return ob_get_clean();
    }

    /**
     * Generate Login HTML.
     *
     * @param string $key
     * @param array $data
     *
     * @return string
     */
    public function generate_login_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults  = [];

        $data = wp_parse_args($data, $defaults);

        ob_start();
    ?>
        <tr valign="top">
            <td class="forminp">
                <p><?php echo $this->get_option('email'); ?></p>
                <p>
                    <input id="payplug-logout" type="submit" name="submit_logout" value="<?php _e('Logout', 'payplug'); ?>">
                    <input type="hidden" name="save" value="logout">
                    <?php wp_nonce_field('payplug_user_logout', '_logoutaction'); ?>
                    |
                    <a href="https://portal.payplug.com" target="_blank"><?php _e('Go to your PayPlug Portal', 'payplug'); ?></a>
                </p>
            </td>
        </tr>
<?php

        return ob_get_clean();
    }


	/**
	 * Generate Oney popup option HTML.
	 *
	 * @param string $key
	 * @param array $data
	 *
	 * @return string
	 */
	public function generate_oney_product_animation_html($key, $data)
	{
		$field_key = $this->get_field_key($key);

		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'checkbox',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => [],
			'options'           => [],
		);

		$data = wp_parse_args($data, $defaults);

		ob_start();
		?>
		<tr valign="top" id="oney_installments_pop_up">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr($field_key); ?>">
					<?php echo wp_kses_post($data['title']); ?>
					<?php echo $this->get_tooltip_html($data); ?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
					<label for="woocommerce_payplug_oney_product_animation">
						<input class="" type="checkbox" name="woocommerce_payplug_oney_product_animation" id="woocommerce_payplug_oney_product_animation" style="" <?php echo (($this->oney_product_animation == 'yes') ? "checked" : ''); ?>> <?php echo wp_kses_post($data['label']); ?></label><br>
					<p class="description"> <?php echo wp_kses_post($data['description']); ?></p>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate Oney Type Input HTML.
	 *
	 * @param string $key
	 * @param array $data
	 *
	 * @return string
	 */
	public function generate_oney_type_html($key, $data)
	{
		$field_key = $this->get_field_key($key);
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => [],
			'options'           => [],
		);

		$data = wp_parse_args($data, $defaults);

		ob_start();
		?>
        <tr valign="top" id="woocommerce_payplug_oney_type">
            <th scope="row" class="titledesc" style="padding-top: 0px;">
                <label for="<?php echo esc_attr($field_key); ?>">
					<?php echo wp_kses_post($data['title']); ?>
					<?php echo $this->get_tooltip_html($data); ?>
                </label>
            </th>
            <td class="forminp" style="padding-top: 0px;">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span>
                    </legend>
					<?php foreach ($data['options'] as $option_key => $option_value) : ?>
                        <input class="radio <?php echo esc_attr($data['class']); ?>" type="radio" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>-<?php echo esc_attr($option_key); ?>" value="<?php echo esc_attr($option_key); ?>" <?php checked($option_key, $this->get_option($key)); ?> <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); ?>>
                        <label for="<?php echo esc_attr($field_key); ?>-<?php echo esc_attr($option_key); ?>" style="margin-right: 20px !important;">
                            <span style="font-weight: 500;"><?php echo esc_html($option_value); ?></span>
                            <span style="color:#646970;"> : <?php echo $data['descriptions'][$option_key] ;?></span>
                        </label>
					<?php endforeach; ?>
					<?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate Oney Thresholds Input HTML.
	 *
	 * @param string $key
	 * @param array $data
	 *
	 * @return string
	 */
	public function generate_oney_thresholds_html($key, $data)
	{
		$field_key = $this->get_field_key($key);
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => [],
			'options'           => [],
		);

		$data = wp_parse_args($data, $defaults);

		ob_start();
		?>
        <tr valign="top" id="woocommerce_payplug_oney_thresholds">
            <th scope="row" class="titledesc" style="padding-top: 0px;">
                <label for="<?php echo esc_attr($field_key); ?>">
					<?php echo wp_kses_post($data['title']); ?>
					<?php echo $this->get_tooltip_html($data); ?>
                </label>
            </th>
            <td class="forminp" style="padding-top: 0px;">
                <fieldset>
					<div id="oney_thresholds_description"><?php echo $this->get_description_html($data); ?></div>
					 <input type="number" id="payplug_oney_thresholds_min" min="<?php echo $this->min_oney_price;?>" max="<?php echo $this->max_oney_price;?>" class="payplug-admin-oney-threshold-input">
                    <b class="d-inline-block">€</b>
                    <div class="d-inline-block" id="slider-range"></div>
					<input type="number" id="payplug_oney_thresholds_max" min="<?php echo $this->min_oney_price;?>" max="<?php echo $this->max_oney_price;?>" class="payplug-admin-oney-threshold-input">
					<b class="d-inline-block">€</b>
                </fieldset>
            </td>
        </tr>
		<?php

		return ob_get_clean();
	}

    /**
     * Validate Radio Field.
     *
     * Make sure the data is escaped correctly, etc.
     *
     * @param string $key
     * @param string|null $value Posted Value
     *
     * @return string
     */
    public function validate_radio_field($key, $value)
    {
        $value = is_null($value) ? '' : $value;

        return wc_clean(stripslashes($value));
    }

    /**
     * Validate Yes/No Field.
     *
     * @param string $key
     * @param string $value Posted Value
     *
     * @return string
     */
    public function validate_yes_no_field($key, $value)
    {
        return ('1' === (string) $value) ? 'yes' : 'no';
    }

	/**
	 * Validate Yes/No Field.
	 *
	 * @param string $key
	 * @param string $value Posted Value
	 *
	 * @return string
	 */
	public function validate_oney_product_animation_field($key, $value)
	{
		return ('on' === (string) $value) ? 'yes' : 'no';
	}

	/**
	 * Validate Oney Type Field.
	 *
	 * Make sure the data is escaped correctly, etc.
	 *
	 * @param string $key
	 * @param string|null $value Posted Value
	 *
	 * @return string
	 */
	public function validate_oney_type_field($key, $value)
	{
		$value = is_null($value) ? 'with_fees' : $value;

		return wc_clean(stripslashes($value));
	}

	/**
	 * Validate Oney Thresholds Field.
	 *
	 * Make sure the data is escaped correctly, etc.
	 *
	 * @param string $key
	 * @param string|null $value Posted Value
	 *
	 * @return string
	 */
	public function validate_oney_thresholds_field($key, $value)
	{
		$value = is_null($value) ? [100, 3000] : $value;

		return wc_clean($value);
	}

    /**
     * Get PayPlug gateway mode.
     *
     * @return string
     */
    public function get_current_mode()
    {
        return ('yes' === $this->get_option('mode')) ? 'live' : 'test';
    }

    /**
     * Get user API key.
     *
     * @param string $mode
     *
     * @return string
     */
    public function get_api_key($mode = 'test')
    {

        switch ($mode) {
            case 'test':
                $key = $this->get_option('payplug_test_key');
                break;
            case 'live':
                $key = $this->get_option('payplug_live_key');
                break;
            default:
                $key = '';
                break;
        }

        return $key;
    }

    /**
     * Check if an api key exist for a mode.
     *
     * @param string $mode
     *
     * @return bool
     */
    public function has_api_key($mode = 'test')
    {
        $key = $this->get_api_key($mode);
        $key = trim($key);

        return !empty($key);
    }

    /**
     * Get current merchant id.
     *
     * @return string
     */
    public function get_merchant_id()
    {
        return $this->get_option('payplug_merchant_id', '');
    }

    /**
     * Check if user is logged in and we have an API key for TEST mode.
     *
     * @return bool
     */
    public function user_logged_in()
    {
        return !empty($this->get_option('payplug_test_key'));
    }

    /**
     * Check if oneclick payment is activated and merchant can use it.
     *
     * @return bool
     */
    public function oneclick_available()
    {
        return $this->user_logged_in()
            && $this->oneclick
            && $this->permissions->has_permissions(PayplugPermissions::SAVE_CARD);
    }

    /**
     * Check if the gatteway is allowed for the order amount
     *
     * @param array
     * @return array
     */
    public function check_gateway($gateways)
    {
        if ( !empty( WC()->cart ) && isset($gateways[$this->id]) && $gateways[$this->id]->id == $this->id) {
            $order_amount = $this->get_order_total();
            if ($order_amount < self::MIN_AMOUNT || $order_amount > self::MAX_AMOUNT) {
                unset($gateways[$this->id]);
            }
        }

		//FIXME:: refactoring to remove this - we should see this on the oney classes and not here
		if($this->oney_type == 'with_fees'){
			unset($gateways['oney_x3_without_fees']);
			unset($gateways['oney_x4_without_fees']);
		} else{
			unset($gateways['oney_x3_with_fees']);
			unset($gateways['oney_x4_with_fees']);
		}

        return $gateways;
    }

    /**
     * Can the order be refunded via this gateway?
     *
     *
     * @param  WC_Order $order Order object.
     * @return bool If false, the automatic refund button is hidden in the UI.
     */
    public function can_refund_order($order)
    {
        $status = $order->get_status();
        return $order && $this->supports('refunds') && $status !== "cancelled" && $status !== "failed";
    }

	public function getPayplugMerchantCountry(){
		return $this->payplug_merchant_country;
	}

	public function setPayplugMerchantCountry($country){
		$this->payplug_merchant_country = $country;
	}

	public function handle_ip_auto_activation(){

	}

	protected function activate_integrated_payments(){
		//get options
		$options = get_option('woocommerce_payplug_settings', []);

		//was this option updated?
		if(empty($options['update_gateway'])){
			//get transient
			$transient_key = PayplugWoocommerceHelper::get_transient_key($options);
			$transient = get_transient($transient_key);

			if( !empty($transient["permissions"]['can_use_integrated_payments']) && $transient["permissions"]['can_use_integrated_payments']) {
				$options['payment_method'] = "integrated";
				$options['update_gateway'] = true;
				update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options) );
			}
		}
	}
}
