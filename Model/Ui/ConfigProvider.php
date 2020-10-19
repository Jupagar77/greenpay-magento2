<?php
/**
 * Copyright © 2019 Bananacode SA, All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bananacode\GreenPay\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Gateway\Config\Config;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'greenpay';

    const PUBLIC_KEY = 'public_key';

    const PUBLIC_KEY_SANDBOX = 'public_key_sandbox';

    /**
     * @var \Magento\Payment\Gateway\ConfigInterface
     */
    protected $config;

    /**
     * ConfigProvider constructor.
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
        $this->config->setMethodCode(self::CODE);
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $sandbox = $this->config->getValue(
            'sandbox'
        );

        return [
            'payment' => [
                self::CODE => [
                    'publicKey' => $this->config->getValue(
                        $sandbox ? self::PUBLIC_KEY_SANDBOX : self::PUBLIC_KEY
                    ),
                    'sandbox' => $sandbox
                ]
            ]
        ];
    }
}
