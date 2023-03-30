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

abstract class BaseAction extends ModuleFrontController
{
    /**
     * Validate the callback
     * @param string &$message
     * @param mixed $cart
     * @return boolean
     */
    protected function validateAction( &$message, &$cart  )
    {
        if (!Tools::getIsset("txnid")) {
            $message = "No GET(txnid) was supplied to the system!";
            return false;
        }

        $id_cart = Tools::getValue("orderid");

        if (!isset($id_cart)) {
            $message = "No GET(orderid) was supplied to the system!";
            return false;
        }

        $cart = new Cart($id_cart);

        if (!isset($cart)) {
            $message =  "Please provide a valid orderid";
            return false;
        }

        $storeMd5 = Configuration::get('BAMBORA_MD5KEY');
        if (!empty($storeMd5)) {
            $accept_params = Tools::getAllValues();
            $var = "";
            foreach ($accept_params as $key => $value) {
                if ($key == "hash") {
                    break;
                }
                $var .= $value;
            }

            $storeHash = md5($var . $storeMd5);
            if ($storeHash != Tools::getValue("hash")) {
                $message = "Hash validation failed - Please check your MD5 key";
                return false;
            }
        }

        return true;
    }

    /**
     * Process Action
     * @param bool $isPaymentRequest
     * @param mixed $cart
     * @param mixed $responseCode
     * @return mixed
     */
    protected function processAction( $isPaymentRequest, $cart, &$responseCode)
    {
        try {
            if (!$cart->orderExists() || $isPaymentRequest) {
                $apiKey = BamboraHelpers::generateApiKey();
                $api = new BamboraApi($apiKey);
                $transactionId = Tools::getValue("txnid");
                $bamboraTransaction = $api->gettransaction($transactionId);

                if (!isset($bamboraTransaction)) {
                    $message = "Transaction Information is null";
                    return $message;
                }

                if (!$bamboraTransaction['meta']['result']) {
                    $message = $this->isCallback ?
                        $bamboraTransaction['meta']['message']['merchant'] :
                        $bamboraTransaction['meta']['message']['enduser'];

                    return $message;
                }

                if ($isPaymentRequest) {
                    $id_order = Order::getIdByCartId($cart->id);
                    $order = new Order($id_order);
                    $payments = $order->getOrderPayments();
                    foreach ($payments as $payment) {
                        if (!empty($payment->transaction_id)) {
                            $message = "Payment Request Callback was already made";
                            return $message;
                        }
                    }
                }

                $bamboraTransactionInfo = $bamboraTransaction['transaction'];

                $currencyCode = $bamboraTransactionInfo["currency"]["code"];
                $currencyId = Currency::getIdByIsoCode($currencyCode);
                $paymentType = null;
                if (isset($bamboraTransactionInfo['information']['paymenttypes'][0])) {
                    $paymentType = $bamboraTransactionInfo['information']['paymenttypes'][0]['displayname'];
                }
                $truncatedCardNumber = "";
                if (isset($bamboraTransactionInfo['information']['primaryaccountnumbers'][0])) {
                    $truncatedCardNumber = $bamboraTransactionInfo['information']['primaryaccountnumbers'][0]['number'];
                }
                $acquirerReference = null;
                if (isset($bamboraTransactionInfo['information']['acquirerreferences'][0])) {
                    $acquirerReference = $bamboraTransactionInfo['information']['acquirerreferences'][0]['reference'];
                }


                $mailVars = array('TransactionId' => $transactionId,
                    'PaymentType' => $paymentType,
                    'CardNumber' => $truncatedCardNumber,
                    'AcquirerReference' => $acquirerReference
                );

                $minorUnits = $bamboraTransactionInfo["currency"]["minorunits"];
                $amountInMinorUnits = $bamboraTransactionInfo["total"]["authorized"];
                $feeAmountInMinorUnits = $bamboraTransactionInfo["total"]["feeamount"];
                $transactionfee = $feeAmountInMinorUnits > 0 ? BamboraCurrency::convertPriceFromMinorUnits($feeAmountInMinorUnits, $minorUnits) : 0;
                $totalAmount = BamboraCurrency::convertPriceFromMinorUnits($amountInMinorUnits, $minorUnits);
                $amountWithoutFee = $totalAmount - $transactionfee;

                $paymentMethod = $this->module->displayName . ' (' . $paymentType . ')';
                $id_cart = $cart->id;

                if (!$isPaymentRequest) {
                    try {
                        $this->module->validateOrder((int)$id_cart,
                            Configuration::get('PS_OS_PAYMENT'),
                            $amountWithoutFee,
                            $paymentMethod, null, $mailVars, $currencyId, false, $cart->secure_key);
                    } catch (Exception $ex) {
                        $message = 'Prestashop threw an exception on validateOrder: ' . $ex->getMessage();
                        $responseCode = 500;
                        return $message;
                    }
                    $id_order = Order::getIdByCartId($id_cart);
                    $order = new Order($id_order);
                } else {
                    $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
                }


                $payment = $order->getOrderPayments();
                $payment[0]->transaction_id = $transactionId;
                $payment[0]->acquirer_reference = $acquirerReference;
                $payment[0]->amount = $totalAmount;
                $payment[0]->card_number = $truncatedCardNumber;
                $payment[0]->card_brand = $paymentType;
                $payment[0]->save();
                if ($feeAmountInMinorUnits > 0) {
                    if (Configuration::get('BAMBORA_ADDFEETOSHIPPING')) {
                        $order->total_paid = $order->total_paid + $transactionfee;
                        $order->total_paid_tax_incl = $order->total_paid_tax_incl + $transactionfee;
                        $order->total_paid_tax_excl = $order->total_paid_tax_excl + $transactionfee;
                        $order->total_paid_real = $order->total_paid_real + $transactionfee;
                        $order->total_shipping = $order->total_shipping + $transactionfee;
                        $order->total_shipping_tax_incl = $order->total_shipping_tax_incl + $transactionfee;
                        $order->total_shipping_tax_excl = $order->total_shipping_tax_excl + $transactionfee;
                        $order->save();

                        $invoice = new OrderInvoice($order->invoice_number);
                        if (isset($invoice->id)) {
                            $invoice->total_paid_tax_incl = $invoice->total_paid_tax_incl + $transactionfee;
                            $invoice->total_paid_tax_excl = $invoice->total_paid_tax_excl + $transactionfee;
                            $invoice->total_shipping_tax_incl = $invoice->total_shipping_tax_incl + $transactionfee;
                            $invoice->total_shipping_tax_excl = $invoice->total_shipping_tax_excl + $transactionfee;
                            $invoice->save();
                        }
                    }
                }
                if ($isPaymentRequest){
                    $message = "Payment Added to Payment Request Order";
                }else{
                    $message = "Order Created";
                }
                $responseCode = 200;

            } else {
                $message = "Order was already Created";
                $responseCode = 200;
            }
        } catch (Exception $e) {
            $responseCode = 500;
            $message = "Action Failed: " . $e->getMessage();
        }

        return $message;
    }

    /**
     * Create error log Message
     *
     * @param mixed $message
     * @param mixed $cart
     * @return string
     */
    protected function createLogMessage($message, $severity = 3, $cart = null)
    {
        $result = "";
        if (isset($cart)) {
            $invoiceAddress = new Address((int)$cart->id_address_invoice);
            $customer = new Customer((int)$cart->id_customer);
            $phoneNumber = $this->module->getPhoneNumber($invoiceAddress);
            $personString = "Name: {$invoiceAddress->firstname}{$invoiceAddress->lastname} Phone: {$phoneNumber} Mail: {$customer->email} - ";
            $result = $personString;
        }
        $result .= "An payment error occured: " . $message;
        if ($this->module->getPsVersion() === Bambora::V15) {
            Logger::addLog($result, $severity);
        } else {
            PrestaShopLogger::addLog($result, $severity);
        }
    }
}
