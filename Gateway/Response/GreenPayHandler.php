<?php

namespace Bananacode\GreenPay\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;

class GreenPayHandler implements HandlerInterface
{
    const TIMEOUT_CODE = 504;

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

        $response = (Array)json_decode(json_encode($response), true);
        //Validate timeout
        if(isset($response['response'])) {
            if($response['response'] == self::TIMEOUT_CODE) {
                $payment = $paymentDO->getPayment();
                $payment->setAdditionalInformation('timeout', true);
            }
        }

        if(isset($response['result']['success'])) {
            if((boolean)$response['result']['success']) {
                $payment = $paymentDO->getPayment();
                $payment->setAdditionalInformation('retrieval_ref_num', $response['result']['retrieval_ref_num']);
                $payment->setAdditionalInformation('authorization_id_resp', $response['result']['authorization_id_resp']);
            }
        }
    }
}
