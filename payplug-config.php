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
 *  URL of the official Oney widget loader script (assets.oney.io in production,
 *  assets-uat.oney.io in staging). Depends on whether the connected merchant account is QA
 *  or production - not on the Test/Live mode toggle - so, like SECURE_DOMAIN above, this is a
 *  build-time value, not something computed from plugin settings at runtime.
 */
define('ONEY_LOADER_URL', '');

/**
 *  URL of the Payplug (Dalenys) hosted-fields SDK script. Depends on
 *  whether the connected merchant account is QA or production - not on
 *  the Test/Live mode toggle - so, like the constants above, this is a
 *  build-time value, not something computed from plugin settings at
 *  runtime.
 */
define('HOSTED_FIELDS_SDK_URL', '');
