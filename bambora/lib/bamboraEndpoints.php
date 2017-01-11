<?php
/**
 * 888                             888
 * 888                             888
 * 88888b.   8888b.  88888b.d88b.  88888b.   .d88b.  888d888  8888b.
 * 888 "88b     "88b 888 "888 "88b 888 "88b d88""88b 888P"       "88b
 * 888  888 .d888888 888  888  888 888  888 888  888 888     .d888888
 * 888 d88P 888  888 888  888  888 888 d88P Y88..88P 888     888  888
 * 88888P"  "Y888888 888  888  888 88888P"   "Y88P"  888     "Y888888
 *
 * @category    Online Payment Gatway
 * @package     Bambora_Online
 * @author      Bambora Online
 * @copyright   Bambora (http://bambora.com)
 */

define('bambora_endpoint_transaction', 'https://transaction-v1.api.epay.eu');
define('bambora_endpoint_merchant', 'https://merchant-v1.api.epay.eu');
define('bambora_endpoint_checkout', 'https://api.v1.checkout.bambora.com');
define('bambora_checkout_assets', 'https://v1.checkout.bambora.com/Assets');


class BamboraendpointConfig{

    static function getTransactionEndpoint(){
        return constant('bambora_endpoint_transaction');
    }

    static function getMerchantEndpoint(){
        return constant('bambora_endpoint_merchant');
    }

    static function getCheckoutEndpoint(){
        return constant('bambora_endpoint_checkout');
    }

    static function getCheckoutAssets(){
        return constant('bambora_checkout_assets');
    }

}