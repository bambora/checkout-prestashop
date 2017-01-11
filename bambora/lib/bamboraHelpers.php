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