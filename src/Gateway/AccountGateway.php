<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\PayplugWoocommerce\Traits\Configuration;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;

class AccountGateway
{
    use ServiceGetter;

    public function _construct()
    {
        $this->configuration = $this->get_service('configuration');
    }

    /**
     * @param $email
     * @param $password
     *
     * @return array
     */
    public function register($email = '', $password = '')
    {
        // Clean previous configuration to avoid any conflict with
        $this->get_configuration()->clean_option();

        // Initialize default option value
        $this->get_configuration()->initialize_option();

        // get admin keys
        $keys = $this->get_api()->get_keys_by_login($email, $password);
        if (!$keys['result']) {
            return [];
        }

        // if keys got, register keys in database
        $secret_keys = $keys['response']['secret_keys'];
        $options_to_update = [
            'version' => PAYPLUG_GATEWAY_VERSION,
            'enabled' => true,
            'mode' => isset($secret_keys['live']) && !empty($secret_keys['live']) ? true : false,
            'email' => $email,
            'api_key' => json_encode([
                'live' => isset($secret_keys['live']) ? $secret_keys['live'] : '',
                'test' => isset($secret_keys['test']) ? $secret_keys['test'] : '',
            ]),
        ];

        $this->get_configuration()->update_options($options_to_update);

        $this->initialize_permisssion();

        return $this->get_configuration()->get_options();
    }

    /**
     * @param $client_id
     * @param $company_id
     *
     * @return void
     */
    public function initialize_jwt($client_id = '', $company_id = '')
    {
        // Clean previous configuration to avoid any conflict with
        $this->get_configuration()->clean_option();

        // Initialize default option value
        $this->get_configuration()->initialize_option();

        //
        $client_id = sanitize_text_field($client_id);
        $company_id = sanitize_text_field($company_id);
        $this->get_configuration()->update_option('oauth_client_id', $client_id);
        $this->get_configuration()->update_option('oauth_company_id', $company_id);

        // Generate a code verifier
        $code_verifier = bin2hex(openssl_random_pseudo_bytes(50));
        $this->get_configuration()->update_option('oauth_code_verifier', $code_verifier);

        // Then get callbackUri
        $oauth_callback_uri = admin_url('admin.php?page=wc-settings&tab=checkout&section=payplug');
        $this->get_configuration()->update_option('oauth_callback_uri', $oauth_callback_uri);

        // Initiate OAuth flow using the PayPlug library
        $this->get_api()->initiate_oauth($client_id, $oauth_callback_uri, $code_verifier);

        // This function will perform the redirect and exit
    }

    /**
     * @param $authorization_code
     *
     * @return bool
     */
    public function register_jwt($authorization_code = '')
    {
        if (!is_string($authorization_code) || empty($authorization_code)) {
            PayplugGateway::log('Missing authorization code', 'error');

            return false;
        }

        $temporary_jwt = $this->generate_temporary_jwt((string) $authorization_code);
        if (empty($temporary_jwt)) {
            PayplugGateway::log('Failed to obtain access token from authorization code', 'error');

            return false;
        }

        // Get Email from the token
        $id_token = $temporary_jwt['id_token'];
        $id_token_split = explode('.', $id_token);
        $payload = base64_decode($id_token_split[1]);
        $payload_decode = json_decode($payload, true);
        $email = isset($payload_decode['email']) ? $payload_decode['email'] : '';

        // Then get the client id and secret
        $access_token = $temporary_jwt['access_token'];
        $oauth_client_data = $this->get_oauth_merchant_data($access_token);
        if (empty($oauth_client_data)) {
            PayplugGateway::log('Failed to get oauth merchant data', 'error');

            return false;
        }

        // Then update the options
        $options_to_update = [
            'version' => PAYPLUG_GATEWAY_VERSION,
            'enabled' => true,
            'mode' => isset($oauth_client_data['live']) && !empty($oauth_client_data['live']) ? true : false,
            'email' => $email,
            'oauth_client_data' => json_encode($oauth_client_data),
        ];
        $this->get_configuration()->update_options($options_to_update);

        // Then generate the JWT
        $jwt = $this->generate_jwt();
        if (empty($jwt)) {
            PayplugGateway::log('Failed to get jwt', 'error');

            return false;
        }
        $this->get_configuration()->update_option('jwt', json_encode($jwt));

        // Initialize the merchant permission
        $this->initialize_permisssion();

        // Clean up transients
        $this->get_configuration()->update_option('oauth_client_id', '');
        $this->get_configuration()->update_option('oauth_company_id', '');
        $this->get_configuration()->update_option('oauth_code_verifier', '');
        $this->get_configuration()->update_option('oauth_callback_uri', '');

        return true;
    }

