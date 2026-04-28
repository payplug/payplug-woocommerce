<?php

namespace Payplug\PayplugWoocommerce\Service;

use Payplug\Core\HttpClient;
use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;

class Api
{
    use ServiceGetter;

    protected $api_payplug;

    /**
     * @description get merchant account permission
     *
     * @param $mode
     *
     * @throws \Payplug\Exception\ConfigurationException
     *
     * @return array
     */
    public function get_account($mode = '')
    {
        if (!$this->api_payplug) {
            $this->initialize($mode);
        }
        $account = $this->do_request_with_fallback('\Payplug\Authentication::getAccount', [$this->api_payplug]);

        return [
            'result' => $account['result'],
            'response' => isset($account['response']['httpResponse']) && !empty($account['response']['httpResponse'])
                ? $account['response']['httpResponse']
                : null,
        ];
    }

    /**
     * @description get the api key for a given email and password
     *
     * @param $email
     * @param $password
     *
     * @return array
     */
    public function get_keys_by_login($email = '', $password = '')
    {
        $keys = $this->do_request_with_fallback('\Payplug\Authentication::getKeysByLogin', [$email, $password]);

        return [
            'result' => $keys['result'],
            'response' => isset($keys['response']['httpResponse']) && !empty($keys['response']['httpResponse'])
                ? $keys['response']['httpResponse']
                : null,
        ];
    }

    /**
     * @description Initialize the api with current bearer token
     *
     * @param $mode
     *
     * @throws \Payplug\Exception\ConfigurationException
     *
     * @return void
     */
    protected function initialize($mode = '')
    {
        $bearer_token = $this->get_bearer_token($mode);
        $this->api_payplug = new Payplug($bearer_token, '2019-08-06');
        HttpClient::setDefaultUserAgentProduct(
            'PayPlug-WooCommerce',
            PAYPLUG_GATEWAY_VERSION,
            sprintf('WooCommerce/%s', WC()->version)
        );
    }

    /**
     * @description get current mode configured (live|test)
     *
     * @return string
     */
    protected function get_mode()
    {
        return $this->get_configuration()->get_option('mode') ? 'live' : 'test';
    }

    /**
     * @description get current bearer token for api
     *
     * @param $mode
     *
     * @return string
     */
    public function get_bearer_token($mode = true)
    {
        $options = $this->get_configuration()->get_options();
        $api_keys = isset($options['api_key'])
            ? json_decode($options['api_key'], true)
            : [];
        $mode = is_string($mode) && !empty($mode)
            ? $mode
            : $this->get_mode();
        $bearer_token = isset($api_keys[$mode]) ? $api_keys[$mode] : '';

        $jwt = isset($options['jwt']) ? json_decode($options['jwt'], true) : [];
        $oauth_client_data = isset($options['oauth_client_data']) ? json_decode($options['oauth_client_data'], true) : [];

        if (!empty($jwt) && !empty($jwt[$mode]) && !empty($oauth_client_data) && !empty($oauth_client_data[$mode])) {
            $jwt_data = $jwt[$mode];
            $now = time();
            $expires_date = isset($jwt_data['expires_date']) ? (int)$jwt_data['expires_date'] : 0;
            if ($expires_date > 0 && ($expires_date - $now) < 30) {
                $new_jwt = $this->generate_jwt_one_shot(
                    isset($jwt_data['authorization_code']) ? $jwt_data['authorization_code'] : '',
                    isset($jwt_data['callback_uri']) ? $jwt_data['callback_uri'] : '',
                    isset($oauth_client_data[$mode]['client_id']) ? $oauth_client_data[$mode]['client_id'] : '',
                    isset($jwt_data['code_verifier']) ? $jwt_data['code_verifier'] : ''
                );
                if (!empty($new_jwt) && isset($new_jwt['access_token'])) {
                    $jwt[$mode] = $new_jwt;
                    $api_keys[$mode] = $new_jwt['access_token'];
                    $this->get_configuration()->update_option('jwt', json_encode($jwt));
                    $this->get_configuration()->update_option('api_key', json_encode($api_keys));
                    $bearer_token = $new_jwt['access_token'];
                }
            } else {
                $validate_jwt = $this->validate_jwt($oauth_client_data[$mode], $jwt[$mode]);
                if (!$validate_jwt['result'] || empty($validate_jwt['token'])) {
                    return '';
                }
                $token_validated = $validate_jwt['token'];
                $token_validated['expires_date'] -= 30;
                $jwt[$mode] = $token_validated;
                if ($validate_jwt['need_update']) {
                    $this->get_configuration()->update_option('jwt', json_encode($jwt));
                }
                $bearer_token = $jwt[$mode]['access_token'];
            }
        }

        return (string) $bearer_token;
    }

