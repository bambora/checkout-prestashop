<?php
/**
 * Copyright (c) 2017. All rights reserved Bambora Online A/S.
 *
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test account that you can use to test the module.
 *
 * @author    Bambora Online A/S
 * @copyright Bambora (http://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 */

define('bambora_endpoint_transaction', 'https://transaction-v1.api-eu.bambora.com');
define('bambora_endpoint_merchant', 'https://merchant-v1.api-eu.bambora.com');
define('bambora_endpoint_checkout', 'https://api.v1.checkout.bambora.com');
define('bambora_checkout_assets', 'https://v1.checkout.bambora.com/Assets');

class BamboraendpointConfig
{
    public static function getTransactionEndpoint()
    {
        return constant('bambora_endpoint_transaction');
    }

    public static function getMerchantEndpoint()
    {
        return constant('bambora_endpoint_merchant');
    }

    public static function getCheckoutEndpoint()
    {
        return constant('bambora_endpoint_checkout');
    }

    public static function getCheckoutAssets()
    {
        return constant('bambora_checkout_assets');
    }
}
