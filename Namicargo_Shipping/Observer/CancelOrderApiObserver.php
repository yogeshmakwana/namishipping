<?php

namespace Namicargo\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\Client\Curl;
use Namicargo\Shipping\Helper\Data;
use Namicargo\Shipping\Logger\NamicargoCancelLogger;

if (!defined('BP')) {
    define('BP', dirname(__DIR__));
}

class CancelOrderApiObserver implements ObserverInterface
{
    public function __construct(
        private readonly NamicargoCancelLogger $cancelLogger,
        protected Curl $curl,
        protected Data $shippingApi
    ) {
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getData('order');
        $shippingId = $order->getNamiShippingId();
        $this->cancelLogger->info('order id : ' . $shippingId);
        if ($order->isCanceled() && $shippingId != null) {
            $apiConnection = $this->shippingApi->getAPIConnection();
            if ($apiConnection) {
                $payload = [
                    'token' => $apiConnection,
                    'order' => [
                        'order_id' => $shippingId
                    ]
                ];

                $jsonPayloadDelete = json_encode($payload);
                $this->cancelLogger->info('Delete Payload: ' . $jsonPayloadDelete);
                try {
                    $apiUrlDelete = 'https://api.nami.la/order';

                    $this->curl->setHeaders(
                        [
                            'Content-Type' => 'application/json'
                        ]
                    );
                    $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');
                    $this->curl->setOption(CURLOPT_POSTFIELDS, $jsonPayloadDelete);
                    $this->curl->get($apiUrlDelete);

                    $responseStatus = $this->curl->getStatus();
                    $response = $this->curl->getBody();
                    if ($responseStatus == 200) {
                        $this->cancelLogger->info(
                            'Order cancellation successfully sent to API. Response: ' . $response
                        );
                    }

                    $this->cancelLogger->error(
                        'Failed to cancel order via API. Status: '
                        . $responseStatus . ' Response: ' . $response
                    );
                } catch (\Exception $e) {
                    $this->cancelLogger->error(
                        'Error occurred while sending order cancellation to API: ' . $e->getMessage()
                    );
                }
            }
            $this->cancelLogger->error('generate the new token');
        }
    }
}