    /**
     * @description validate usage for a given jwt
     *
     * @param $oauth_client_data
     * @param $jwt
     *
     * @return array
     */
    protected function validate_jwt($oauth_client_data = [], $jwt = [])
    {
        $request = $this->do_request_with_fallback('\Payplug\Authentication::validateJWT', [$oauth_client_data, $jwt]);
        if (!$request['result']) {
            return [];
        }

        return isset($request['response']) ?
            $request['response']
            : [];
    }

    /**
     * @description Send request
     *
     * @param $callback
     * @param $params
     *
     * @return array
     */
    protected function do_request_with_fallback($callback, $params = [])
    {
        try {
            $response = [
                'result' => true,
                'response' => $this->do_request($callback, $params),
            ];
        } catch (\Exception $e) {
            $response = [
                'result' => false,
                'response' => null,
                'code' => $e->getCode(),
            ];
        }

        return $response;
    }

    /**
     * @description Send request to the api without fallback
     *
     * @param $callback
     * @param $params
     *
     * @return mixed
     */
    protected function do_request($callback, $params = [])
    {
        if (!is_array($params)) {
            $params = [$params];
        }

        return call_user_func_array($callback, $params);
    }

    /**
     * @description create client id and secret
     *
     * @param $access_token
     * @param $company_id
     * @param $mode
     *
     * @throws \Payplug\Exception\ConfigurationException
     *
     * @return array
     */
    public function create_client_id_and_secret($access_token = '', $company_id = '', $mode = 'live')
    {
        try {
            Payplug::init([
                'secretKey' => $access_token,
            ]);
        } catch (\Exception $e) {
            if (method_exists($e, 'getCode') && $e->getCode() == 401) {
                \Payplug\PayplugWoocommerce\PayplugWoocommerceHelper::payplug_logout();
                return [];
            } elseif (strpos($e->getMessage(), '401') !== false) {
                \Payplug\PayplugWoocommerce\PayplugWoocommerceHelper::payplug_logout();
                return [];
            }
            throw $e;
        }
        $request = $this->do_request_with_fallback('\Payplug\Authentication::createClientIdAndSecret', [$company_id, 'WooCommerce', $mode]);
        if (!$request['result']) {
            return [];
        }

        return isset($request['response']['httpResponse']) ?
            $request['response']['httpResponse']
            : [];
    }

    /**
     * @description generate jwt
     *
     * @param $client_id
     * @param $client_secret
     *
     * @return array
     */
    public function generate_jwt($client_id = '', $client_secret = '')
    {
        $request = $this->do_request_with_fallback('\Payplug\Authentication::generateJWT', [$client_id, $client_secret]);
        if (!$request['result']) {
            return [];
        }

        return isset($request['response']['httpResponse']) ?
            $request['response']['httpResponse']
            : [];
    }

    /**
     * @description initiate oauth
     *
     * @param $client_id
     * @param $oauth_callback_uri
     * @param $code_verifier
     *
     * @return void
     */
    public function initiate_oauth($client_id, $oauth_callback_uri, $code_verifier)
    {
        return $this->do_request('\Payplug\Authentication::initiateOAuth', [$client_id, $oauth_callback_uri, $code_verifier]);
    }

    /**
     * @description generate jwt one shot
     *
     * @param $authorization_code
     * @param $callback_uri
     * @param $client_id
     * @param $code_verifier
     *
     * @return array
     */
    public function generate_jwt_one_shot($authorization_code, $callback_uri, $client_id, $code_verifier)
    {
        $request = $this->do_request_with_fallback('\Payplug\Authentication::generateJWTOneShot', [
            $authorization_code,
            $callback_uri,
            $client_id,
            $code_verifier,
        ]);
        if (!$request['result']) {
            return [];
        }

        return isset($request['response']['httpResponse']) ?
            $request['response']['httpResponse']
            : [];
    }

    // todo: this method bellow should be implemented, do no removed it for now
    public function get_permissions()
    {
    }

    public function get_register_url()
    {
    }
}
