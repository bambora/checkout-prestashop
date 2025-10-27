<?php

abstract class BaseAction extends ModuleFrontController
{
    /** @var Bambora */
    private $bamboraModule;

    public function __construct()
    {
        parent::__construct();
        $this->bamboraModule = $this->module;
    }

    /**
     * Validate the callback
     *
     * @param string &$message
     * @param mixed $cart
     *
     * @return bool
     */
    protected function validateAction(&$message, &$cart)
    {
        if (!Tools::getIsset('txnid')) {
            $message = 'No GET(txnid) was supplied to the system!';

            return false;
        }

        $id_cart = Tools::getValue('orderid');

        if (!isset($id_cart)) {
            $message = 'No GET(orderid) was supplied to the system!';

            return false;
        }

        $cart = new Cart($id_cart);

        if (!isset($cart)) {
            $message = 'Please provide a valid orderid';

            return false;
        }

        $storeMd5 = Configuration::get('BAMBORA_MD5KEY');
        if (!empty($storeMd5)) {
            $accept_params = Tools::getAllValues();
            $var = '';
            foreach ($accept_params as $key => $value) {
                if ($key == 'hash') {
                    break;
                }
                $var .= $value;
            }

            $storeHash = md5($var . $storeMd5);
            if ($storeHash != Tools::getValue('hash')) {
                $message = 'Callback Validation failed - Please check the module configuration';

                return false;
            }
        }

        return true;
    }

    /**
     * Process Action
     *
     * @param bool $isPaymentRequest
     * @param mixed $cart
     * @param string $callbackOrigin
     * @param mixed $responseCode
     *
     * @return mixed
     */
    protected function processAction($isPaymentRequest, $cart, $callbackOrigin, &$responseCode)
    {
        $lockFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
                        'callback_lock_' . md5(Tools::getValue('txnid')) .
                        '.lock';
        $lockFileHandle = $this->lockCallback($lockFileName);

        try {
            if ($cart->orderExists() && !$isPaymentRequest) {
                $responseCode = 200;

                return 'Order was already Created';
            }

            if (!isset($lockFileHandle)) {
                $message = "Unable to acquire lock for {$callbackOrigin} after maximum retries";
                PrestaShopLogger::addLog($message, 1, '0', $this->module->name, $this->module->id, true);

                return $message;
            }
            $transactionId = Tools::getValue('txnid');
            $getTransactionResponse = BamboraApiHelper::getTransaction($transactionId);
            if (!isset($getTransactionResponse) || !$getTransactionResponse->meta->result) {
                $message = 'An unknown error occurred';
                if (isset($getTransactionResponse)) {
                    $message = $callbackOrigin === 'callback'
                        ? $getTransactionResponse->meta->message->merchant
                        : $getTransactionResponse->meta->message->enduser;
                }

                return $message;
            }

            if ($isPaymentRequest) {
                $id_order = Order::getIdByCartId($cart->id);
                $order = new Order($id_order);
                $payments = $order->getOrderPayments();
                foreach ($payments as $payment) {
                    if (!empty($payment->transaction_id)) {
                        $message = 'Payment Request Callback was already made';

                        return $message;
                    }
                }
            }
            $transaction = $getTransactionResponse->transaction;

            $currencyCode = $transaction->currency->code;
            $currencyId = Currency::getIdByIsoCode($currencyCode);
            $paymentType = '';
            if (isset($transaction->information->paymenttypes[0])) {
                $paymentType = $transaction->information->paymenttypes[0]->displayname;
            }
            $truncatedCardNumber = '';
            if (isset($transaction->information->primaryaccountnumbers[0])) {
                $truncatedCardNumber = $transaction->information->primaryaccountnumbers[0]->number;
            }
            $acquirerReference = '';
            if (isset($transaction->information->acquirerreferences[0])) {
                $acquirerReference = $transaction->information->acquirerreferences[0]->reference;
            }

            $extraVars = [
                'TransactionId' => $transactionId,
                'PaymentType' => $paymentType,
                'CardNumber' => $truncatedCardNumber,
            ];

            $minorUnits = $transaction->currency->minorunits;
            $amountInMinorUnits = $transaction->total->authorized;
            $feeAmountInMinorUnits = $transaction->total->feeamount;
            $transactionfee = $feeAmountInMinorUnits > 0 ? BamboraCurrencyHelper::convertPriceFromMinorUnits(
                $feeAmountInMinorUnits,
                $minorUnits
            ) : 0;
            $totalAmount = BamboraCurrencyHelper::convertPriceFromMinorUnits(
                $amountInMinorUnits,
                $minorUnits
            );
            $amountWithoutFee = $totalAmount - $transactionfee;

            $paymentMethod = $this->module->displayName . ' (' . $paymentType . ')';
            $id_cart = $cart->id;

            if (!$isPaymentRequest) {
                try {
                    $orderMessage = "Validation done via: {$callbackOrigin}";
                    $this->bamboraModule->validateOrder(
                        (int) $id_cart,
                        Configuration::get('PS_OS_PAYMENT'),
                        $amountWithoutFee,
                        $paymentMethod,
                        $orderMessage,
                        $extraVars,
                        $currencyId,
                        false,
                        $cart->secure_key
                    );
                } catch (Exception $ex) {
                    $message = 'Prestashop threw an exception on validateOrder: ' . $ex->getMessage();
                    $responseCode = 500;

                    return $message;
                }
            }

            $id_order = Order::getIdByCartId($id_cart);
            $order = new Order($id_order);

            if ($isPaymentRequest) {
                $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
            }

            $payment = $order->getOrderPayments();
            $payment[0]->transaction_id = $transactionId;
            $payment[0]->amount = $totalAmount;
            $payment[0]->card_number = $truncatedCardNumber;
            $payment[0]->card_brand = $paymentType;
            $payment[0]->save();
            if ($feeAmountInMinorUnits > 0) {
                if (Configuration::get('BAMBORA_ADDFEETOSHIPPING')) {
                    $order->total_paid += $transactionfee;
                    $order->total_paid_tax_incl += $transactionfee;
                    $order->total_paid_tax_excl += $transactionfee;
                    $order->total_paid_real += $transactionfee;
                    $order->total_shipping += $transactionfee;
                    $order->total_shipping_tax_incl += $transactionfee;
                    $order->total_shipping_tax_excl += $transactionfee;
                    $order->save();

                    $invoice = new OrderInvoice($order->invoice_number);
                    if (isset($invoice->id)) {
                        $invoice->total_paid_tax_incl += $transactionfee;
                        $invoice->total_paid_tax_excl += $transactionfee;
                        $invoice->total_shipping_tax_incl += $transactionfee;
                        $invoice->total_shipping_tax_excl += $transactionfee;
                        $invoice->save();
                    }
                }
            }
            $message = $isPaymentRequest ? 'Payment Added to Payment Request Order' : 'Order Created';
            $responseCode = 200;
        } catch (Exception $e) {
            $responseCode = 500;
            $message = "Action Failed: {$e->getMessage()}";
        } finally {
            $this->unlockCallback($lockFileName, $lockFileHandle);
        }

        return $message;
    }