    /**
     * @return bool
     */
    public function is_logged()
    {
        $jwt = json_decode($this->get_configuration()->get_option('jwt'), true);

        if (!empty($jwt)) {
            $is_logged = isset($jwt['test']) && isset($jwt['test']['access_token']) && !empty($jwt['test']['access_token']);
        } else {
            $api_key = json_decode($this->get_configuration()->get_option('api_key'), true);
            $is_logged = isset($api_key['test']) && !empty($api_key['test']);
        }

        return $is_logged;
    }

    /**
     * @return void
     */
    public function logout()
    {
    }

    /**
     * @param $mode
     *
     * @return array
     */
    public function get_permissions($mode = '')
    {
        $account_permissions = $this->get_api()->get_account($mode);

        return $this->format_account_permissions($account_permissions['response']);
    }

    /**
     * @return string
     */
    public function get_merchant_id()
    {
        $account_permissions = $this->get_api()->get_account('test');

        return $account_permissions['response']['id'];
    }

    /**
     * @return void
     */
    public function initialize_permisssion()
    {
        // get the permissions
        $permissions = $this->get_permissions();

        // then get test merchant id
        $company_id = $this->get_merchant_id();
        $permissions['global']['company_id'] = $company_id;

        // and update options
        $this->get_configuration()->update_options($permissions['global']);
        $this->get_configuration()->update_payment_permissions($permissions['payment_methods']);
    }

    /**
     * @param $access_token
     *
     * @return array
     */
    public function get_oauth_merchant_data($access_token)
    {
        if (!is_string($access_token) || empty($access_token)) {
            return [];
        }
        $company_id = $this->get_configuration()->get_option('oauth_company_id');
        $auth_live = $this->get_api()->create_client_id_and_secret($access_token, $company_id, 'live');
        $auth_test = $this->get_api()->create_client_id_and_secret($access_token, $company_id, 'test');
        $oauth_client_data = [
            'live' => !empty($auth_live) ? [
                'client_secret' => $auth_live['client_secret'],
                'client_id' => $auth_live['client_id'],
            ] : $auth_live,
            'test' => !empty($auth_test) ? [
                'client_secret' => $auth_test['client_secret'],
                'client_id' => $auth_test['client_id'],
            ] : $auth_test,
        ];

        return (empty($auth_live) && empty($auth_test)) ? [] : $oauth_client_data;
    }

    /**
     * @return array
     */
    public function generate_jwt()
    {
        $oauth_client_data = json_decode($this->get_configuration()->get_option('oauth_client_data'), true);
        if (empty($oauth_client_data)) {
            return [];
        }

        foreach ($oauth_client_data as $key => $data) {
            if (empty($data) || !in_array($key, ['live', 'test'])) {
                $jwt[$key] = [];
                continue;
            }

            if (isset($data['client_id']) && isset($data['client_secret'])) {
                $generated_jwt = $this->get_api()->generate_jwt($data['client_id'], $data['client_secret']);
                if (empty($generated_jwt)) {
                    return false;
                }
                $jwt[$key] = $generated_jwt;
            }
        }

        return $jwt;
    }

    /**
     * @param $authorization_code
     *
     * @return array
     */
    private function generate_temporary_jwt($authorization_code = '')
    {
        if (!is_string($authorization_code) || empty($authorization_code)) {
            return [];
        }

        $code_verifier = $this->get_configuration()->get_option('oauth_code_verifier');
        $callback_uri = $this->get_configuration()->get_option('oauth_callback_uri');
        $client_id = $this->get_configuration()->get_option('oauth_client_id');
        if (empty($code_verifier) || empty($callback_uri) || empty($client_id)) {
            PayplugGateway::log('Missing OAuth parameters for token exchange', 'error');

            return [];
        }

        $temporary_jwt = $this->get_api()->generate_jwt_one_shot($authorization_code, $callback_uri, $client_id, $code_verifier);
        if (empty($temporary_jwt)) {
            PayplugGateway::log('Failed to obtain access token from authorization code', 'error');

            return [];
        }

        return $temporary_jwt;
    }

