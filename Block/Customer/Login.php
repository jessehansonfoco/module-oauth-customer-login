<?php

namespace JesseHanson\OAuthCustomerLogin\Block\Customer;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use JesseHanson\OAuthCustomerLogin\Helper\OAuth2 as OAuthHelper;

class Login
    extends Template
{

    protected OAuthHelper $oauthHelper;

    public function __construct(
        Context $context,
        OAuthHelper $oauthHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->oauthHelper = $oauthHelper;
    }

    /**
     * @return OAuthHelper
     */
    public function getOAuthHelper(): OAuthHelper
    {
        return $this->oauthHelper;
    }

    public function isGoogleEnabled(): bool
    {
        return $this->oauthHelper->isProviderEnabled('google');
    }

    public function isKeycloakEnabled(): bool
    {
        return $this->oauthHelper->isProviderEnabled('keycloak');
    }
}
