<?php

class BamboraAdminCommonHelper
{
    /**
     * Process Remote Action
     *
     * @param Bambora $module
     *
     * @return BamboraUiMessage|null
     */
    public static function processRemoteAction($module)
    {
        $bamboraUiMessage = null;
        if ((Tools::isSubmit('bambora-capture')
            || Tools::isSubmit('bambora-credit')
            || Tools::isSubmit('bambora-delete'))
            && Tools::getIsset('bambora-post-key')
            && Tools::getValue('bambora-post-key') == $_SESSION['bambora-post-key']
            && Tools::getIsset('bambora-transaction-id')
            && Tools::getIsset('bambora-currency-code')
        ) {
            $bamboraUiMessage = new BamboraUiMessage();
            $result = '';

            try {
                $transactionId = Tools::getValue('bambora-transaction-id');
                $currencyCode = Tools::getValue('bambora-currency-code');
                $minorUnits = BamboraCurrencyHelper::getCurrencyMinorunits($currencyCode);
                $orderId = Tools::getValue('bambora-order-id');
                $roundingMode = Configuration::get('BAMBORA_ROUNDING_MODE');

                if (Tools::isSubmit('bambora-capture')) {
                    $captureInputValue = Tools::getValue('bambora-capture-value');

                    $amountSanitized = str_replace(',', '.', $captureInputValue);
                    $amount = (float) $amountSanitized;
                    if (is_float($amount)) {
                        $amountMinorunits = BamboraCurrencyHelper::convertPriceToMinorUnits(
                            $amount,
                            $minorUnits,
                            $roundingMode
                        );
                        $captureRequest = new BamboraCaptureRequest();
                        $captureRequest->amount = $amountMinorunits;
                        $captureRequest->currency = $currencyCode;
                        $result = BamboraApiHelper::capture($transactionId, $captureRequest);
                    } else {
                        $bamboraUiMessage->type = 'issue';
                        $bamboraUiMessage->title = $module->l(
                            'Inputfield is not a valid number',
                            'admincommonhelper'
                        );

                        return $bamboraUiMessage;
                    }
                } elseif (Tools::isSubmit('bambora-credit')) {
                    $captureInputValue = Tools::getValue('bambora-credit-value');

                    $amountSanitized = str_replace(',', '.', $captureInputValue);
                    $amount = (float) $amountSanitized;
                    if (is_float($amount)) {
                        $amountMinorunits = BamboraCurrencyHelper::convertPriceToMinorUnits(
                            $amount,
                            $minorUnits,
                            $roundingMode
                        );
                        $isCollectorBank = Tools::getValue('bambora_isCollector');
                        $creditRequest = new BamboraCreditRequest();
                        $creditRequest->amount = $amountMinorunits;
                        $creditRequest->currency = $currencyCode;
                        $creditRequest->invoicelines = $isCollectorBank
                            ? BamboraCheckoutHelper::buildCreditInvoiceLine($amountMinorunits)
                            : null;
                        $result = BamboraApiHelper::credit($transactionId, $creditRequest);
                    } else {
                        $bamboraUiMessage->type = 'issue';
                        $bamboraUiMessage->title = $module->l(
                            'Inputfield is not a valid number',
                            'admincommonhelper'
                        );

                        return $bamboraUiMessage;
                    }
                } elseif (Tools::isSubmit('bambora-delete')) {
                    $result = BamboraApiHelper::delete($transactionId);
                }

                if (isset($result) && $result->meta->result) {
                    $logText = '';
                    if (Tools::isSubmit('bambora-capture')) {
                        $captureText = $module->l(
                            'The Payment was captured successfully',
                            'admincommonhelper'
                        );
                        $bamboraUiMessage->type = 'capture';
                        $bamboraUiMessage->title = $captureText;
                        $logText = $captureText;
                    } elseif (Tools::isSubmit('bambora-credit')) {
                        $creditText = $module->l(
                            'The Payment was refunded successfully',
                            'admincommonhelper'
                        );
                        $bamboraUiMessage->type = 'credit';
                        $bamboraUiMessage->title = $creditText;
                        $logText = $creditText;
                    } elseif (Tools::isSubmit('bambora-delete')) {
                        $deleteText = $module->l(
                            'The Payment was deleted successfully',
                            'admincommonhelper'
                        );
                        $bamboraUiMessage->type = 'delete';
                        $bamboraUiMessage->title = $deleteText;
                        $logText = $deleteText;
                    }
                    $employee = $module->bamboraContext->employee;
                    $logText .= ' :: OrderId: ' . $orderId . ' TransactionId: ' . $transactionId .
                                ' Employee: ' . $employee->firstname . ' ' . $employee->lastname .
                                ' ' . $employee->email;
                    PrestaShopLogger::addLog($logText, 1);
                } else {
                    $bamboraUiMessage->type = 'issue';
                    $bamboraUiMessage->title = $module->l(
                        'An issue occurred, and the operation was not performed.',
                        'admincommonhelper'
                    );
                    if (isset($result)) {
                        $message = $result->meta->message->merchant;

                        if (isset($result->meta->action)
                                && $result->meta->action->source == 'ePayEngine'
                                && ($result->meta->action->code == '113'
                                || $result->meta->action->code == '114')) {
                            preg_match_all('!\d+!', $message, $matches);
                            foreach ($matches[0] as $match) {
                                $matchAmount = BamboraCurrencyHelper::convertPriceFromMinorUnits(
                                    $match,
                                    $minorUnits
                                );
                                $matchAmountFormatted = $module->bamboraContext->currentLocale->formatPrice(
                                    $matchAmount,
                                    $currencyCode
                                );
                                $message = str_replace(
                                    $match,
                                    $matchAmountFormatted,
                                    $message
                                );
                            }
                        }
                        $bamboraUiMessage->message = $message;
                    } else {
                        $bamboraUiMessage->message = 'Communication error with Bambora';
                    }
                }
            } catch (Exception $e) {
                $module->displayError($e->getMessage());
            }
        }

        return $bamboraUiMessage;
    }

