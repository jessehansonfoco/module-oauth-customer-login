<?php

namespace JesseHanson\OAuthCustomerLogin\OAuth2;

use Magento\Framework\DataObject;

class PkceData extends DataObject
{
    const CODE_VERIFIER = 'code_verifier';
    const CODE_CHALLENGE = 'code_challenge';
    const CODE_CHALLENGE_METHOD = 'code_challenge_method';

    /**
     * @param string $codeVerifier
     * @return $this
     */
    public function setCodeVerifier(string $codeVerifier)
    {
        $this->setData(self::CODE_VERIFIER, $codeVerifier);
        return $this;
    }

    /**
     * @return string
     */
    public function getCodeVerifier(): string
    {
        return (string) $this->_getData(self::CODE_VERIFIER);
    }

    /**
     * @param string $codeChallenge
     * @return $this
     */
    public function setCodeChallenge(string $codeChallenge)
    {
        $this->setData(self::CODE_CHALLENGE, $codeChallenge);
        return $this;
    }

    /**
     * @return string
     */
    public function getCodeChallenge(): string
    {
        return (string) $this->_getData(self::CODE_CHALLENGE);
    }

    /**
     * @param string $codeChallengeMethod
     * @return $this
     */
    public function setCodeChallengeMethod(string $codeChallengeMethod)
    {
        $this->setData(self::CODE_CHALLENGE_METHOD, $codeChallengeMethod);
        return $this;
    }

    /**
     * @return string
     */
    public function getCodeChallengeMethod(): string
    {
        return (string) $this->_getData(self::CODE_CHALLENGE_METHOD);
    }
}
