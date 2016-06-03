<?php

class BamboraHelpers
{

    public static function create_bambora_paymentscript($paymentWindowUrl, $windowState, $bamboraCheckoutUrl, $runOnLoad)
    {
        return "<script type='text/javascript'>
                     (function (n, t, i, r, u, f, e) { n[u] = n[u] || function() {
                        (n[u].q = n[u].q || []).push(arguments)}; f = t.createElement(i);
                        e = t.getElementsByTagName(i)[0]; f.async = 1; f.src = r; e.parentNode.insertBefore(f, e)
                        })(window, document, 'script','".$paymentWindowUrl."', 'bam');
                        var windowstate = ".$windowState.";

                       var options = {
                            'windowstate': windowstate,
                       }

                       function openPaymentWindow()
                       {
                            bam('open', '".$bamboraCheckoutUrl."', options);
                       }
                   
                       if(".$runOnLoad.")
				       {
					        bam('open', '".$bamboraCheckoutUrl."', options);
				       }
                </script>";
    }

    public static function generateApiKey($merchant, $accesstoken, $secrettoken)
    {
        //Basic (accestoken@merchantnumer:secrettoken) -> base64
        $combined = $accesstoken . '@' . $merchant .':'. $secrettoken;
        $encodedKey = base64_encode($combined);
        $apiKey = 'Basic '.$encodedKey;

        return $apiKey;      
    } 


    public static function formatTruncatedCardnumber($cardnumber)
    {
        $wordWrapped =  wordwrap($cardnumber, 4, ' ', true);
        return  str_replace("X", "&bull;", $wordWrapped);
    }

    

    public static function handleUserAmountInput($price, $currency = null, Context $context = null)
    {
        //  if (!is_float($price)) {
        //     return $price;
        // }
        if (!$context) {
            $context = Context::getContext();
        }
        if ($currency === null) {
            $currency = $context->currency;
        }     
        elseif (is_int($currency)) {
            $currency = Currency::getCurrencyInstance((int)$currency);
        }

        if (is_array($currency)) {
            $c_format = $currency['format'];
        } elseif (is_object($currency)) {
            $c_format = $currency->format;
        } else {
            return false;
        }

        
        $ret = 0;

        /*
         * If the language is RTL and the selected currency format contains spaces as thousands separator
         * then the number will be printed in reverse since the space is interpreted as separating words.
         * To avoid this we replace the currency format containing a space with the one containing a comma (,) as thousand
         * separator when the language is RTL.
         *
         */
        if (($c_format == 2) && ($context->language->is_rtl == 1)) {
            $c_format = 4;
        }

        switch ($c_format) {
            /* X 0,000.00 */
            case 1:            
                $ret =  str_replace(',','',$price);
                break;
            /* 0 000,00 X*/
            case 2:     
                $ret =  str_replace(' ','', str_replace(',','.',$price));
                break;
            /* X 0.000,00 */
            case 3:
                $temp = str_replace('.','',$price);
                $ret = str_replace(',','.',$temp);
                break;
            /* 0,000.00 X */
            case 4:
                $ret = str_replace(',','',$price);
                break;
            /* X 0'000.00  Added for the switzerland currency */
            case 5:
                $ret = str_replace("'", "", $price);
                break;
        }
        
        return floatval($ret);
    }

    public static function displayPricewithoutCurrency($price, Context $context = null)
    {
        $currency = null;
        if (!is_numeric($price)) {
            return $price;
        }
        if (!$context) {
            $context = Context::getContext();
        }
        if ($currency === null) {
            $currency = $context->currency;
        }
        // if you modified this function, don't forget to modify the Javascript function formatCurrency (in tools.js)
        elseif (is_int($currency)) {
            $currency = Currency::getCurrencyInstance((int)$currency);
        }

        if (is_array($currency)) {         
            $c_format = $currency['format'];
            $c_decimals = (int)$currency['decimals'] * _PS_PRICE_DISPLAY_PRECISION_;            
        } elseif (is_object($currency)) {            
            $c_format = $currency->format;
            $c_decimals = (int)$currency->decimals * _PS_PRICE_DISPLAY_PRECISION_;            
        } else {
            return false;
        }
        
        $ret = 0;
        if (($is_negative = ($price < 0))) {
            $price *= -1;
        }
        $price = Tools::ps_round($price, $c_decimals);

        /*
         * If the language is RTL and the selected currency format contains spaces as thousands separator
         * then the number will be printed in reverse since the space is interpreted as separating words.
         * To avoid this we replace the currency format containing a space with the one containing a comma (,) as thousand
         * separator when the language is RTL.
         *
         * TODO: This is not ideal, a currency format should probably be tied to a language, not to a currency.
         */
        if (($c_format == 2) && ($context->language->is_rtl == 1)) {
            $c_format = 4;
        }

        switch ($c_format) {
            /* X 0,000.00 */
            case 1:
                $ret = number_format($price, $c_decimals, '.', ',');
                break;
            /* 0 000,00 X*/
            case 2:
                $ret = number_format($price, $c_decimals, ',', ' ');
                break;
            /* X 0.000,00 */
            case 3:
                $ret = number_format($price, $c_decimals, ',', '.');
                break;
            /* 0,000.00 X */
            case 4:
                $ret = number_format($price, $c_decimals, '.', ',');
                break;
            /* X 0'000.00  Added for the switzerland currency */
            case 5:
                $ret = number_format($price, $c_decimals, '.', "'");
                break;
        }
        if ($is_negative) {
            $ret = '-'.$ret;
        }
        return $ret;
    }

    public static function getCardNameById($card_id)
	{
		switch($card_id)
		{
			case 1:
				return 'Dankort / VISA/Dankort';
			case 2:
				return 'VISA / VISA Electron';
			case 3:
				return 'MasterCard';
			case 4:
				return 'Maestro';
			case 5:
				return 'JCB';
            case 6:
                return 'American Express';
			case 7:
				return 'Diners Club';
			case 8:
				return 'Discover';
            default:
                return 'Unknown';
		}		
	}	
}


?>