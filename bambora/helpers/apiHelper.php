<?php

class BamboraApiHelper
{
    public const TRANSACTION_ENDPOINT = 'https://transaction-v1.api-eu.bambora.com';
    public const MERCHANT_ENDPOINT = 'https://merchant-v1.api-eu.bambora.com';
    public const CHECKOUT_ENDPOINT = 'https://api.v1.checkout.bambora.com';
    public const DATA_ENDPOINT = 'https://data-v1.api-eu.bambora.com';
    public const LOGIN_ENDPOINT = 'https://login-v1.api-eu.bambora.com';
    public const STATIC_ASSETS_ENDPOINT = 'https://static.bambora.com';
    public const MERCHANT_FRONTEND_ENDPOINT = 'https://merchant.bambora.com';

    /**
     * Get the url for the Checkout SDK Web
     *
     * @return string
     */
    public static function getCheckoutSDKWebUrl()
    {
        $endpoint = self::STATIC_ASSETS_ENDPOINT;

        return "{$endpoint}/checkout-sdk-web/latest/checkout-sdk-web.min.js";
    }

    /**
     * API Get Checkout Response
     *
     * @param BamboraCheckoutRequest $request
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function getCheckoutResponse($request, $apiKey = null)
    {
        $endpoint = self::CHECKOUT_ENDPOINT;
        $serviceUrl = "{$endpoint}/checkout";
        $jsonData = json_encode($request);

        return self::callRestService(
            $serviceUrl,
            $jsonData,
            'POST',
            $apiKey
        );
    }

    /**
     * API Capture
     *
     * @param string $transactionId
     * @param BamboraCaptureRequest $request
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function capture($transactionId, $request, $apiKey = null)
    {
        $endpoint = self::TRANSACTION_ENDPOINT;
        $serviceUrl = "{$endpoint}/transactions/{$transactionId}/capture";
        $jsonData = json_encode($request);

        return self::callRestService(
            $serviceUrl,
            $jsonData,
            'POST',
            $apiKey
        );
    }

    /**
     * API Credit
     *
     * @param string $transactionId
     * @param BamboraCreditRequest $request
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function credit($transactionId, $request, $apiKey = null)
    {
        $endpoint = self::TRANSACTION_ENDPOINT;
        $serviceUrl = "{$endpoint}/transactions/{$transactionId}/credit";
        $jsonData = json_encode($request);

        return self::callRestService(
            $serviceUrl,
            $jsonData,
            'POST',
            $apiKey
        );
    }

    /**
     * API Delete
     *
     * @param string $transactionId
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function delete($transactionId, $apiKey = null)
    {
        $endpoint = self::TRANSACTION_ENDPOINT;
        $serviceUrl = "{$endpoint}/transactions/{$transactionId}/delete";

        return self::callRestService(
            $serviceUrl,
            null,
            'POST',
            $apiKey
        );
    }

    /**
     * API Get Transaction
     *
     * @param string $transactionId
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function getTransaction($transactionId, $apiKey = null)
    {
        $endpoint = self::MERCHANT_ENDPOINT;
        $serviceUrl = "{$endpoint}/transactions/{$transactionId}";

        return self::callRestService(
            $serviceUrl,
            null,
            'GET',
            $apiKey
        );
    }

    /**
     * API Get Transaction Operations
     *
     * @param string $transactionId
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function getTransactionOperations($transactionId, $apiKey = null)
    {
        $endpoint = self::MERCHANT_ENDPOINT;
        $serviceUrl = "{$endpoint}/transactions/{$transactionId}/transactionoperations";

        return self::callRestService(
            $serviceUrl,
            null,
            'GET',
            $apiKey
        );
    }

    /**
     * API Get Response Code Data
     *
     * @param string $source
     * @param string $actionCode
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function getResponseCodeData($source, $actionCode, $apiKey = null)
    {
        $endpoint = self::DATA_ENDPOINT;
        $serviceUrl = "{$endpoint}/responsecodes/{$source}/{$actionCode}";

        return self::callRestService(
            $serviceUrl,
            null,
            'GET',
            $apiKey
        );
    }

    /**
     * API Get Payment Types
     *
     * @param string $currency
     * @param string|int $amount
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function getPaymentTypes($currency, $amount, $apiKey = null)
    {
        $endpoint = self::MERCHANT_ENDPOINT;
        $serviceUrl = "{$endpoint}/paymenttypes?currency={$currency}&amount={$amount}";

        return self::callRestService(
            $serviceUrl,
            null,
            'GET',
            $apiKey
        );
    }

    /**
     * API Check if the credentials for the API are valid
     *
     * @param string $merchantNumber
     * @param string $apiKey
     *
     * @return bool
     */
    public static function isValidCredentials($merchantNumber, $apiKey = null)
    {
        if (empty($merchantNumber)) {
            return false;
        }

        $endpoint = self::LOGIN_ENDPOINT;
        $serviceUrl = "{$endpoint}/merchant/functionpermissionsandfeatures";

        $response = self::callRestService(
            $serviceUrl,
            null,
            'GET',
            $apiKey
        );

        return isset($response) && $response->meta->result;
    }

