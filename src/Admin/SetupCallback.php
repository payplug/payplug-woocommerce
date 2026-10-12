<?php

namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Traits\GatewayGetter;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;

/**
 * PayPlug Setup Callback Handler
 * Handles the callback after a user has been authenticated on the PayPlug Portal
 */
class SetupCallback
{
    use ServiceGetter;
    use GatewayGetter;

    public const SETUP_NONCE_ACTION = 'payplug_setup';
    public const SETUP_NONCE_PARAM = 'payplug_setup_nonce';

    /**
     * Initialize the handler
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'handle_setup_callback'], 5);
        add_action('admin_init', [$this, 'handle_oauth_callback'], 5);
    }

    /**
     * Handle the setup callback from PayPlug Portal
     */
    public function handle_setup_callback(): void
    {
        if (wp_doing_ajax() || !$this->is_payplug_settings_page() || !current_user_can('manage_woocommerce')) {
            return;
        }

        // Check if we have the client_id and company_id parameters
        if (isset($_GET['client_id']) && isset($_GET['company_id'])) {
            $client_id = sanitize_text_field($_GET['client_id']);
            $company_id = sanitize_text_field($_GET['company_id']);
            $nonce = isset($_GET[self::SETUP_NONCE_PARAM]) ? sanitize_text_field(wp_unslash($_GET[self::SETUP_NONCE_PARAM])) : '';
            if (!wp_verify_nonce($nonce, self::SETUP_NONCE_ACTION)) {
                PayplugGateway::log('Setup callback rejected: invalid or missing nonce', 'warning');
                add_action('admin_notices', function (): void {
                    echo '<div class="notice notice-error is-dismissible">' .
                        '<p>' . esc_html__('The PayPlug setup link is invalid or has expired. Please restart the connection from the PayPlug settings page.', 'payplug') . '</p>' .
                        '</div>';
                });

                return;
            }
            $this->start_oauth($client_id, $company_id);
        }

        // Display success message if setup was successful
        if (isset($_GET['setup_success']) && $_GET['setup_success'] == '1') {
            add_action('admin_notices', function (): void {
                echo '<div class="notice notice-success is-dismissible">' .
                    '<p>' . esc_html__('PayPlug account successfully connected!', 'payplug') . '</p>' .
                    '</div>';
            });
        }

        // Display error message if setup failed
        if (isset($_GET['setup_error'])) {
            add_action('admin_notices', function (): void {
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
    public function handle_oauth_callback(): void
    {
        // Only handle the callback when we're on the PayPlug settings page — prevents
        // intercepting OAuth callbacks from other plugins (e.g. Google Site Kit) that
        // also use a ?code= parameter on wp-admin pages.
        if (!$this->is_payplug_settings_page() || !current_user_can('manage_woocommerce')) {
            return;
        }

        $account_gateway = $this->get_gateway('account');
        if (!isset($_GET['code'])) {
            return;
        }

        // Bail if no OAuth flow was initiated by PayPlug (missing stored verifier).
        if (empty($this->get_configuration()->get_option('oauth_code_verifier'))) {
            return;
        }
        $register_jwt = false;

        try {
            $authorization_code = sanitize_text_field($_GET['code']);
            $register_jwt = $account_gateway->register_jwt((string) $authorization_code);
            if ($register_jwt) {
                PayplugGateway::log('Successfully obtained and stored access token');
            }
        } catch (Exception $e) {
            PayplugGateway::log(sprintf('Token exchange error: %s', $e->getMessage()), 'error');
        }

        if (!$register_jwt) {
            $this->get_configuration()->clean_option();
        }

        // Redirect to success page
        wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payplug'));
        exit;
    }

    /**
     * Reset the PayPlug configuration and redirect to the OAuth authorization page
     */
    protected function start_oauth(string $client_id, string $company_id): void
    {
        $this
            ->get_gateway('account')
            ->initialize_jwt($client_id, $company_id);
    }

    /**
     * OAuth redirect_uri registered on the PayPlug portal, compared by exact match
     */
    public static function oauth_callback_uri(): string
    {
        return get_admin_url(null, '/admin.php?page=wc-settings&tab=checkout&section=payplug');
    }

    /**
     * URI the PayPlug portal redirects to with client_id and company_id
     */
    public static function setup_redirection_uri(): string
    {
        return add_query_arg(
            self::SETUP_NONCE_PARAM,
            wp_create_nonce(self::SETUP_NONCE_ACTION),
            self::oauth_callback_uri()
        );
    }

    /**
     * Check if we're on the PayPlug settings page
     */
    private function is_payplug_settings_page()
    {
        return is_admin() &&
            isset($_GET['page']) && $_GET['page'] === 'wc-settings' &&
            isset($_GET['tab']) && $_GET['tab'] === 'checkout' &&
            isset($_GET['section']) && $_GET['section'] === 'payplug';
    }
}
