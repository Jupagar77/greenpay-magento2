<?php
/**
 * Copyright © 2019 Bananacode SA, All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bananacode\GreenPay\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

/**
 * Class ClientMock
 * @package Bananacode\GreenPay\Gateway\Http\Client
 */
class ClientMock implements ClientInterface
{
    /**
     * GreenPay Success Code
     */
    const SUCCESS = 200;

    /**
     * GreenPay Sandbox URLS
     */
    const SANDBOX_CHECKOUT_URL = 'https://sandbox-checkout.greenpay.me/kount';
    const SANDBOX_PAYMENT_URL = 'https://sandbox-merchant.greenpay.me';

    /**
     * GreenPay Production URLS
     */
    const CHECKOUT_URL = 'https://checkout.greenpay.me/kount';
    const PAYMENT_URL = 'https://merchant.greenpay.me';

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    private $_curl;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $_storeManager;

    /**
     * @var \Magento\Sales\Model\Order
     */
    private $_orderRepository;

    /**
     * ClientMock constructor.
     * @param Logger $logger
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Sales\Model\Order $orderRepository
     */
    public function __construct(
        Logger $logger,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Model\Order $orderRepository
    )
    {
        $this->logger = $logger;
        $this->_curl = $curl;
        $this->_storeManager = $storeManager;
        $this->_orderRepository = $orderRepository;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array|bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $requestData = $transferObject->getBody();

        /**
         * Get payment order
         */
        $response = false;
        if ($createOrder = $this->getPaymentOrder($requestData)) {
            $response = $this->placeOrder($requestData, $createOrder);
        }

        return $response;
    }

    /**
     * @param $requestData
     * @return bool|mixed|string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getPaymentOrder($requestData)
    {
        $parameters = [
            'secret' => ($requestData['secret']) ?? '',
            'merchantId' => ($requestData['merchant_id']) ?? '',
            'terminal' => ($requestData['terminal']) ?? '',
            'amount' => (float)($requestData['amount']) ?? '',
            'currency' => $this->_storeManager->getStore()->getBaseCurrencyCode(),
            'description' => 'Transaction order #' . ($requestData['order_increment']) ?? '',
            'orderReference' => ($requestData['order_increment']) ?? '',
            'callback' => ''
        ];

        if($requestData['additional']) {
            $parameters['additional'] = $requestData['additional'];
        }

        $url = self::PAYMENT_URL;
        if (isset($requestData['sandbox'])) {
            if ($requestData['sandbox']) {
                $url = self::SANDBOX_PAYMENT_URL;
            }
        }

        $headers = ["Content-Type" => "application/json"];
        $this->_curl->setHeaders($headers);
        $this->_curl->post($url, json_encode($parameters));
        if($response  = $this->_curl->getBody()){
            $response = (Array)json_decode($response);
            if (is_array($response)) {
                if (isset($response['session']) && isset($response['token'])) {
                    return $response;
                } else {
                    $this->logger->debug(['payment order error...']);
                    $this->logger->debug($response);
                }
            }
        }

        return false;
    }

    /**
     * @param $requestData
     * @param $orderData
     * @return array|string
     */
    private function placeOrder($requestData, $orderData)
    {
        $parameters = [
            'session' => ($orderData['session']) ?? '',
            'ld' => ($requestData['payment_method_nonce']['ld']) ?? '',
            'lk' => ($requestData['payment_method_nonce']['lk']) ?? ''
        ];

        $url = self::CHECKOUT_URL;
        if (isset($requestData['sandbox'])) {
            if ($requestData['sandbox']) {
                $url = self::SANDBOX_CHECKOUT_URL;
            }
        }

        $headers = [
            "Content-Type" => "application/json",
            "Accept" => "application/json",
            "liszt-token" => ($orderData['token']) ?? '',
        ];
        $this->_curl->setHeaders($headers);
        $this->_curl->post($url, json_encode($parameters));

        //Validate timeout
        if(intval($this->_curl->getStatus() == 504)) {
            $response['response'] = 504;
        } else {
            if($response = $this->_curl->getBody()) {
                $response = (Array)json_decode($response);
                $this->logger->debug(['response:']);
                $this->logger->debug($response);
            }
        }

        return $response;
    }

}
