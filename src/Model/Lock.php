<?php

namespace Payplug\PayplugWoocommerce\Model;

class Lock
{
	static  $table="woocommerce_payplug_lock";
	/**
	 * create Lock table
	 */

	static function create_lock_table(){
		global $wpdb;

		$table_name = self::getTableName();

		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name)
		{
			$sql = "CREATE TABLE  IF NOT EXISTS `$table_name` (";
			$sql .= " `payment_id` VARCHAR(50) NOT NULL,";
			$sql .= " `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,";
			$sql .= " PRIMARY KEY (`payment_id`));";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
	}

	/**
	 * @param $payment_id
	 * @return bool|int
	 */
	static function insert_lock($payment_id){
		global $wpdb;
		return $wpdb->insert(self::getTableName(), array("payment_id" => $payment_id));
	}

	/**
	 * @param $payment_id
	 * @return bool|int
	 */
	static function delete_lock($payment_id){
		global $wpdb;
		return $wpdb->delete(self::getTableName(), array("payment_id" => $payment_id));
	}

	/**
	 * @param $payment_id
	 * @return bool|int
	 */
	static function get_lock($payment_id){
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `".self::getTableName()."` WHERE payment_id = %s", array( $payment_id ) ) );

		if ( !$result ) {
			return false;
		}

		return $result;

	}

	static function delete_lock_table(){
		global $wpdb;

		$sql = "DROP TABLE IF EXISTS `" .self::getTableName()."`;";
		$wpdb->query($sql);

	}

	static function getTableName()
	{
		global $wpdb;
		return $wpdb->base_prefix . self::$table;
	}

}
