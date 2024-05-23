<?php

namespace JesseHanson\OAuthCustomerLogin\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use JesseHanson\OAuthCustomerLogin\OAuth2\Service\Google;
use OAuth\Common\Http\Uri\Uri;
use OAuth\ServiceFactory;

class GoogleProviderObserver implements ObserverInterface
{

    public function execute(Observer $observer)
    {
        /** @var \JesseHanson\OAuthCustomerLogin\Helper\OAuth2 $helper */
        $helper = $observer->getEvent()->getData('helper');
        if (!$helper->isProviderEnabled(Google::PROVIDER_CODE)) {
            return;
        }

        /** @var ServiceFactory $providerFactory */
        $providerFactory = $observer->getEvent()->getData('factory');
        $providerFactory->registerService(Google::PROVIDER_CODE, Google::class);

        $scopes = ['openid', 'email', 'profile']; // the only required scope, all others are optional
        // https://openid.net/specs/openid-connect-basic-1_0-22.html
        // openid, profile, email, address, phone, offline_access

        $provider = $providerFactory->createService(
            Google::PROVIDER_CODE,
            $helper->createProviderCredentials(Google::PROVIDER_CODE),
            $helper->createProviderStorage(Google::PROVIDER_CODE),
            $scopes,
            null,
            2
        );

        /** @var \JesseHanson\OAuthCustomerLogin\Helper\BasicCollection $basicCollection */
        $basicCollection = $observer->getEvent()->getData('collection');
        $basicCollection->addItem(Google::PROVIDER_CODE, $provider);
    }
}
