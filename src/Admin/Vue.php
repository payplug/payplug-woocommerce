<?php

namespace Payplug\PayplugWoocommerce\Admin;


use Payplug\PayplugWoocommerce\Admin\Vue\Component;
use Payplug\PayplugWoocommerce\Admin\Vue\PaymentMethods;
use Payplug\PayplugWoocommerce\Controller\ApplePay;
use Payplug\PayplugWoocommerce\Gateway\PayplugApi;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayRequirements;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Traits\GatewayGetter;

/**
 * PayPlug admin Vue.js dashboard handler.
 *
 * @package Payplug\PayplugWoocommerce\Admin
 */
class Vue
{
	use GatewayGetter;

	private $configuration;
	private $options;
	private $payplug_api;

	public function __construct()
	{
		$payplug = (new PayplugGateway());
		$this->configuration = $payplug->get_configuration();
		$this->options = $payplug->settings;
	}

	/**
	 * @return array
	 */
	public function init()
	{
		if ($this->get_gateway('account')->is_logged()) {
			$header = $this->payplug_section_header();
			$logged = $this->payplug_section_logged();

			unset($this->options['api_key']);
			unset($this->options['company_id']);
			unset($this->options['jwt']);
			unset($this->options['oauth_client_data']);
			unset($this->options['oauth_client_id']);
			unset($this->options['oauth_code_verifier']);
			unset($this->options['oauth_company_id']);

			return [
				'payplug_wooc_settings' => $this->options,
				'header' => $header,
				'oauth_login' => $this->payplug_section_oauth_login(),
				'logged' => $logged,
				'payment_methods' => $this->payplug_section_payment_methods($this->options),
				'payment_paylater' => $this->payplug_section_paylater(),
				'status' => $this->payplug_section_status($this->options),
				'footer' => $this->payplug_section_footer(),
			];
		}

		return [
			'header' => $this->payplug_section_header(),
			'oauth_login' => $this->payplug_section_oauth_login(),
			'subscribe' => $this->payplug_section_subscribe(),
			'payment_methods' => $this->payplug_section_payment_methods(),
			'payment_paylater' => $this->payplug_section_paylater(),
			'status' => $this->payplug_section_status(),
			'footer' => $this->payplug_section_footer(),
		];
	}

	/**
	 * @return array
	 */
	public function payplug_section_logged()
	{

		$inactive = false;
		if (empty(PayplugWoocommerceHelper::get_live_key())) {
			$inactive = true;
		}
		$this->payplug_api = new PayplugApi($this);
		$callback_uri = get_admin_url(null, '/admin.php?page=wc-settings&tab=checkout&section=payplug');
		$register_url = $this->payplug_api->retrieve_register_url($callback_uri);

		return [
			'title' => __('payplug_section_logged_title', 'payplug'),
			'descriptions' => [
				'live' => [
					'description' => __('payplug_section_logged_description', 'payplug'),
					'logout' => __('payplug_section_logged_logout', 'payplug'),
					'mode' => __('payplug_section_logged_mode', 'payplug'),
					'mode_description' => __('payplug_section_logged_live_description', 'payplug'),
					'link_learn_more' => [
						'text' => __('payplug_learn_more', 'payplug'),
						'url' => __('payplug_mode_learn_more_url', 'payplug'),
						'target' => '_blank'
					],
					'link_access_portal' => [
						'text' => __('payplug_section_logged_link_access_portal', 'payplug'),
						'url' => 'https://portal.payplug.com/',
						'target' => '_blank'
					],
				],
				'sandbox' => [
					'description' => __('payplug_section_logged_description', 'payplug'),
					'logout' => __('payplug_section_logged_logout', 'payplug'),
					'mode' => __('payplug_section_logged_mode', 'payplug'),
					'mode_description' => __('payplug_section_logged_test_description', 'payplug'),
					'link_learn_more' => [
						'text' => __('payplug_learn_more', 'payplug'),
						'url' => __('payplug_mode_learn_more_url', 'payplug'),
						'target' => '_blank'
					],
					'link_access_portal' => [
						'text' => __('payplug_section_logged_link_access_portal', 'payplug'),
						'url' => 'https://portal.payplug.com/',
						'target' => '_blank'
					],
				]
			],
			'options' => [
				[
					'name' => 'payplug_sandbox',
					'label' => 'Test',
					'value' => 1, //test
					'checked' => !PayplugWoocommerceHelper::check_mode()
				],
				[
					'name' => 'payplug_sandbox',
					'label' => 'Live',
					'value' => 0, //live
					'checked' => PayplugWoocommerceHelper::check_mode()
				]
			],
			'inactive_modal' => [
				'inactive' => $inactive,
				'title' => __('payplug_live_mode', 'payplug'),
				'description' => __('payplug_section_logged_modal_description', 'payplug'),
				'description_1' => __('payplug_section_logged_modal_description_1_uauth', 'payplug'),
				'description_2' => __('payplug_section_logged_modal_description_2_uauth', 'payplug'),
				'password_label' => __('payplug_section_login_password_label', 'payplug'),
				'cancel' => __('payplug_cancel', 'payplug'),
				'ok' => __('payplug_ok', 'payplug'),
				'oauth' => __('payplug_reconnect', 'payplug'),
				'oauth_url' => $register_url
			],
			'inactive_account' => [
				'warning' => [
					'title' => __('payplug_inactive_account_warning_title', 'payplug'),
					'description' => __('payplug_inactive_account_warning_description1', 'payplug') .
						__('payplug_inactive_account_warning_description2', 'payplug') .
						__('payplug_inactive_account_warning_description3', 'payplug'),
				],
				'error' => [
					'title' => __('payplug_inactive_account_error_title', 'payplug'),
					'description' => __('payplug_inactive_account_error_description', 'payplug'),
				]
			],
		];
	}

