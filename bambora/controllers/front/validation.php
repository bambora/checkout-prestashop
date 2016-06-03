<?php
/*
 * Copyright (c) 2016. All rights reserved Bambora - www.bambora.com
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software. 
 * It is also not legal to do any changes to the software and distribute it in your own name / brand. 
 */
include('lib/bamboraApi.php');
include('lib/bamboraCurrency.php');

class BamboraValidationModuleFrontController extends ModuleFrontController
{	
    public $ssl = true;
	public $display_column_left = false;
	
	public function postProcess()
	{
        
        $id_cart = Tools::getValue('orderid');
		$cart = new Cart($id_cart);  

		$mailVars = array();
		
		$params = $_GET;
		$var = "";
		
		foreach($params as $key => $value)
		{
			if($key != "hash")
			{
				$mailVars['{bambora_' . $key . '}'] = $value;
				$var .= $value;
			} 
			else
			{
				break;
			}
		}

        if(strlen(Configuration::get('BAMBORA_MD5KEY')) > 0)
        {
            $genstamp = md5($var . Configuration::get('BAMBORA_MD5KEY'));
            
            if($genstamp != $_REQUEST["hash"])
            {
                try
                {
                    $this->sendEmailToShopOwner('Suspicious payment','An order has been places in you shop, which seems to be messed with. the orderid is '. $id_cart.', and the bambora payment transaction id is: '.$transactionid .' . note:if you have just set up the bambora payment module, please enshure that the MD5 key settings is correct');
                    die(Tools::displayError('Error in MD5 data! Please review your passwords in both Bambora and your Prestashop admin!'));
                }
                catch(Exception $e){
                    die(Tools::displayError($e->getMessage));
                }
            }
        }
		if(!$cart->orderExists())
		{	
            $cardid = 0;
            $bamboraOrderId = 0;
            $transactionfee = 0;
            $transactionfeeInMinorUnits = 0;
            $amountInMinorUnits = 0;
            $amount = 0;
            
            $currency = $cart->id_currency;

            $transactionid = $_GET['txnid'];

            $apiKey = strval(Configuration::get('BAMBORA_REMOTE_API_PASSWORD'));
			$api = new BamboraApi($apiKey);
			
            $tranactionInformation =  $api->gettransactionInformation($transactionid);
            
			if($tranactionInformation)
			{
				$transInfoMeta = $api->convertJSonResultToArray($tranactionInformation, "meta");
                
				if ($transInfoMeta != null && $transInfoMeta['result'] == true)
				{
					$transInfo = $api->convertJSonResultToArray($tranactionInformation, "transaction");
                    
					if ($transInfo != null)
					{										
						$currency = $transInfo["currency"]["code"];
                        $currencyNumber = $transInfo["currency"]["number"];
						$currencyid = Currency::getIdByIsoCodeNum($currencyNumber);


						foreach( $transInfo["information"]["paymenttypes"] as $paymenttype)
						{						
							//we can only handle one paymenttype here - enshure only to take the correct paymenttype here
                            $cardid	= 	$paymenttype["groupid"];	                            
                            
						}
                        $minorUnits = $transInfo["currency"]["minorunits"];
                        if($minorUnits == null || $minorUnits == "")
                            $minorUnits = BamboraCurrency::getCurrencyMinorunits($currency);

						$transactionfeeInMinorUnits = $transInfo["total"]["feeamount"];
						$transactionfee = BamboraCurrency::convertPriceFromMinorUnits($transInfo["total"]["feeamount"],$minorUnits);
						$bamboraOrderId	= $transInfo["orderid"];					
						$amountInMinorUnits = $transInfo["total"]["authorized"];
						$amount = BamboraCurrency::convertPriceFromMinorUnits($transInfo["total"]["authorized"], $minorUnits);  
                        
					}		
				}
			}	

			if($this->module->validateOrder((int)$id_cart, Configuration::get('PS_OS_PAYMENT'), $amount,$this->module->displayName, null, $mailVars, $currencyid, false, $cart->secure_key))
			{
				$this->module->recordTransaction(null, $id_cart, $transactionid, $cardid, $currency, $amountInMinorUnits, $transactionfeeInMinorUnits,$bamboraOrderId);
				$order = new Order($this->module->currentOrder);
				
				$payment = $order->getOrderPayments();
				$payment[0]->transaction_id = $transactionid;
				$payment[0]->amount = $amount;
				
				if($transactionfee > 0)
				{
					$payment[0]->amount = $payment[0]->amount + $transactionfee;
					if(Configuration::get('BAMBORA_ADDFEETOSHIPPING'))
					{
						$order->total_paid = $order->total_paid + $transactionfee;
						$order->total_paid_tax_incl = $order->total_paid_tax_incl + $transactionfee;
						$order->total_paid_tax_excl = $order->total_paid_tax_excl + $transactionfee;
						$order->total_paid_real = $order->total_paid_real + $transactionfee;
						$order->total_shipping = $order->total_shipping + $transactionfee;
						$order->total_shipping_tax_incl = $order->total_shipping_tax_incl + $transactionfee;
						$order->total_shipping_tax_excl = $order->total_shipping_tax_excl + $transactionfee;
						$order->save();
						
						$invoice = $payment[0]->getOrderInvoice($this->module->currentOrder);
						$invoice->total_paid_tax_incl = $invoice->total_paid_tax_incl + $transactionfee;
						$invoice->total_paid_tax_excl = $invoice->total_paid_tax_excl + $transactionfee;
						$invoice->total_shipping_tax_incl = $invoice->total_shipping_tax_incl + $transactionfee;
						$invoice->total_shipping_tax_excl = $invoice->total_shipping_tax_excl + $transactionfee;
						
						$invoice->save();
					}                      
                }
                $payment[0]->save();
			}
		}
        
		
		$id_order = Order::getOrderByCartId($id_cart);
		
        if(Tools::getValue('callback') != "1")
			Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$cart->secure_key.'&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.(int)$id_order);
	}

	/**
     * @see FrontController::initContent()
     */
	public function initContent()
	{
		parent::initContent();

		$this->context->smarty->assign(array(
			'total' => $this->context->cart->getOrderTotal(true, Cart::BOTH),
			'this_path' => $this->module->getPathUri(),//keep for retro compat
			'this_path_cod' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));

		$this->setTemplate('validation.tpl');
	}

    public function sendEmailToShopOwner($subject, $text)
    {
        
        $shopEmail = Configuration::get('PS_SHOP_EMAIL') ;        
        Mail::SendMailTest(false, Configuration::get('PS_MAIL_SERVER'), $text, $subject, Configuration::get('PS_MAIL_TYPE'), $shopEmail, $shopEmail, Configuration::get('PS_MAIL_USER'), Configuration::get('PS_MAIL_PASSWD'),  Configuration::get('PS_MAIL_SMTP_PORT'), Configuration::get('PS_MAIL_SMTP_ENCRYPTION'));

    }

}

?>