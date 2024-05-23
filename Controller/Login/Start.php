<?php

namespace JesseHanson\OAuthCustomerLogin\Controller\Login;

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
        if ($this->customerHelper->getCustomerSession()->isLoggedIn()) {
            return $this->_redirect('customer/account');
        }

        $providerCode = trim($this->getRequest()->getParam(OAuth2::PROVIDER));
        if ($providerCode && in_array($providerCode, $this->oauthHelper->getProviderCodes())) {
            $this->oauthHelper->setCurrentProviderCode($providerCode);
        } else {
            $providerCode = $this->oauthHelper->getProviderCode(); // get default, 'keycloak'
            $this->oauthHelper->setCurrentProviderCode($providerCode);
        }

        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $provider = $this->oauthHelper->getProvider($providerCode);
        } catch(\Exception $e) {
            $this->logger->critical("Exception: {$e->getMessage()}\nStack trace:\n{$e->getTraceAsString()}");
            $this->messageManager->addErrorMessage("Error while loading OAuth2 provider: {$providerCode}");
            $resultRedirect->setUrl('/');
            return $resultRedirect;
        }

        // build redirect URL
        $redirectUrl = $this->oauthHelper->getAuthorizationUrl($providerCode);
        $resultRedirect->setUrl($redirectUrl);
        return $resultRedirect;
    }
}
