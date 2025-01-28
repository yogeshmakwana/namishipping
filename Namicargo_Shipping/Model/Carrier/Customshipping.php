<?php

declare(strict_types=1);

namespace Namicargo\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\HTTP\Client\Curl;
use Namicargo\Shipping\Service\ShippingApiService;
use Namicargo\Shipping\Logger\NamicargoShippingLogger;

if (!defined('BP')) {
    define('BP', dirname(__DIR__));
}

class Customshipping extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $code = 'namicargoshipping';
    protected $data;
    protected $logger;
    private $preparationTime;
    private $innerShippingTime;
    private $includePrice;

    public function __construct(
        protected ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        protected NamicargoShippingLogger $shippingLogger,
        protected ResultFactory $rateResultFactory,
        protected MethodFactory $rateMethodFactory,
        protected Session $checkoutSession,
        protected Curl $curl,
        protected ShippingApiService $shippingApiService,
        array $data = []
    ) {
        parent::__construct($this->scopeConfig, $rateErrorFactory, $this->shippingLogger, $data);
        $this->preparationTime = $this->scopeConfig->getValue(
            'carriers/namicargoshipping/preparation_time',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $this->innerShippingTime = $this->scopeConfig->getValue(
            'carriers/namicargoshipping/inner_shipping_time',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $this->includePrice = $this->scopeConfig->getValue(
            'carriers/namicargoshipping/include_price',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @param RateRequest $request
     * @return bool|\Magento\Framework\DataObject|\Magento\Shipping\Model\Rate\Result|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->isCarrierEnabled()) {
            return false;
        }
        $result = $this->rateResultFactory->create();
        $quote = $this->checkoutSession->getQuote();
        $shippingAddress = $quote->getShippingAddress();
        $apiData = $this->shippingApiService->prepareApiData(
            $quote,
            $request,
            $request->getDestStreet(),
            $request->getDestCity(),
            $request->getDestCountryId(),
            $shippingAddress
        );
        // If API connection is available, process rates
        if ($this->shippingApiService->isApiConnectionAvailable()) {
            $this->processShippingRates($apiData, $result, $quote, $shippingAddress, $request);
        }
        return $result;
    }

    /**
     * Check if the carrier is enabled
     *
     * @return bool
     */
    private function isCarrierEnabled(): bool
    {
        return $this->scopeConfig->getValue(
            'carriers/namicargoshipping/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) == 1;
    }

    /**
     * Process the shipping rates and update quote address
     *
     * @param array $apiData
     * @param \Magento\Shipping\Model\Rate\Result $result
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote\Address $shippingAddress
     * @param RateRequest $request
     * @return void
     */
    private function processShippingRates($apiData, $result, $quote, $shippingAddress, $request): void
    {
        $quoteAddress = $quote->getShippingAddress();
        $currentCustomerData = json_encode($apiData['customer']);
        $currentWarehouseData =  json_encode($this->shippingApiService->prepareWarehousesData($request));
        $oldCustomerData = $quoteAddress->getData('nami_customer_data');
        $oldWarehouseData = $quoteAddress->getData('nami_warehouse_data');
        if ($oldCustomerData !== $currentCustomerData || $oldWarehouseData !== $currentWarehouseData) {
            $payload = json_encode($apiData);
            $url = "https://api.nami.la/order";
            $this->shippingLogger->info('API request: ' . json_encode($apiData));
            $this->curl->setHeaders(['Content-Type' => 'application/json']);
            $this->curl->setOption(CURLOPT_CUSTOMREQUEST, "PUT");
            $this->curl->setOption(CURLOPT_POSTFIELDS, $payload);
            $this->curl->get($url);
            $response = json_decode($this->curl->getBody(), true);
            $this->shippingLogger->info('API response: ' . json_encode($response));
            if (is_array($response) && isset($response['successful']) && $response['successful'] != 1) {
                $this->resetQuoteAddress($quoteAddress);
            }
            $this->shippingLogger->info('API call successful, updating shipping methods.');
            $this->updateShippingMethods($response, $result, $quoteAddress);
            $this->processShippingOptions(
                $shippingAddress,
                $quoteAddress,
                $response,
                $currentCustomerData,
                $currentWarehouseData
            );
            $this->updateShipOptions($shippingAddress, $quoteAddress);
            return;
        }
        $this->shippingLogger->info('No changes in shipping data, skipping API call.');
        $shipment = $quoteAddress->getData('nami_shipping_response');
        $response = json_decode((string) $shipment, true);

        if (is_array($response) && isset($response['body']) && is_array($response['body'])) {
            foreach ($response['body'] as $shipment) {
                $finalEtaMinutes = $this->generateFinalEta($shipment['eta'], $response['body'], $shipment['type']);
                $namiOrderId = $shipment['order_id'];
                $estimatedPrice = $shipment['price'];
                $name = $shipment['type'];
                $method = $this->rateMethodFactory->create();
                $description = $shipment['description']
                    ?: 'Nami Cargo' . ' | Deliveries: ' . $shipment['deliveries']
                    . ' | Distance: ' . $shipment['distance']
                    . ' | ETA: ' . $finalEtaMinutes;
                $method->setCarrier($this->code);
                $method->setCarrierTitle($description);
                $method->setMethod($name);
                $method->setMethodTitle('Nami ' . $name);
                $method->setPrice($estimatedPrice);
                $method->setCost($estimatedPrice);
                $result->append($method);
                $quoteAddress->setData('nami_shipping_id', $namiOrderId);
            }
            $this->processShippingOptions(
                $shippingAddress,
                $quoteAddress,
                $response,
                $currentCustomerData,
                $currentWarehouseData
            );
            $this->updateShipOptions($shippingAddress, $quoteAddress);
        }
    }

    private function updateShipOptions($shippingAddress, $quoteAddress)
    {
        $shipOption = $shippingAddress->getShippingMethod();
        if ($shipOption) {
            $parts = explode('_', (string) $shipOption);
            $cargoId = $parts[1];
            $quoteAddress->setData('cargo_id', $cargoId);
            $quoteAddress->save();
        }
    }

    private function processShippingOptions(
        $shippingAddress,
        $quoteAddress,
        $response,
        $currentCustomerData,
        $currentWarehouseData
    ) {
        $shippingOptions = $shippingAddress->getShippingMethod();
        if ($shippingOptions) {
            $this->shippingLogger->info('selectedShippingMethod in success::');
        }

        $quoteAddress->setData('nami_customer_data', $currentCustomerData);
        $quoteAddress->setData('nami_warehouse_data', $currentWarehouseData);
        $shipmentResponse = json_encode($response);
        $quoteAddress->setData('nami_shipping_response', $shipmentResponse);
        $quoteAddress->save();
    }

    /**
     * Update shipping methods based on the API response
     *
     * @param array $response
     * @param \Magento\Shipping\Model\Rate\Result $result
     * @param \Magento\Quote\Model\Quote\Address $quoteAddress
     * @return void
     */
    private function updateShippingMethods($response, $result, $quoteAddress): void
    {
        if (is_array($response) && isset($response['body']) && is_array($response['body'])) {
            foreach ($response['body'] as $shipment) {
                $method = $this->rateMethodFactory->create();
                $finalEtaMinutes = $this->generateFinalEta($shipment['eta'], $response['body'], $shipment['type']);
                $namiOrderId = $shipment['order_id'];
                $description = $shipment['description']
                    ?: 'Nami Cargo' . ' | Deliveries: ' . $shipment['deliveries']
                    . ' | Distance: ' . $shipment['distance']
                    . ' | ETA: ' . $finalEtaMinutes;
                $method->setCarrier($this->code);
                $method->setCarrierTitle($description);
                $method->setMethod($shipment['type']);
                $method->setMethodTitle('Nami ' . $shipment['type'] . ($this->includePrice ? ' (Included)' : ''));
                $method->setPrice($shipment['price']);
                $method->setCost($shipment['price']);
                $result->append($method);
                $quoteAddress->setData('nami_shipping_id', $namiOrderId);
            }
        }
    }

    private function generateFinalEta($shipmentEta, $responseBody, $shipmentType): string
    {
        $finalEtaMinutes = $this->preparationTime + $shipmentEta;
        $finalEtaMinutes = $this->convertMinutesToHours($finalEtaMinutes);
        if ($shipmentType == 'one-shipment' && count($responseBody) > 1) {
            $finalEtaMinutes = (int) $this->innerShippingTime . __(' days ', 'nami-shipping') . $finalEtaMinutes;
        }
        return $finalEtaMinutes;
    }

    public function convertMinutesToHours($minutes): string
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0) {
            return "{$hours} hours and {$remainingMinutes} min";
        }

        return "{$remainingMinutes} min";
    }

    /**
     * Reset the quote address data if the API response is unsuccessful
     *
     * @param \Magento\Quote\Model\Quote\Address $quoteAddress
     * @return void
     */
    private function resetQuoteAddress($quoteAddress): void
    {
        $quoteAddress->setData('nami_shipping_response', null);
        $quoteAddress->setData('nami_shipping_id', null);
        $quoteAddress->setData('nami_warehouse_data', null);
        $quoteAddress->setData('nami_customer_data', null);
        $quoteAddress->setData('cargo_id', null);
        $quoteAddress->save();
    }

    /**
     * Get the code for shipping
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return [$this->code => $this->getConfigData('name')];
    }
}
