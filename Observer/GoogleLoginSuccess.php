<?php

namespace JesseHanson\OAuthCustomerLogin\Observer;

use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\OAuth2\Token\StdOAuth2Token;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use JesseHanson\OAuthCustomerLogin\Helper\Customer as CustomerHelper;
use JesseHanson\OAuthCustomerLogin\Helper\OAuth2 as OAuthHelper;
use JesseHanson\OAuthCustomerLogin\OAuth2\Service\Google;

class GoogleLoginSuccess
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
        if (Google::PROVIDER_CODE !== $providerCode) {
            return;
        }

        $this->oauthHelper->setCurrentProviderCode(Google::PROVIDER_CODE);

        /** @var StdOAuth2Token $token */
        $token = $event->getData('token');
        $request = $event->getData('request');
        $response = $event->getData('response');
        $provider = $event->getData('provider');

        $googleData = $this->oauthHelper->createGoogleData($token);
        if (!$googleData) {
            throw new \Exception('Google ID Token not found');
        }

        $googleClient = $this->oauthHelper->createGoogleClient();
        $googleClient->setAccessToken($googleData);

        $oauth = new Oauth2($googleClient);
        $userData = $oauth->userinfo->get();
        //$userData = $googleClient->verifyIdToken();
        if (!$userData) {
            throw new TokenResponseException('Unable to Verify ID Token.');
        }

        $email = $userData['email'] ?? '';
        $firstName = $userData['given_name'] ?? '';
        $lastName = $userData['family_name'] ?? '';

        $quoteId = $this->customerHelper->getCheckoutSession()->getQuoteId();
        $customer = $this->customerHelper->findOrCreateByEmail($email, $firstName, $lastName);
        $this->customerHelper->getCustomerSession()->setCustomerAsLoggedIn($customer);
        $this->customerHelper->getCheckoutSession()->setQuoteId($quoteId);
    }
}