    /**
     * Create error log Message
     *
     * @param mixed $message
     * @param mixed $cart
     *
     * @return void
     */
    protected function createErrorLogMessage($message, $severity = 3, $cart = null)
    {
        $result = '';
        if (isset($cart)) {
            $invoiceAddress = new Address((int) $cart->id_address_invoice);
            $customer = new Customer((int) $cart->id_customer);
            $phoneNumber = BamboraCommonHelper::getPhoneNumberByAddress($invoiceAddress);
            $personString = "Name: {$invoiceAddress->firstname}{$invoiceAddress->lastname}
                            Phone: {$phoneNumber} 
                            Mail: {$customer->email} - ";
            $result = $personString;
        }
        $result .= "An payment error occured: {$message}";
        PrestaShopLogger::addLog($result, $severity);
    }

    /**
     * Create a file lock for a callback
     *
     * @param string $lockFileName
     *
     * @return bool|resource|null
     */
    protected function lockCallback($lockFileName)
    {
        $maxRetries = 10;
        $retryDelay = 1000000; // 1-sec

        $lockAcquired = false;
        $retryCount = 0;

        while (!$lockAcquired && $retryCount < $maxRetries) {
            $fileHandle = @fopen($lockFileName, 'w');

            if ($fileHandle !== false) {
                $lockAcquired = flock($fileHandle, LOCK_EX | LOCK_NB);

                if (!$lockAcquired) {
                    fclose($fileHandle);
                    usleep($retryDelay);
                    ++$retryCount;
                }
            } else {
                usleep($retryDelay);
                ++$retryCount;
            }
        }

        if (!$lockAcquired) {
            return null;
        }

        return $fileHandle;
    }

    /**
     * Remove file lock for a callback
     *
     * @param string $lockFileName
     * @param bool|resource $fileHandle
     *
     * @return void
     */
    protected function unlockCallback($lockFileName, $fileHandle)
    {
        flock($fileHandle, LOCK_UN);
        fclose($fileHandle);

        if (file_exists($lockFileName)) {
            unlink($lockFileName);
        }
    }
}
