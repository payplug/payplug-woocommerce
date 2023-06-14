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

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT count(column_name) as column_exists FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$wpdb->base_prefix}woocommerce_payplug_lock' AND column_name = %s ", ['id']
			)
		);

		if( !empty($result) && !$result->column_exists){
			static::update_lock_table();
		}

	}

	/**
	 * update table
	 */
	static function update_lock_table(){
		global $wpdb;

		$sql = "ALTER TABLE `{$wpdb->base_prefix}woocommerce_payplug_lock` ";
		$sql .= " ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST,";
		$sql .= " DROP PRIMARY KEY,";
		$sql .= " ADD PRIMARY KEY (`id`);";
		$wpdb->query($sql);

	}

	/**
	 * @param $payment_id
	 * @return bool|int
	 */
	static function insert_lock($payment_id){
		global $wpdb;
		$wpdb->hide_errors();
		$wpdb->insert($wpdb->prefix . 'woocommerce_payplug_lock', array("payment_id" => $payment_id));
		return $wpdb->insert_id;
	}

	/**
	 * @param $id
	 * @return bool|int
	 */
	static function delete_lock($id){
		global $wpdb;
		return $wpdb->delete($wpdb->prefix . 'woocommerce_payplug_lock', array("id" => $id));
	}


	/**
	 * @param $payment_id
	 * @param $id
	 * @return false
	 */
	static function locked($payment_id, $id){
		global $wpdb;

		$table_name = $wpdb->prefix . 'woocommerce_payplug_lock';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE `payment_id` = %s AND `id` <> %s ", array( $payment_id ) ) );

		if ( !$result ) {
			return false;
		}

		return $result;

	}

	/**
	 * @param $id
	 * @return bool|int
	 */
	static function get_lock_by_payment_id($payment_id){
		global $wpdb;

		$table_name = $wpdb->prefix . 'woocommerce_payplug_lock';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE payment_id = %s", array( $payment_id ) ) );
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
