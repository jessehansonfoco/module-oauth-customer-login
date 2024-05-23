<?php

namespace JesseHanson\OAuthCustomerLogin\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Customer\Model\Session as CustomerSession;
use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\ServiceFactory;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Http\Client\CurlClient;
use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Storage\Session as OAuthSession;
use OAuth\Common\Service\ServiceInterface;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\Common\Token\TokenInterface;
use Google\Client as GoogleClient;
use JesseHanson\OAuthCustomerLogin\OAuth2\PkceData;

/**
 * Class OAuth2
 * @package JesseHanson\OAuthCustomerLogin\Helper
 */
class OAuth2
    extends AbstractHelper
{
    // communication constants
    const GRANT_TYPE = 'grant_type';
    const CODE = 'code';
    const STATE = 'state';
    const CLIENT_ID = 'client_id';
    const CLIENT_SECRET = 'client_secret';
    const REDIRECT_URI = 'redirect_uri';
    const CODE_VERIFIER = 'code_verifier';
    const CODE_CHALLENGE = 'code_challenge'; // for PKCE, Authorization URL
    const CODE_CHALLENGE_METHOD = 'code_challenge_method'; // for PKCE, Authorization URL
    const S256 = 'S256';

    // system configuration constants
    const SECTION_ID = 'oauthcustomerlogin';
    const FORCE_REDIRECT = 'force_redirect';
    const REALM = 'realm';
    const IS_ENABLED = 'is_enabled';
    const IS_PKCE_ENABLED = 'is_pkce_enabled';

    const BASE_URL = 'base_url';

    // module constants
    const PROVIDER = 'provider';
    const EVENT_COLLECT_PROVIDERS = 'collect_oauth_providers';
    const EVENT_LOGIN_SUCCESS = 'oauth_login_success';
    const ROUTE_KEY = 'oauthcustomer';
    const CURRENT_PROVIDER = 'current_provider';

    /**
     * @var string
     */
    protected $providerCode = 'keycloak';

    /**
     * @var ServiceFactory
     */
    protected $providerFactory;

    /**
     * @var array|ServiceInterface[]
     */
    protected $providers = [];

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var RedirectFactory
     */
    protected $redirectFactory;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        RedirectFactory $redirectFactory
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
    }

    /**
     * @return string
     */
    public function getProviderCode(): string
    {
        return $this->providerCode;
    }

    /**
     * @param string $providerCode
     * @return $this
     */
    public function setProviderCode(string $providerCode)
    {
        $this->providerCode = $providerCode;
        return $this;
    }

    /**
     * @param string $providerCode
     * @return ServiceInterface
     * @throws \InvalidArgumentException
     */
    public function getProvider(string $providerCode): ServiceInterface
    {
        if (!is_object($this->providerFactory)) {
            $this->collectProviders();
        }

        if (!isset($this->providers[$providerCode])) {
            throw new \InvalidArgumentException("OAuth provider has not been defined: {$providerCode}");
        }

        return $this->providers[$providerCode];
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function createStartUrl(string $providerCode): string
    {
        return $this->_urlBuilder->getUrl('oauthcustomer/login/start', [self::PROVIDER => $providerCode]);
    }

    /**
     * @return ServiceFactory
     */
    public function createProviderFactory(): ServiceFactory
    {
        $providerFactory = new ServiceFactory();
        $httpClient = new CurlClient();
        $providerFactory->setHttpClient($httpClient);
        return $providerFactory;
    }

    /**
     * @param string $providerCode
     * @return OAuthSession
     */
    public function createProviderStorage(string $providerCode): OAuthSession
    {
        return new OAuthSession(
            true,
            $this->getTokenSessionKey($providerCode),
            $this->getStateSessionKey($providerCode)
        );
    }

    /**
     * @param string $providerCode
     * @return Credentials
     */
    public function createProviderCredentials(string $providerCode): Credentials
    {
        return new Credentials(
            $this->getClientId($providerCode),
            $this->getClientSecret($providerCode),
            $this->getRedirectUri($providerCode)
        );
    }

    /**
     * @return \OAuth\Common\Http\Uri\UriInterface
     */
    public function getAuthorizationUrl(string $providerCode)
    {
        $provider = $this->getProvider($providerCode);
        $addtlParams = [];
        if ($this->isPkceEnabled($providerCode)) {

            /*

            The code verifier is a cryptographically random string
            using the characters A-Z, a-z, 0-9, the punctuation characters -._~ (hyphen, period, underscore, and tilde), \
            between 43 and 128 characters long.

            //*/

            if ($this->getStoredPkceData($providerCode)) {
                $pkceData = $this->getStoredPkceData($providerCode);
                $codeVerifierEncoded = $pkceData->getCodeChallenge();

            } else {

                $codeVerifier = md5('time-' . microtime(true));
                $codeVerifier = $codeVerifier . $codeVerifier;
                $codeVerifierHash = hash('sha256', $codeVerifier);
                $codeVerifierEncoded = $this->base64UrlEncode(pack('H*', $codeVerifierHash));

                $pkceData = new PkceData();
                $pkceData->setCodeVerifier($codeVerifier);
                $pkceData->setCodeChallenge($codeVerifierEncoded);
                $pkceData->setCodeChallengeMethod(self::S256);
                $this->storePkceData($providerCode, $pkceData);
            }

            $addtlParams = [
                self::CODE_CHALLENGE => $codeVerifierEncoded,
                self::CODE_CHALLENGE_METHOD => self::S256
            ];
        }

        return $provider->getAuthorizationUri($addtlParams);
    }

    /**
     * @param $data
     * @return string
     */
    public function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param string $providerCode
     * @param PkceData $pkceData
     * @return $this
     */
    public function storePkceData(string $providerCode, PkceData $pkceData)
    {
        $_SESSION[$this->getPkceSessionKey($providerCode)] = $pkceData;
        return $this;
    }

    /**
     * @param string $providerCode
     * @return PkceData|null
     */
    public function getStoredPkceData(string $providerCode): ?PkceData
    {
        if (!isset($_SESSION[$this->getPkceSessionKey($providerCode)])) {
            return null;
        }
        return $_SESSION[$this->getPkceSessionKey($providerCode)];
    }

    /**
     * @param string $providerCode
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function createRedirectResponse(string $providerCode)
    {
        $redirectUrl = $this->getAuthorizationUrl($providerCode);
        $redirectResponse = $this->redirectFactory->create();
        $redirectResponse->setUrl($redirectUrl);
        return $redirectResponse;
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getTokenSessionKey(string $providerCode): string
    {
        return $providerCode . '-oauth-token';
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getStateSessionKey(string $providerCode): string
    {
        return $providerCode . '-oauth-state';
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getPkceSessionKey(string $providerCode): string
    {
        return $providerCode . '-oauth-pkce';
    }

    /**
     * @param string $providerCode
     * @return $this
     */
    public function setCurrentProviderCode(string $providerCode)
    {
        $_SESSION[self::CURRENT_PROVIDER] = $providerCode;
        $this->setProviderCode($providerCode);
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrentProviderCode(): string
    {
        if ($providerCode = $this->_request->getParam(self::PROVIDER)) {
            $providerCodes = $this->getProviderCodes();
            if (in_array($providerCode, $providerCodes)) {
                return $providerCode;
            }
        }
        return $_SESSION[self::CURRENT_PROVIDER] ?? $this->providerCode;
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getProviderServiceCode(string $providerCode): string
    {
        $provider = $this->getProvider($providerCode);
        return $provider->service();
    }

    /**
     * @return $this
     */
    protected function collectProviders()
    {
        if (!is_object($this->providerFactory)) {
            $this->providerFactory = $this->createProviderFactory();
        }

        $basicCollection = new BasicCollection();

        $event = [
            'factory' => $this->providerFactory,
            'helper' => $this,
            'collection' => $basicCollection,
        ];

        $this->_eventManager->dispatch(self::EVENT_COLLECT_PROVIDERS, $event);
        $this->providers = $basicCollection->getItems();
        return $this;
    }

    /**
     * @return array
     */
    public function getProviderCodes(): array
    {
        if (!$this->providers) {
            $this->collectProviders();
        }
        return array_keys($this->providers);
    }

    /**
     * @param string $provider
     * @param string $key
     * @return string
     */
    public function getProviderValue(string $provider, string $key)
    {
        return $this->scopeConfig->getValue(implode('/', [self::SECTION_ID, $provider, $key]));
    }

    /**
     * @param TokenInterface $token
     * @return bool
     */
    public function isTokenExpired(TokenInterface $token): bool
    {
        return $token->getEndOfLife() !== TokenInterface::EOL_NEVER_EXPIRES
            && $token->getEndOfLife() !== TokenInterface::EOL_UNKNOWN
            && time() > $token->getEndOfLife();
    }

    /**
     * @param string $providerCode
     * @return bool
     */
    public function isProviderEnabled(string $providerCode): bool
    {
        return (bool) $this->getProviderValue($providerCode, self::IS_ENABLED);
    }

    /**
     * @param string $providerCode
     * @return bool
     */
    public function getForceRedirect(string $providerCode): bool
    {
        return (bool) $this->getProviderValue($providerCode, self::FORCE_REDIRECT);
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getClientId(string $providerCode): string
    {
        return (string) $this->getProviderValue($providerCode, self::CLIENT_ID);
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getClientSecret(string $providerCode): string
    {
        return (string) $this->getProviderValue($providerCode, self::CLIENT_SECRET);
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getRealm(string $providerCode): string
    {
        return (string) $this->getProviderValue($providerCode, self::REALM);
    }

    /**
     * @param string $providerCode
     * @return bool
     */
    public function isPkceEnabled(string $providerCode)
    {
        return (bool) $this->getProviderValue($providerCode, self::IS_PKCE_ENABLED);
    }

    /**
     * @return bool
     */
    public function isTestPageEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(implode('/', [self::SECTION_ID, 'general', 'is_testpage_enabled']));
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getRedirectUri(string $providerCode): string
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->_getRequest();
        $url = $request->getRequestUri();
        $testPage = is_int(strpos($url, self::ROUTE_KEY . '/test'));

        $path = $testPage
            ? self::ROUTE_KEY . '/test/finish'
            : self::ROUTE_KEY . '/login/finish';

        switch($request->getParam('redirect', '')) {
            case 'account':
            case 'checkout':

                $url = $this->_getUrl($path, [
                    self::PROVIDER => $providerCode,
                    'redirect' => $request->getParam('redirect', ''),
                ]);

                break;
            default:

                $url = $this->_getUrl($path, [
                    self::PROVIDER => $providerCode
                ]);

                break;
        }

        return $url;
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getApiBaseUrl(string $providerCode): string
    {
        return (string) $this->getProviderValue($providerCode, self::BASE_URL);
    }

    /**
     * @param string $providerCode
     * @return bool|OAuthSession
     */
    public function getCurrentProviderStorage(string $providerCode)
    {
        return $this->createProviderStorage($providerCode);
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getCurrentAccessToken(string $providerCode): string
    {
        $provider = $this->getProvider($providerCode);
        $storage = $this->getCurrentProviderStorage($providerCode);

        try {
            $token = $storage->retrieveAccessToken($provider->service());
        } catch(TokenNotFoundException $e) {
            return '';
        }

        return $token->getAccessToken();
    }

    /**
     * @param string $providerCode
     * @return string
     */
    public function getCurrentRefreshToken(string $providerCode): string
    {
        $provider = $this->getProvider($providerCode);
        $storage = $this->getCurrentProviderStorage($providerCode);

        try {
            $token = $storage->retrieveAccessToken($provider->service());
        } catch(TokenNotFoundException $e) {
            return '';
        }

        return $token->getRefreshToken();
    }

    /**
     * @param string $accessToken
     * @return \stdClass
     */
    public function decodeAccessToken(string $accessToken)
    {
        $parts = explode('.', $accessToken);

        // could also validate using validation steps outlined in RFC:
        //  https://tools.ietf.org/html/rfc7519#page-14

        // in the future, use a better php library listed here:
        //  https://jwt.io/

        return json_decode(base64_decode($parts[1]));
    }

    /**
     * This method allows you to specify Credentials and API Endpoint
     *  and create an instance of your provider which is different from 'default' behavior
     *
     * @param string $providerCode
     * @param Credentials $credentials
     * @param array $scopes
     * @param string $apiBaseUrl
     * @param int $oauthVersion
     * @return ServiceInterface
     */
    public function createProvider(
        string $providerCode,
        Credentials $credentials,
        array $scopes = ['openid'],
        string $apiBaseUrl = '',
        $oauthVersion = 2
    ) {

        $baseUrl = null;
        if ($apiBaseUrl) {
            $baseUrl = new Uri($apiBaseUrl);
        } elseif ($this->getApiBaseUrl($providerCode)) {
            $baseUrl = new Uri($this->getApiBaseUrl($providerCode));
        }

        if (!is_object($this->providerFactory)) {
            $this->collectProviders();
        }

        return $this->providerFactory->createService(
            $providerCode,
            $credentials,
            $this->createProviderStorage($providerCode),
            $scopes,
            $baseUrl,
            $oauthVersion
        );
    }

    /**
     * @param StdOAuth2Token $token
     * @return array
     */
    public function createGoogleData(StdOAuth2Token $token): array
    {
        // get access token
        $accessToken = $token->getAccessToken();
        $extraParams = $token->getExtraParams();

        // get email, first/last name from Google ID Verify call
        $idToken = $extraParams['id_token'] ?? '';
        $scope = $extraParams['scope'] ?? '';
        $tokenType = $extraParams['token_type'] ?? '';

        if (!$idToken) {
            return [];
        }

        // here, we are re-creating the array we received in Google::parseAccessTokenResponse()
        return [
            'access_token' => $accessToken,
            'expires_in' => $token->getEndOfLife() - time(),
            'scope' => $scope,
            'token_type' => $tokenType,
            'id_token' => $idToken,
        ];
    }

    /**
     * @return GoogleClient
     */
    public function createGoogleClient(): GoogleClient
    {
        $googleClient = new GoogleClient([
            'client_id' => $this->getClientId('google'),
            'client_secret' => $this->getClientSecret('google'),
            'approval_prompt' => 'force'
        ]);
        $googleClient->addScope('email');
        $googleClient->addScope('profile');
        return $googleClient;
    }
}
