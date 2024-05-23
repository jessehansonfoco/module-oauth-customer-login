<?php

namespace JesseHanson\OAuthCustomerLogin\OAuth2\Service;

use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth2\Token\StdOAuth2Token;

/**
 * Interface OpenIdConnectInterface
 * @package JesseHanson\OAuthCustomerLogin\OAuth2\Service
 */
interface OpenIdConnectInterface
{
    /**
     * @return Uri
     */
    public function getResourceEndpoint();

    /**
     * @return Uri
     */
    public function getEndSessionEndpoint();

    /**
     * @param $code
     * @param array $addtl
     * @return \OAuth\Common\Token\TokenInterface|StdOAuth2Token
     * @throws TokenResponseException
     * @throws \OAuth\OAuth2\Service\Exception\InvalidAuthorizationStateException
     */
    public function requestAccessTokenAddtl($code, array $addtl);
}
