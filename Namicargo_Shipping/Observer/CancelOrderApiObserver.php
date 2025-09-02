<?php

namespace Namicargo\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\Client\Curl;
use Namicargo\Shipping\Helper\Data;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class CancelOrderApiObserver implements ObserverInterface
{
    /**
     * @param PsrLoggerInterface $namiLogger
     * @param Curl $curl
     * @param Data $shippingApi
     */
    public function __construct(
        protected PsrLoggerInterface $namiLogger,
        protected Curl $curl,
        protected Data $shippingApi
    ) {
    }

    /**
     * Cancel order token and call
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getData('order');
        $shippingId = $order->getNamiShippingId();
        $this->namiLogger->info('order id : ' . $shippingId);
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
                $this->namiLogger->info('Delete Payload: ' . $jsonPayloadDelete);
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
                        $this->namiLogger->info(
                            'Order cancellation successfully sent to API. Response: ' . $response
                        );
                    }

                    $this->namiLogger->error(
                        'Failed to cancel order via API. Status: '
                        . $responseStatus . ' Response: ' . $response
                    );
                } catch (\Exception $e) {
                    $this->namiLogger->error(
                        'Error occurred while sending order cancellation to API: ' . $e->getMessage()
                    );
                }
            }
            $this->namiLogger->error('generate the new token');
        }
    }
}
