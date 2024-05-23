<?php

namespace JesseHanson\OAuthCustomerLogin\Controller\Login;

use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session as CustomerSession;
use OAuth\OAuth2\Service\Exception\InvalidAuthorizationStateException;
use OAuth\Common\Http\Exception\TokenResponseException;
use JesseHanson\OAuthCustomerLogin\Helper\OAuth2;

/**
 * Class Finish
 * @package JesseHanson\OAuthCustomerLogin\Controller\Login
 */
class Finish
    extends Action
{
    /**
     * @var OAuth2
     */
    protected $oauthHelper;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        Context $context,
        OAuth2 $oauthHelper,
        CustomerSession $customerSession,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->oauthHelper = $oauthHelper;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
    }

    public function execute()
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->_redirect('customer/account');
        }

        // could check the request for required variables, ensure its not a 'security scan'

        $response = $this->resultRedirectFactory->create();
        $url = $this->_redirect->getRefererUrl();

        switch($this->getRequest()->getParam('redirect', '')) {
            case 'checkout':
                $url = $this->_url->getUrl('checkout/index');
                break;
            case 'account':
                $url = $this->_url->getUrl('customer/account');
                break;
            default:

                if (is_int(strpos($url, 'protocol/openid-connect'))
                    || is_int(strpos($url, 'customer/account/log'))
                ) {
                    $url = $this->_url->getUrl('customer/account');
                }

                break;
        }

        $response->setUrl($url);
        $authCode = $this->_request->getParam(OAuth2::CODE);
        $state = $this->_request->getParam(OAuth2::STATE);
        $providerCode = $this->_request->getParam(OAuth2::PROVIDER);

        try {
            /** @var \OAuth\OAuth2\Service\AbstractService $provider */
            $provider = $this->oauthHelper->getProvider($providerCode);
            $this->oauthHelper->setCurrentProviderCode($providerCode);

            if ($this->oauthHelper->isPkceEnabled($providerCode)) {

                $pkceData = $this->oauthHelper->getStoredPkceData($providerCode);
                $codeVerifier = $pkceData->getCodeVerifier();

                $this->logger->debug("OAuthCustomerLogin: Finish.php: code_verifier={$codeVerifier}");

                $token = $provider->requestAccessTokenAddtl($authCode, [
                    OAuth2::CODE_VERIFIER => $codeVerifier
                ]);
            } else {
                $token = $provider->requestAccessToken($authCode, $state);
            }
        } catch(InvalidArgumentException $e) {

            $this->messageManager->addErrorMessage(
                __('Provider not found with code: ' . $this->oauthHelper->getProviderCode())
            );

            return $response;
        } catch(TokenResponseException $e) {

            $this->messageManager->addErrorMessage(
                __('Token Exception : ' . $e->getMessage())
            );

            return $response;
        } catch(InvalidAuthorizationStateException $e) {

            $this->messageManager->addErrorMessage(
                __('Authorization State Exception : ' . $e->getMessage())
            );

            return $response;
        }

        try {

            $this->_eventManager->dispatch(OAuth2::EVENT_LOGIN_SUCCESS, [
                'token' => $token,
                'request' => $this->getRequest(),
                'response' => $response,
                'provider_code' => $this->oauthHelper->getProviderCode(),
                'provider' => $provider,
            ]);

        } catch(\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->critical("Exception: {$e->getMessage()}\nStack Trace:\n{$e->getTraceAsString()}");

            $url = $this->_redirect->getRefererUrl();
            $response->setUrl($url);
        }

        return $response;
    }
}
