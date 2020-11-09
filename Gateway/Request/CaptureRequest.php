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
            'order_id' => $order->getId(),
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
}
