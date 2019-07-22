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

		$billing_first_name = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name  = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_last_name : $order->get_billing_last_name();
		$billing_email      = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
		$billing_address1   = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_address_1 : $order->get_billing_address_1();
		$billing_address2   = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_address_2 : $order->get_billing_address_2();
		$billing_postcode   = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_postcode : $order->get_billing_postcode();
		$billing_city       = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_city : $order->get_billing_city();
		$billing_country    = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_country : $order->get_billing_country();
		$this->billing      = [
			'first_name' => $this->limit_length( $billing_first_name ),
			'last_name'  => $this->limit_length( $billing_last_name ),
			'email'      => $this->limit_length( $billing_email, 255 ),
			'address1'   => $this->limit_length( $billing_address1, 255 ),
			'postcode'   => $this->limit_length( $billing_postcode, 16 ),
			'city'       => $this->limit_length( $billing_city ),
			'country'    => $this->limit_length( $billing_country, 2 ),
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
				$this->shipping    = [
					'first_name' => $this->limit_length( $shipping_first_name ),
					'last_name'  => $this->limit_length( $shipping_last_name ),
					'email'      => $this->limit_length( $shipping_email, 255 ),
					'address1'   => $this->limit_length( $shipping_address1, 255 ),
					'postcode'   => $this->limit_length( $shipping_postcode, 16 ),
					'city'       => $this->limit_length( $shipping_city ),
					'country'    => $this->limit_length( $shipping_country, 2 ),
				];

				if ( ! empty( $shipping_address2 ) ) {
					$this->shipping['address2'] = $this->limit_length( $shipping_address2, 255 );
				}

				// Set the delivery_type to `NEW` since we have separate shipping address
				$this->shipping['delivery_type'] = self::DELIVERY_TYPE_NEW;
			} else {
				// Set the delivery_type to `BILLING` since we reuse the billing address for shipping
				$this->shipping                  = $this->billing;
				$this->shipping['delivery_type'] = self::DELIVERY_TYPE_BILLING;
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

				if ( PhoneNumberType::MOBILE === $phone_number_util->getNumberType( $phone_number ) ) {
					$this->billing['mobile_phone_number'] = $phone_number_util->format( $phone_number, PhoneNumberFormat::E164 );
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
