<?php

declare(strict_types=1);

namespace Namicargo\Shipping\Service;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Namicargo\Shipping\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class ShippingApiService
{
    /**
     * Constructor for ShippingApiService
     *
     * @param GetSourceItemsBySkuInterface $getSourceItemsBySku Source items retriever
     * @param SourceRepositoryInterface $sourceRepository Source repository
     * @param Data $shippingApi Shipping API helper
     * @param ScopeConfigInterface $scopeConfig Scope configuration
     * @param Curl $curl HTTP client
     * @param PsrLoggerInterface $namiLogger Shipping logger
     */
    public function __construct(
        private readonly GetSourceItemsBySkuInterface $getSourceItemsBySku,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly Data $shippingApi,
        private readonly ScopeConfigInterface $scopeConfig,
        protected Curl $curl,
        protected PsrLoggerInterface $namiLogger,
    ) {
    }

    /**
     * Send API request to Namicargo service
     *
     * @param array $apiData API request data
     * @param string $payload Request payload
     * @return array Response from API
     */
    public function sendRequest($apiData, $payload)
    {
        $url = "https://api.nami.la/order";
        $this->namiLogger->info('API request: ' . json_encode($apiData));
        $this->curl->setHeaders(['Content-Type' => 'application/json']);
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, "PUT");
        $this->curl->setOption(CURLOPT_POSTFIELDS, $payload);
        $this->curl->get($url);
        $response = json_decode($this->curl->getBody(), true);
        $this->namiLogger->info('API response: ' . json_encode($response));
        return $response;
    }

    /**
     * Prepare API data for shipping request
     *
     * @param Quote $quote Quote object
     * @param RateRequest $request Rate request object
     * @param string $streetAddress Street address
     * @param string $city City name
     * @param string $countryCode Country code
     * @param mixed $shippingAddress Shipping address object
     * @return array Prepared API data
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Random\RandomException
     */
    public function prepareApiData(
        Quote $quote,
        RateRequest $request,
        $streetAddress,
        $city,
        $countryCode,
        $shippingAddress
    ): array {
        $subtotal = $quote->getSubtotal();
        $firstname = $shippingAddress->getFirstname();
        $lastname = $shippingAddress->getLastname();        
        $orderId = bin2hex(random_bytes(6));
        $vehicleType = $quote->getStore()->getConfig('carriers/namicargoshipping/vehicle_type');
        $innerShippingTime = $quote->getStore()->getConfig('carriers/namicargoshipping/inner_shipping_time');
        $multiShipping = $quote->getStore()->getConfig('carriers/namicargoshipping/multi_shipping');
        $multiShipping = $multiShipping == 1;
        return [
            "token" => $this->isApiConnectionAvailable(),
            "order" => [
                "order_id" => $orderId,
                "order_type" => $vehicleType,
                "total_value" => $subtotal,
                "payment_method" => "ONLINE",
                "inner_shipping" => "true",
                "inner_shipping_time" => $innerShippingTime,
                "multi_shipping" => $multiShipping
            ],
            "warehouses" => $this->prepareWarehousesData($request),
            "customer" => [
                "address" => $streetAddress,
                "complement" => null,
                "firstname" => $shippingAddress->getFirstname(),
                "lastname" => $shippingAddress->getLastname(),
                "phone" => $shippingAddress->getTelephone(),
                "email" => $this->getCustomerEmail($quote),
                "city" => $city,
                "country" => $countryCode,
                "instructions" => "",
            ]
        ];
    }

    /**
     * Check if API connection is available
     *
     * @return string API connection token
     */
    public function isApiConnectionAvailable(): string
    {
        return $this->shippingApi->getAPIConnection();
    }

    /**
     * Prepare warehouses data for API request
     *
     * @param RateRequest $request Rate request object
     * @return array Prepared warehouses data
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function prepareWarehousesData(RateRequest $request): array
    {
        $preparationTime = $this->scopeConfig->getValue(
            'carriers/namicargoshipping/preparation_time',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $includeVirtualPrice = $this->scopeConfig->getValue(
            'carriers/namicargoshipping/include_virtual_price',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $warehouses = [];
        if (!$includeVirtualPrice && $request->getAllItems()) {
            $items = $request->getAllItems();
            foreach ($items as $item) {
                if ($item->getProductType() == "bundle") {
                    continue; // Skip bundle products
                }
                $this->processItem($item, $warehouses, $preparationTime);
            }
        }
        return $warehouses;
    }

    /**
     * Process individual item for warehouse data
     *
     * @param mixed $item Product item
     * @param array $warehouses Reference to warehouses array
     * @param string $preparationTime Preparation time value
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function processItem($item, &$warehouses, $preparationTime): void
    {
        $sku = $item->getSku();
        $name = $item->getName();
        $qty = $item->getQty();
        $price = $item->getPrice();
        $weight = $item->getWeight();

        $warehousesData = $this->getSourceItemsBySku->execute($sku);

        foreach ($warehousesData as $warehouse) {
            $sourceCode = $warehouse->getSourceCode();
            if ($sourceCode != 'default') {
                $this->addWarehouseData(
                    $sourceCode,
                    $sku,
                    $name,
                    $qty,
                    $price,
                    $weight,
                    $preparationTime,
                    $warehouses
                );
            }
        }
    }

    /**
     * Add warehouse data to warehouses array
     *
     * @param string $sourceCode Source code
     * @param string $sku Product SKU
     * @param string $name Product name
     * @param float $qty Quantity
     * @param float $price Price
     * @param float $weight Weight
     * @param string $preparationTime Preparation time
     * @param array $warehouses Reference to warehouses array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function addWarehouseData(
        $sourceCode,
        $sku,
        $name,
        $qty,
        $price,
        $weight,
        $preparationTime,
        &$warehouses
    ): void {
        $source = $this->sourceRepository->get($sourceCode);
        $sourceName = $source->getName();
        $sourceStreet = $source->getStreet();
        $sourceCity = $source->getCity();
        $sourceCountryCode = $source->getCountryId();

        foreach ($warehouses as $key => $existingWarehouse) {
            if ($existingWarehouse['id'] == $sourceCode) {
                $warehouses[$key]['products'][] = $this->prepareProductData($sku, $name, $qty, $weight, $price);
                return;
            }
        }

        $warehouses[] = [
            "id" => $sourceCode,
            "name" => $sourceName,
            "address" => $sourceStreet,
            "preparation_time" => $preparationTime,
            "city" => $sourceCity,
            "country" => $sourceCountryCode,
            "products" => [
                $this->prepareProductData($sku, $name, $qty, $weight, $price)
            ]
        ];
    }

    /**
     * Prepare product data for API request
     *
     * @param string $sku Product SKU
     * @param string $name Product name
     * @param float $qty Quantity
     * @param float $weight Weight
     * @param float $price Price
     * @return array Prepared product data
     */
    private function prepareProductData($sku, $name, $qty, $weight, $price): array
    {
        return [
            "sku" => $sku,
            "name" => $name,
            "qty" => $qty,
            "width" => $weight,
            "height" => '', // Add height if available
            "depth" => '',  // Add depth if available
            "price" => $price
        ];
    }

    /**
     * Retrieve the customer email from the quote
     *
     * @param Quote $quote Quote object
     * @return string Customer email address
     */
    private function getCustomerEmail($quote): string
    {
        $customerEmail = $quote->getCustomerEmail();
        if (empty($customerEmail)) {
            $billingAddress = $quote->getBillingAddress();
            $customerEmail = $billingAddress->getEmail() ?: "test@example.com";
        }
        return $customerEmail;
    }
}
