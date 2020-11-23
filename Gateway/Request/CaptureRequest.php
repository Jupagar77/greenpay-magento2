<?php
/**
 * Copyright © 2019 Bananacode SA, All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bananacode\GreenPay\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Payment\Helper\Formatter;
use Magento\Braintree\Gateway\SubjectReader;

/**
 * Class CaptureRequest
 * @package Bananacode\GreenPay\Gateway\Request
 */
class CaptureRequest implements BuilderInterface
{
    use Formatter;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * CaptureRequest constructor.
     * @param ConfigInterface $config
     * @param SubjectReader $subjectReader
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        ConfigInterface $config,
        SubjectReader $subjectReader,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->config = $config;
        $this->subjectReader = $subjectReader;
        $this->_encryptor = $encryptor;
    }

    /**
     * Builds required request data
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];
        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();

        if (!$payment instanceof OrderPaymentInterface) {
            throw new \LogicException('Order payment should be provided.');
        }

        $sandbox = $this->config->getValue(
            'sandbox',
            $order->getStoreId()
        );

        return [
            'amount' => $this->formatPrice($this->subjectReader->readAmount($buildSubject)),
            'payment_method_nonce' => (array)json_decode($payment->getAdditionalInformation('payment_method_nonce')),
            'order_increment' => $order->getOrderIncrementId(),
            'additional' => $this->getCustomerAdditionalData($order),
            'merchant_id' => $this->_encryptor->decrypt($this->config->getValue(
                $sandbox ? 'merchant_id_sandbox' : 'merchant_id',
                $order->getStoreId()
            )),
            'secret' => $this->_encryptor->decrypt($this->config->getValue(
                $sandbox ? 'secret_sandbox' : 'secret' ,
                $order->getStoreId()
            )),
            'terminal' => $this->_encryptor->decrypt($this->config->getValue(
                $sandbox ? 'terminal_sandbox' : 'terminal',
                $order->getStoreId()
            )),
            'sandbox' => $sandbox
        ];
    }

    /**
     * @param $order \Magento\Payment\Gateway\Data\OrderAdapterInterface
     * @return bool|array
     */
    private function getCustomerAdditionalData($order)
    {
        try {
            $billingAddress = [];
            if($order->getBillingAddress()) {
                $billingAddress = array(
                    'country'  => $order->getBillingAddress()->getCountryId(),
                    'province' => $order->getBillingAddress()->getRegionCode(),
                    'city'     => $order->getBillingAddress()->getCity(),
                    'street1'  => $order->getBillingAddress()->getStreetLine1(),
                    'zip'      => $order->getBillingAddress()->getPostcode()
                );
            }

            $shippingAddress = $billingAddress;
            if ($order->getShippingAddress()) {
                $shippingAddress = array(
                    'country'  => $order->getShippingAddress()->getCountryId(),
                    'province' => $order->getShippingAddress()->getRegionCode(),
                    'city'     => $order->getShippingAddress()->getCity(),
                    'street1'  => $order->getShippingAddress()->getStreetLine1(),
                    'zip'      => $order->getShippingAddress()->getPostcode()
                );
            }

            $products = array();
            if($items = $order->getItems()) {
                foreach ($items as $item) {
                    $object['description'] = $item->getName();
                    $object['skuId']       = $item->getProductId();
                    $object['quantity']    = intval($item->getQtyOrdered());
                    $object['price']       = (float) number_format((float) $item->getPrice(), 2, '.', '');
                    $object['type']        = $item->getProductType();
                    array_push($products, $object);
                }
            }

            return array(
                'customer' => array(
                    'name' => $order->getBillingAddress()->getFirstname() .' '.$order->getBillingAddress()->getLastname(),
                    'email' => $order->getBillingAddress()->getEmail(),
                    'shippingAddress' => $shippingAddress,
                    'billingAddress' => $billingAddress
                ),
                "products" => $products
            );
        } catch (\Exception $e) {
            return false;
        }
    }
}
