<?php

namespace Namicargo\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\Client\Curl;
use Namicargo\Shipping\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Namicargo\Shipping\Logger\NamicargoSendOrderToApiLogger;

if (!defined('BP')) {
    define('BP', dirname(__DIR__));
}

class SendOrderToApi implements ObserverInterface
{
    public function __construct(
        protected Curl $curl,
        protected Data $shippingApi,
        protected ScopeConfigInterface $scopeConfig,
        private readonly NamicargoSendOrderToApiLogger $sendOrderLogger
    ) {
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getData('order');
        $namiShippingId = $order->getData('nami_shipping_id');
        $cargoId = $order->getData('cargo_id');

        $apiConnection = $this->shippingApi->getAPIConnection();

        try {
            if (!empty($apiConnection)) {
                $payload = [
                    'token' => $apiConnection,
                    'order' => [
                        'order_id' => $namiShippingId,
                        'option' => $cargoId
                    ]
                ];
                $jsonPayloadOrder = json_encode($payload);

                $apiUrlPost = 'https://api.nami.la/order';
                $this->curl->setHeaders(
                    ['Content-Type' => 'application/json']
                );
                $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'POST');
                $this->curl->setOption(CURLOPT_POSTFIELDS, $jsonPayloadOrder);
                $this->curl->get($apiUrlPost);

                $responsePost = $this->curl->getBody();
                $responsePost = json_decode($responsePost, true);
                $this->sendOrderLogger->info('Order sent to API. Response: ' . $responsePost);
            }
        } catch (\Exception $e) {
            $this->sendOrderLogger->error('Error sending order to API: ' . $e->getMessage());
        }
    }
}
