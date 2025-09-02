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
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\App\State;

class Customshipping extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     * Carrier code.
     */
    protected $_code = 'namicargoshipping';

    /**
     * @var array
     * Additional data.
     */
    protected $data;

    /**
     * @var int
     * Preparation time for shipping.
     */
    private $preparationTime;

    /**
     * @var int
     * Inner shipping time.
     */
    private $innerShippingTime;

    /**
     * @var bool
     * Whether to include price in shipping method title.
     */
    private $includePrice;

    /**
     * Constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param PsrLoggerInterface $namiLogger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param Session $checkoutSession
     * @param Curl $curl
     * @param ShippingApiService $shippingApiService
     * @param array $data
     */
    public function __construct(
        protected ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        protected PsrLoggerInterface $namiLogger,
        protected ResultFactory $rateResultFactory,
        protected MethodFactory $rateMethodFactory,
        protected Session $checkoutSession,
        protected Curl $curl,
        protected ShippingApiService $shippingApiService,
        protected CartRepositoryInterface $quoteRepository,
        private State $appState,
        array $data = []
    ) {
        parent::__construct($this->scopeConfig, $rateErrorFactory, $this->namiLogger, $data);
        $this->preparationTime = (int) $this->scopeConfig->getValue(
            'carriers/namicargoshipping/preparation_time',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $this->innerShippingTime = (int) $this->scopeConfig->getValue(
            'carriers/namicargoshipping/inner_shipping_time',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $this->includePrice = (bool) $this->scopeConfig->getValue(
            'carriers/namicargoshipping/include_price',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Collect rates for the shipping method.
     *
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

        $quote = null;
        $items = $request->getAllItems();
        $result = $this->rateResultFactory->create();
        if (!empty($items)) {
            // Get the first item from the rate request
            /** @var \Magento\Quote\Model\Quote\Item $firstItem */
            $firstItem = reset($items);

            // Get the quote object from the item
            if ($firstItem instanceof \Magento\Quote\Model\Quote\Item) {
                $quote = $firstItem->getQuote();
            }
        }
        if (!$quote instanceof Quote) {
            try {
                $quoteId = $request->getQuoteId();
                if ($quoteId) {
                    $quote = $this->quoteRepository->get($quoteId);
                }
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                return false;
            }
        }

        if ($quote) {
            $shippingAddress = $quote->getShippingAddress();

            // Prepare API data
            $apiData = $this->shippingApiService->prepareApiData(
                $quote,
                $request,
                $request->getDestStreet(),
                $request->getDestCity(),
                $request->getDestCountryId(),
                $shippingAddress
            );

            // Call external API for shipping rates
            if ($this->shippingApiService->isApiConnectionAvailable()) {
                $this->processShippingRates($apiData, $result, $quote, $shippingAddress, $request);
            }
        }

        return $result;
    }

    /**
     * Check if the carrier is enabled.
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
     * Process the shipping rates and update quote address.
     *
     * @param array $apiData
     * @param \Magento\Shipping\Model\Rate\Result $result
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote\Address $shippingAddress
     * @param RateRequest $request
     * @return void
     */
    private function processShippingRates(
        array $apiData,
        \Magento\Shipping\Model\Rate\Result $result,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Model\Quote\Address $shippingAddress,
        RateRequest $request
    ): void {
        $quoteAddress = $quote->getShippingAddress();
        $currentCustomerData = json_encode($apiData['customer']);
        $currentWarehouseData = json_encode($this->shippingApiService->prepareWarehousesData($request));
        $oldCustomerData = $quoteAddress->getData('nami_customer_data');
        $oldWarehouseData = $quoteAddress->getData('nami_warehouse_data');
        if ($oldCustomerData !== $currentCustomerData || $oldWarehouseData !== $currentWarehouseData) {
            $payload = json_encode($apiData);
            $url = "https://api.nami.la/order";
            $this->namiLogger->info('API request: ' . json_encode($apiData));
            $this->curl->setHeaders(['Content-Type' => 'application/json']);
            $this->curl->setOption(CURLOPT_CUSTOMREQUEST, "PUT");
            $this->curl->setOption(CURLOPT_POSTFIELDS, $payload);
            $this->curl->get($url);
            $this->namiLogger->info('actuall respoinse:');
            $this->namiLogger->info($this->curl->getBody());
            $response = json_decode($this->curl->getBody(), true);
            $this->namiLogger->info('API response: ' . json_encode($response));
            if (is_array($response) && isset($response['successful']) && $response['successful'] != false) {
                $this->namiLogger->info('its inside');
                $this->resetQuoteAddress($quoteAddress);
                $this->namiLogger->info('API call successful, updating shipping methods.');
                $this->updateShippingMethods($response, $result, $quoteAddress);
                $this->processShippingOptions(
                    $shippingAddress,
                    $quoteAddress,
                    $response,
                    $currentCustomerData,
                    $currentWarehouseData
                );
                $this->updateShipOptions($shippingAddress, $quoteAddress);
            }
            $this->namiLogger->info('its inside');
            return;
        }
        $this->namiLogger->info('No changes in shipping data, skipping API call.');
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
                    ?: 'Tiempo estimado: ' . $finalEtaMinutes;
                $method->setCarrier($this->_code);
                $method->setCarrierTitle($description);
                $method->setMethod($name);
                $method->setMethodTitle('Envío Rápido');
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

    /**
     * Update shipping options in the quote address.
     *
     * @param \Magento\Quote\Model\Quote\Address $shippingAddress
     * @param \Magento\Quote\Model\Quote\Address $quoteAddress
     * @return void
     */
    private function updateShipOptions(
        \Magento\Quote\Model\Quote\Address $shippingAddress,
        \Magento\Quote\Model\Quote\Address $quoteAddress
    ): void {
        $shipOption = $shippingAddress->getShippingMethod();
        if ($shipOption) {
            $parts = explode('_', (string) $shipOption);
            $cargoId = $parts[1];
            $quoteAddress->setData('cargo_id', $cargoId);
            $quoteAddress->save();
        }
    }

    /**
     * Process shipping options and update quote address.
     *
     * @param \Magento\Quote\Model\Quote\Address $shippingAddress
     * @param \Magento\Quote\Model\Quote\Address $quoteAddress
     * @param array $response
     * @param string $currentCustomerData
     * @param string $currentWarehouseData
     * @return void
     */
    private function processShippingOptions(
        \Magento\Quote\Model\Quote\Address $shippingAddress,
        \Magento\Quote\Model\Quote\Address $quoteAddress,
        array $response,
        string $currentCustomerData,
        string $currentWarehouseData
    ): void {
        $shippingOptions = $shippingAddress->getShippingMethod();
        if ($shippingOptions) {
            $this->namiLogger->info('selectedShippingMethod in success::');
        }

        $quoteAddress->setData('nami_customer_data', $currentCustomerData);
        $quoteAddress->setData('nami_warehouse_data', $currentWarehouseData);
        $shipmentResponse = json_encode($response);
        $quoteAddress->setData('nami_shipping_response', $shipmentResponse);
        $quoteAddress->save();
    }

    /**
     * Update shipping methods based on the API response.
     *
     * @param array $response
     * @param \Magento\Shipping\Model\Rate\Result $result
     * @param \Magento\Quote\Model\Quote\Address $quoteAddress
     * @return void
     */
    private function updateShippingMethods(
        array $response,
        \Magento\Shipping\Model\Rate\Result $result,
        \Magento\Quote\Model\Quote\Address $quoteAddress
    ): void {
        if (is_array($response) && isset($response['body']) && is_array($response['body'])) {
            foreach ($response['body'] as $shipment) {
                $method = $this->rateMethodFactory->create();
                $finalEtaMinutes = $this->generateFinalEta($shipment['eta'], $response['body'], $shipment['type']);
                $namiOrderId = $shipment['order_id'];
                $description = $shipment['description']
                    ?: 'Tiempo estimado: ' . $finalEtaMinutes;
                $method->setCarrier($this->_code);
                $method->setCarrierTitle($description);
                $method->setMethod($shipment['type']);
                $method->setMethodTitle('Envío Rápido ' . ($this->includePrice ? ' (Included)' : ''));
                $method->setPrice($shipment['price']);
                $method->setCost($shipment['price']);
                $result->append($method);
                $quoteAddress->setData('nami_shipping_id', $namiOrderId);
            }
        }
    }

    /**
     * Generate final ETA for shipping.
     *
     * @param int $shipmentEta
     * @param array $responseBody
     * @param string $shipmentType
     * @return string
     */
    private function generateFinalEta(int $shipmentEta, array $responseBody, string $shipmentType): string
    {
        $this->namiLogger->info("preparationTime : ".$this->preparationTime);
        $this->namiLogger->info("shipmentEta : ".$shipmentEta);
        $finalEtaMinutes = $this->preparationTime + $shipmentEta;
        $this->namiLogger->info("finalEtaMinutes".$finalEtaMinutes);
        $finalEtaMinutes = $this->convertMinutesToHours($finalEtaMinutes);
        if ($shipmentType == 'one-shipment' && count($responseBody) > 1) {
            $finalEtaMinutes = (int) $this->innerShippingTime . __(' days ', 'nami-shipping') . $finalEtaMinutes;
        }
        return $finalEtaMinutes;
    }

    /**
     * Convert minutes into hours and minutes.
     *
     * @param int $minutes
     */
    public function convertMinutesToHours(int $minutes)
    {
        $this->namiLogger->info("Minutes".$minutes);
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0) {
            return "{$hours} hours and {$remainingMinutes} min";
        }

        return "{$remainingMinutes} min";
    }

    /**
     * Reset the quote address data if the API response is unsuccessful.
     *
     * @param \Magento\Quote\Model\Quote\Address $quoteAddress
     * @return void
     */
    private function resetQuoteAddress(\Magento\Quote\Model\Quote\Address $quoteAddress): void
    {
        $quoteAddress->setData('nami_shipping_response', null);
        $quoteAddress->setData('nami_shipping_id', null);
        $quoteAddress->setData('nami_warehouse_data', null);
        $quoteAddress->setData('nami_customer_data', null);
        $quoteAddress->setData('cargo_id', null);
        $quoteAddress->save();
    }

    /**
     * Get allowed shipping methods.
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return [$this->_code => $this->getConfigData('name')];
    }
}
