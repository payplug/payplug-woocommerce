<?php

namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

/**
 * Handle admin notices.
 *
 * @package Payplug\PayplugWoocommerce\Admin
 */
class Notices {

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
     * Enqueue PayPlug notice style.
     *
     * @return void
	 */
	public function admin_enqueue_scripts() {
		$options          = get_option( 'woocommerce_payplug_settings' );
		$payplug_test_key = ! empty( $options['payplug_test_key'] ) ? $options['payplug_test_key'] : '';
		$payplug_live_key = ! empty( $options['payplug_live_key'] ) ? $options['payplug_live_key'] : '';
		if ( empty( $payplug_test_key ) && empty( $payplug_live_key ) ) {
			wp_enqueue_style(
				'payplug-notice',
				PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/notice.css',
				[],
				PAYPLUG_GATEWAY_VERSION
			);
		}
	}

	/**
	 * Display admin notices.
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		/*
		 * Before Woocommerce 3.2.x, settings were saved just before displaying the page
		 * which cause the admin_notice to display old data.
		 *
		 * This condition check if we are on the PayPlug gateway settings page and if we
		 * have new settings to save. If true we hook the notice to a hook which will run after the new
		 * settings have been saved.
		 */
		$screen = get_current_screen();
		$wc     = function_exists( 'WC' ) ? WC() : $GLOBALS['woocommerce'];

		if (
			version_compare( $wc->version, '3.2.0', '<' )
			&& ! empty( $_POST )
			&& 'woocommerce_page_wc-settings' === $screen->id
			&& isset( $_GET['section'] )
			&& 'payplug' === $_GET['section']
		) {
			add_action( 'woocommerce_settings_saved', [ $this, 'display_notice' ] );

			return;
		}

		$this->display_notice();
	}

	public function display_notice() {
		$options          = get_option( 'woocommerce_payplug_settings' );
		$testmode         = ( isset( $options['mode'] ) && 'no' === $options['mode'] ) ? true : false;
		$payplug_test_key = ! empty( $options['payplug_test_key'] ) ? $options['payplug_test_key'] : '';
		$payplug_live_key = ! empty( $options['payplug_live_key'] ) ? $options['payplug_live_key'] : '';

		if ( empty( $payplug_test_key ) && empty( $payplug_live_key ) ) {
			?>
            <div class="notice notice--start">
                <div class="inside">
                    <div class="main">
                        <h2 class="notice__title"><?php _e( 'Thank you for installing PayPlug as your online payment solution.', 'payplug' ); ?>
                            <br>
                            <strong><?php _e( 'Only one step left to activate the plugin on your site !', 'payplug' ); ?></strong>
                        </h2>
                        <a href="<?php echo esc_url( PayplugWoocommerceHelper::get_setting_link() ); ?>"
                           class="button button-hero"><?php _e( 'Login', 'payplug' ); ?></a>
                        <img class="notice__img"
                             src="<?php echo esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/PAYPLUG_LOGO_blanc.svg' ); ?>"
                             alt="PayPlug logo">
                    </div>
                </div>
            </div>
			<?php
		} elseif ( ! empty( $payplug_test_key ) && empty( $payplug_live_key ) ) {
			?>
            <div class="notice notice-warning">
                <p><strong><?php _e( 'PayPlug is in TEST mode', 'payplug' ); ?></strong></p>
                <p><?php _e( 'Once your PayPlug account has been validated, please log out and log in again from the configuration page in order to activate LIVE mode.', 'payplug' ); ?></p>
            </div>
			<?php
		} elseif ( ! empty( $payplug_live_key ) && $testmode ) {
			?>
            <div class="notice notice-info">
                <p><?php _e( 'Payments in TEST mode will be simulations and will not generate real transactions.', 'payplug' ); ?></p>
            </div>
			<?php
		}
	}
}
