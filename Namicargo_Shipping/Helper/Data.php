<?php

namespace Namicargo\Shipping\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Data extends AbstractHelper
{
    protected $curl;
    protected $scopeConfig;

    public function __construct(
        Curl $curl,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * API Connection
     *
     * @return string
     */
    public function getAPIConnection(): string
    {
        $token = $this->scopeConfig->getValue(
            'carriers/namicargoshipping/token',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $payload = json_encode(['hash' => $token]);
        $url = "https://api.nami.la/auth";

        // Set options for the POST request
        $this->curl->setHeaders(
            [
                'Content-Type' => 'application/json'
            ]
        );
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, "POST"); // Parameters changed to strings
        $this->curl->setOption(CURLOPT_POSTFIELDS, $payload);  // Parameters changed to strings
        $this->curl->get($url); // Use post instead of get due to POST request

        $response = $this->curl->getBody();
        $response = json_decode((string) $response, true);

        if (isset($response['key']) && isset($response['secret'])) {
            $payload2 = json_encode(['key' => $response['key'],'secret' => $response['secret']]);
            $url2 = "https://api.nami.la/auth";

            // Set options for the POST request
            $this->curl->setHeaders(
                [
                    'Content-Type' => 'application/json'
                ]
            );
            $this->curl->setOption(CURLOPT_CUSTOMREQUEST, "POST"); // Parameters changed to strings
            $this->curl->setOption(CURLOPT_POSTFIELDS, $payload2);  // Parameters changed to strings
            $this->curl->post($url2, []); // Use post instead of get due to post request

            $response2 = $this->curl->getBody();
            $response2 = json_decode((string) $response2, true);

            if ($response2['status'] == 1) {
                return $response2['token']; // Returning the token if successful
            }
        }

        return ''; // Returning an empty string
    }
}
