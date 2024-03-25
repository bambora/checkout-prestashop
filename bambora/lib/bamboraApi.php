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

include('bamboraModels.php');
include('bamboraEndpoints.php');

class BamboraApi
{
    /**
     * Api Key
     * @var string
     */
    private $apiKey;

    /**
     * Checkout Api endpoint
     * @var string
     */
    private $checkoutEndpoint;

    /**
     * Transaction Api endpoint
     * @var string
     */
    private $transactionEndpoint;

    /**
     * Merchant Api endpoint
     * @var string
     */
    private $merchantEndpoint;

    /**
     * Assets endpoint
     * @var string
     */
    private $assetsEndpoint;

    /**
     * Data endpoint
     * @var string
     */
    private $dataEndpoint;

    /**
     * Login endpoint
     * @var string
     */
    private $loginEndpoint;

    /**
     * __construct
     *
     * @param mixed $apiKey
     */
    public function __construct($apiKey = "")
    {
        $this->apiKey = $apiKey;
        $this->checkoutEndpoint = BamboraEndpointConfig::getCheckoutEndpoint();
        $this->transactionEndpoint = BamboraEndpointConfig::getTransactionEndpoint();
        $this->merchantEndpoint = BamboraEndpointConfig::getMerchantEndpoint();
        $this->assetsEndpoint = BamboraEndpointConfig::getCheckoutAssets();
        $this->dataEndpoint = BamboraEndpointConfig::getDataEndpoint();
        $this->loginEndpoint = BamboraEndpointConfig::getLoginEndpoint();
    }

    /**
     * getcheckoutresponse
     *
     * @param mixed $bamboracheckoutrequest
     * @return mixed
     */
    public function getcheckoutresponse($bamboracheckoutrequest)
    {
        $serviceUrl = "{$this->checkoutEndpoint}/checkout";

        $jsonData = json_encode($bamboracheckoutrequest);
        $expresscheckoutresponse = $this->_callRestService(
            $serviceUrl,
            $jsonData,
            "POST"
        );

        return json_decode($expresscheckoutresponse, true);
    }


    /**
     * capture
     *
     * @param mixed $transactionid
     * @param mixed $amount
     * @param mixed $currency
     * @param mixed $invoiceLines
     * @return mixed
     */
    public function capture($transactionid, $amount, $currency, $invoiceLines = null)
    {
        $serviceUrl = "{$this->transactionEndpoint}/transactions/{$transactionid}/capture";

        $data = array();
        $data["amount"] = $amount;
        $data["currency"] = $currency;
        if (isset($invoiceLines)) {
            $data["invoicelines"] = $invoiceLines;
        }
        $jsonData = json_encode($data);

        $result = $this->_callRestService($serviceUrl, $jsonData, "POST");
        return json_decode($result, true);
    }

    /**
     * credit
     *
     * @param mixed $transactionid
     * @param mixed $amount
     * @param mixed $currency
     * @param mixed $invoiceLines
     * @return mixed
     */
    public function credit($transactionid, $amount, $currency, $invoiceLines = null)
    {
        $serviceUrl = "{$this->transactionEndpoint}/transactions/{$transactionid}/credit";

        $data = array();
        $data["amount"] = $amount;
        $data["currency"] = $currency;
        if (isset($invoiceLines)) {
            $data["invoicelines"] = $invoiceLines;
        }


        $jsonData = json_encode($data);

        $result = $this->_callRestService($serviceUrl, $jsonData, "POST");
        return json_decode($result, true);
    }

    /**
     * delete
     *
     * @param mixed $transactionid
     * @return mixed
     */
    public function delete($transactionid)
    {
        $serviceUrl = "{$this->transactionEndpoint}/transactions/{$transactionid}/delete";

        $result = $this->_callRestService($serviceUrl, null, "POST");
        return json_decode($result, true);
    }

    /**
     * gettransaction
     *
     * @param mixed $transactionid
     * @return mixed
     */
    public function gettransaction($transactionid)
    {
        $serviceUrl = "{$this->merchantEndpoint}/transactions/{$transactionid}";

        $result = $this->_callRestService($serviceUrl, null, "GET");
        return json_decode($result, true);
    }

    /**
     * gettransactionoperations
     *
     * @param mixed $transactionid
     * @return mixed
     */
    public function gettransactionoperations($transactionid)
    {
        $serviceUrl = "{$this->merchantEndpoint}/transactions/{$transactionid}/transactionoperations";

        $result = $this->_callRestService($serviceUrl, null, "GET");
        return json_decode($result, true);
    }

    /**
     * getresponsecodedata
     *
     * @param string $source
     * @param string $actionCode
     * @return mixed
     */
    public function getresponsecodedata($source, $actionCode)
    {
        $serviceUrl = "{$this->dataEndpoint}/responsecodes/" . $source . "/" . $actionCode;
        $responseCodeData = $this->_callRestService($serviceUrl, null, "GET");

        return json_decode($responseCodeData, true);
    }

