<?php

/**
 * bamboraCurrency short summary.
 *
 * bamboraCurrency description.
 *
 * @version 1.0
 * @author Allan W. Lie
 */
class BamboraCurrency
{
    public static function convertPriceToMinorUnits($amount, $minorUnits, $defaultMinorUnits = 2)
    {
        if($minorUnits == "" || $minorUnits == null)
            $minorUnits = $defaultMinorUnits; 

        if($amount == "" || $amount == null)
            return 0;

        return round($amount,$minorUnits) * pow(10,$minorUnits);
    }

    public static function convertPriceFromMinorUnits($amount, $minorUnits, $defaultMinorUnits = 2)
    {
        if($minorUnits == "" || $minorUnits == null)
            $minorUnits = $defaultMinorUnits;
         
        if($amount == "" || $amount == null)
            return 0;

        return $amount / pow(10,$minorUnits);
    }

    public static function getCurrencyMinorunits($currencyCode)
    {
        switch($currencyCode)
        {
            case "TTD":
            case "KMF":
            case "ADP":
            case "TPE":
            case "BIF":
            case "DJF":
            case "MGF":
            case "XPF":
            case "GNF":
            case "BYR":
            case "PYG":
            case "JPY":
            case "CLP":
            case "XAF":
            case "TRL":
            case "VUV":
            case "CLF":
            case "KRW":
            case "XOF":
            case "RWF":
                return 0;

            case "IQD":
            case "TND":
            case "BHD":
            case "JOD":
            case "OMR":
            case "KWD":
            case "LYD":
                return 3;

            default:
                return 2;
        }
        
    }
}

