<?php declare (strict_types = 1);

namespace Buckaroo\Shopware6\Subscribers;

use Buckaroo\Shopware6\Helpers\Helper;
use Buckaroo\Shopware6\Storefront\Struct\BuckarooStruct;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Buckaroo\Shopware6\Helpers\CheckoutHelper;

class CheckoutConfirmTemplateSubscriber implements EventSubscriberInterface
{
    /** @var Helper */
    private $helper;
    private $customerRepository;
    /** @var CheckoutHelper $checkoutHelper */
    public $checkoutHelper;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var array
     */
    protected $issuers = [
        [
            'name' => 'ABN AMRO',
            'code' => 'ABNANL2A',
        ],
        [
            'name' => 'ASN Bank',
            'code' => 'ASNBNL21',
        ],
        [
            'name' => 'Bunq Bank',
            'code' => 'BUNQNL2A',
        ],
        [
            'name' => 'ING',
            'code' => 'INGBNL2A',
        ],
        [
            'name' => 'Knab Bank',
            'code' => 'KNABNL2H',
        ],
        [
            'name' => 'Moneyou',
            'code' => 'MOYONL21',
        ],
        [
            'name' => 'Rabobank',
            'code' => 'RABONL2U',
        ],
        [
            'name' => 'RegioBank',
            'code' => 'RBRBNL21',
        ],
        [
            'name' => 'SNS Bank',
            'code' => 'SNSBNL2A',
        ],
        [
            'name' => 'Triodos Bank',
            'code' => 'TRIONL2U',
        ],
        [
            'name' => 'Van Lanschot',
            'code' => 'FVLBNL22',
        ],
        [
            'name' => 'Handelsbanken',
            'code' => 'HANDNL2A',
        ],
    ];

    protected $availableCreditcards = [
        'mastercard'     => 'MasterCard',
        'visa'           => 'Visa',
        'amex'           => 'American Express',
        'vpay'           => 'VPay',
        'maestro'        => 'Maestro',
        'visaelectron'   => 'Visa Electron',
        'cartebleuevisa' => 'Carte Bleue',
        'cartebancaire'  => 'Carte Bancaire',
        'dankort'        => 'Dankort',
        'nexi'           => 'Nexi',
    ];

    /**
     * CheckoutConfirmTemplateSubscriber constructor.
     * @param Helper $helper
     * @param EntityRepositoryInterface $customerRepository
     * @param SalesChannelRepositoryInterface $paymentMethodRepository
     */
    public function __construct(
        Helper $helper,
        EntityRepositoryInterface $customerRepository,
        SalesChannelRepositoryInterface $paymentMethodRepository,
        CheckoutHelper $checkoutHelper
    ) {
        $this->helper                  = $helper;
        $this->customerRepository      = $customerRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->checkoutHelper = $checkoutHelper;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'addBuckarooExtension',
        ];
    }

    /**
     * @param CheckoutConfirmPageLoadedEvent $event
     * @throws \Exception
     */
    public function addBuckarooExtension(CheckoutConfirmPageLoadedEvent $event): void
    {
        $context = $event->getContext();
        $request  = $this->helper->getGlobals();
        $customer = $event->getSalesChannelContext()->getCustomer();
        $customFields = $event->getSalesChannelContext()->getPaymentMethod()->getCustomFields();
        $buckarooKey = isset($customFields['buckaroo_key']) ? $customFields['buckaroo_key'] : null;
        $currency = $this->checkoutHelper->getOrderCurrency($context);

        if ($lastCreditcard = $request->get('creditcard')) {
            $this->customerRepository->upsert(
                [[
                    'id'           => $customer->getId(),
                    'customFields' => [
                        'last_used_creditcard' => $lastCreditcard,
                    ],
                ]],
                $event->getContext()
            );
            $customer = $this->getCustomer($customer->getId(), $event->getContext());
        }

        $struct             = new BuckarooStruct();
        $issuers            = $this->issuers;
        $lastUsedCreditcard = $customer->getCustomFields()['last_used_creditcard'];
        
        $allowedcreditcard = $this->helper->getSettingsValue('allowedcreditcard');
        if (!empty($allowedcreditcard)){
            foreach ($allowedcreditcard as $value) {
                $creditcard[] = [
                    'name' => $this->checkoutHelper->getBuckarooFeeLabel('allowedcreditcard',$this->availableCreditcards[$value], $context),
                    'code' => $value,
                ];
            }
        }

        $allowedcreditcards = $this->helper->getSettingsValue('allowedcreditcards');
        if (!empty($allowedcreditcards)){
            foreach ($allowedcreditcards as $value) {
                $creditcards[] = [
                    'name' => $this->checkoutHelper->getBuckarooFeeLabel('allowedcreditcards',$this->availableCreditcards[$value], $context),
                    'code' => $value,
                ];
            }
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');
        $paymentLabels = [];
        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->paymentMethodRepository->search($criteria, $event->getSalesChannelContext())->getEntities();
        foreach ($paymentMethods as $key => $paymentMethod) {
            $method = $paymentMethod->getTranslated();
            if (!empty($method['customFields']['buckaroo_key'])) {
                $buckaroo_key = $method['customFields']['buckaroo_key'];
                $paymentLabels[$buckaroo_key] = $this->checkoutHelper->getBuckarooFeeLabel($buckaroo_key, $this->helper->getSettingsValue($buckaroo_key . 'Label'), $context);
            }
        }

        $struct->assign([
            'currency'                 => $currency->getIsoCode(),
            'issuers'                  => $issuers,
            'payment_method_name_card' => $this->getPaymentMethodName($creditcard, $lastUsedCreditcard, ''),
            'creditcard'               => $creditcard,
            'creditcards'              => $creditcards,
            'last_used_creditcard'     => $lastUsedCreditcard,
            'payment_labels'           => $paymentLabels,
            'media_path'               => '/bundles/buckaroopayment/storefront/buckaroo/logo/',
            'payment_media'            => $lastUsedCreditcard . '.png',
            'buckarooFee'              => $this->checkoutHelper->getBuckarooFee($buckarooKey . 'Fee'),
        ]);

        $event->getPage()->addExtension(
            BuckarooStruct::EXTENSION_NAME,
            $struct
        );
    }

    /**
     * @param string $customerId
     * @param Context $context
     * @return CustomerEntity
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getCustomer(string $customerId, Context $context): CustomerEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('id', $customerId));
        return $this->customerRepository->search($criteria, $context)->first();
    }

    /**
     * @param array $issuers
     * @param string|null $lastUsedIssuer
     * @param name
     * @return string
     */
    private function getPaymentMethodName(array $issuers,  ? string $lastUsedIssuer, $name = '') : string
    {
        foreach ($issuers as $issuer) {
            if ($issuer['code'] === $lastUsedIssuer) {
                $issuerName = $issuer['name'];
                return $name == '' ? $issuerName : $name . ' (' . $issuerName . ')';
            }
        }
        return $name;
    }
}
