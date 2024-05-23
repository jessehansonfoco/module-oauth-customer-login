<?php

namespace JesseHanson\OAuthCustomerLogin\Plugin;

use Closure;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Controller\Account\Login as LoginController;
use JesseHanson\OAuthCustomerLogin\Helper\OAuth2;

/**
 * Class CustomerLogin
 * @package JesseHanson\OAuthCustomerLogin\Plugin
 */
class CustomerLogin
{
    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var OAuth2
     */
    protected $oauthHelper;

    public function __construct(
        CustomerSession $customerSession,
        OAuth2 $oauthHelper
    ) {
        $this->customerSession = $customerSession;
        $this->oauthHelper = $oauthHelper;
    }

    /**
     * @param LoginController $controller
     * @param Closure $next
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function aroundExecute(LoginController $controller, Closure $next)
    {
        $providerCode = $this->oauthHelper->getCurrentProviderCode();
        if (!$this->oauthHelper->isProviderEnabled($providerCode)) {
            return $next();
        }

        // this basically disables magento's login form and forces a redirect to google or keycloak
        if ($this->oauthHelper->getForceRedirect($providerCode)
            && !$this->customerSession->isLoggedIn()
        ) {
            return $this->oauthHelper->createRedirectResponse($providerCode);
        }

        return $next();
    }
}
