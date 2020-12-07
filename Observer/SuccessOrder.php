<?php
/**
 * Copyright © 2019 Bananacode SA, All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bananacode\GreenPay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Sales\Model\Order;

class SuccessOrder extends AbstractDataAssignObserver
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory
     */
    protected $_orderHistoryFactory;

    /**
     * SuccessOrder constructor.
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param Order\Status\HistoryFactory $orderHistoryFactory
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory
    )
    {
        $this->_orderRepository = $orderRepository;
        $this->_orderHistoryFactory = $orderHistoryFactory;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var $order Order */
        $order = $observer->getOrder();

        if($order) {
            $response = $order->getPayment()->getAdditionalInformation();
            if ($order->canComment() && isset($response['retrieval_ref_num']) && isset($response['authorization_id_resp'])) {
                $history = $this->_orderHistoryFactory->create()
                    ->setStatus($order->getStatus())
                    ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                    ->setComment(
                        __('GreenPay bank reference: %1.', $response['retrieval_ref_num'])
                    )->setIsCustomerNotified(false)
                    ->setIsVisibleOnFront(false);

                $order->addStatusHistory($history);

                $history = $this->_orderHistoryFactory->create()
                    ->setStatus($order->getStatus())
                    ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                    ->setComment(
                        __('GreenPay bank authorization number: %1.', $response['authorization_id_resp'])
                    )->setIsCustomerNotified(false)
                    ->setIsVisibleOnFront(false);

                $order->addStatusHistory($history);
            }
        }
    }
}
