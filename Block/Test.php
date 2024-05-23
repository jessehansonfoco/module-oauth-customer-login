<?php

namespace JesseHanson\OAuthCustomerLogin\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use JesseHanson\OAuthCustomerLogin\Helper\OAuth2 as OAuthHelper;

/**
 * Class Test
 * @package JesseHanson\OAuthCustomerLogin\Block
 */
class Test
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
}
