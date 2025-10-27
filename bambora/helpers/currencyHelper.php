<?php

class BamboraCurrencyHelper
{
    public const ROUND_UP = 'round_up';
    public const ROUND_DOWN = 'round_down';
    public const ROUND_DEFAULT = 'round_default';

    /**
     * Convert Price To MinorUnits
     *
     * @param mixed $amount
     * @param mixed $minorUnits
     * @param mixed $defaultMinorUnits
     *
     * @return int
     */
    public static function convertPriceToMinorUnits($amount, $minorUnits, $rounding)
    {
        if ($amount == '' || $amount == null) {
            return 0;
        }

        switch ($rounding) {
            case self::ROUND_UP:
                $amount = ceil($amount * pow(10, $minorUnits));
                break;
            case self::ROUND_DOWN:
                $amount = floor($amount * pow(10, $minorUnits));
                break;
            default:
                $amount = round($amount * pow(10, $minorUnits));
                break;
        }

        return $amount;
    }

    /**
     * Convert Price From MinorUnits
     *
     * @param mixed $amount
     * @param mixed $minorUnits
     *
     * @return string
     */
    public static function convertPriceFromMinorUnits(
        $amount,
        $minorUnits,
        $decimal_seperator = '.',
    ) {
        if (!isset($amount)) {
            return 0;
        }

        return number_format(
            $amount / pow(10, $minorUnits),
            $minorUnits,
            $decimal_seperator,
            ''
        );
    }

    /**
     * Get Currency MinorUnits
     *
     * @param mixed $currencyCode
     *
     * @return int
     */
    public static function getCurrencyMinorunits($currencyCode)
    {
        $currencyArray = [
            'TTD' => 0,
            'KMF' => 0,
            'ADP' => 0,
            'TPE' => 0,
            'BIF' => 0,
            'DJF' => 0,
            'MGF' => 0,
            'XPF' => 0,
            'GNF' => 0,
            'BYR' => 0,
            'PYG' => 0,
            'JPY' => 0,
            'CLP' => 0,
            'XAF' => 0,
            'TRL' => 0,
            'VUV' => 0,
            'CLF' => 0,
            'KRW' => 0,
            'XOF' => 0,
            'RWF' => 0,
            'ISK' => 0,
            'IQD' => 3,
            'TND' => 3,
            'BHD' => 3,
            'JOD' => 3,
            'OMR' => 3,
            'KWD' => 3,
            'LYD' => 3,
        ];

        return key_exists(
            $currencyCode,
            $currencyArray
        ) ? $currencyArray[$currencyCode] : 2;
    }
}
