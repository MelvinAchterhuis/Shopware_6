<?php declare(strict_types=1);

namespace Buckaroo\Shopware6\Handlers;

use Buckaroo\Shopware6\PaymentMethods\WeChatPay;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;

class WeChatPayPaymentHandler extends AsyncPaymentHandler
{
    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @param string|null $buckarooKey
     * @param string $type
     * @param array $gatewayInfo
     * @return RedirectResponse
     * @throws \Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext,
        string $buckarooKey = null,
        string $type = null,
        string $version = null,
        array $gatewayInfo = []
    ): RedirectResponse {
        $paymentMethod = new WeChatPay();

        $additional = [
            [
                'Name' => 'Locale',
                '_' => $this->getLocaleCode(),
            ]
        ];

        $gatewayInfo   = [
            'additional' => [$additional],
        ];

        return parent::pay(
            $transaction,
            $dataBag,
            $salesChannelContext,
            $paymentMethod->getBuckarooKey(),
            $paymentMethod->getType(),
            $paymentMethod->getVersion(),
            $gatewayInfo
        );
    }

    private function getLocaleCode($country = false)
    {
        if ($country == 'CN') {
            $localeCode = 'zh-CN';
        } else if ($country == 'TW') {
            $localeCode = 'zh-TW';
        } else {
            $localeCode = 'en-US';
        }
        return $localeCode;
    }
}