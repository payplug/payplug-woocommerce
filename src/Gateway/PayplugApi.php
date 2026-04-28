<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Payplug\Core\HttpClient;
use Payplug\Exception\BadRequestException;
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\ForbiddenException;
use Payplug\Exception\NotFoundException;
use Payplug\Exception\PayplugServerException;
use Payplug\Exception\UnauthorizedException;
use Payplug\Payplug;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;

/**
 * Handle calls to PayPlug PHP client.
 */
class PayplugApi
{
    use ServiceGetter;

    /**
     * @var PayplugGateway
     */
    protected $gateway;

    private $api_payplug;

    /**
     * PayplugApi constructor.
     *
     * @param PayplugGateway $gateway
     */
    public function __construct($gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Get register url
     *
     * @param string $callback_uri
     *
     * @throws \Payplug\Exception\ConfigurationException
     *
     * @return object
     */
    public function retrieve_register_url($callback_uri)
    {
        return $this->do_request_with_fallback('\Payplug\Authentication::getRegisterUrl', [$callback_uri, $callback_uri]);
    }

    /**
     * Try to refresh the JWT and update the API key if successful.
     *
     * @param string $current_mode The current mode (test or live) for which to refresh the JWT.
     *
     * @return bool
     */
    private function try_refresh_jwt($current_mode)
    {
        if (class_exists('Payplug\\PayplugWoocommerce\\Service\\Api')) {
            $apiService = $this->get_api();
            $configuration = $this->gateway->get_configuration();
            $options = $configuration->get_options();
            $oauth_client_data = isset($options['oauth_client_data']) ? json_decode($options['oauth_client_data'], true) : [];
            $client_data = isset($oauth_client_data[$current_mode]) ? $oauth_client_data[$current_mode] : [];
            if (!empty($client_data)) {
                $new_jwt = $apiService->generate_jwt(
                    isset($client_data['client_id']) ? $client_data['client_id'] : '',
                    isset($client_data['client_secret']) ? $client_data['client_secret'] : ''
                );
                if (!empty($new_jwt) && isset($new_jwt['access_token'])) {
                    $jwt = isset($options['jwt']) ? json_decode($options['jwt'], true) : [];
                    $jwt[$current_mode] = $new_jwt;
                    $api_keys = isset($options['api_key']) ? json_decode($options['api_key'], true) : [];
                    $api_keys[$current_mode] = $new_jwt['access_token'];
                    $configuration->update_option('jwt', json_encode($jwt));
                    $configuration->update_option('api_key', json_encode($api_keys));

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Configure PayPlug client.
     */
    public function init()
    {
        $current_mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
        $bearer_token = $this->get_api()->get_bearer_token($current_mode);
        try {
            $this->api_payplug = Payplug::init([
                'secretKey' => (string) $bearer_token,
                'apiVersion' => '2019-08-06',
            ]);
            HttpClient::setDefaultUserAgentProduct(
                'PayPlug-WooCommerce',
                PAYPLUG_GATEWAY_VERSION,
                sprintf('WooCommerce/%s', WC()->version)
            );
        } catch (ConfigurationException $e) {
            if ($this->try_refresh_jwt($current_mode)) {
                if (!is_string($bearer_token) || empty($bearer_token)) {
                    PayplugWoocommerceHelper::payplug_logout();
                    throw $e;
                }
                $this->api_payplug = Payplug::init([
                    'secretKey' => (string) $bearer_token,
                    'apiVersion' => '2019-08-06',
                ]);
                HttpClient::setDefaultUserAgentProduct(
                    'PayPlug-WooCommerce',
                    PAYPLUG_GATEWAY_VERSION,
                    sprintf('WooCommerce/%s', WC()->version)
                );
            } else {
                PayplugWoocommerceHelper::payplug_logout();
                throw $e;
            }
        }
    }

    /**
     * Retrieve payment data from PayPlug API.
     *
     * @param string $transaction_id
     *
     * @throws \Payplug\Exception\ConfigurationException
     *
     * @return null|\Payplug\Resource\Payment
     */
    public function payment_retrieve($transaction_id)
    {
        return $this->do_request_with_fallback('\Payplug\Payment::retrieve', $transaction_id);
    }

    /**
     * Create a payment.
     *
     * @param array $data
     *
     * @return null|\Payplug\Resource\Payment
     */
    public function payment_create($data)
    {
        return $this->do_request('\Payplug\Payment::create', [$data]);
    }

    /**
     * Retrieve all refunds associated with a payment.
     *
     * @param string $transaction_id
     *
     * @throws \Payplug\Exception\ConfigurationException
     *
     * @return \Payplug\Resource\Refund[]
     */
    public function refund_list($transaction_id)
    {
        return $this->do_request_with_fallback('\Payplug\Refund::listRefunds', $transaction_id);
    }

    /**
     * Retrieve refund data from PayPlug API.
     *
     * @param string $transaction_id
     * @param string $refund_id
     *
     * @throws \Payplug\Exception\ConfigurationException
     *
     * @return \Payplug\Resource\Refund
     */
    public function refund_retrieve($transaction_id, $refund_id)
    {
        return $this->do_request_with_fallback('\Payplug\Refund::retrieve', [$transaction_id, $refund_id]);
    }

    /**
     * Create a refund.
     *
     * @param string $transaction_id
     * @param array $data
     *
     * @throws \Payplug\Exception\ConfigurationException
     *
     * @return null|\Payplug\Resource\Refund
     *
     * @author Clément Boirie
     */
    public function refund_create($transaction_id, $data)
    {
        return $this->do_request_with_fallback('\Payplug\Refund::create', [$transaction_id, $data]);
    }

    /**
     * Simulate a oney payment
     *
     * @return array
     */
    public function simulate_oney_payment($price, $oney_type = 'with_fees')
    {
        $country = PayplugWoocommerceHelper::get_payplug_merchant_country();
        $oney_fees = ['x3_' . $oney_type, 'x4_' . $oney_type];

        try {
            try {
                try {
                    try {
                        $response = $this->do_request('\Payplug\OneySimulation::getSimulations', [[
                            'amount' => intval(floatval($price) * 100),
                            'country' => $country,
                            'operations' => $oney_fees,
                        ]]);
                        PayplugWoocommerceHelper::oney_simulation_values($oney_fees, $response);
                    } catch (PayplugServerException $e) {
                        $response = __('Your payment schedule simulation is temporarily unavailable. You will find this information at the payment stage.', 'payplug');
                    }
                } catch (BadRequestException $e) {
                    $response = __('Your payment schedule simulation is temporarily unavailable. You will find this information at the payment stage.', 'payplug');
                }
            } catch (UnauthorizedException $e) {
                $response = __('Your payment schedule simulation is temporarily unavailable. You will find this information at the payment stage.', 'payplug');
            }
        } catch (ForbiddenException $e) {
            $response = __('Your payment schedule simulation is temporarily unavailable. You will find this information at the payment stage.', 'payplug');
        }

        return $response;
    }

    public function validate_jwt($client_data, $jwt)
    {
        return $this->do_request_with_fallback('\Payplug\Authentication::validateJWT', [$client_data, $jwt]);
    }

    /**
     * Invoke PayPlug API. If it fail it switch to the other mode and retry the same request.
     *
     * @param callable $callback
     * @param array $params
     *
     * @throws \Payplug\Exception\ConfigurationException
     *
     * @return object
     */
    protected function do_request_with_fallback($callback, $params = [])
    {
        try {
            $response = $this->do_request($callback, $params);
        } catch (NotFoundException $e) {
            try {
                $this->switch_mode();
                $response = $this->do_request($callback, $params);
                $this->restore_mode();
            } catch (\Exception $e) {
                $this->restore_mode();
                throw $e;
            }
        }

        return $response;
    }

    /**
     * Invoke PayPlug API.
     *
     * @param callable $callback
     * @param array $params
     *
     * @return object
     */
    protected function do_request($callback, $params = [])
    {
        if (!is_array($params)) {
            $params = [$params];
        }

        try {
            return call_user_func_array($callback, $params);
        } catch (UnauthorizedException $e) {
            $current_mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
            if ($this->try_refresh_jwt($current_mode)) {
                Payplug::setSecretKey((string) $this->gateway->get_api_key($current_mode));
                try {
                    return call_user_func_array($callback, $params);
                } catch (UnauthorizedException $e) {
                    PayplugWoocommerceHelper::payplug_logout();
                    throw $e;
                }
            }
            PayplugWoocommerceHelper::payplug_logout();
            throw $e;
        }
    }

    /**
     * Reconfigure PayPlug client with new secret keys.
     *
     * @throws \Payplug\Exception\ConfigurationException
     */
    protected function switch_mode()
    {
        $switched_mode = 'test' === $this->gateway->get_current_mode() ? 'live' : 'test';
        $new_key = $this->gateway->get_api_key($switched_mode);
        if (empty($new_key)) {
            throw new \Exception(sprintf(
                'No secret key available for the %s mode',
                $switched_mode
            ));
        }

        Payplug::setSecretKey($new_key);
    }

    /**
     * Restore PayPlug client secret keys for the current mode.
     *
     * @throws \Payplug\Exception\ConfigurationException
     */
    protected function restore_mode()
    {
        $key = $this->gateway->get_api_key($this->gateway->get_current_mode());
        if (empty($key)) {
            throw new \Exception(sprintf(
                'No secret key available for the %s mode',
                $this->gateway->get_current_mode()
            ));
        }

        Payplug::setSecretKey($key);
    }
}
