<?php
namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

use Payplug\Authentication;
use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Gateway\PayplugApi;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Exception;

/**
 * PayPlug Setup Callback Handler
 * Handles the callback after a user has been authenticated on the PayPlug Portal
 */
class SetupCallback {

	/**
	 * Initialize the handler
	 */
	public function __construct() {
		add_action('admin_init', array($this, 'handle_setup_callback'), 5);
		add_action('admin_init', array($this, 'handle_oauth_callback'), 5);
	}

	/**
	 * Handle the setup callback from PayPlug Portal
	 */
	public function handle_setup_callback() {
		// Check if we're on the PayPlug settings page
		if (!$this->is_payplug_settings_page()) {
			return;
		}

		// Check if we have the client_id and company_id parameters
		if (isset($_GET['client_id']) && isset($_GET['company_id'])) {

			$client_id = sanitize_text_field($_GET['client_id']);
			$company_id = sanitize_text_field($_GET['company_id']);

			$this->process_setup_credentials($client_id, $company_id);

			try {
				// Generate a code verifier
				$code_verifier = bin2hex(openssl_random_pseudo_bytes(50));

				set_transient('payplug_code_verifier', $code_verifier, 3600); // Expires in 1 hour

				$oauth_callback_uri = admin_url('admin.php?page=wc-settings&tab=checkout&section=payplug');

				// Store the redirect URI in a transient for the callback
				set_transient('payplug_oauth_callback_uri', $oauth_callback_uri, 3600);

				// Initiate OAuth flow using the PayPlug library
				Authentication::initiateOAuth($client_id, $oauth_callback_uri, $code_verifier);

				// This function will perform the redirect and exit

			} catch (Exception $e) {
				PayplugGateway::log(sprintf('OAuth initiation error: %s', $e->getMessage()), 'error');
				// Redirect back to settings page with error
				wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payplug'));
				exit;
			}
		}

		// Display success message if setup was successful
		if (isset($_GET['setup_success']) && $_GET['setup_success'] == '1') {
			add_action('admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible">' .
				     '<p>' . esc_html__('PayPlug account successfully connected!', 'payplug') . '</p>' .
				     '</div>';
			});
		}

