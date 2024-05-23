<?php

namespace JesseHanson\OAuthCustomerLogin\Controller\Test;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use OAuth\OAuth2\Service\Exception\InvalidAuthorizationStateException;
use JesseHanson\OAuthCustomerLogin\Helper\OAuth2;

class Finish
    extends Action
{

    protected OAuth2 $oauthHelper;

    protected PageFactory $pageFactory;

    public function __construct(
        Context $context,
        OAuth2 $oauthHelper,
        PageFactory $pageFactory
    ) {
        parent::__construct($context);
        $this->oauthHelper = $oauthHelper;
        $this->pageFactory = $pageFactory;
    }

    public function execute()
    {
        if (!$this->oauthHelper->isTestPageEnabled()) {
            $response = $this->resultRedirectFactory->create();
            $url = $this->_redirect->getRefererUrl();
            if (is_int(strpos($url, 'customer/account/log'))) {
                $url = $this->_redirect('*/*/*');
            }
            $response->setUrl($url);
            return $response;
        }

        $providerCode = $this->_request->getParam(OAuth2::PROVIDER);
        $authCode = $this->_request->getParam(OAuth2::CODE);
        $this->oauthHelper->setCurrentProviderCode($providerCode);

        try {

            /** @var \OAuth\OAuth2\Service\AbstractService $provider */
            $provider = $this->oauthHelper->getProvider($providerCode);

            if ($this->oauthHelper->isPkceEnabled($providerCode)) {

                $pkceData = $this->oauthHelper->getStoredPkceData($providerCode);
                $codeVerifier = $pkceData->getCodeVerifier();
                $token = $provider->requestAccessTokenAddtl($authCode, [
                    OAuth2::CODE_VERIFIER => $codeVerifier
                ]);
            } else {
                $token = $provider->requestAccessToken($authCode);
            }

            return $this->_redirect('*/*/userinfo', ['provider' => $providerCode]);
        } catch(InvalidAuthorizationStateException $e) {
            $this->messageManager->addErrorMessage(__('Invalid Authorization State'));
            return $this->_redirect('*/*/welcome');
        } catch(Exception $e) {
            $this->messageManager->addExceptionMessage($e);
            return $this->_redirect('*/*/welcome');
        }
    }
}