    /**
     * getPaymentTypes
     *
     * @param mixed $currency
     * @param mixed $amount
     * @return mixed
     */
    public function getPaymentTypes($currency, $amount)
    {
        $serviceUrl = "{$this->merchantEndpoint}/paymenttypes?currency={$currency}&amount={$amount}";

        $result = $this->_callRestService($serviceUrl, null, "GET");
        return $result;
    }


    /**
     * Check if the credentials for the API are valid
     *
     * @return boolean
     */
    public function testIfValidCredentials()
    {
        $merchantnumber = Configuration::get('BAMBORA_MERCHANTNUMBER');
        if (empty($merchantnumber)) { // Do not even try to contact rest service if merchant number is not even set.
            return false;
        }
        $serviceUrl = "{$this->loginEndpoint}/merchant/functionpermissionsandfeatures";
        $result = $this->_callRestService($serviceUrl, null, "GET");
        $decoded = json_decode($result);
        if (!isset($decoded->meta->result) || !$decoded->meta->result) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $jsonData
     * @return mixed
     */

    public function checkIfMerchantHasPaymentRequestCreatePermissions()
    {
        $serviceUrl = "{$this->loginEndpoint}/merchant/functionpermissionsandfeatures";
        $result = $this->_callRestService($serviceUrl, null, "GET");
        $decoded = json_decode($result);
        if (isset($decoded->meta->result) && $decoded->meta->result) {
            $functionpermissions = $decoded->functionpermissions;
            foreach ($functionpermissions as $value) {
                if ($value->name == "function#expresscheckoutservice#v1#createpaymentrequest") {
                    return true;
                }
            }
        }

        return false;
    }

    /*
     * Create a PaymentRequest
     *
     * @return mixed
     */
    public function createPaymentRequest($jsonData)
    {
        $serviceUrl = "{$this->checkoutEndpoint}/paymentrequests";
        $result = $this->_callRestService($serviceUrl, $jsonData, "POST");
        return json_decode($result, true);
    }

    /**
     * Get a PaymentRequest
     * @param string $paymentRequestId
     * @return mixed
     */
    public function getPaymentRequest($paymentRequestId)
    {
        $serviceUrl = "{$this->checkoutEndpoint}/paymentrequests/{$paymentRequestId}";
        $result = $this->_callRestService($serviceUrl, null, "GET");
        return json_decode($result, true);
    }

    /**
     * Send PaymentRequest email
     * @param string $paymentRequestId , $jsonData
     * @return mixed
     */
    public function sendPaymentRequestEmail($paymentRequestId, $jsonData)
    {
        $serviceUrl = "{$this->checkoutEndpoint}/paymentrequests/{$paymentRequestId}/email-notifications";
        $result = $this->_callRestService($serviceUrl, $jsonData, "POST");
        return json_decode($result, true);
    }

    /**
     * Delete a PaymentRequest
     * @param string $paymentRequestId
     * @return mixed
     */
    public function deletePaymentRequest($paymentRequestId)
    {
        $serviceUrl = "{$this->checkoutEndpoint}/paymentrequests/{$paymentRequestId}";
        $result = $this->_callRestService($serviceUrl, null, "DELETE");
        return json_decode($result, true);
    }


    public function listPaymentRequests($exclusivestartkey, $pagesize, $filters)
    {
        $serviceUrl = "{$this->checkoutEndpoint}/paymentrequests/?exclusivestartkey={$exclusivestartkey}&pagesize={$pagesize}&filters={$filters}";
        $result = $this->_callRestService($serviceUrl, null, "GET");
        return json_decode($result, true);
    }


    /**
     * getAvaliablePaymentcardidsForMerchant
     *
     * @param mixed $currency
     * @param mixed $amount
     * @return array
     */
    public function getAvaliablePaymentcardidsForMerchant($currency, $amount)
    {
        $res = array();
        $serviceRes = $this->getPaymentTypes($currency, $amount);

        $availablePaymentTypesResjson = json_decode($serviceRes, true);
        if (isset($availablePaymentTypesResjson['meta']['result']) && $availablePaymentTypesResjson['meta']['result'] == true) {
            foreach ($availablePaymentTypesResjson['paymentcollections'] as $payment) {
                foreach ($payment['paymentgroups'] as $card) {
                    //ensure unique id:
                    $cardname = $card['id'];
                    $res[$cardname] = $card['id'];
                }
            }

            ksort($res);
        }
        return $res;
    }

    /**
     * _callRestService
     *
     * @param mixed $serviceUrl
     * @param mixed $jsonData
     * @param mixed $postOrGet
     * @return mixed
     */
    private function _callRestService($serviceUrl, $jsonData, $postOrGet)
    {
        $headers = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen(@$jsonData),
            'Accept: application/json',
            'Authorization: ' . $this->apiKey,
            'X-EPay-System: ' . BamboraHelpers::getModuleHeaderInfo()
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $postOrGet);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($curl, CURLOPT_URL, $serviceUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);


        $result = curl_exec($curl);
        return $result;
    }
}