		// Display error message if setup failed
		if (isset($_GET['setup_error'])) {
			add_action('admin_notices', function() {
				$error_type = sanitize_text_field($_GET['setup_error']);
				$error_message = '';

				switch ($error_type) {
					case 'oauth_init':
						$error_message = __('Failed to initiate OAuth authentication. Please try again.', 'payplug');
						break;
					case 'token_exchange':
						$error_message = __('Failed to exchange authorization code for access token.', 'payplug');
						break;
					default:
						$error_message = __('An error occurred during authentication.', 'payplug');
				}

				echo '<div class="notice notice-error is-dismissible">' .
				     '<p>' . esc_html($error_message) . '</p>' .
				     '</div>';
			});
		}
	}

	/**
	 * Handle the OAuth callback
	 */
	public function handle_oauth_callback() {
		if (!isset($_GET['code'])) {
			return;
		}
		$authorization_code = sanitize_text_field($_GET['code']);

		$code_verifier = get_transient('payplug_code_verifier');
		$callback_uri = get_transient('payplug_oauth_callback_uri');

		$settings = PayplugWoocommerceHelper::get_payplug_options();
		$client_id = isset($settings['client_data']['client_id']) ? $settings['client_data']['client_id'] : '';

		if (empty($code_verifier) || empty($callback_uri) || empty($client_id)) {
			PayplugGateway::log('Missing OAuth parameters for token exchange', 'error');
			wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payplug'));
			exit;
		}

		try {
			$jwt_response = Authentication::generateJWTOneShot(
				$authorization_code,
				$callback_uri,
				$client_id,
				$code_verifier
			);


			if (empty($jwt_response) || !isset($jwt_response['httpResponse']) || !isset($jwt_response['httpResponse']['access_token'])) {
				PayplugGateway::log('Failed to obtain access token from authorization code', 'error');
				wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payplug&setup_error=token_exchange'));
				exit;
			}

			// Get the tokens from the response
			$access_token = $jwt_response['httpResponse']['access_token'];

			// Update settings with tokens
			$settings = PayplugWoocommerceHelper::get_payplug_options();

			$id_token = $jwt_response['httpResponse']['id_token'];
			$id_token_split = explode('.', $id_token);
			$payload = base64_decode($id_token_split[1]);
			$payload_decode = json_decode($payload, true);

			$email = isset($payload_decode['email']) ? $payload_decode['email'] : '';

			Payplug::init(['secretKey' => $access_token]);

			$company_id = PayplugWooCommerceHelper::get_payplug_options()['client_data']['company_id'];

			$auth_live = Authentication::createClientIdAndSecret($company_id, "WooCommerce", "live");
			$auth_test = Authentication::createClientIdAndSecret($company_id, "WooCommerce", "test");

			if (!empty($auth_live['httpResponse']) ) {
				$settings['client_data']['live']['client_secret'] = $auth_live['httpResponse']['client_secret'];
				$settings['client_data']['live']['client_id'] = $auth_live['httpResponse']['client_id'];
			}

			if (!empty($auth_test['httpResponse']) ) {
				$settings['client_data']['test']['client_secret'] = $auth_test['httpResponse']['client_secret'];
				$settings['client_data']['test']['client_id'] = $auth_test['httpResponse']['client_id'];
			}


			$payplug = new PayplugGateway();

			update_option(
				$payplug->get_option_key(),
				apply_filters('woocommerce_settings_api_sanitized_fields_' . $payplug->id, $settings)
			);

			$payplug->tmp_generate_jwt();

			$settings = PayplugWoocommerceHelper::get_payplug_options();
			$merchant_id = $payplug->retrieve_merchant_id($settings['client_data']['jwt']['test']['access_token']);

			// Save settings
			$form_fields = $payplug->get_form_fields();

			foreach ($form_fields as $key => $field) {
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
						$val = !empty($api_keys['test']) ? esc_attr($api_keys['test']) : null;
						break;
					case 'payplug_live_key':
						$val = !empty($api_keys['live']) ? esc_attr($api_keys['live']) : null;
						break;
					case 'email':
						$val = esc_html($email);
						break;
					case 'payplug_merchant_id':
						$val = esc_attr($merchant_id);
						break;
					default:
						$val = $payplug->get_option($key);
				}

				$settings[$key] = $val;
			}


			$payplug->set_post_data($settings);
			update_option(
				$payplug->get_option_key(),
				apply_filters('woocommerce_settings_api_sanitized_fields_' . $payplug->id, $settings)
			);

			// Clean up transients
			delete_transient('payplug_code_verifier');
			delete_transient('payplug_oauth_callback_uri');

			PayplugGateway::log('Successfully obtained and stored access token');

			// Redirect to success page
			wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payplug'));
			exit;

		} catch (Exception $e) {
			PayplugGateway::log(sprintf('Token exchange error: %s', $e->getMessage()), 'error');
			wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payplug'));
			exit;
		}
	}

	/**
	 * Check if we're on the PayPlug settings page
	 */
	private function is_payplug_settings_page() {
		return is_admin() &&
		       isset($_GET['page']) && $_GET['page'] === 'wc-settings' &&
		       isset($_GET['tab']) && $_GET['tab'] === 'checkout' &&
		       isset($_GET['section']) && $_GET['section'] === 'payplug';
	}

	/**
	 * Process the setup credentials
	 */
	private function process_setup_credentials($client_id, $company_id) {
		// Get the current PayPlug settings
		$settings = PayplugWoocommerceHelper::get_payplug_options();

		// Initialize auth array if it doesn't exist
		if (!isset($settings['client_data'])) {
			$settings['client_data'] = array();
		}

		// Update the settings with the new credentials
		$settings['client_data']['client_id'] = $client_id;
		$settings['client_data']['company_id'] = $company_id;

		// Save the updated settings
		update_option('woocommerce_payplug_settings', $settings);

		// Log the successful connection
		PayplugGateway::log(sprintf('PayPlug Setup: Received client_id %s and company_id %s',
			substr($client_id, 0, 6) . '***',
			substr($company_id, 0, 6) . '***'
		));
	}
}
