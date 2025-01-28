<?php

namespace Namicargo\Shipping\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Backend\Block\Template\Context;
use Namicargo\Shipping\Helper\Data;

class ConnectAccount extends Field
{
    protected $curl;
    protected $shippingApi;

    public function __construct(
        Context $context,
        Curl $curl,
        Data $shippingApi,
        array $data = []
    ) {
        $this->curl = $curl;
        $this->shippingApi = $shippingApi;
        parent::__construct($context, $data);
    }

    /**
     * Initialization.
     *
     * @return void
     */
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('Namicargo_Shipping::system/config/button.phtml');
    }

    /**
     * Render button
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Get the URL for the button
     *
     * @return string|null
     */
    public function getButtonUrl()
    {
        $token = $this->_scopeConfig->getValue(
            'carriers/namicargoshipping/token',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (!$token) {
            return null;
        }

        $encodedToken = base64_encode((string) $token);
        $apiUrl = "https://api.nami.la/registration/?hash=";

        return $apiUrl . $encodedToken;
    }

    /**
     * Get the label for the button
     *
     * @return string
     */
    public function getButtonLabel(): string
    {
        $apiConnection = $this->shippingApi->getAPIConnection();
        if ($apiConnection) {
            return 'Account Connected';
        }
        return 'Connect Your Account';
    }
}