	/**
	 * @return array[]
	 */
	public function payplug_section_login()
	{

		$login = [
			'name' => 'generalLogin',
			'title' => __('payplug_section_logged_title', 'payplug'),
			'descriptions' => [
				'live' => [
					'description' => __('payplug_section_login_description', 'payplug'),
					'not_registered' => __('payplug_section_login_not_registered', 'payplug'),
					'connect' => __('payplug_section_login_connect', 'payplug'),
					'email_label' => __('payplug_section_login_email_label', 'payplug'),
					'email_placeholder' => __('payplug_section_login_email_label', 'payplug'),
					'password_label' => __('payplug_section_login_password_label', 'payplug'),
					'password_placeholder' => __('payplug_section_login_password_label', 'payplug'),
					'link_forgot_password' => [
						'text' => __('payplug_section_login_forgot_password', 'payplug'),
						'url' => 'https://portal.payplug.com/forgot_password',
						'target' => '_blank'
					],
				],
				'sandbox' => [
					'description' => __('payplug_section_login_description', 'payplug'),
					'not_registered' => __('payplug_section_login_not_registered', 'payplug'),
					'connect' => __('payplug_section_login_connect', 'payplug'),
					'email_label' => __('payplug_section_login_email_label', 'payplug'),
					'email_placeholder' => __('payplug_section_login_email_label', 'payplug'),
					'password_label' => __('payplug_section_login_password_label', 'payplug'),
					'password_placeholder' => __('payplug_section_login_password_label', 'payplug'),
					'link_forgot_password' => [
						'text' => __('payplug_section_login_forgot_password', 'payplug'),
						'url' => 'https://portal.payplug.com/forgot_password',
						'target' => '_blank'
					],
				]
			]
		];

		return $login;
	}

