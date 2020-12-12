<?php

namespace Bananacode\GreenPay\Model;

class GreenPay implements \Bananacode\GreenPay\Api\GreenPayInterface
{

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory
     */
    protected $_orderHistoryFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * GreenPay constructor.
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_orderRepository = $orderRepository;
        $this->_orderHistoryFactory = $orderHistoryFactory;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * GreenPay WebHook for checkout process
     *
     * @return string|void
     */
    public function checkout()
    {
        $hookResponse = file_get_contents('php://input');
        $response = json_decode($hookResponse, true);
        if (isset($response['result']) && isset($response['orderId'])) {
            $searchCriteria = $this->_searchCriteriaBuilder
                ->addFilter('increment_id', $response['orderId'], 'eq')
                ->create();

            $orderList = $this->_orderRepository
                ->getList($searchCriteria)
                ->getItems();

            /** @var \Magento\Sales\Model\Order $order */
            $order = count($orderList) ? array_values($orderList)[0] : null;
            if ($order) {
                $order->setGreenpayResponse($hookResponse);
                if ((boolean)$response['result']['success']) {
                    if ($order->canComment()) {
                        $history = $this->_orderHistoryFactory->create()
                            ->setStatus($this->getConfig('order_status'))
                            ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                            ->setComment(
                                __('GreenPay bank reference: %1.', $response['result']['retrieval_ref_num'])
                            )->setIsCustomerNotified(false)
                            ->setIsVisibleOnFront(false);

                        $order->addStatusHistory($history);

                        $history = $this->_orderHistoryFactory->create()
                            ->setStatus($this->getConfig('order_status'))
                            ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                            ->setComment(
                                __('GreenPay bank authorization number: %1.', $response['result']['authorization_id_resp'])
                            )->setIsCustomerNotified(false)
                            ->setIsVisibleOnFront(false);

                        $order->addStatusHistory($history);

                        $this->_orderRepository->save($order);
                    }
                } else {
                    $history = $this->_orderHistoryFactory->create()
                        ->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED)
                        ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                        ->setComment(
                            __('GreenPay order rejected')
                        )->setIsCustomerNotified(false)
                        ->setIsVisibleOnFront(false);

                    $order->addStatusHistory($history);

                    $this->_orderRepository->save($order);
                }
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