    /**
     * Render HTML for PDF Invoice
     *
     * @param Bambora $module
     * @param mixed $transactionId
     * @param mixed $formattedCardnumber
     *
     * @return string
     */
    public static function renderPDFInvoiceHtml(
        $module,
        $transactionId,
        $formattedCardnumber,
    ) {
        if (empty($transactionId) && empty($paymentType) && empty($formattedCardnumber)) {
            return '';
        }

        $html = '<table>
                    <tr style="font-weight: bold;">
                        <td colspan="2">' .
                                $module->l(
                                    'Payment information',
                                    'admincommonhelper'
                                ) .
                        '</td>
                    </tr>';
        if (!empty($transactionId)) {
            $html .= '<tr>
                    <td width="15%">' .
                            $module->l(
                                'Transaction ID',
                                'admincommonhelper'
                            ) .
                    ':</td>
                        <td>' . $transactionId . '</td>
                </tr>';
        }
        if (!empty($formattedCardnumber)) {
            $html .= '<tr>
                        <td width="15%">' .
                            $module->l(
                                'Card Number',
                                'admincommonhelper'
                            ) .
                        ':</td>
                        <td>' . $formattedCardnumber . '</td>
                    </tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Render Display Admin Order Side Bottom
     *
     * @param Bambora $module
     *
     * @return string
     */
    public static function renderDisplayAdminOrderSideBottomHtml($module)
    {
        return '<div class="card mt-2 d-print-none">
                        <div class="card-header">
                            <h3 class="card-header-title">
                            Worldline Merchant Administration
                            </h3>
                        </div>
                        <div class="card-body bambora-card-body">' .
                            self::buildCardInformationHtml($module) .
                        '</div>
                    </div>';
    }

    /**
     * Build Admin Card Logo HTML
     *
     * @param Bambora $module
     *
     * @return string
     */
    public static function buildCardInformationHtml($module)
    {
        $staticAssetEndpoint = BamboraApiHelper::STATIC_ASSETS_ENDPOINT;
        $merchantFrontendEndpoint = BamboraApiHelper::MERCHANT_FRONTEND_ENDPOINT;

        return '<a href="' . $merchantFrontendEndpoint . '" alt="" title="' .
                    $module->l('Go to Bambora Merchant Administration', 'admincommonhelper') .
                        '" target="_blank">
                    <img class="bambora-logo" src="' . $staticAssetEndpoint .
                        '/assets/bambora/worldline-logo.svg" width="150px;"/>
                </a>
                <div class="worldline-info">' .
                    $module->l(
                        'Bambora will now be known as Worldline. Together, we create digital payments for a trusted world.',
                        'admincommonhelper'
                    ) .
                '</div>
                <div>
                    <a href="' . $merchantFrontendEndpoint . '" alt="Bambora Online Merchant" title="' .
                        $module->l(
                            'Go to Bambora Merchant Administration',
                            'admincommonhelper'
                        ) .
                        '" target="_blank">' . $module->l(
                            'Go to Bambora Merchant Administration',
                            'admincommonhelper'
                        ) .
                    '</a>
                </div>';
    }

    /**
     * Create and add a private order message
     *
     * @param int $orderId
     * @param string $message
     */
    public static function createStatusChangesMessage($orderId, $message)
    {
        $msg = new Message();
        $message = strip_tags($message, '<br>');
        if (Validate::isCleanHtml($message)) {
            $msg->name = 'Worldline Online Checkout';
            $msg->message = $message;
            $msg->id_order = (int) $orderId;
            $msg->private = 1;
            $msg->add();
        }
    }
}
