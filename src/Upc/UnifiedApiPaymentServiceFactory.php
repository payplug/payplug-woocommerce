<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceConfigurationRepository;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceTokenCache;
use Payplug\PayplugWoocommerce\Upc\Adapters\WpOAuthHttpClient;
use Payplug\PayplugWoocommerce\Upc\Adapters\WpUnifiedApiHttpClient;
use PayplugUnifiedCore\Auth\OAuth2Client;
use PayplugUnifiedCore\Auth\TokenManager;
use PayplugUnifiedCore\Services\UnifiedApiPaymentService;

class UnifiedApiPaymentServiceFactory
{
    public function create(): UnifiedApiPaymentService
    {
        $configuration_repository = new WooCommerceConfigurationRepository();

        // redirectUri/scope are never read by OAuth2Client::getClientCredentialsToken() (only
        // buildAuthorizationUrl()/exchangeAuthorizationCode() use them, for the
        // authorization-code+PKCE flow this module doesn't need - client_id/client_secret are
        // already obtained via the existing account-linking flow) - hardcoded empty rather than
        // adding config constants nobody can meaningfully fill in for this flow.
        $oauth2_client = new OAuth2Client(
            new WpOAuthHttpClient(),
            UPC_OAUTH_BASE_URL,
            '',
            '',
            UPC_OAUTH_AUDIENCE
        );

        $token_manager = new TokenManager(new WooCommerceTokenCache(), $oauth2_client);

        return new UnifiedApiPaymentService(
            new WpUnifiedApiHttpClient(),
            $token_manager,
            UPC_UNIFIED_API_BASE_URL,
            $configuration_repository->getClientId(),
            $configuration_repository->getClientSecret()
        );
    }
}
