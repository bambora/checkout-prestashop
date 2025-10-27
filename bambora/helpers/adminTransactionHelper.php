<?php

class BamboraAdminTransactionHelper
{
    /**
     * Display Transaction Form Html
     *
     * @param Bambora $module
     * @param Order $order
     *
     * @return string
     */
    public static function renderTransactionCardHtml($module, $order)
    {
        $payments = $order->getOrderPayments();
        if (empty($payments)) {
            return '';
        }

        $transactionId = $payments[0]->transaction_id;

        return '<div class="card mt-2">
                    <div class="card-header">
                        <h3 class="card-header-title">' .
                            $module->l(
                                'Worldline Online Checkout - Transaction information',
                                'admintransactionhelper'
                            ) .
                        '</h3>
                    </div>
                    <div class="card-body bambora-card-body">' .
                        self::generateCardTransactionHtml($module, $transactionId, $order) .
                    '</div>
                </div>';
    }

    /**
     * Generate Transaction Html Content
     *
     * @param Bambora $module
     * @param string $transactionId
     * @param Order $order
     *
     * @return string
     */
    public static function generateCardTransactionHtml($module, $transactionId, $order)
    {
        if (empty($transactionId)) {
            return 'No payment transaction was found';
        }

        $html = '';
        try {
            $getTransactionResponse = BamboraApiHelper::getTransaction($transactionId);
            if (!$getTransactionResponse->meta->result) {
                return "An error occured: {$getTransactionResponse->meta->message->merchant}";
            }

            $getTransactionOperationsResponse = BamboraApiHelper::getTransactionOperations(
                $transactionId
            );
            if (!$getTransactionOperationsResponse->meta->result) {
                return "An error occured: {$getTransactionOperationsResponse->meta->message->merchant}";
            }

            $transaction = $getTransactionResponse->transaction;
            $transactionOperations = $getTransactionOperationsResponse->transactionoperations;
            $currency = new Currency($order->id_currency);
            $isoCode = $currency->iso_code;

            $html .= self::generateCardTransactionTableHtml($module, $transaction, $isoCode, $transactionOperations);
            $html .= self::generateCardTransactionFormHtml($module, $transaction);
            $html .= self::generateCardTransactionOperationsTableHtml($module, $transactionOperations, $isoCode);
        } catch (Exception $e) {
            $module->displayError($e->getMessage());
        }

        return $html;
    }

