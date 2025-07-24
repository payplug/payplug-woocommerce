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
define('INTEGRATED_PAYMENT', true);
