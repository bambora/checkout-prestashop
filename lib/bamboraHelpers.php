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

class BamboraHelpers
{
    /**
     * Generate ApiKey
     *
     * @return string
     */
    public static function generateApiKey()
    {
        $merchant = Configuration::get('BAMBORA_MERCHANTNUMBER');
        $accessToken = Configuration::get('BAMBORA_ACCESSTOKEN');
        $secretToken = Configuration::get('BAMBORA_SECRETTOKEN');

        $combined = $accessToken . '@' . $merchant .':'. $secretToken;
        $encodedKey = base64_encode($combined);
        $apiKey = 'Basic '.$encodedKey;

        return $apiKey;
    }

    /**
     * Format Truncated Cardnumber
     *
     * @param mixed $cardnumber
     * @return mixed
     */
    public static function formatTruncatedCardnumber($cardnumber)
    {
        $wordWrapped =  wordwrap($cardnumber, 4, ' ', true);
        return  str_replace("X", "&bull;", $wordWrapped);
    }

    /**
     * Get Module Header Info
     *
     * @return string
     */
    public static function getModuleHeaderInfo()
    {
        $bamboraVersion = Bambora::MODULE_VERSION;
        $prestashopVersion = _PS_VERSION_;
        $result = 'Prestashop/' . $prestashopVersion . ' Module/' . $bamboraVersion;

        return $result;
    }

    /**
     *  Get the Card Authentication Brand Name
     *
     * @param integer $paymentGroupId
     * @return string
     */
    public static function getCardAuthenticationBrandName($paymentGroupId)
    {
        switch ($paymentGroupId) {
            case 1:
                return "Dankort Secured by Nets";
            case 2:
                return "Verified by Visa";
            case 3:
            case 4:
                return "MasterCard SecureCode";
            case 5:
                return "J/Secure";
            case 6:
                return "American Express SafeKey";
            default:
                return "3D Secure";
        }
    }

    /**
     *  Get the 3D Secure info.
     *
     * @param integer $eciLevel
     *
     * @return string
     */
    public static function get3DSecureText($eciLevel)
    {
        switch ($eciLevel) {
            case "7":
            case "00":
            case "0":
            case "07":
                return "Authentication is unsuccessful or not attempted. The credit card is either a non-3D card or card issuing bank does not handle it as a 3D transaction.";
            case "06":
            case "6":
            case "01":
            case "1":
                return "Either cardholder or card issuing bank is not 3D enrolled. 3D card authentication is unsuccessful, in sample situations as: 1. 3D Cardholder not enrolled, 2. Card issuing bank is not 3D Secure ready.";
            case "05":
            case "5":
            case "02":
            case "2":
                return "Both cardholder and card issuing bank are 3D enabled. 3D card authentication is successful.";
            default:
                return "";
        }
    }

