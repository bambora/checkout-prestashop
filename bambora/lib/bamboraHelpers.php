<?php
/**
 * Copyright (c) 2019. All rights reserved Bambora Online A/S.
 *
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test account that you can use to test the module.
 *
 * @author    Bambora Online A/S
 * @copyright Bambora (https://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 */

class BamboraHelpers
{
    /**
     * Generate ApiKey
     *
     * @return string
     */
    public static function generateApiKey()
    {
        $merchant = Configuration::get('BAMBORA_MERCHANTNUMBER');
        $accessToken = Configuration::get('BAMBORA_ACCESSTOKEN');
        $secretToken = Configuration::get('BAMBORA_SECRETTOKEN');
        $combined = $accessToken . '@' . $merchant .':'. $secretToken;
        $encodedKey = base64_encode($combined);
        $apiKey = 'Basic '.$encodedKey;

        return $apiKey;
    }

    /**
     * Format Truncated Cardnumber
     *
     * @param mixed $cardnumber
     * @return mixed
     */
    public static function formatTruncatedCardnumber($cardnumber)
    {
        $wordWrapped =  wordwrap($cardnumber, 4, ' ', true);
        return  str_replace("X", "&bull;", $wordWrapped);
    }

    /**
     * Get Module Header Info
     *
     * @return string
     */
    public static function getModuleHeaderInfo()
    {
        $bamboraVersion = Bambora::MODULE_VERSION;
        $prestashopVersion = _PS_VERSION_;
        $result = 'Prestashop/' . $prestashopVersion . ' Module/' . $bamboraVersion;

        return $result;
    }
}
