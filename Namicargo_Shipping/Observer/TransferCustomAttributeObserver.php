<?php

namespace Namicargo\Shipping\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class TransferCustomAttributeObserver implements ObserverInterface
{
    /**
     * @param PsrLoggerInterface $namiLogger
     */
    public function __construct(
        protected PsrLoggerInterface $namiLogger
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
        $this->namiLogger->info('nami_shipping_id :: ' . $nameShippingId);
        $cargoId = $shippingAddress->getData('cargo_id');
        $this->namiLogger->info('cargo_id :: ' . $cargoId);

        if ($nameShippingId) {
            $order->setData('nami_shipping_id', $nameShippingId);
            $order->setData('cargo_id', $cargoId);
        }

        return 'Custom Attribute Transfer executed successfully.';
    }
}
