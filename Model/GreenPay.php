<?php

namespace Bananacode\GreenPay\Model;

class GreenPay implements \Bananacode\GreenPay\Api\GreenPayInterface {

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
     * GreenPay constructor.
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory
     */
    public function __construct(
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory
    )
    {
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_orderRepository = $orderRepository;
        $this->_orderHistoryFactory = $orderHistoryFactory;
    }

    /**
     * GreenPay WebHook for checkout process
     *
     * @api
     * @return string
     */
    public function checkout()
    {
        $hookResponse = file_get_contents('php://input');
        $hookResponseObj = (Object)json_decode($hookResponse);
        if(isset($hookResponseObj->result) && isset($hookResponseObj->orderId)) {
            $searchCriteria = $this->_searchCriteriaBuilder
                ->addFilter('increment_id', $hookResponseObj->orderId, 'eq')
                ->create();

            $orderList = $this->_orderRepository
                ->getList($searchCriteria)
                ->getItems();

            /** @var \Magento\Sales\Model\Order $order */
            $order = count($orderList) ? array_values($orderList)[0] : null;

            if($order) {
                $order->setGreenpayResponse($hookResponse);

                if(!(boolean)$hookResponseObj->result->success) {
                    if($hookResponseObj->result->resp_code == 996) {
                        $order->setState(\Magento\Sales\Model\Order::STATUS_FRAUD);
                        $order->setStatus(\Magento\Sales\Model\Order::STATUS_FRAUD);
                    } else {
                        $order->setState(\Magento\Sales\Model\Order::STATE_HOLDED);
                        $order->setStatus(\Magento\Sales\Model\Order::STATE_HOLDED);
                    }
                } else {

                    if ($order->canComment()) {
                        $history = $this->_orderHistoryFactory->create()
                            ->setStatus($order->getStatus())
                            ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                            ->setComment(
                                __('GreenPay bank reference: %1.', $hookResponseObj->result->retrieval_ref_num)
                            )->setIsCustomerNotified(false)
                            ->setIsVisibleOnFront(false);

                        $order->addStatusHistory($history);

                        $history = $this->_orderHistoryFactory->create()
                            ->setStatus($order->getStatus())
                            ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                            ->setComment(
                                __('GreenPay bank authorization number: %1.', $hookResponseObj->result->authorization_id_resp)
                            )->setIsCustomerNotified(false)
                            ->setIsVisibleOnFront(false);

                        $order->addStatusHistory($history);
                    }
                }

                $this->_orderRepository->save($order);
            }
        }
    }
}
