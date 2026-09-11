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
 *  URL of the Payplug (Dalenys) hosted-fields SDK script. Per the UHF spec
 *  (§3.6, flagged there as blocking for production), the official Sylius
 *  PayPlug plugin currently hardcodes a STAGING url
 *  (staging-internal-payment.gcp.dlns.io) directly in its shop template -
 *  the production URL, and whether it should be pinned to a version or a
 *  "latest" channel, are not yet defined anywhere. This constant ships
 *  empty until that production value exists; an empty value here degrades
 *  to not offering the hosted_fields mode's card form rather than
 *  registering a script with no src (see PayplugCreditCard::hosted_fields_scripts()).
 */
define('HOSTED_FIELDS_SDK_URL', '');

/**
 *  Identity-provider base URL for the OAuth2 client-credentials flow used to call the Unified
 *  API server-to-server (see PayplugUnifiedCore\Auth\OAuth2Client). Depends on whether the
 *  connected merchant account is QA or production - not on the Test/Live mode toggle - so, like
 *  the constants above, this is a build-time value, not something computed from plugin settings
 *  at runtime.
 */
define('UPC_OAUTH_BASE_URL', '');

/**
 *  OAuth2 "audience" parameter for the same client-credentials flow - identifies which API the
 *  minted token is valid for, per the identity provider's own client-credentials registration for
 *  this grant type. Same QA/production distinction as UPC_OAUTH_BASE_URL above.
 */
define('UPC_OAUTH_AUDIENCE', '');

/**
 *  Base URL of the Unified API itself (PayplugUnifiedCore\Services\UnifiedApiPaymentService),
 *  as opposed to the identity provider above. Same QA/production distinction.
 */
define('UPC_UNIFIED_API_BASE_URL', '');
