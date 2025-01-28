<?php

namespace Namicargo\Shipping\Block\Adminhtml\Order\View;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\ConfigInterface;
use Magento\Sales\Helper\Reorder;

class Buttons extends \Magento\Sales\Block\Adminhtml\Order\View
{
    protected $orderRepository;
    protected $registry;
    protected $salesConfig;
    protected $reorderHelper;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Registry $registry
     * @param OrderRepositoryInterface $orderRepository
     * @param ConfigInterface $salesConfig
     * @param Reorder $reorderHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        OrderRepositoryInterface $orderRepository,
        ConfigInterface $salesConfig,
        Reorder $reorderHelper,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->registry = $registry;
        $this->salesConfig = $salesConfig;
        $this->reorderHelper = $reorderHelper;
        parent::__construct($context, $registry, $salesConfig, $reorderHelper, $data); // Call the parent constructor

        // Check for order existence before proceeding
        if (!$this->getOrderId()) {
            return; // Simply return if order ID is not found
        }

        if ($this->geShippingID() != null) {
            $buttonUrl = $this->_urlBuilder->getUrl(
                'https://api.nami.la/status.php?order_id=' . $this->geShippingID(),
                ['order_id' => $this->geShippingID()]
            );

            $this->addButton(
                'create_custom_button1',
                ['label' => __('Request Shipping'), 'onclick' => 'setLocation(\'' . $buttonUrl . '\')']
            );

            $this->addButton(
                'create_custom_button2',
                ['label' => __('Track Order'), 'onclick' => 'setLocation(\'' . $buttonUrl . '\')']
            );
        }
    }

    /**
     * Shipping ID from the order.
     *
     * @return string|null The Shipping ID, or null if not set.
     */
    public function geShippingID()
    {
        $shippingId = '';
        $order = $this->registry->registry('current_order');
        if ($order->getStatus() != 'canceled') {
            $shippingId =  $order->getData('nami_shipping_id');
        }
        return $shippingId;
    }

    /**
     * Increment ID from the order.
     *
     * @return string|null The Increment ID, or null if not set.
     */
    public function getIncrementID()
    {
        $order = $this->registry->registry('current_order');
        return $order->getIncrementId();
    }
}