    /**
     * API Has Payment Request Create Permissions
     *
     * @param string $apiKey
     *
     * @return bool
     */
    public static function hasPaymentRequestCreatePermissions($apiKey = null)
    {
        $endpoint = self::LOGIN_ENDPOINT;
        $serviceUrl = "{$endpoint}/merchant/functionpermissionsandfeatures";

        $response = self::callRestService(
            $serviceUrl,
            null,
            'GET',
            $apiKey
        );

        if (isset($response) && $response->meta->result) {
            $functionpermissions = $response->functionpermissions;
            foreach ($functionpermissions as $value) {
                if ($value->name == 'function#expresscheckoutservice#v1#createpaymentrequest') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * API Create a Payment Request
     *
     * @param BamboraCheckoutPaymentRequest $request
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function createPaymentRequest($request, $apiKey = null)
    {
        $endpoint = self::CHECKOUT_ENDPOINT;
        $serviceUrl = "{$endpoint}/paymentrequests";
        $jsonData = json_encode($request);

        return self::callRestService(
            $serviceUrl,
            $jsonData,
            'POST',
            $apiKey
        );
    }

    /**
     * API Get Payment Request
     *
     * @param string $paymentRequestId
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function getPaymentRequest($paymentRequestId, $apiKey = null)
    {
        $endpoint = self::CHECKOUT_ENDPOINT;
        $serviceUrl = "{$endpoint}/paymentrequests/{$paymentRequestId}";

        return self::callRestService(
            $serviceUrl,
            null,
            'GET',
            $apiKey
        );
    }

    /**
     * API Send Payment Request Email
     *
     * @param string $paymentRequestId
     * @param BamboraCheckoutPaymentRequestEmailRecipient $request
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function sendPaymentRequestEmail($paymentRequestId, $request, $apiKey = null)
    {
        $endpoint = self::CHECKOUT_ENDPOINT;
        $serviceUrl = "{$endpoint}/paymentrequests/{$paymentRequestId}/email-notifications";
        $jsonData = json_encode($request);

        return self::callRestService(
            $serviceUrl,
            $jsonData,
            'POST',
            $apiKey
        );
    }

    /**
     * API Delete a PaymentRequest
     *
     * @param string $paymentRequestId
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function deletePaymentRequest($paymentRequestId, $apiKey = null)
    {
        $endpoint = self::CHECKOUT_ENDPOINT;
        $serviceUrl = "{$endpoint}/paymentrequests/{$paymentRequestId}";

        return self::callRestService(
            $serviceUrl,
            null,
            'DELETE',
            $apiKey
        );
    }

    /**
     * API List Payment Requests
     *
     * @param string|int $exclusiveStartKey
     * @param string|int $pageSize
     * @param string $filters
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function listPaymentRequests($exclusiveStartKey, $pageSize, $filters, $apiKey = null)
    {
        $endpoint = self::CHECKOUT_ENDPOINT;
        $serviceUrl = "{$endpoint}/paymentrequests/?exclusivestartkey={$exclusiveStartKey}
                        &pagesize={$pageSize}&filters={$filters}";

        return self::callRestService(
            $serviceUrl,
            null,
            'GET',
            $apiKey
        );
    }

    /**
     * API Get Avaliable Payment Card Ids For Merchant
     *
     * @param string $currency
     * @param string|int $amount
     * @param string $apiKey
     *
     * @return array
     */
    public static function getAvaliablePaymentCardIdsForMerchant($currency, $amount, $apiKey = null)
    {
        $res = [];
        $paymentTypeResponse = self::getPaymentTypes($currency, $amount, $apiKey);

        if (isset($paymentTypeResponse) && $paymentTypeResponse->meta->result) {
            foreach ($paymentTypeResponse->paymentcollections as $payment) {
                foreach ($payment->paymentgroups as $card) {
                    // ensure unique id:
                    $cardname = $card->id;
                    $res[$cardname] = $card->id;
                }
            }
            ksort($res);
        }

        return $res;
    }

    /**
     * API Call Rest Service
     *
     * @param string $serviceUrl
     * @param string $jsonData
     * @param string $action
     * @param string $apiKey
     *
     * @return mixed
     */
    public static function callRestService($serviceUrl, $jsonData, $action, $apiKey = null)
    {
        if (empty($apiKey)) {
            $apiKey = BamboraCommonHelper::generateApiKey();
        }

        $moduleHeader = BamboraCommonHelper::getModuleHeaderInfo();
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: {$apiKey}",
            "X-EPay-System: {$moduleHeader}",
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $action);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($curl, CURLOPT_URL, $serviceUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($curl);

        return json_decode($result);
    }
}
