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
class BamboraCurrency
{
    /**
     * Convert Price To MinorUnits
     * 
     * @param mixed $amount 
     * @param mixed $minorUnits 
     * @param mixed $defaultMinorUnits 
     * @return double|integer
     */
    public static function convertPriceToMinorUnits($amount, $minorUnits, $defaultMinorUnits = 2)
    {
        if($minorUnits == "" || $minorUnits == null)
            $minorUnits = $defaultMinorUnits;

        if($amount == "" || $amount == null)
            return 0;

        return round($amount,$minorUnits) * pow(10,$minorUnits);
    }

    /**
     * Convert Price From MinorUnits
     * 
     * @param mixed $amount 
     * @param mixed $minorUnits 
     * @param mixed $defaultMinorUnits 
     * @return double|integer
     */
    public static function convertPriceFromMinorUnits($amount, $minorUnits, $defaultMinorUnits = 2)
    {
        if($minorUnits == "" || $minorUnits == null)
            $minorUnits = $defaultMinorUnits;

        if($amount == "" || $amount == null)
            return 0;

        return $amount / pow(10,$minorUnits);
    }

    /**
     * Get Currency MinorUnits
     * 
     * @param mixed $currencyCode 
     * @return integer
     */
    public static function getCurrencyMinorunits($currencyCode)
    {
        $currencyArray = array(
        'TTD' => 0, 'KMF' => 0, 'ADP' => 0, 'TPE' => 0, 'BIF' => 0,
        'DJF' => 0, 'MGF' => 0, 'XPF' => 0, 'GNF' => 0, 'BYR' => 0,
        'PYG' => 0, 'JPY' => 0, 'CLP' => 0, 'XAF' => 0, 'TRL' => 0,
        'VUV' => 0, 'CLF' => 0, 'KRW' => 0, 'XOF' => 0, 'RWF' => 0,
        'IQD' => 3, 'TND' => 3, 'BHD' => 3, 'JOD' => 3, 'OMR' => 3,
        'KWD' => 3, 'LYD' => 3);

        return key_exists($currencyCode, $currencyArray) ? $currencyArray[$currencyCode] : 2;
    }
}