<?php

namespace Payplug\PayplugWoocommerce\Model;

class Lock
{

	/**
	 * create Lock table
	 */
	static function create_lock_table(){
		global $wpdb;

		$sql = "CREATE TABLE  IF NOT EXISTS `{$wpdb->base_prefix}woocommerce_payplug_lock` (";
		$sql .= " `payment_id` VARCHAR(50) NOT NULL,";
		$sql .= " `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,";
		$sql .= " PRIMARY KEY (`payment_id`));";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta( $sql );

	}

	/**
	 * @param $payment_id
	 * @return bool|int
	 */
	static function insert_lock($payment_id){
		global $wpdb;
		$wpdb->hide_errors();
		return $wpdb->insert($wpdb->prefix . 'woocommerce_payplug_lock', array("payment_id" => $payment_id));
	}

	/**
	 * @param $payment_id
	 * @return bool|int
	 */
	static function delete_lock($payment_id){
		global $wpdb;
		return $wpdb->delete($wpdb->prefix . 'woocommerce_payplug_lock', array("payment_id" => $payment_id));
	}

	/**
	 * @param $payment_id
	 * @return bool|int
	 */
	static function get_lock($payment_id){
		global $wpdb;

		$table_name = $wpdb->prefix . 'woocommerce_payplug_lock';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE payment_id = %s", array( $payment_id ) ) );

		if ( !$result ) {
			return false;
		}

		return $result;

	}

	static function delete_lock_table(){
		global $wpdb;

		$table_name = $wpdb->prefix . 'woocommerce_payplug_lock';
		$sql = "DROP TABLE IF EXISTS $table_name;";
		$wpdb->query($sql);

	}


}