	/**
	 * @return array[]
	 */
	public function payplug_section_oauth_login()
	{
		$this->payplug_api = new PayplugApi($this);
		$callback_uri = get_admin_url(null, '/admin.php?page=wc-settings&tab=checkout&section=payplug');
		$register_url = $this->payplug_api->retrieve_register_url($callback_uri);

		$oauth_login = [
			'name' => 'oauthLogin',
			'title' => __('payplug_section_logged_title', 'payplug'),
			'descriptions' => [
				'live' => [
					'description' => __('payplug_section_oauth_login_description', 'payplug'),
					'form' => [
						'email' => [
							'label' => __('payplug_section_login_email_label', 'payplug'),
							'placeholder' => __('payplug_section_login_email_label', 'payplug')
						],
						'password' => [
							'label' => __('payplug_section_login_password_label', 'payplug'),
							'placeholder' => __('payplug_section_login_password_label', 'payplug')
						],
						'connexion' => __('payplug_section_login_connect', 'payplug'),
						'create_account' => __('payplug_section_login_not_registered', 'payplug'),
						'forgot_password' => __('payplug_section_login_forgot_password', 'payplug'),
						'error' => __('payplug_section_login_error', 'payplug'),
						'create_account_url' => 'https://portal.payplug.com/auth/signup',
						'forgot_password_url' => 'https://portal.payplug.com/forgot_password'
					],
					'sso' => [
						'title' => __('payplug_section_oauth_login_title', 'payplug'),
						'description' => __('payplug_section_oauth_login_description', 'payplug'),
						'information' => __('payplug_section_oauth_info', 'payplug'),
						'button' => __('payplug_section_oauth_login_btn_connect', 'payplug'),
						'button_url' => $register_url
					]
				],
				'sandbox' => [
					'description' => __('payplug_section_oauth_login_description', 'payplug'),
					'form' => [
						'email' => [
							'label' => __('payplug_section_login_email_label', 'payplug'),
							'placeholder' => __('payplug_section_login_email_label', 'payplug')
						],
						'password' => [
							'label' => __('payplug_section_login_password_label', 'payplug'),
							'placeholder' => __('payplug_section_login_password_label', 'payplug')
						],
						'connexion' => __('payplug_section_login_connect', 'payplug'),
						'create_account' => __('payplug_section_login_not_registered', 'payplug'),
						'forgot_password' => __('payplug_section_login_forgot_password', 'payplug'),
						'error' => __('payplug_section_login_error', 'payplug'),
						'create_account_url' => 'https://portal.payplug.com/auth/signup',
						'forgot_password_url' => 'https://portal.payplug.com/forgot_password'
					],
					'sso' => [
						'title' => __('payplug_section_oauth_login_title', 'payplug'),
						'description' => __('payplug_section_oauth_login_description', 'payplug'),
						'information' => __('payplug_section_oauth_info', 'payplug'),
						'button' => __('payplug_section_oauth_login_btn_connect', 'payplug'),
						'button_url' => $register_url
					]
				]
			],
		];

		return $oauth_login;
	}

	/**
	 * @return array
	 */
	public function payplug_section_subscribe()
	{
		return [
			'name' => 'generalSubscribe',
			'title' => __('payplug_section_logged_title', 'payplug'),
			'descriptions' => [
				'live' => [
					'description' => __('payplug_section_subscribe_description', 'payplug'),
					'link_create_account' => [
						'text' => __('payplug_section_subscribe_link_create_account', 'payplug'),
						'url' => 'https://portal.payplug.com/auth/signup',
						'target' => '_blank'
					],
					'content_description' => __('payplug_section_subscribe_content_description', 'payplug'),
					'already_have_account' => __('payplug_section_subscribe_already_have_account', 'payplug'),
				],
				'sandbox' => [
					'description' => __('payplug_section_subscribe_description', 'payplug'),
					'link_create_account' => [
						'text' => __('payplug_section_subscribe_link_create_account', 'payplug'),
						'url' => 'https://portal.payplug.com/signup',
						'target' => '_blank'
					],
					'content_description' => __('payplug_section_subscribe_content_description', 'payplug'),
					'already_have_account' => __('payplug_section_subscribe_already_have_account', 'payplug'),
				]
			]
		];
	}

	/**
	 * @return array
	 */
	public function payplug_section_header()
	{
		$enable = isset($this->options['enabled']) && $this->options['enabled'];
		$enable = $enable && $this->payplug_requirements();
		$disabled = !$this->payplug_requirements();

		return [
			'title' => __('payplug_section_header_title', 'payplug'),
			'descriptions' => [
				'live' => [
					'description' => __('payplug_section_header_live_description', 'payplug'),
					'plugin_version' => PAYPLUG_GATEWAY_VERSION
				],
				'sandbox' => [
					'description' => __('payplug_section_header_test_description', 'payplug'),
					'plugin_version' => PAYPLUG_GATEWAY_VERSION
				],
			],
			'options' => [
				'type' => 'select',
				'name' => 'payplug_enable',
				'disabled' => $disabled,
				'options' => [
					[
						'value' => 1,
						'label' => __('payplug_section_header_enable_label', 'payplug'),
						'checked' => $enable === true ? true : false
					],
					[
						'value' => 0,
						'label' => __('payplug_section_header_disable_label', 'payplug'),
						'checked' => $enable === false ? true : false
					]
				]
			]
		];

	}

