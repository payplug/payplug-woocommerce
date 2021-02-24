<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugAddressData {

	const DELIVERY_TYPE_BILLING = 'BILLING';
	const DELIVERY_TYPE_VERIFIED = 'VERIFIED';
	const DELIVERY_TYPE_NEW = 'NEW';
	const DELIVERY_TYPE_SHIP_TO_STORE = 'SHIP_TO_STORE';
	const DELIVERY_TYPE_DIGITAL_GOODS = 'DIGITAL_GOODS';
	const DELIVERY_TYPE_TRAVEL_OR_EVENT = 'TRAVEL_OR_EVENT';
	const DELIVERY_TYPE_OTHER = 'OTHER';

	public static $address_fields = [
		'address1',
		'address2',
		'postcode',
		'city',
		'state',
		'country'
	];

	/**
	 * @var array
	 */
	protected $billing;

	/**
	 * @var array
	 */
	protected $shipping;

	/**
	 * Get address data from order.
	 *
	 * @param $order
	 *
	 * @return PayplugAddressData
	 * @throws \InvalidArgumentException
	 */
	public static function from_order( $order ) {
		if ( ! is_a( $order, '\WC_Order' ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Expected WC_Order object, got %s', get_class( $order ) )
			);
		}

		$address_data = new PayplugAddressData();
		$address_data->prepare_address_data( $order );

		return $address_data;
	}

	/**
	 * Check if an address has already been used by a customer
	 *
	 * @param int $customer
	 * @param string $hash
	 *
	 * @return bool
	 */
	public static function address_already_used( $customer, $hash ) {
		$hash_list = self::get_customer_addresses_hash( $customer );

		return ! empty( $hash_list ) && in_array( $hash, $hash_list );
	}

	/**
	 * Get the list of hash of all addresses used by the customer
	 *
	 * @param int $customer
	 *
	 * @return array
	 */
	public static function get_customer_addresses_hash( $customer ) {
		$hash_list = get_user_meta( $customer, 'payplug_addresses_hash', true );

		return is_array( $hash_list ) ? $hash_list : [];
	}

	/**
	 * Update the list of hash of all addresses used by the customer
	 *
	 * @param int $customer
	 * @param array $hash_list
	 *
	 * @return bool
	 */
	public static function update_customer_addresses_hash( $customer, $hash_list ) {
		$result = update_user_meta( $customer, 'payplug_addresses_hash', $hash_list );

		return false !== $result;
	}

	/**
	 * Hash an address
	 *
	 * @param array $address
	 *
	 * @return string
	 */
	public static function hash_address( $address ) {

		$to_hash = '';
		foreach ( self::$address_fields as $field ) {
			if ( empty( $address[ $field ] ) ) {
				continue;
			}

			$to_hash .= strtolower( remove_accents( $address[ $field ] ) );
		}

		return md5( $to_hash );
	}

	/**
	 * Get billing data.
	 *
	 * @return array|null
	 */
	public function get_billing() {
		return ( ! empty( $this->billing ) ) ? $this->billing : null;
	}

	/**
	 * Get shipping data.
	 *
	 * @return array|null
	 */
	public function get_shipping() {
		return ( ! empty( $this->shipping ) ) ? $this->shipping : null;
	}

	/**
	 * Prepare data for billing and shipping.
	 *
	 * @param \WC_Order $order
	 */
	protected function prepare_address_data( $order ) {

		$customer = PayplugWoocommerceHelper::is_pre_30() ? $order->customer_user : $order->get_customer_id();

		$billing_first_name = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name  = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_last_name : $order->get_billing_last_name();
		$billing_email      = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
		$billing_address1   = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_address_1 : $order->get_billing_address_1();
		$billing_address2   = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_address_2 : $order->get_billing_address_2();
		$billing_postcode   = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_postcode : $order->get_billing_postcode();
		$billing_city       = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_city : $order->get_billing_city();
		$billing_country    = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_country : $order->get_billing_country();
		$billing_company    = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_company : $order->get_billing_company();
		if (empty($billing_company)) { 
			$billing_company = $billing_first_name .' '. $billing_last_name;
		}

		if ( ! PayplugWoocommerceHelper::is_country_supported( $billing_country ) ) {
			$billing_country = PayplugWoocommerceHelper::get_default_country();
		}

		$this->billing = [
			'first_name'   => $this->limit_length( $billing_first_name ),
			'last_name'    => $this->limit_length( $billing_last_name ),
			'email'        => $this->limit_length( $billing_email, 255 ),
			'address1'     => $this->limit_length( $billing_address1, 255 ),
			'postcode'     => $this->limit_length( $billing_postcode, 16 ),
			'city'         => $this->limit_length( $billing_city ),
			'country'      => $this->limit_length( $billing_country, 2 ),
            'company_name' => $this->limit_length( $billing_company )
		];

		if ( ! empty( $billing_address2 ) ) {
			$this->billing['address2'] = $this->limit_length( $billing_address2, 255 );
		}

		// We need to know if current cart contain products we will be shipped to the customer
		// to set the correct `delivery_type` value
		if ( WC()->cart->needs_shipping() ) {
			$has_shipping_address = PayplugWoocommerceHelper::is_pre_30()
				? $order->shipping_address_1
				: $order->get_shipping_address_1();

			if ( $has_shipping_address && ! wc_ship_to_billing_address_only() ) {
				$shipping_first_name = PayplugWoocommerceHelper::is_pre_30() ? $order->shipping_first_name : $order->get_shipping_first_name();
				$shipping_last_name  = PayplugWoocommerceHelper::is_pre_30() ? $order->shipping_last_name : $order->get_shipping_last_name();
				// Using email from billing address since Woocommerce doesn't provide one for the shipping address by default.
				$shipping_email    = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
				$shipping_address1 = PayplugWoocommerceHelper::is_pre_30() ? $order->shipping_address_1 : $order->get_shipping_address_1();
				$shipping_address2 = PayplugWoocommerceHelper::is_pre_30() ? $order->shipping_address_2 : $order->get_shipping_address_2();
				$shipping_postcode = PayplugWoocommerceHelper::is_pre_30() ? $order->shipping_postcode : $order->get_shipping_postcode();
				$shipping_city     = PayplugWoocommerceHelper::is_pre_30() ? $order->shipping_city : $order->get_shipping_city();
				$shipping_country  = PayplugWoocommerceHelper::is_pre_30() ? $order->shipping_country : $order->get_shipping_country();
				$shipping_company = PayplugWoocommerceHelper::is_pre_30() ? $order->shipping_company : $order->get_shipping_company();
                
                if (empty($shipping_company)) {
                    $shipping_company = $shipping_first_name .' '. $shipping_last_name;
                }

				if ( ! PayplugWoocommerceHelper::is_country_supported( $shipping_country ) ) {
					$shipping_country = PayplugWoocommerceHelper::get_default_country();
				}

				$this->shipping = [
					'first_name' => $this->limit_length( $shipping_first_name ),
					'last_name'  => $this->limit_length( $shipping_last_name ),
					'email'      => $this->limit_length( $shipping_email, 255 ),
					'address1'   => $this->limit_length( $shipping_address1, 255 ),
					'postcode'   => $this->limit_length( $shipping_postcode, 16 ),
					'city'       => $this->limit_length( $shipping_city ),
                    'country'    => $this->limit_length( $shipping_country, 2 ),
                    'company_name' => $this->limit_length( $shipping_company )
				];

				if ( ! empty( $shipping_address2 ) ) {
					$this->shipping['address2'] = $this->limit_length( $shipping_address2, 255 );
				}

				if ( $customer > 0 ) {
					$address_hash         = self::hash_address( $this->shipping );
					$address_already_used = self::address_already_used( $customer, $address_hash );

					// Set the delivery_type to `VERIFIED` if the customer has already used the address before
					// otherwise set it to `NEW` since we have separate shipping address
					$this->shipping['delivery_type'] = ( $address_already_used )
						? self::DELIVERY_TYPE_VERIFIED
						: self::DELIVERY_TYPE_NEW;
				} else {
					// Set the delivery_type to `NEW` since we have separate shipping address
					$this->shipping['delivery_type'] = self::DELIVERY_TYPE_NEW;
				}
			} else {
				$this->shipping = $this->billing;

				if ( $customer > 0 ) {
					$address_hash         = self::hash_address( $this->shipping );
					$address_already_used = self::address_already_used( $customer, $address_hash );

					// Set the delivery_type to `VERIFIED` if the customer has already used the address before
					// otherwise set it to `BILLING` since we reuse the billing address for shipping
					$this->shipping['delivery_type'] = ( $address_already_used )
						? self::DELIVERY_TYPE_VERIFIED
						: self::DELIVERY_TYPE_BILLING;
				} else {
					// Set the delivery_type to `BILLING` since we reuse the billing address for shipping
					$this->shipping['delivery_type'] = self::DELIVERY_TYPE_BILLING;
				}
			}
		} else {
			// Set the delivery_type to `OTHER` since we dont't need to ship the products
			$this->shipping                  = $this->billing;
			$this->shipping['delivery_type'] = self::DELIVERY_TYPE_OTHER;
		}

		// Maybe set the phone field if available
		$country = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_country : $order->get_billing_country();
		$phone   = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_phone : $order->get_billing_phone();
		if ( ! empty( $phone ) && ! empty( $country ) ) {
			try {
				$phone_number_util = PhoneNumberUtil::getInstance();
				$phone_number      = $phone_number_util->parse( $phone, $country );

				if ( ! $phone_number_util->isValidNumber( $phone_number ) ) {
					throw new \Exception( 'Invalid phone number' );
				}

				if ( PhoneNumberType::MOBILE === $phone_number_util->getNumberType( $phone_number ) ) {
					$this->billing['mobile_phone_number'] = $phone_number_util->format( $phone_number, PhoneNumberFormat::E164 );
					$this->shipping['mobile_phone_number'] = $phone_number_util->format( $phone_number, PhoneNumberFormat::E164 );
				} else {
					$this->billing['landline_phone_number'] = $phone_number_util->format( $phone_number, PhoneNumberFormat::E164 );
				}
			} catch ( \Exception $e ) {
				// Fail to parse phone number.
				// Could be an incorrect number or the number doesn't belong to the billing country.
			}
		}
	}

	/**
	 * Limit string length.
	 *
	 * @param string $value
	 * @param int $maxlength
	 *
	 * @return string
	 */
	protected function limit_length( $value, $maxlength = 100 ) {
		if ( ! empty( $value ) ) {
			$value = ( strlen( $value ) > $maxlength ) ? substr( $value, 0, $maxlength ) : $value;
		}

		return $value;
	}
}
