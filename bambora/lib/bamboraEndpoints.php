<?php
/**
 * Bambora Online 2017
 *
 * @author    Bambora Online
 * @copyright Bambora (http://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

define('bambora_endpoint_transaction', 'https://transaction-v1.api.epay.eu');
define('bambora_endpoint_merchant', 'https://merchant-v1.api.epay.eu');
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