	/**
	 * @return array
	 */
	public function payplug_section_payment_methods($options = array())
	{
		$payment_configuration = $this->options['payment_methods']['configuration'];

		// get applepay carriers
		$carriers = json_decode($payment_configuration['apple_pay']['carriers'], true);
		$carriers = empty($carriers) ? [] : $carriers;

		$section = [
			'name' => 'paymentMethodsBlock',
			'title' => __('payplug_section_payment_methods_title', 'payplug'),
			'descriptions' => [
				'live' => [
					'description' => __('payplug_section_payment_methods_description', 'payplug'),
				],
				'sandbox' => [
					'description' => __('payplug_section_payment_methods_description', 'payplug'),
				]
			],
			'options' => [
				(new PaymentMethods($this->options))->payment_method_standard(),
				PaymentMethods::payment_method_amex($payment_configuration['american_express']['active']),
				PaymentMethods::payment_method_applepay($payment_configuration['apple_pay']['active'], $this->options, $carriers),
				PaymentMethods::payment_method_bancontact($payment_configuration['bancontact']['active']),
				PaymentMethods::payment_method_satispay($payment_configuration['satispay']['active']),
				PaymentMethods::payment_method_mybank($payment_configuration['mybank']['active']),
				PaymentMethods::payment_method_ideal($payment_configuration['ideal']['active']),
				PaymentMethods::payment_method_wero($payment_configuration['wero']['active']),
				PaymentMethods::payment_method_bizum($payment_configuration['bizum']['active']),
			]
		];

		return $section;
	}

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public function payplug_section_paylater()
	{
		$custom_amounts = json_decode($this->options['payment_methods']['configuration']['oney']['custom_amounts'], true);
		$default_amounts = json_decode($this->options['payment_methods']['configuration']['oney']['default_amounts'], true);

		$max = !empty($custom_amounts['max']) ? $custom_amounts['max'] : $default_amounts['max'];
		$min = !empty($custom_amounts['min']) ? $custom_amounts['min'] : $default_amounts['min'];
		$product_page = $this->options['payment_methods']['configuration']['oney']['cta_product'];

		$section = [
			'name' => 'paymentMethodsBlock',
			'title' => __('payplug_section_paylater_title', 'payplug'),
			'descriptions' => [
				'live' => [
					'description' => __('payplug_section_paylater_description', 'payplug'),
				],
				'sandbox' => [
					'description' => __('payplug_section_paylater_description', 'payplug'),
				]
			],
			'options' => [
				'name' => 'oney',
				'title' => __('payplug_section_oney_title', 'payplug'),
				'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/lg-oney.png'),
				'checked' => (bool) $this->options['payment_methods']['configuration']['oney']['active'],
				'descriptions' => [
					'live' => [
						'description' => __('payplug_section_paylater_description_oney', 'payplug'),
						'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), 'https://support.payplug.com/hc/fr/articles/4408142346002', '_blank'),
					],
					'sandbox' => [
						'description' => __('payplug_section_paylater_description_oney', 'payplug'),
						'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), 'https://support.payplug.com/hc/fr/articles/4408142346002', '_blank'),
					],
					'advanced' => [
						'description' => __('payplug_advanced_settings', 'payplug'), ''
					]
				],
				'options' => [
					[
						'name' => 'payplug_oney_type',
						'className' => '_paylaterLabel',
						'label' => __('payplug_label_with_fees', 'payplug'),
						'subText' => __('payplug_text_with_fees', 'payplug'),
						'value' => 'with_fees',
						'checked' => (bool) $this->options['payment_methods']['configuration']['oney']['with_fees'],
					],
					[
						'name' => 'payplug_oney_type',
						'className' => '_paylaterLabel',
						'label' => __('payplug_label_without_fees', 'payplug'),
						'subText' => __('payplug_text_without_fees', 'payplug'),
						'value' => 'without_fees',
						'checked' => !(bool) $this->options['payment_methods']['configuration']['oney']['with_fees'],
					]
				],
				'advanced_options' => [
					$this->thresholds_option($max, $min),
					$this->show_oney_popup_product($product_page)
				]
			]
		];

