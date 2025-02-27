<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Payplug\Authentication;
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\HttpException;
use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Controller\IntegratedPayment;
use Payplug\PayplugWoocommerce\Helper\Lock;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Resource\Payment as PaymentResource;
use Payplug\Resource\Refund as RefundResource;
use WC_Blocks_Utils;
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
	 * @var string
	 */
	public $mode;
	/**
	 * @var bool
	 */
	public $debug;
	/**
	 * @var string
	 */
	public $email;
	/**
	 * @var string
	 */
	public $payment_method;
	/**
	 * @var bool
	 */
	public $oneclick;

	/**
	 * @var string
	 */
	public $oney_type;

	/**
	 * @var string
	 */
	public $oney_product_animation;

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

	const ENABLE_ON_TEST_MODE = true;

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
		//required plugin id
		$this->id = 'payplug';
		$this->supports           = array(
			'products',
			'refunds',
			'tokenization',
		);

		$payplug_gateways = array('payplug', 'american_express', 'apple_pay', 'bancontact', 'oney_x3_with_fees', 'oney_x3_without_fees', 'oney_x4_with_fees', 'oney_x4_without_fees', 'satispay', 'ideal', 'mybank');

		//save buttom admin
		if ((!empty($_GET['section'])) && (in_array($_GET['section'], $payplug_gateways))) {
			$GLOBALS['hide_save_button'] = true;
		}

        $this->init_settings();
        $this->requirements = new PayplugGatewayRequirements($this);
        if ($this->user_logged_in()) {
            $this->init_payplug();

        }

        $this->mode           = 'yes' === $this->get_option('mode', 'no') ? 'live' : 'test';
        $this->debug          = 'yes' === $this->get_option('debug', 'no');
        $this->email          = $this->get_option('email');

		$this->oney_type      = $this->get_option('oney_type', 'with_fees');
	    $oney_range = PayplugWoocommerceHelper::get_min_max_oney();
	    $this->min_oney_price = (isset($oney_range['min'])) ? intval($oney_range['min']) : 100;
	    $this->max_oney_price = (isset($oney_range['max'])) ? intval($oney_range['max']) : 3000;
	    $this->oney_thresholds_min = $this->get_option('oney_thresholds_min', $this->min_oney_price );
	    $this->oney_thresholds_max = $this->get_option('oney_thresholds_max', $this->max_oney_price );

		//admin form
        $this->init_form_fields();

		//used for oney
        $this->payplug_merchant_country = PayplugWoocommerceHelper::get_payplug_merchant_country();
        $this->oney_product_animation = $this->get_option('oney_product_animation');

        add_filter('woocommerce_get_customer_payment_tokens', [$this, 'filter_tokens'], 10, 3);

        self::$log_enabled = $this->debug;

        add_filter('woocommerce_get_order_item_totals', [$this, 'customize_gateway_title'], 10, 2);
		add_action('woocommerce_checkout_order_processed', [$this, 'validate_payment']);
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
    public function validate_payment($id = null, $save_request = true, $ipn = false)
    {
		global $wp;

		if(!$ipn){
			if (!is_wc_endpoint_url('order-received') || (empty($_GET['key']) && empty($id)) ) {
				return;
			}
		}

		if (!empty($_GET['order-received'])) {
			$order_id = (int) ($_GET['order-received']);

		} elseif (!empty($id) && !is_object($id)) {
			$order_id = (int) $id;
		}

		if (empty($order_id)) {
			$order_id = apply_filters(
				'woocommerce_thankyou_order_id',
				absint($wp->query_vars['order-received'])
			);
		}

		if (empty($order_id)) {

			if (empty($_GET['key']) && empty($id) && !is_object($id)) {
				return;
			}

			$order_id = wc_get_order_id_by_order_key(wc_clean( (!empty($_GET['key']) ? $_GET['key'] : (int) $id) ) );

		}

		if (empty($order_id)) {
			return;
		}

        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            return;
        }

        $payment_method = PayplugWoocommerceHelper::is_pre_30() ? $order->payment_method : $order->get_payment_method();
        if (!in_array($payment_method, ['payplug', 'oney_x3_with_fees', 'oney_x4_with_fees', 'oney_x3_without_fees', 'oney_x4_without_fees','bancontact', 'apple_pay', 'american_express', "satispay","mybank","ideal" ])) {
            return;
        }


        $transaction_id = PayplugWoocommerceHelper::is_pre_30() ? get_post_meta($order_id, '_transaction_id', true) : $order->get_transaction_id();
        if (empty($transaction_id)) {
            PayplugGateway::log(sprintf('Order #%s : Missing transaction id.', $order_id), 'error');
            return;
        }

		if($payment_method === $this->id) {

			$lock_id = Lock::handle_insert($save_request, $transaction_id);
			if(!$lock_id){
				return;
			}

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

			$this->response->process_payment($payment);

			\Payplug\PayplugWoocommerce\Model\Lock::delete_lock($lock_id);
			$waiting_requests = \Payplug\PayplugWoocommerce\Model\Lock::get_lock_by_payment_id($transaction_id);

			if($waiting_requests){
				\Payplug\PayplugWoocommerce\Model\Lock::delete_lock_by_payment_id($transaction_id);
				$this->validate_payment($order_id, false);
			};
		}
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

		if($this->oneclick === false){
			foreach($tokens as $token_id => $token){
				if($token->get_gateway_id() === "payplug"){
					unset($tokens[$token_id]);
				}
			}
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

	/**
	 * extra payment fields
	 */
    public function payment_fields()
    {
        $description = $this->get_description();

		if (!empty($description)) {
			echo wpautop(wptexturize($description));
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
		wp_enqueue_script('chunk-vendors.js', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/dist/js/chunk-vendors-1.7.2.js', [], PAYPLUG_GATEWAY_VERSION);
		wp_enqueue_script('app.js', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/dist/js/app-1.7.2.js', [], PAYPLUG_GATEWAY_VERSION);
		wp_enqueue_style('app.css', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/dist/css/app.css', [], PAYPLUG_GATEWAY_VERSION);
		wp_localize_script('app.js', 'payplug_admin_config',
			array(
				"img_path"		=> esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/dist/'),
				'ajax_url'      => get_home_url()
			));

		?>
		<script>window.get_data_url = "<?php echo rest_url('payplug/data'); ?>"</script>
		<script>window.set_data_url = "<?php echo rest_url('payplug/save_data'); ?>"</script>
		<div id="payplug_admin"></div>

		<?php

		/*********** End VUE Code ***********/

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

        if ($payment_token_id && (int) $customer_id > 0) {
            PayplugGateway::log(sprintf('Payment token found.', $amount));

            return $this->process_payment_with_token($order, $amount, $customer_id, $payment_token_id);
        }

		return $this->process_standard_payment($order, $amount, $customer_id);
    }

	/**
	 * if payment was generated by an intend, we shouldn't generate another one and try to pay it, this would generate duplications
	 * @param $order
	 * @return array|null
	 * @throws \Exception
	 */
	private function process_standard_intent_payment($order){

		//no order-pay page, no ajax_on_order_review_page
		if ( !is_wc_endpoint_url('order-pay') &&
			PayplugWoocommerceHelper::is_checkout_block() &&
			(
				( $this->id === "payplug" && ($this->payment_method === 'integrated'|| $this->payment_method === 'popup') ) ||
				( $this->id === "american_express" && $this->payment_method === 'popup')
			) &&
			$_GET["wc-ajax"] !== "payplug_order_review_url"
		) {

			$order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();

			try {
				$payment = $this->api->payment_retrieve($order->get_transaction_id());
				if (ob_get_length() > 0) {
					ob_clean();
				}

				// Save transaction id for the order
				PayplugWoocommerceHelper::is_pre_30()
					? update_post_meta($order_id, '_transaction_id', $payment->id)
					: $order->set_transaction_id($payment->id);

				if($payment->is_paid){
					$finished_status = wc_get_is_paid_statuses();
					$order->set_status($finished_status[0]);
				}

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

				PayplugGateway::log(sprintf('Payment intent created for order #%s', $order_id));

				$return_url = esc_url_raw($order->get_checkout_order_received_url());

				wp_send_json_success(array(
					'payment_id' => $payment->id,
					'result' => 'success',
					'redirect' => !empty($payment->hosted_payment->payment_url) ? $payment->hosted_payment->payment_url : $return_url,
					'cancel' => !empty($payment->hosted_payment->cancel_url) ? $payment->hosted_payment->cancel_url : null
				));

				return array("stt" => "OK");

			} catch (HttpException $e) {
				PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');
				throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
			} catch (\Exception $e) {
				PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, $e->getMessage()), 'error');
				throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
			}
		}

		return null;
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

		$intent = $this->process_standard_intent_payment($order);
		if( !empty($intent) ){
			return $intent;
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

			if (PayplugWoocommerceHelper::is_checkout_block() && is_checkout()) {
				$payment_data['metadata']['woocommerce_block'] = "CHECKOUT";

			} elseif (PayplugWoocommerceHelper::is_cart_block() && is_cart()) {
				$payment_data['metadata']['woocommerce_block'] = "CART";
			}

			//IP request required variables
			if($this->payment_method === 'integrated'){
				$payment_data['initiator'] = 'PAYER';
				$payment_data['integration'] = 'INTEGRATED_PAYMENT';
				unset($payment_data['hosted_payment']['cancel_url']);
			}

			//for subscriptions the card needs to be saved
			$is_subscription = PayplugWoocommerceHelper::is_subscription();
			if( !empty($is_subscription) && $is_subscription === true ){
				$payment_data['allow_save_card'] = false;
				$payment_data['save_card'] = true;
				$payment_data['force_3ds'] = true;
				$payment_data['metadata']['subscription'] = 'subscription';
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

			$order->set_payment_method( $this->id );
			$order->set_payment_method_title($this->method_title);

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

			if(ob_get_length() > 0){
				ob_clean();
			}

			return array(
				'payment_id' => $payment->id,
				'result'   => 'success',
				'redirect' => !empty($payment->hosted_payment->payment_url) ? $payment->hosted_payment->payment_url : $return_url,
				'cancel'   => !empty($payment->hosted_payment->cancel_url) ? $payment->hosted_payment->cancel_url : null
			);

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
					'woocommerce_block' => \WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' )
				],
            ];

			$is_subscription = PayplugWoocommerceHelper::is_subscription();
			if( !empty($is_subscription) && $is_subscription === true ){
				$payment_data['metadata']['subscription'] = 'subscription';
			}


            /** This filter is documented in src/Gateway/PayplugGateway */
            $payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $order_id, [], $address_data);
            $payment      = $this->api->payment_create($payment_data);

			// Save transaction id for the order
			PayplugWoocommerceHelper::is_pre_30()
				? update_post_meta($order_id, '_transaction_id', $payment->id)
				: $order->set_transaction_id($payment->id);

			if (is_callable([$order, 'save'])) {
				$order->save();
			}

            /** This action is documented in src/Gateway/PayplugGateway */
            \do_action('payplug_gateway_payment_created', $order_id, $payment);


			$metadata = PayplugWoocommerceHelper::extract_transaction_metadata($payment);
			PayplugWoocommerceHelper::save_transaction_metadata($order, $metadata);

            $this->response->process_payment($payment, true);

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
            $response    = !is_null($key) && !empty($key) ? Authentication::getAccount(new Payplug($key)) : Authentication::getAccount();
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
        return $this->id === "payplug" && $this->user_logged_in()
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

}
