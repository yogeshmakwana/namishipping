<?php

namespace Namicargo\Shipping\Block\Order;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Registry;

class TrackButton extends Template
{
    protected $orderRepository;
    protected $registry;

    /**
     * Constructor.
     *
     * @param Template\Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        OrderRepositoryInterface $orderRepository,
        Registry $registry,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->registry = $registry;
        parent::__construct($context, $data); // Pass only the required parameters
    }

    /**
     * Retrieve the order ID from the request parameters.
     *
     * @return string|null The order ID, or null if not set.
     */
    public function getOrderId()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        return $orderId;
    }

    /**
     * Shipping ID from the order.
     *
     * @return string|null The order ID, or null if not set.
     */
    public function geShippingID()
    {
        $shippingId = '';
        $order = $this->registry->registry('current_order');
        if ($order->getStatus() != 'canceled') {
            $shippingId = $order->getData('nami_shipping_id');
        }
        return $shippingId;
    }

    /**
     * Retrieve the Increment ID from order.
     *
     * @return string|null The Increment ID, or null if not set.
     */
    public function getIncrementID()
    {
        $order = $this->registry->registry('current_order');
        return $order->getIncrementId();
    }
}
