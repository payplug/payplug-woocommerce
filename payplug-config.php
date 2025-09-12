<?php
/**
 * The base configuration for payplug payment gateways
 *
 * This file contains the following configurations:
 * * required CDN path
 * @package payplug
 */

/**
 *  CDN path to Hosted-Fields lib
 */
define( 'HF_API', 'https://staging-internal-payment.gcp.dlns.io/ui/hosted-fields-lib/hosted-fields/v2.1.0/hosted-fields.min.js');
/**
 *  CDN path to integrated-payment lib
 */
define( 'IP_API', 'https://cdn-qa.payplug.com/js/integrated-payment/v1@1/index.js' );
define('USE_HOSTED_FIELDS', true);

//$hosted_field_mid = [
//	'identifier' 	 => 'PluginTestMultideviseCredorax',
//	'api_key_id' 	 => '1a8172b3-a060-4bce-b0ea-9abcdf288ff6',
//	'api_key' 		 => ')N-wwom4KmZ3aui$',
//	'account_key' 	 => '}XYZ--[rwD&UgeQg',
//];

// 3DS Account
$hosted_field_mid = [
	'identifier' 	 => 'PluginTestClient3DS',
	'api_key_id' 	 => '1a8172b3-a060-4bce-b0ea-9abcdf288ff6',
	'api_key' 		 => ')N-wwom4KmZ3aui$',
	'account_key' 	 => 'fB<kug;G0Ai}VW@P',
];

define('HOSTED_FIELD_MID', $hosted_field_mid);
