<?php
/**
 * Copyright © 2019 Bananacode SA, All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bananacode\GreenPay\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Bananacode\GreenPay\Gateway\Http\Client\ClientMock;

/**
 * Class ResponseCodeValidator
 * @package Bananacode\GreenPay\Gateway\Validator
 */
class ResponseCodeValidator extends AbstractValidator
{
    const RESULT_CODE = 'status';

    const ERROR_MESSAGES = array(
        "01" => "Refer to Issuing Bank",
        "03" => "Invalid Commerce",
        "04" => "Remove Card / Take Out Card",
        "05" => "Denied",
        "12" => "Invalid Transaction",
        "13" => "Invalid Amount Try Again",
        "14" => "Invalid Card",
        "19" => "Re-enter Transaction",
        "31" => "Bank Not Supported",
        "41" => "Lost Card",
        "43" => "Retain and Call",
        "51" => "Non Sufficient Funds",
        "54" => "Expired Card Remove Renewal",
        "55" => "Incorrect Pin",
        "58" => "Function Not Allowed",
        "62" => "Failure to Authorize",
        "63" => "Failure to Authorize",
        "65" => "Failure to Authorize",
        "78" => "Failure to Authorize",
        "89" => "Invalid Terminal",
        "91" => "Non-answering Issuing Bank",
        "96" => "Not Supported"
    );

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
     * ResponseCodeValidator constructor.
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory
    )
    {
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_orderRepository = $orderRepository;
        $this->_orderHistoryFactory = $orderHistoryFactory;

        parent::__construct($resultFactory);
    }

    /**
     * Performs validation of result code
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response']) || !is_array($validationSubject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        $response = $validationSubject['response'];

        if ($this->isSuccessfulTransaction($response)) {

            $hookResponseObj = (Object)$response;
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
                    if((boolean)$hookResponseObj->result->success) {
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

                            $this->_orderRepository->save($order);
                        }
                    }
                }
            }

            return $this->createResult(
                true,
                []
            );
        } else {
            $errors = $this->extractErrors($response);
            return $this->createResult(
                false,
                [
                    $errors[1]
                ],
                [
                    $errors[0]
                ]
            );
        }
    }

    /**
     * @param array $response
     * @return bool
     */
    private function isSuccessfulTransaction(array $response)
    {
        return isset($response[self::RESULT_CODE]) && $response[self::RESULT_CODE] === ClientMock::SUCCESS;
    }

    /**
     * @param array $response
     * @return array
     */
    private function extractErrors(array $response)
    {
        if (isset($response['result'])) {
            $response['result'] = (array)$response['result'];
            if (isset($response['result']['resp_code'])) {
                if (isset(self::ERROR_MESSAGES[$response['result']['resp_code']])) {
                    return [
                        $response['result']['resp_code'],
                        self::ERROR_MESSAGES[$response['result']['resp_code']]
                    ];
                }
            }
        }
        return [
            01 ,
            __('Transaction has been declined. Please try again later.')
        ];
    }
}
