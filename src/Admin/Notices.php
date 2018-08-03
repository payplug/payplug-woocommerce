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
            <style>
                .notice--start {
                    position: relative;
                    border: 0;
                    padding: 30px 40px;
                    background: url(<?php echo esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/bg_notice--start.jpg' ); ?>) no-repeat 50% 50%;
                    background-size: cover;
                }

                .notice--start:before {
                    content: "";
                    display: block;
                    z-index: 1;
                    position: absolute;
                    top: 0;
                    right: 0;
                    bottom: 0;
                    left: 0;
                    background-color: #49829f;
                    opacity: .28;
                }

                .notice--start .main {
                    position: relative;
                    z-index: 2;
                }

                .notice--start .notice__title {
                    margin-top: 0;
                    color: #fff;
                    font-size: 16px;
                    font-weight: 400;
                    line-height: 1.55;
                }

                .notice--start .notice__title strong {
                    font-size: 20px;
                    font-weight: 700;
                }

                .notice--start .button.button-hero {
                    font-weight: 400;
                    font-size: 16px;
                    height: 46px;
                    box-shadow: none;
                    color: #fff;
                    border-color: #55bf9c;
                    background-color: #55bf9c;
                }

                .notice--start .button:hover,
                .notice--start .button:focus,
                .notice--start .button:active {
                    color: #fff;
                    border-color: #4a977d;
                    background-color: #4a977d;
                }

                .notice--start .notice__img {
                    display: block;
                    margin: auto;
                }

                @media screen and (min-width: 48em) {
                    .notice--start .main {
                        padding-right: 250px;
                    }

                    .notice--start .notice__img {
                        margin: 0;
                        position: absolute;
                        right: 0;
                        top: 50%;
                        transform: translateY(-50%);
                    }
                }
            </style>
            <div class="notice notice--start">
                <div class="inside">
                    <div class="main">
                        <h2 class="notice__title"><?php _e( "Thank you for installing PayPlug your online payment solution", 'payplug' ); ?>
                            <br>
                            <strong><?php _e( 'It remains a step to activate the plugin on your site !', 'payplug' ); ?></strong>
                        </h2>
                        <a href="<?php echo esc_url( PayplugWoocommerceHelper::get_setting_link() ); ?>"
                           class="button button-hero"><?php _e( 'Login', 'payplug' ); ?></a>
                        <img class="notice__img"
                             src="<?php echo esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/Payplug_logoWhite.png' ); ?>"
                             alt="PayPlug logo">
                    </div>
                </div>
            </div>
			<?php
		} elseif ( ! empty( $payplug_test_key ) && empty( $payplug_live_key ) ) {
			?>
            <div class="notice notice-warning">
                <p><strong><?php _e( 'PayPlug is in TEST mode', 'payplug' ); ?></strong></p>
                <p><?php _e( 'When your account is approved by PayPlug, please disconnect and reconnect in the settings page to activate LIVE mode.', 'payplug' ); ?></p>
            </div>
			<?php
		} elseif ( ! empty( $payplug_live_key ) && $testmode ) {
			?>
            <div class="notice notice-info">
                <p><?php _e( 'PayPlug is in TEST mode. All payments are fictitious and will not generate real transactions.', 'payplug' ); ?></p>
            </div>
			<?php
		}
	}
}