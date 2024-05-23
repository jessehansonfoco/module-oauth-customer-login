<?php

namespace JesseHanson\OAuthCustomerLogin\Helper;

use Magento\Checkout\Model\Session as CartSession;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Customer
 * @package JesseHanson\OAuthCustomerLogin\Helper
 */
class Customer
    extends AbstractHelper
{
    /**
     * @var AccountManagementInterface
     */
    protected $accountManager;

    /**
     * @var CartSession
     */
    protected $cartSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CustomerInterfaceFactory
     */
    protected $apiCustomerFactory;

    public function __construct(
        Context $context,
        AccountManagementInterface $accountManager,
        CartSession $cartSession,
        CustomerSession $customerSession,
        CustomerRepository $customerRepository,
        CustomerFactory $customerFactory,
        StoreManagerInterface $storeManager,
        CustomerInterfaceFactory $apiCustomerFactory
    ) {
        parent::__construct($context);
        $this->accountManager = $accountManager;
        $this->cartSession = $cartSession;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->storeManager = $storeManager;
        $this->apiCustomerFactory = $apiCustomerFactory;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getWebsiteId()
    {
        return $this->storeManager->getWebsite()->getId();
    }

    /**
     * @return CustomerSession
     */
    public function getCustomerSession()
    {
        return $this->customerSession;
    }

    /**
     * @return CartSession
     */
    public function getCheckoutSession()
    {
        return $this->cartSession;
    }

    /**
     * @param string $email
     * @return \Magento\Customer\Api\Data\CustomerInterface|\Magento\Customer\Model\Customer
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function findByEmail(string $email)
    {
        return $this->customerFactory->create()
            ->setWebsiteId($this->getWebsiteId())
            ->loadByEmail($email);
    }

    /**
     * @param string $email
     * @param string $firstname
     * @param string $lastname
     * @return \Magento\Customer\Api\Data\CustomerInterface|\Magento\Customer\Model\Customer
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function createByEmail(string $email, string $firstname='', string $lastname='')
    {
        $customer = $this->apiCustomerFactory->create();
        $customer->setEmail($email)
            ->setWebsiteId($this->getWebsiteId())
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setStoreId($this->getStoreId());

        $this->accountManager->createAccount($customer);

        return $this->findByEmail($customer->getEmail());
    }

    /**
     * @param string $email
     * @param string $firstname
     * @param string $lastname
     * @return \Magento\Customer\Api\Data\CustomerInterface|\Magento\Customer\Model\Customer
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function findOrCreateByEmail(string $email, string $firstname='', string $lastname='')
    {
        $customer = $this->findByEmail($email);
        if ($customer->getId()) {
            return $customer;
        }

        return $this->createByEmail($email, $firstname, $lastname);
    }
}
