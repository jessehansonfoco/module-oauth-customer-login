<?php

namespace JesseHanson\OAuthCustomerLogin\Controller\Test;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use JesseHanson\OAuthCustomerLogin\Helper\OAuth2;

/**
 * Class Userinfo
 * @package JesseHanson\OAuthCustomerLogin\Controller\Test
 */
class Userinfo
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
            return $this->_redirect('/');
        }

        $providerCode = trim($this->getRequest()->getParam(OAuth2::PROVIDER));
        if ($providerCode && in_array($providerCode, $this->oauthHelper->getProviderCodes())) {
            $this->oauthHelper->setCurrentProviderCode($providerCode);
        }

        if (!strlen($providerCode)) {
            $providerCode = $this->oauthHelper->getCurrentProviderCode();
        }

        if (!strlen($providerCode)) {
            $providerCode = $this->oauthHelper->getProviderCode(); // get default, 'keycloak'
            $this->oauthHelper->setCurrentProviderCode($providerCode);
        }

        $provider = $this->oauthHelper->getProvider($providerCode);
        $tokenStorage = $this->oauthHelper->getCurrentProviderStorage($providerCode);

        try {
            $token = $tokenStorage->retrieveAccessToken($provider->service());
        } catch(TokenNotFoundException $e) {
            $this->messageManager->addWarningMessage(__('TokenNotFoundException'));
            return $this->_redirect('*/*/welcome');
        }

        if ($this->oauthHelper->isTokenExpired($token)) {
            $this->messageManager->addWarningMessage(__('Token is expired.'));
            return $this->_redirect('*/*/welcome');
        }

        return $this->pageFactory->create();
    }
}
