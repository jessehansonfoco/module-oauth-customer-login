<?php

namespace JesseHanson\OAuthCustomerLogin\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use OAuth\ServiceFactory;
use OAuth\Common\Http\Uri\Uri;
use JesseHanson\OAuthCustomerLogin\OAuth2\Service\Keycloak;

/**
 * Class KeycloakProviderObserver
 * @package JesseHanson\OAuthCustomerLogin\Observer
 */
class KeycloakProviderObserver
    implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        /** @var \JesseHanson\OAuthCustomerLogin\Helper\OAuth2 $helper */
        $helper = $observer->getEvent()->getData('helper');
        if (!$helper->isProviderEnabled(Keycloak::PROVIDER_CODE)) {
            return;
        }

        /** @var ServiceFactory $providerFactory */
        $providerFactory = $observer->getEvent()->getData('factory');
        $providerFactory->registerService(Keycloak::PROVIDER_CODE, Keycloak::class);

        $scopes = ['openid']; // the only required scope, all others are optional
        // https://openid.net/specs/openid-connect-basic-1_0-22.html
        // openid, profile, email, address, phone, offline_access

        /** @var Keycloak $provider */
        $provider = $providerFactory->createService(
            Keycloak::PROVIDER_CODE,
            $helper->createProviderCredentials(Keycloak::PROVIDER_CODE),
            $helper->createProviderStorage(Keycloak::PROVIDER_CODE),
            $scopes,
            new Uri($helper->getApiBaseUrl(Keycloak::PROVIDER_CODE)),
            2
        );

        $provider->setRealm($helper->getRealm(Keycloak::PROVIDER_CODE));

        /** @var \JesseHanson\OAuthCustomerLogin\Helper\BasicCollection $basicCollection */
        $basicCollection = $observer->getEvent()->getData('collection');
        $basicCollection->addItem(Keycloak::PROVIDER_CODE, $provider);
    }
}
