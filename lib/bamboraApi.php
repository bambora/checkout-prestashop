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
        $expresscheckoutresponse = $this ->_callRestService($serviceUrl, $jsonData, "POST");

        return json_decode($expresscheckoutresponse, true);
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
        $serviceUrl = "{$this->dataEndpoint}/responsecodes/".$source."/".$actionCode;
        $responseCodeData = $this ->_callRestService($serviceUrl, null, "GET");

        return json_decode($responseCodeData, true);
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
        if (isset($invoiceLines)){
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
        if (isset($invoiceLines)){
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
        if ($availablePaymentTypesResjson['meta']['result'] == true) {
            foreach ($availablePaymentTypesResjson['paymentcollections'] as $payment) {
                foreach ($payment['paymentgroups'] as $card) {
                    //enshure unique id:
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
            'Content-Length: '.strlen(@$jsonData),
            'Accept: application/json',
            'Authorization: '.$this->apiKey,
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
