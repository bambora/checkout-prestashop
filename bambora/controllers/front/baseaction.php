<?php
/**
 * Bambora Online 2017
 *
 * @author    Bambora Online
 * @copyright Bambora (http://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

abstract class BaseAction extends ModuleFrontController
{
    /**
     * Validate the callback
     *
     * @param string &$message
     * @return boolean
     */
    protected function validateAction(&$message, &$cart)
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
     *
     * @param mixed $cart
     * @param mixed $responseCode
     * @return mixed
     */
    protected function processAction($cart, &$responseCode)
    {
        try {
            if (!$cart->orderExists()) {
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

                $bamboraTransactionInfo = $bamboraTransaction['transaction'];

                $currencyCode = $bamboraTransactionInfo["currency"]["code"];
                $currencyid = Currency::getIdByIsoCode($currencyCode);
                $paymentType = $bamboraTransactionInfo['information']['paymenttypes'][0]['displayname'];
                $truncatedCardNumber = $bamboraTransactionInfo['information']['primaryaccountnumbers'][0]['number'];

                $mailVars = array('TransactionId'=>$transactionId,
                                  'PaymentType'=>$paymentType,
                                  'CardNumber'=>$truncatedCardNumber);

                $minorUnits = $bamboraTransactionInfo["currency"]["minorunits"];
                $amountInMinorUnits = $bamboraTransactionInfo["total"]["authorized"];
                $feeAmountInMinorUnits = $bamboraTransactionInfo["total"]["feeamount"];
                if ($feeAmountInMinorUnits > 0) {
                    $amountInMinorUnits = $amountInMinorUnits - $feeAmountInMinorUnits;
                }

                $amount = BamboraCurrency::convertPriceFromMinorUnits($amountInMinorUnits, $minorUnits);

                $paymentMethod = $this->module->displayName . ' ('. $paymentType .')';
                $id_cart = $cart->id;

                if ($this->module->validateOrder((int)$id_cart, Configuration::get('PS_OS_PAYMENT'), $amount, $paymentMethod, null, $mailVars, $currencyid, false, $cart->secure_key)) {
                    $id_order = Order::getOrderByCartId($id_cart);
                    $order = new Order($id_order);
                    $payment = $order->getOrderPayments();
                    $payment[0]->transaction_id = $transactionId;
                    $payment[0]->amount = $amount;
                    $payment[0]->card_number = $truncatedCardNumber;
                    $payment[0]->card_brand = $paymentType;

                    if ($feeAmountInMinorUnits > 0) {
                        if (Configuration::get('BAMBORA_ADDFEETOSHIPPING')) {
                            $transactionfee = BamboraCurrency::convertPriceFromMinorUnits($feeAmountInMinorUnits, $minorUnits);

                            $order->total_paid = $order->total_paid + $transactionfee;
                            $order->total_paid_tax_incl = $order->total_paid_tax_incl + $transactionfee;
                            $order->total_paid_tax_excl = $order->total_paid_tax_excl + $transactionfee;
                            $order->total_paid_real = $order->total_paid_real + $transactionfee;
                            $order->total_shipping = $order->total_shipping + $transactionfee;
                            $order->total_shipping_tax_incl = $order->total_shipping_tax_incl + $transactionfee;
                            $order->total_shipping_tax_excl = $order->total_shipping_tax_excl + $transactionfee;
                            $order->save();

                            $invoice = $payment[0]->getOrderInvoice($order->id);
                            $invoice->total_paid_tax_incl = $invoice->total_paid_tax_incl + $transactionfee;
                            $invoice->total_paid_tax_excl = $invoice->total_paid_tax_excl + $transactionfee;
                            $invoice->total_shipping_tax_incl = $invoice->total_shipping_tax_incl + $transactionfee;
                            $invoice->total_shipping_tax_excl = $invoice->total_shipping_tax_excl + $transactionfee;

                            $invoice->save();
                        }
                    }
                    $payment[0]->save();
                    $message = "Order created";
                    $responseCode = 200;
                } else {
                    $message = "Prestashop could not validate order";
                    $responseCode = 500;
                }
            } else {
                $message = "Order was already Created";
                $responseCode = 200;
            }
        } catch (Exception $e) {
            $responseCode = 500;
            $message = "Action Failed: " .$e->getMessage();
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
