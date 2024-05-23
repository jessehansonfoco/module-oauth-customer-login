<?php

namespace JesseHanson\OAuthCustomerLogin\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use OAuth\Common\Token\TokenInterface;
use JesseHanson\OAuthCustomerLogin\OAuth2\Service\Keycloak;
use JesseHanson\OAuthCustomerLogin\Helper\Customer as CustomerHelper;
use JesseHanson\OAuthCustomerLogin\Helper\OAuth2 as OAuthHelper;

/**
 * Class KeycloakLoginSuccess
 * @package JesseHanson\OAuthCustomerLogin\Observer
 */
class KeycloakLoginSuccess
    implements ObserverInterface
{
    /**
     * @var CustomerHelper
     */
    protected $customerHelper;

    /**
     * @var OAuthHelper
     */
    protected $oauthHelper;

    public function __construct(
        CustomerHelper $customerHelper,
        OAuthHelper $oauthHelper
    ) {
        $this->customerHelper = $customerHelper;
        $this->oauthHelper = $oauthHelper;
    }

    public function execute(Observer $observer)
    {
        // General Plan
        //  1. retrieve or create customer
        //   a. lookup by email
        //   b. create new Customer if necessary, copy data to customer
        //   c. possibly load customer data from Keycloak
        //  2. login the customer
        //  3. update redirect if necessary

        // token, request, response, provider_code, provider, helper
        $event = $observer->getEvent();
        $providerCode = $event->getData('provider_code');
        if (Keycloak::PROVIDER_CODE !== $providerCode) {
            return;
        }

        $this->oauthHelper->setCurrentProviderCode(Keycloak::PROVIDER_CODE);

        /** @var TokenInterface $token */
        $token = $event->getData('token');
        $request = $event->getData('request');
        $response = $event->getData('response');
        $provider = $event->getData('provider');

        // get access token
        $accessToken = $token->getAccessToken();
        $refreshToken = $token->getRefreshToken();

        $jwtData = $this->oauthHelper->decodeAccessToken($accessToken);

        // could use more flexible/configurable mapping here, an observer or service
        $email = $jwtData->email;
        $firstName = $jwtData->given_name;
        $lastName = $jwtData->family_name;

        $quoteId = $this->customerHelper->getCheckoutSession()->getQuoteId();
        $customer = $this->customerHelper->findOrCreateByEmail($email, $firstName, $lastName);
        $this->customerHelper->getCustomerSession()->setCustomerAsLoggedIn($customer);
        $this->customerHelper->getCheckoutSession()->setQuoteId($quoteId);
    }
}
