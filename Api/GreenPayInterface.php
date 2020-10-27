<?php

namespace Bananacode\GreenPay\Api;

/**
 * Created by PhpStorm.
 * User: pablogutierrez
 * Date: 2020-02-09
 * Time: 23:02
 */
interface GreenPayInterface
{
    /**
     * GreenPay WebHook for checkout process
     *
     * @api
     * @return string
     */
    public function checkout();
}
