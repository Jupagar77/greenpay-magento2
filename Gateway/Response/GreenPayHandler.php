<?php

namespace Bananacode\GreenPay\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;

class GreenPayHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $handlingSubject['payment'];

        $response = json_decode(json_encode($response), true);
        if((boolean)$response['result']['success']) {
            $payment = $paymentDO->getPayment();
            $payment->setAdditionalInformation('retrieval_ref_num', $response['result']['retrieval_ref_num']);
            $payment->setAdditionalInformation('authorization_id_resp', $response['result']['authorization_id_resp']);
        }
    }
}
