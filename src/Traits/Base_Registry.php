<?php

namespace Payplug\PayplugWoocommerce\Traits;

use Payplug\PayplugWoocommerce\PayplugWoocommerce;

if ( ! trait_exists( 'Base_Registry' ) ) {

	/**
	 * Base Registry Trait
	 *
	 * Controller Registry and Model Registry use this trait to deal with all objects.
	 *
	 * This trait provides methods to store & retrieve objects in Registry
	 *
	 * Registry is like a hashmap to store the objects
	 */

	trait Base_Registry {

		/**
		 * @var array
		 */

		protected static $stored_objects = [];


		/**
		 *
		 * Set object to registry
		 *
		 * @param $key
		 * @param $value
		 *
		 * @return void
		 */

		public static function set( $key, $value ) {
			if ( ! is_string( $key ) ) {
				trigger_error( __( 'Key passed to `set` method must be key', PayplugWoocommerce::PLUGIN_ID ), E_USER_ERROR ); // @codingStandardsIgnoreLine.
			}
			static::$stored_objects[ $key ] = $value;
		}
	}
}
