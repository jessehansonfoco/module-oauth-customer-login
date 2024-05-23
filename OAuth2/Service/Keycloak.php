<?php

namespace JesseHanson\OAuthCustomerLogin\OAuth2\Service;

use OAuth\OAuth2\Service\AbstractService;
use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;

/**
 * Class Keycloak
 * @package JesseHanson\OAuthCustomerLogin\OAuth2\Service
 */
class Keycloak
    extends AbstractService
    implements OpenIdConnectInterface
{
    const PROVIDER_CODE = 'keycloak';

    // these are required by the underlying framework in vendor/lusitanian/
    const SCOPE_OPENID  = 'openid';
    const SCOPE_PROFILE = 'profile';
    const SCOPE_EMAIL   = 'email';
    const SCOPE_ADDRESS = 'address';
    const SCOPE_PHONE   = 'phone';

    /**
     * @var string
     */
    protected $realm = '';

    /**
     * @param $code
     * @param array $addtl
     * @return \OAuth\Common\Token\TokenInterface|StdOAuth2Token
     * @throws TokenResponseException
     * @throws \OAuth\OAuth2\Service\Exception\InvalidAuthorizationStateException
     */
    public function requestAccessTokenAddtl($code, array $addtl)
    {
        $bodyParams = array(
            'code'          => $code,
            'client_id'     => $this->credentials->getConsumerId(),
            'client_secret' => $this->credentials->getConsumerSecret(),
            'redirect_uri'  => $this->credentials->getCallbackUrl(),
            'grant_type'    => 'authorization_code',
        );

        foreach($addtl as $key => $value) {
            $bodyParams[$key] = $value;
        }

        $responseBody = $this->httpClient->retrieveResponse(
            $this->getAccessTokenEndpoint(),
            $bodyParams,
            $this->getExtraOAuthHeaders()
        );

        $token = $this->parseAccessTokenResponse($responseBody);
        $this->storage->storeAccessToken($this->service(), $token);

        return $token;
    }

    /**
     * @param string $realm
     * @return $this
     */
    public function setRealm(string $realm)
    {
        $this->realm = $realm;
        return $this;
    }

    /**
     * @return string
     */
    public function getRealm(): string
    {
        return (string) $this->realm;
    }

    /**
     * @return Uri
     */
    public function getAuthorizationEndpoint()
    {
        $baseUrl = is_object($this->baseApiUri)
            ? $this->baseApiUri->getAbsoluteUri()
            : $this->baseApiUri;

        return new Uri($baseUrl . 'realms/'.$this->getRealm().'/protocol/openid-connect/auth');
    }

    /**
     * @return Uri
     */
    public function getAccessTokenEndpoint()
    {
        $baseUrl = is_object($this->baseApiUri)
            ? $this->baseApiUri->getAbsoluteUri()
            : $this->baseApiUri;

        return new Uri($baseUrl . 'realms/'.$this->getRealm().'/protocol/openid-connect/token');
    }

    /**
     * @return int
     */
    protected function getAuthorizationMethod()
    {
        return static::AUTHORIZATION_METHOD_HEADER_BEARER;
    }

    /**
     * @return Uri
     */
    public function getResourceEndpoint()
    {
        $baseUrl = is_object($this->baseApiUri)
            ? $this->baseApiUri->getAbsoluteUri()
            : $this->baseApiUri;

        return new Uri($baseUrl . 'realms/'.$this->getRealm().'/protocol/openid-connect/userinfo');
    }

    /**
     * @return Uri
     */
    public function getEndSessionEndpoint()
    {
        $baseUrl = is_object($this->baseApiUri)
            ? $this->baseApiUri->getAbsoluteUri()
            : $this->baseApiUri;

        return new Uri($baseUrl . 'realms/'.$this->getRealm().'/protocol/openid-connect/logout');
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
