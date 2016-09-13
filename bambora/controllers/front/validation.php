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
    private $isCallback = false;

	public function postProcess()
	{
        $this->isCallback = Tools::getValue('callback') === "1";

        $id_cart = Tools::getValue('orderid');
		$cart = new Cart($id_cart);

        if(!isset($id_cart) || !isset($cart))
        {
            $message =  "Please provide a valid orderid";
            $this->setResponse($id_cart, $message, 400,true);
            return $message;
        }

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

        $shopMd5 = Configuration::get('BAMBORA_MD5KEY');

        if(strlen($shopMd5) > 0)
        {
            $genstamp = md5($var . $shopMd5);

            if($genstamp != $_REQUEST["hash"])
            {
                $message = "Bambora MD5 check failed";
                $this->setResponse($id_cart, $message, 400, true);
                return $message;
            }
        }

        $responseMessage = "";

		if(!$cart->orderExists())
		{
            $cardid = 0;
            $currency = $cart->id_currency;
            $transactionid = $_GET['txnid'];

            $apiKey = strval(Configuration::get('BAMBORA_REMOTE_API_PASSWORD'));
			$api = new BamboraApi($apiKey);

            $tranaction = $api->gettransaction($transactionid);

			if(!$tranaction)
			{
                $message = "Transaction Information is null";
                $this->setResponse($id_cart, $message, 400, true);
                return $message;
            }

            if(!$tranaction['meta']['result'])
            {
                $message = $this->isCallback ? $tranaction['meta']['message']['merchant'] : $tranaction['meta']['message']['enduser'];
                $this->setResponse($id_cart, $message, 400, true);
                return $message;
            }

            $transInfo = $tranaction['transaction'];

            $currency = $transInfo["currency"]["code"];
            $currencyNumber = $transInfo["currency"]["number"];
            $currencyid = Currency::getIdByIsoCodeNum($currencyNumber);

            foreach($transInfo["information"]["paymenttypes"] as $paymenttype)
            {
                //we can only handle one paymenttype here - enshure only to take the correct paymenttype here
                $cardid	= $paymenttype["groupid"];
            }

            $minorUnits = $transInfo["currency"]["minorunits"];
            if(!isset($minorUnits))
            {
                $minorUnits = BamboraCurrency::getCurrencyMinorunits($currency);
            }

            $transactionfeeInMinorUnits = $transInfo["total"]["feeamount"];
            $transactionfee = BamboraCurrency::convertPriceFromMinorUnits($transInfo["total"]["feeamount"],$minorUnits);
            $bamboraOrderId	= $transInfo["orderid"];
            $amountInMinorUnits = $transInfo["total"]["authorized"];
            $amount = BamboraCurrency::convertPriceFromMinorUnits($transInfo["total"]["authorized"], $minorUnits);

            if($this->module->validateOrder((int)$id_cart, Configuration::get('PS_OS_PAYMENT'), $amount,$this->module->displayName, null, $mailVars, $currencyid, false, $cart->secure_key))
            {
                $id_order = Order::getOrderByCartId($id_cart);
                $this->module->recordTransaction($id_order, $id_cart, $transactionid, $cardid, $currency, $amountInMinorUnits, $transactionfeeInMinorUnits,$bamboraOrderId);
                $order = new Order($id_order);

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
            $responseMessage = "Order Created";
        }
        else
        {
            $responseMessage = "Order was already Created";
        }

        if(!$this->isCallback)
        {
            Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$cart->secure_key.'&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.(int)Order::getOrderByCartId($id_cart));
        }

        $this->setResponse($id_cart, $responseMessage, 200);

    }

    /**
     * Set the response
     * @param mixed $id
     * @param string $message
     * @param int $responseCode
     */
    public function setResponse($id, $message,$responseCode, $showMessage = false)
    {
        if($showMessage){
            Tools::displayError($message);
            Tools::redirect('index.php?controller=order&step=1');
        }
        $responseMessage = ['id'=>$id,
                          'message'=>$message,
                          'module'=>"Bambora Checkout Prestashop",
                          'version'=>Bambora::MODULE_VERSION];
        $messageJson = json_encode($responseMessage);
        header($messageJson,true,$responseCode);
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

}