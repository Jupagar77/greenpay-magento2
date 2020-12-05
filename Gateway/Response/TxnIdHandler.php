<?php
/**
 * Copyright © 2019 Bananacode SA, All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bananacode\GreenPay\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

/**
 * Class TxnIdHandler
 * @package Bananacode\GreenPay\Gateway\Response
 */
class TxnIdHandler implements HandlerInterface
{
    /**
     * Store response data on payment
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        //todo?
    }
}
