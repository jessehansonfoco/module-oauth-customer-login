<?php

namespace JesseHanson\OAuthCustomerLogin\Controller\Test;

use Psr\Log\LoggerInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use JesseHanson\OAuthCustomerLogin\Helper\OAuth2;
use JesseHanson\OAuthCustomerLogin\Helper\Customer as CustomerHelper;

class Start
    extends Action
{

    protected OAuth2 $oauthHelper;

    protected CustomerHelper $customerHelper;

    protected LoggerInterface $logger;

    public function __construct(
        Context $context,
        OAuth2 $oauthHelper,
        CustomerHelper $customerHelper,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->oauthHelper = $oauthHelper;
        $this->customerHelper = $customerHelper;
        $this->logger = $logger;
    }

    public function execute()
    {
        if (!$this->oauthHelper->isTestPageEnabled()) {
            return $this->_redirect('/');
        }

        if ($this->customerHelper->getCustomerSession()->isLoggedIn()) {
            return $this->_redirect('customer/account');
        }

        // this is where you can call $this->oauthHelper->setProviderCode(string) , to use a different provider
        $providerCode = trim($this->getRequest()->getParam(OAuth2::PROVIDER));
        if ($providerCode && in_array($providerCode, $this->oauthHelper->getProviderCodes())) {
            $this->oauthHelper->setCurrentProviderCode($providerCode);
        }

        if (!strlen($providerCode)) {
            $providerCode = $this->oauthHelper->getProviderCode(); // get default, 'keycloak'
            $this->oauthHelper->setCurrentProviderCode($providerCode);
        }

        try {
            $provider = $this->oauthHelper->getProvider($providerCode);
        } catch(\Exception $e) {
            $this->_forward('noroute');
        }

        // build redirect URL
        $redirectUrl = $this->oauthHelper->getAuthorizationUrl($providerCode);
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($redirectUrl);
        return $resultRedirect;
    }
}
