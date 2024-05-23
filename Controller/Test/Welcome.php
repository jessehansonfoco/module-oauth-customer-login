<?php

namespace JesseHanson\OAuthCustomerLogin\Controller\Test;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use JesseHanson\OAuthCustomerLogin\Helper\OAuth2;

class Welcome implements HttpGetActionInterface
{

    protected RequestInterface $request;

    protected PageFactory $pageFactory;
    protected RedirectFactory $redirectFactory;

    protected OAuth2 $oauthHelper;

    public function __construct(
        RequestInterface $request,
        PageFactory $pageFactory,
        RedirectFactory $redirectFactory,
        OAuth2 $oauthHelper
    ) {
        $this->request = $request;
        $this->pageFactory = $pageFactory;
        $this->redirectFactory = $redirectFactory;
        $this->oauthHelper = $oauthHelper;
    }

    public function execute()
    {
        if (!$this->oauthHelper->isTestPageEnabled()) {
            $response = $this->redirectFactory->create();
            $response->setUrl('/');
            return $response;
        }

        return $this->pageFactory->create();
    }
}
