<?php

namespace Namicargo\Shipping\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Namicargo\Shipping\Logger\NamicargoTransferCustomAttributeLogger;

if (!defined('BP')) {
    define('BP', dirname(__DIR__));
}

readonly class TransferCustomAttributeObserver implements ObserverInterface
{
    public function __construct(
        private NamicargoTransferCustomAttributeLogger $transferAttribute
    ) {
    }

    /**
     * API Connection.
     *
     * @param Observer $observer
     * @return string|null
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getData('order');
        $quote = $observer->getData('quote');

        $shippingAddress = $quote->getShippingAddress();
        $nameShippingId = $shippingAddress->getData('nami_shipping_id');
        $this->transferAttribute->info('nami_shipping_id :: ' . $nameShippingId);
        $cargoId = $shippingAddress->getData('cargo_id');
        $this->transferAttribute->info('cargo_id :: ' . $cargoId);

        if ($nameShippingId) {
            // Do something with shipping address custom attribute
            $order->setData('nami_shipping_id', $nameShippingId);
            $order->setData('cargo_id', $cargoId);
        }

        return 'Custom Attribute Transfer executed successfully.';
    }
}
