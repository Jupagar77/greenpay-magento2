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
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory
     */
    protected $_orderHistoryFactory;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;

    /**
     * SuccessOrder constructor.
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param Order\Status\HistoryFactory $orderHistoryFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Message\ManagerInterface $messageManager
    )
    {
        $this->_orderRepository = $orderRepository;
        $this->_orderHistoryFactory = $orderHistoryFactory;
        $this->_scopeConfig = $scopeConfig;
        $this->_messageManager = $messageManager;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var $order Order */
        $order = $observer->getOrder();

        if ($order) {
            $response = $order->getPayment()->getAdditionalInformation();

            if ($order->canComment() && isset($response['timeout'])) {
                $history = $this->_orderHistoryFactory->create()
                    ->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
                    ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                    ->setComment(
                        __('Order timeout waiting for hook response')
                    )->setIsCustomerNotified(false)
                    ->setIsVisibleOnFront(false);

                $order->addStatusHistory($history);

                $this->_messageManager->addErrorMessage(__("Ha habido un problema de comunicación. Verifique su estado de cuenta antes de intentarlo de nuevo."));
            }

            if ($order->canComment() && isset($response['retrieval_ref_num']) && isset($response['authorization_id_resp'])) {
                $history = $this->_orderHistoryFactory->create()
                    ->setStatus($this->getConfig('order_status'))
                    ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                    ->setComment(
                        __('GreenPay bank reference: %1.', $response['retrieval_ref_num'])
                    )->setIsCustomerNotified(false)
                    ->setIsVisibleOnFront(false);

                $order->addStatusHistory($history);

                $history = $this->_orderHistoryFactory->create()
                    ->setStatus($this->getConfig('order_status'))
                    ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                    ->setComment(
                        __('GreenPay bank authorization number: %1.', $response['authorization_id_resp'])
                    )->setIsCustomerNotified(false)
                    ->setIsVisibleOnFront(false);

                $order->addStatusHistory($history);
            }
        }
    }

    /**
     * @param $config
     * @return mixed
     */
    public function getConfig($config)
    {
        return $this->_scopeConfig->getValue(
            "payment/greenpay/" . $config,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
