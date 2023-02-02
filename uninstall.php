<?php

// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

$option_name = 'woocommerce_payplug_settings';

delete_option( $option_name );

// for site options in Multisite
delete_site_option( $option_name );
