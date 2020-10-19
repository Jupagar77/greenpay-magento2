<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\GreenPay\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class SettlementDataBuilder
 */
class SettlementDataRequest implements BuilderInterface
{
    const SUBMIT_FOR_SETTLEMENT = 'submitForSettlement';

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        //todo?
        return [

        ];
    }
}