    /**
     *  Get event Log text.
     *
     * @param array $operation
     *
     * @return array
     */
    public static function getEventText($operation)
    {
        $action = strtolower($operation['action']);
        $subAction = strtolower($operation['subaction']);
        $approved = $operation['status'] == 'approved';

        $threeDSecureBrandName = "";
        $eventInfo = array();
        $merchantLabel = "";

        $source = $operation['actionsource'];
        $actionCode = $operation['actioncode'];
        $api = new BamboraApi(BamboraHelpers::generateApiKey());
        $responseCode = $api->getresponsecodedata($source, $actionCode);

        if (isset($responseCode['responsecode'])) {
            $merchantLabel = $responseCode['responsecode']['merchantlabel'] . " - " . $source . " " . $actionCode;
        }
        if ($action === "authorize") {
            if (isset($operation['paymenttype']['id'])) {
                $threeDSecureBrandName = BamboraHelpers::getCardAuthenticationBrandName($operation['paymenttype']['id']);
            }
            // Temporary renaming for Lindorff to Walley require until implemented in Acquire
            $thirdPartyName = $operation['acquirername'];
            $thirdPartyName = strtolower($thirdPartyName) !== ("lindorff" || "collectorbank")
                ? $thirdPartyName
                : "Walley";

            switch ($subAction) {
                case "threed":
                {
                    $title = $approved ? 'Payment completed (' . $threeDSecureBrandName . ')' : 'Payment failed (' . $threeDSecureBrandName . ')';
                    $eci = $operation['eci']['value'];
                    $statusText = $approved
                        ? "completed successfully"
                        : "failed";
                    $description = "";
                    if ($eci === "7") {
                        $description = 'Authentication was either not attempted or unsuccessful. Either the card does not support' .
                            $threeDSecureBrandName . ' or the issuing bank does not handle it as a ' .
                            $threeDSecureBrandName . ' payment. Payment ' . $statusText . ' at ECI level ' . $eci;
                    }
                    if ($eci === "6") {
                        $description = 'Authentication was attempted but failed. Either cardholder or card issuing bank is not enrolled for ' .
                            $threeDSecureBrandName . '. Payment ' . $statusText . ' at ECI level ' . $eci;
                    }
                    if ($eci === "5") {
                        $description = $approved
                            ? 'Payment was authenticated at ECI level ' . $eci . ' via ' . $threeDSecureBrandName . ' and ' . $statusText
                            : 'Payment was did not authenticate via ' . $threeDSecureBrandName . ' and ' . $statusText;
                    }
                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    if (!$approved) {
                        $eventInfo['description'] = $eventInfo['description'] . '<div style="color:#E08F95">' . $merchantLabel . '</div>';
                    }
                    return $eventInfo;
                }
                case "ssl":
                {
                    $title = $approved
                        ? 'Payment completed'
                        : 'Payment failed';

                    $description = $approved
                        ? 'Payment was completed and authorized via SSL.'
                        : 'Authorization was attempted via SSL, but failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';
                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    return $eventInfo;
                }
                case "recurring":
                {
                    $title = $approved
                        ? 'Subscription payment completed'
                        : 'Subscription payment failed';

                    $description = $approved
                        ? 'Payment was completed and authorized on a subscription.'
                        : 'Authorization was attempted on a subscription, but failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';
                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    return $eventInfo;
                }
                case "update":
                {
                    $title = $approved
                        ? 'Payment updated'
                        : 'Payment update failed';

                    $description = $approved
                        ? 'The payment was successfully updated.'
                        : 'The payment update failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';

                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    return $eventInfo;
                }
                case "return":
                {
                    $title = $approved
                        ? 'Payment completed'
                        : 'Payment failed';
                    $statusText = $approved
                        ? 'successful'
                        : 'failed';

                    $description = 'Returned from ' . $thirdPartyName . ' authentication with a ' . $statusText . ' authorization.';
                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    if (!$approved) {
                        $eventInfo['description'] = $eventInfo['description'] . '<div style="color:#E08F95">' . $merchantLabel . '</div>';
                    }
                    return $eventInfo;
                }
                case "redirect":
                {
                    $statusText = $approved
                        ? "Successfully"
                        : "Unsuccessfully";
                    $eventInfo['title'] = 'Redirect to ' . $thirdPartyName;
                    $eventInfo['description'] = $statusText . ' redirected to ' . $thirdPartyName . ' for authentication.';
                    return $eventInfo;
                }
            }
        }
        if ($action === "capture") {
            $captureMultiText = (($subAction === "multi" || $subAction === "multiinstant") && $operation['currentbalance'] > 0)
                ? 'Further captures are possible.'
                : 'Further captures are no longer possible.';

            switch ($subAction) {
                case "full":
                {
                    $title = $approved
                        ? 'Captured full amount'
                        : 'Capture failed';

                    $description = $approved
                        ? 'The full amount was successfully captured.'
                        : 'The capture attempt failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';

                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;

                    return $eventInfo;
                }
                case "fullinstant":
                {
                    $title = $approved
                        ? 'Instantly captured full amount'
                        : 'Instant capture failed';

                    $description = $approved
                        ? 'The full amount was successfully captured.'
                        : 'The instant capture attempt failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';

                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;

                    return $eventInfo;
                }
                case "partly":
                case "multi":
                {
                    $title = $approved
                        ? 'Captured partial amount'
                        : 'Capture failed';

                    $description = $approved
                        ? 'The partial amount was successfully captured. ' . $captureMultiText
                        : 'The partial capture attempt failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';

                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    return $eventInfo;
                }
                case "partlyinstant":
                case "multiinstant":
                {
                    $title = $approved
                        ? 'Instantly captured partial amount'
                        : 'Instant capture failed';
                    $description = $approved
                        ? 'The partial amount was successfully captured. ' . $captureMultiText
                        : 'The instant partial capture attempt failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';

                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    return $eventInfo;
                }
            }
        }

        if ($action === "credit") {
            switch ($subAction) {
                case "full":
                {
                    $title = $approved
                        ? 'Refunded full amount'
                        : 'Refund failed';
                    $description = $approved
                        ? 'The full amount was successfully refunded.'
                        : 'The refund attempt failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';

                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    return $eventInfo;
                }
                case "partly":
                case "multi":
                {
                    $title = $approved
                        ? 'Refunded partial amount'
                        : 'Refund failed';

                    $refundMultiText = $subAction === "multi"
                        ? "Further refunds are possible."
                        : "Further refunds are no longer possible.";

                    $description = $approved
                        ? 'The amount was successfully refunded. ' . $refundMultiText
                        : 'The partial refund attempt failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';

                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    return $eventInfo;
                }
            }
        }
        if ($action === "delete") {
            switch ($subAction) {
                case "instant":
                {
                    $title = $approved
                        ? 'Canceled'
                        : 'Cancellation failed';

                    $description = $approved
                        ? 'The payment was canceled.'
                        : 'The cancellation failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';

                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    return $eventInfo;
                }
                case "delay":
                {
                    $title = $approved
                        ? 'Cancellation scheduled'
                        : 'Cancellation scheduling failed';

                    $description = $approved
                        ? 'The payment was canceled.'
                        : 'The cancellation failed. <div style="color:#E08F95">' . $merchantLabel . '</div>';

                    $eventInfo['title'] = $title;
                    $eventInfo['description'] = $description;
                    return $eventInfo;
                }
            }
        }
        $eventInfo['title'] = $action . ":" . $subAction;
        $eventInfo['description'] = null;
        return $eventInfo;
    }
}
