<?php

namespace Namicargo\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\Client\Curl;
use Namicargo\Shipping\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class SendOrderToApi implements ObserverInterface
{

    /**
     * @param Curl $curl
     * @param Data $shippingApi
     * @param ScopeConfigInterface $scopeConfig
     * @param PsrLoggerInterface $namiLogger
     */
    public function __construct(
        protected Curl $curl,
        protected Data $shippingApi,
        protected ScopeConfigInterface $scopeConfig,
        protected PsrLoggerInterface $namiLogger
    ) {
    }

    /**
     * Sendorder Api
     *
     * @param Observer $observer
     * @return void
     */
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
                $this->namiLogger->info('Order sent to API. Response: ' . PHP_EOL . print_r($responsePost, true));
            }
        } catch (\Exception $e) {
            $this->namiLogger->error('Error sending order to API: ' . $e->getMessage());
        }
    }
}