    /**
     * @param $account_permissions
     *
     * @return array
     */
    protected function format_account_permissions($account_permissions = [])
    {
        if (!is_array($account_permissions) || empty($account_permissions)) {
            return [];
        }

        $expected_fields = $this->get_configuration()->get_expected_fields();
        $options = $this->get_configuration()->get_options();

        $formated_account_permissions = [];
        $expected_payment_permissions = $expected_fields['payment_methods']['permissions'];

        // format global configuration
        foreach ($expected_fields as $key => $field) {
            switch (true) {
                case 'company_id' == $key:
                    $value = (string) $account_permissions['id'];
                    break;
                case 'company_iso' == $key:
                    $value = (string) $account_permissions['country'];
                    break;
                case 'currencies' == $key:
                    $value = json_encode($account_permissions['configuration']['currencies']);
                    break;
                default:
                    $value = null;
                    break;
            }
            if (null !== $value) {
                $formated_account_permissions['global'][$key] = $value;
            }
        }

        // format payment configuration
        foreach ($expected_payment_permissions as $key => $value) {
            $payment_permissions = [];
            switch (true) {
                default:
                    $account_payment = isset($account_permissions['payment_methods'][$key])
                    && !empty($account_permissions['payment_methods'][$key])
                        ? $account_permissions['payment_methods'][$key]
                        : [];
                    if (!empty($account_payment)) {
                        $account_payment = $account_permissions['payment_methods'][$key];
                        $countries = isset($account_payment['allowed_countries']) && !empty($account_payment['allowed_countries'])
                            ? $account_payment['allowed_countries']
                            : ['ALL'];
                        $min_amounts = isset($account_payment['min_amounts']) && !empty($account_payment['min_amounts'])
                            ? $account_payment['min_amounts']
                            : $account_permissions['configuration']['min_amounts'];
                        $max_amounts = isset($account_payment['max_amounts']) && !empty($account_payment['max_amounts'])
                            ? $account_payment['max_amounts']
                            : $account_permissions['configuration']['max_amounts'];

                        $payment_permissions['enabled'] = $account_payment['enabled'];
                        $payment_permissions['countries'] = json_encode($countries);
                        $payment_permissions['amounts'] = json_encode([
                            'min' => $min_amounts,
                            'max' => $max_amounts,
                        ]);

                        if ('apple_pay' == $key) {
                            $payment_permissions['allowed_domains'] = json_encode($account_permissions['payment_methods'][$key]['allowed_domain_names']);
                        }
                    }
                    break;
                case 'payplug' == $key:
                    $payment_permissions = [
                        'enabled' => true,
                        'countries' => json_encode(['ALL']),
                        'amounts' => json_encode([
                            'min' => $account_permissions['configuration']['min_amounts'],
                            'max' => $account_permissions['configuration']['max_amounts'],
                        ]),
                    ];
                    break;
                case 'installment' == $key:
                    $payment_permissions = [
                        'enabled' => $account_permissions['permissions']['can_create_installment_plan'],
                        'countries' => json_encode(['ALL']),
                        'amounts' => json_encode([
                            'min' => $account_permissions['configuration']['min_amounts'],
                            'max' => $account_permissions['configuration']['max_amounts'],
                        ]),
                    ];
                    break;
                case 'deferred' == $key:
                    $payment_permissions = [
                        'enabled' => $account_permissions['permissions']['can_create_deferred_payment'],
                        'countries' => json_encode(['ALL']),
                        'amounts' => json_encode([
                            'min' => $account_permissions['configuration']['min_amounts'],
                            'max' => $account_permissions['configuration']['max_amounts'],
                        ]),
                    ];
                    break;
                case 'one_click' == $key:
                    $payment_permissions = [
                        'enabled' => $account_permissions['permissions']['can_save_cards'],
                        'countries' => json_encode(['ALL']),
                        'amounts' => json_encode([
                            'min' => $account_permissions['configuration']['min_amounts'],
                            'max' => $account_permissions['configuration']['max_amounts'],
                        ]),
                    ];
                    break;
            }
            $formated_account_permissions['payment_methods'][$key] = $payment_permissions;
        }

        return $formated_account_permissions;
    }
}
