<?php
/**
 * The base configuration for payplug payment gateways
 *
 * This file contains the following configurations:
 * * required CDN path
 */

/**
 *  CDN path to integrated-payment lib
 */
define('IP_API', '');

/**
 *  Integrated Payment SDK domain (card-tokenization endpoint). Which one to use depends on
 *  whether the connected merchant account is QA or production - not on the Test/Live mode
 *  toggle - so, like IP_API above, this is a build-time value, not something computed from
 *  plugin settings at runtime.
 */
define('SECURE_DOMAIN', '');

/**
 *  URL of the official Oney widget loader script. Depends on whether the connected
 *  merchant account is QA or production, which is a build-time distinction (see
 *  ONEY_LOADER_URL in payplug-config.php), not something this can derive from the
 *  Test/Live mode toggle at runtime - the widget is never shown at all while the plugin
 *  is in Test mode (see PRE-3681: PayPlug's TEST-mode account data has no Oney
 *  merchant_guid/business codes to give it), so there is no "UAT vs prod" runtime choice
 *  to make here in the first place.
 */
define('ONEY_LOADER_URL', '');