		return $section;
	}

	/**
	 * @return array
	 */
	public function thresholds_option($max, $min)
	{
		$default_amounts = json_decode($this->options['payment_methods']['configuration']['oney']['default_amounts'], true);

		$thresholds = [
			'name' => 'thresholds',
			'image_url' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/thresholds.svg'),
			'title' => __('payplug_thresholds_oney_title', 'payplug'),
			'descriptions' => [
				'description' => __('payplug_thresholds_oney_description', 'payplug'),
				'min_amount' => [
					'name' => 'oney_min_amounts',
					'value' => $min,
					'placeholder' => $min,
					'default' => $default_amounts['min']
				],
				'inter' => __('and', 'payplug'),
				'max_amount' => [
					'name' => 'oney_max_amounts',
					'value' => $max,
					'placeholder' => $max,
					'default' => $default_amounts['max']
				],
				'error' => [
					'text' => __('payplug_thresholds_error_msg', 'payplug'),
					'maxtext' => __('payplug_thresholds_error_maxtext_msg', 'payplug'),
					'mintext' => __('payplug_thresholds_error_mintext_msg', 'payplug'),
				]
			],
			'switch' => false
		];

		return $thresholds;
	}

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public function show_oney_popup_product($active = false)
	{
		return [
			'name' => 'oney_product_animation',
			'image_url' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/product.svg'),
			'title' => __('display_the_oney_installments_pop_up_on_the_product_page', 'payplug'),
			'descriptions' => [[
				'description' => __('payplug_oney_product_page_description', 'payplug'),
				'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), 'https://support.payplug.com/hc/fr/articles/4408142346002', '_blank')
			]],
			'switch' => true,
			'checked' => $active
		];
	}

	/**
	 * @return array
	 */
	public function payplug_section_status($options = [])
	{
		$payplug_requirements = new PayplugGatewayRequirements(new PayplugGateway());
		$checked = (bool) $options['debug'];

		$status = [
			'error' => !$this->payplug_requirements(),
			'title' => __('payplug_section_status_title', 'payplug'),
			'descriptions' => [
				'live' => [
					'description' => __('payplug_section_status_description', 'payplug'),
					'errorMessage' => __('payplug_section_status_errorMessage', 'payplug'),
					'check' => __('payplug_section_status_check', 'payplug'),
					'check_success' => __('payplug_section_status_check_success', 'payplug'),
				],
				'sandbox' => [
					'description' => __('payplug_section_status_description', 'payplug'),
					'errorMessage' => __('payplug_section_status_errorMessage', 'payplug'),
					'check' => __('payplug_section_status_check', 'payplug'),
					'check_success' => __('payplug_section_status_check_success', 'payplug'),
				]
			],
			'requirements' => [
				$payplug_requirements->curl_requirement(),
				$payplug_requirements->php_requirement(),
				$payplug_requirements->openssl_requirement(),
				$payplug_requirements->currency_requirement(), //MISSING THIS MESSAGES
				$payplug_requirements->account_requirement(),
			],
			'debug' => [
				'live' => [
					'title' => __('payplug_section_status_debug_label', 'payplug'),
					'description' => __('payplug_section_status_debug_description', 'payplug'),
				],
				'sandbox' => [
					'title' => __('payplug_section_status_debug_label', 'payplug'),
					'description' => __('payplug_section_status_debug_description', 'payplug'),
				]
			],
			'enable_debug_check' => $checked
		];

		return $status;
	}

	/**
	 * check if there's any requirement missing
	 * @return bool
	 */
	private function payplug_requirements()
	{
		$payplug_requirements = new PayplugGatewayRequirements(new PayplugGateway());
		return $payplug_requirements->satisfy_requirements();
	}

	/**
	 * @return array
	 */
	public function payplug_section_footer()
	{
		return [
			'save_changes_text' => __('payplug_save_changes_text', 'payplug'),
			'description' => [
				__('payplug_section_help_description1', 'payplug'),
				__('payplug_section_help_description2', 'payplug')
			],
			'link_help' => Component::link(
				__('payplug_section_help_link_help_text', 'payplug'),
				__('payplug_section_help_link_help_url', 'payplug'),
				'_blank'
			),
		];
	}
}