    /**
     * Build Payment Table
     *
     * @param Bambora $module
     * @param mixed $transaction
     * @param string $currency
     * @param mixed $transactionOperations
     *
     * @return string
     */
    public static function generateCardTransactionTableHtml(
        $module,
        $transaction,
        $currency,
        $transactionOperations,
    ) {
        $html = '<table class="bambora-table">
                    <tr>
                        <td colspan="2" class="bambora-table-title">' .
                            $module->l('Payment', 'admintransactionhelper') .
                        '</td>
                    </tr>';

        $amount = BamboraCurrencyHelper::convertPriceFromMinorUnits(
            $transaction->total->authorized,
            $transaction->currency->minorunits
        );

        $formattedAmount = $module->bamboraContext->currentLocale->formatPrice(
            $amount,
            $currency
        );

        $html .= '<tr>
                    <td>' . $module->l('Transaction Id', 'admintransactionhelper') . ':</td>
                    <td>' . $transaction->id . '</td>
                </tr>
                <tr>
                    <td>' . $module->l('Amount', 'admintransactionhelper') . ':</td>
                    <td>' . $formattedAmount . '</td>
                </tr>
                <tr>
                    <td>' . $module->l('Currency', 'admintransactionhelper') . ':</td>
                    <td>' . $currency . '</td>
                </tr>
                <tr>
                    <td>' . $module->l('Order Id', 'admintransactionhelper') . ':</td>
                    <td>' . $transaction->orderid . '</td>
                </tr>';

        if (!empty($transaction->information->paymenttypes[0]->displayname)) {
            $html .= '<tr>
                        <td>' . $module->l('Card Type', 'admintransactionhelper') . ':</td>
                        <td>
                            <div class="bambora-transaction-paymenttype">' .
                                $transaction->information->paymenttypes[0]->displayname .
                                self::buildCardTransactionTablePaymentLogosHtml($transaction, $transactionOperations) .
                            '</div>
                        </td>
                    </tr>';
        }

        $formattedTruncatedCardnumber = '';
        if (isset($transaction->information->primaryaccountnumbers[0])) {
            $formattedTruncatedCardnumber = BamboraCommonHelper::formatTruncatedCardnumber(
                $transaction->information->primaryaccountnumbers[0]->number
            );
        }

        if (!empty($formattedTruncatedCardnumber)) {
            $html .= '<tr>
                        <td>' . $module->l('Card Number', 'admintransactionhelper') . ':</td>
                        <td>' . $formattedTruncatedCardnumber . '</td>
                    </tr>';
        }

        if (isset($transaction->information->acquirerreferences[0])) {
            $html .= '<tr>
                        <td>' . $module->l('Acquirer Reference', 'admintransactionhelper') . ':</td>
                        <td>' . $transaction->information->acquirerreferences[0]->reference . '</td>
                    </tr>';
        }

        $html .= '<tr>
                    <td>' . $module->l('Status', 'admintransactionhelper') . ':</td>
                    <td>' . BamboraCommonHelper::formatTransactionStatus($transaction->status) . '</td>
                </tr>';

        if (isset($transaction->information->ecis)) {
            $eci = BamboraCommonHelper::getLowestECI($transaction->information->ecis);
            if (!empty($eci)) {
                $html .= '<tr>
                            <td>' . $module->l('ECI', 'admintransactionhelper') . ':</td>
                            <td>' . $eci . '</td>
                        </tr>';
            }
        }

        if (isset($transaction->information->exemptions)) {
            $distinctExemptions = BamboraCommonHelper::getDistinctExemptions(
                $transaction->information->exemptions
            );
            if (!empty($distinctExemptions)) {
                $html .= '<tr>
                            <td>' . $module->l('Exemptions', 'admintransactionhelper') . ':</td>
                            <td>' . $distinctExemptions . '</td>
                        </tr>';
            }
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Get HTML for displaying payment type logoes for a Transaction
     *
     * @param mixed $transaction
     * @param mixed $transactionOperations
     *
     * @return string
     */
    public static function buildCardTransactionTablePaymentLogosHtml($transaction, $transactionOperations)
    {
        if (!isset($transaction, $transactionOperations)) {
            return '';
        }

        $card_group_id = $transaction->information->paymenttypes[0]->groupid;
        $card_name = $transaction->information->paymenttypes[0]->displayname;
        $assetsEndpoint = BamboraApiHelper::STATIC_ASSETS_ENDPOINT;
        $paymentTypeImageBaseUrl = "{$assetsEndpoint}/assets/paymentlogos";
        $paymentTypeImageUrl = "{$paymentTypeImageBaseUrl}/{$card_group_id}.svg";
        $html = '<div class="bambora-transaction-paymenttype-logoes">
                    <img class="bambora_paymenttype_card_img" 
                        src="' . $paymentTypeImageUrl . '" alt="' . $card_name . '" 
                        title="' . $card_name . '" />';
        if (!empty($transactionOperations[0]->transactionoperations)
            && !empty($transactionOperations[0]->transactionoperations[0]->acquirerdata)
            && $transactionOperations[0]->transactionoperations[0]->acquirerdata[0]->key == 'nordeaepaymentfi.customerbank'
        ) {
            $bank_name = $transactionOperations[0]->transactionoperations[0]->acquirerdata[0]->value;
            if (!empty($bank_name)) {
                $paymentTypeBankImageUrl = "{$paymentTypeImageBaseUrl}/bank-{$bank_name}.svg";
                $html .= '<img class="bambora_paymenttype_bank_img" 
                        src="' . $paymentTypeBankImageUrl . '" alt="' . $bank_name . '" 
                        title="' . $bank_name . '"/>';
            }
        }

        if (is_array($transaction->information->wallets) && count($transaction->information->wallets) > 0) {
            $wallet_name = $transaction->information->wallets[0]->name;
            $wallet_img = '';
            if ($wallet_name == 'MobilePay') {
                $wallet_img = '13.svg';
            }
            if ($wallet_name == 'Vipps') {
                $wallet_img = '14.svg';
            }
            if ($wallet_name == 'GooglePay') {
                $wallet_img = '22.svg';
                $wallet_name = 'Google Pay';
            }
            if ($wallet_name == 'ApplePay') {
                $wallet_img = '21.svg';
                $wallet_name = 'Apple Pay';
            }
            if (!empty($wallet_img)) {
                $paymentTypeWalletImageUrl = "{$paymentTypeImageBaseUrl}/{$wallet_img}";
                $html .= '<img class="bambora_paymenttype_wallet_img" 
                            src="' . $paymentTypeWalletImageUrl . '" alt="' . $wallet_name . '" 
                            title="' . $wallet_name . '"/>';
            }
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Action buttons for Capture, Refund and Delete Transactions
     *
     * @param Bambora $module
     * @param mixed $transaction
     *
     * @return string
     */
    public static function generateCardTransactionFormHtml($module, $transaction)
    {
        $html = '';
        if ($transaction->available->capture > 0
                || $transaction->available->credit > 0
                || $transaction->candelete == 'true') {
            $randomKey = rand();
            $_SESSION['bambora-post-key'] = $randomKey;
            $html .= '<form name="bambora-remote" action="' . $_SERVER['REQUEST_URI'] .
                        '" method="post" class="bambora-display-inline" id="bambora-action" >
                            <input type="hidden" name="bambora-transaction-id" value="' . $transaction->id . '" />
                            <input type="hidden" name="bambora-order-id" value="' . $transaction->orderid . '" />
                            <input type="hidden" name="bambora-currency-code" value="' . $transaction->currency->code . '" />
                            <input type="hidden" value="' . $randomKey . '" name="bambora-post-key" />
                            <div id="bambora-transaction-controls-container">';
            $html .= self::buildCardTransactionFormSpinnerHtml($module);

            $minorUnits = $transaction->currency->minorunits;

            $availableForCapture = BamboraCurrencyHelper::convertPriceFromMinorUnits(
                $transaction->available->capture,
                $minorUnits
            );

            $availableForCredit = BamboraCurrencyHelper::convertPriceFromMinorUnits(
                $transaction->available->credit,
                $minorUnits
            );

            $editable = !BamboraCommonHelper::isCollectorBank($transaction);
            if ($availableForCapture > 0) {
                $html .= self::buildCardTransactionFormControlHtml(
                    $module,
                    'capture',
                    $module->l('Capture', 'admintransactionhelper'),
                    'btn bambora-capture-btn',
                    true,
                    $availableForCapture,
                    $transaction->currency->code,
                    $editable
                );
            }

            if ($availableForCredit > 0) {
                $html .= self::buildCardTransactionFormControlHtml(
                    $module,
                    'credit',
                    $module->l('Refund', 'admintransactionhelper'),
                    'btn bambora-credit-btn',
                    true,
                    $availableForCredit,
                    $transaction->currency->code,
                    $editable
                );
            }

            if ($transaction->candelete) {
                $html .= self::buildCardTransactionFormControlHtml(
                    $module,
                    'delete',
                    $module->l('Delete', 'admintransactionhelper'),
                    'btn bambora-delete-btn'
                );
            }

            $html .= '</div></form>';
            $html .= '<span class="bambora-action-info-text">
                        <i>' .
                            $module->l(
                                'More actions will appear when clicking the buttons',
                                'admintransactionhelper'
                            ) .
                        '</i>
                    </span>';
            $html .= '<div id="bambora-format-error" class="alert alert-danger">
                        <strong>' . $module->l('Warning', 'admintransactionhelper') . ' </strong>' .
                            $module->l(
                                'The amount you entered was in the wrong format. Please try again!',
                                'admintransactionhelper'
                            ) .
                    '</div>';
        }

        return $html;
    }

    /**
     * Build Bambora Loading Spinner HTML
     *
     * @param Bambora $module
     *
     * @return string
     */
    public static function buildCardTransactionFormSpinnerHtml($module)
    {
        return '<div id="bambora-spinner">
                    <div>
                        <img src="' . $module->modulePath . 'views/img/arrows.svg">
                    </div>            
                    <span>' . $module->l('Working', 'admintransactionhelper') . '... </span>
                </div>';
    }

    /**
     * Build Transaction Control Html
     *
     * @param Bambora $module
     * @param mixed $type
     * @param mixed $value
     * @param mixed $class
     * @param mixed $addInputField
     * @param mixed $valueOfInputfield
     * @param mixed $currencycode
     * @param mixed $editable
     *
     * @return string
     */
    public static function buildCardTransactionFormControlHtml(
        $module,
        $type,
        $value,
        $class,
        $addInputField = false,
        $valueOfInputfield = 0,
        $currencycode = '',
        $editable = true,
    ) {
        $tooltip = $module->l('Example: 1234.56', 'admintransactionhelper');
        if (!$editable) {
            $readonly = 'readonly';
            $tooltip = '';
            if ($type == 'credit') {
                $tooltip = $module->l(
                    'With Payment Provider Walley only full refund is possible here.
                        For partial refund, please use Bambora Merchant Portal.',
                    'admintransactionhelper'
                );
            }
            if ($type == 'capture') {
                $tooltip = $module->l(
                    'With Payment Provider Walley only full capture is possible here.
                        For partial capture, please use Bambora Merchant Portal.',
                    'admintransactionhelper'
                );
            }
        } else {
            $readonly = '';
        }

        $isCollector = !$editable;
        $html = '<div class="bambora-action-control">
                    <input class="' . $class . ' bambora-action-btn" name="unhide-' . $type . '" type="button" value="' .
                        Tools::strtoupper($value) . '"/>
                    <div class="row bambora-hidden bambora-button-control" data-hasinputfield="' .
                        $addInputField . '">
                        <input class="btn bambora-cancel-btn" type="button"/>';

        if ($addInputField) {
            $html .= '<input name ="bambora_isCollector" value ="' . $isCollector . '" type="hidden"/>
                        <input class="bambora-action-input" type="text" title="' . $tooltip . '" 
                            required="required" ' . $readonly . ' name="bambora-' . $type . '-value"
                            value="' . $valueOfInputfield . '"  />
                        <span>' . $currencycode . '</span>';
        }
        $html .= '<input class="' . $class . ' bambora-action-submit" name="bambora-' . $type . '"
                        type="submit" value="' . Tools::strtoupper($value) . '"/>
                    </div>
                </div>';

        return $html;
    }

    /**
     * Create Checkout Transaction Operations Html
     *
     * @param Bambora $module
     * @param mixed $transactionOperations
     * @param mixed $currency
     *
     * @return string
     */
    public static function generateCardTransactionOperationsTableHtml(
        $module,
        $transactionOperations,
        $currency,
    ) {
        $res = '<table class="bambora-table bambora-operations-table">
                    <tr>
                        <td colspan="6" class="bambora-table-title">
                            <strong>' .
                                $module->l(
                                    'Transaction Operations',
                                    'admintransactionhelper'
                                ) .
                            '</strong>
                        </td>
                    </tr>
                    <th>' . $module->l('Date', 'admintransactionhelper') . '</th>
                    <th>' . $module->l('Action', 'admintransactionhelper') . '</th>
                    <th>' . $module->l('Amount', 'admintransactionhelper') . '</th>
                    <th>' . $module->l('Operation ID', 'admintransactionhelper') . '</th>
                    <th>' . $module->l('Parent Operation ID', 'admintransactionhelper') . '</th>';
        $res .= self::buildCardTransactionOperationTableItemsHtml(
            $module,
            $transactionOperations,
            $currency
        );
        $res .= '</table>';

        return $res;
    }

    /**
     * Create Transaction Operation Items HTML
     *
     * @param Bambora $module
     * @param mixed $transactionOperations
     * @param mixed $currency
     *
     * @return string
     */
    public static function buildCardTransactionOperationTableItemsHtml(
        $module,
        $transactionOperations,
        $currency,
    ) {
        $html = '';
        foreach ($transactionOperations as $operation) {
            $eventInfo = BamboraCommonHelper::getEventText($operation);
            if ($eventInfo['description'] != null) {
                $html .= '<tr>';
                $date = str_replace(
                    'T',
                    ' ',
                    Tools::substr($operation->createddate, 0, 19)
                );
                $html .= '<td>' . Tools::displayDate($date) . '</td>';
                $html .= '<td>' . $eventInfo['title'] . '</td>';
                if ($operation->amount > 0) {
                    $amount = BamboraCurrencyHelper::convertPriceFromMinorUnits(
                        $operation->amount,
                        $operation->currency->minorunits
                    );
                    $html .= '<td>' .
                                $module->bamboraContext->currentLocale->formatPrice($amount, $currency) .
                            '</td>';
                } else {
                    $html .= '<td> - </td>';
                }

                $html .= '<td>' . $operation->id . '</td>';

                if (!empty($operation->parenttransactionoperationid)) {
                    $html .= '<td>' . $operation->parenttransactionoperationid . '</td>';
                } else {
                    $html .= '<td> - </td>';
                }

                $html .= '</tr>
                        <tr>
                            <td colspan="5">
                                <i>' . $eventInfo['description'] . '</i>
                            </td>
                        </tr>';
            }

            if (!empty($operation->transactionoperations)) {
                $html .= self::buildCardTransactionOperationTableItemsHtml(
                    $module,
                    $operation->transactionoperations,
                    $currency
                );
            }
        }
        $res = str_replace('CollectorBank', 'Walley', $html);

        return $res;
    }

    /**
     * Build Overlay Message Html
     *
     * @param mixed $type
     * @param mixed $title
     * @param mixed $message
     *
     * @return string
     */
    public static function buildCardTransactionOverlayMessageHtml($type, $title, $message)
    {
        $html = '<div id="bambora-overlay">
                    <a id="bambora-inline" href="#data"></a>
                    <div id="data" class="row bambora-overlay-data">
                        <div id="bambora-message" class="col-lg-12">';

        if ($type === 'issue') {
            $html .= '<div class="bambora-circle bambora-exclamation-circle">
                        <div class="bambora-exclamation-stem"></div>
                        <div class="bambora-exclamation-dot"></div>
                    </div>';
        } else {
            $html .= '<div class="bambora-circle bambora-checkmark-circle">
                        <div class="bambora-checkmark-stem"></div>
                    </div>';
        }
        $html .= '<div id="bambora-overlay-message-container">';

        if (!empty($message)) {
            $html .= '<span id="bambora-overlay-message-title-with-message">' . $title . '</span>';
            $html .= '<span id="bambora-overlay-message-message">' . $message . '</span>';
        } else {
            $html .= '<span id="bambora-overlay-message-title">' . $title . '</span>';
        }

        $html .= '</div></div></div></div>';

        return $html;
    }
}
