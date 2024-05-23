<?php

namespace JesseHanson\OAuthCustomerLogin\OAuth2\Service;

use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth2\Service\AbstractService;
use OAuth\OAuth2\Token\StdOAuth2Token;

class Google
    extends AbstractService
{

    const PROVIDER_CODE = 'google';

    // these are required by the underlying framework in vendor/lusitanian/
    const SCOPE_OPENID  = 'openid';
    const SCOPE_PROFILE = 'profile';
    const SCOPE_EMAIL   = 'email';
    const SCOPE_ADDRESS = 'address';
    const SCOPE_PHONE   = 'phone';

    /**
     * @return Uri
     */
    public function getAccessTokenEndpoint()
    {
        return new Uri('https://www.googleapis.com/oauth2/v4/token');
    }

    /**
     * @return Uri
     */
    public function getAuthorizationEndpoint()
    {
        return new Uri('https://accounts.google.com/o/oauth2/v2/auth');
    }

    /**
     * @param string $responseBody
     * @return \OAuth\Common\Token\TokenInterface|StdOAuth2Token
     * @throws TokenResponseException
     */
    public function parseAccessTokenResponse($responseBody)
    {
        $data = json_decode($responseBody, true);

        if (null === $data || !is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        } elseif (isset($data['error_description'])) {
            throw new TokenResponseException('Error in retrieving token: "' . $data['error_description'] . '"');
        } elseif (isset($data['error'])) {
            throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
        }

        $token = new StdOAuth2Token();
        $token->setAccessToken($data['access_token']);
        $token->setLifeTime($data['expires_in']);

        if (isset($data['refresh_token'])) {
            $token->setRefreshToken($data['refresh_token']);
            unset($data['refresh_token']);
        }

        unset($data['access_token']);
        unset($data['expires_in']);

        $token->setExtraParams($data);

        return $token;
    }
}
