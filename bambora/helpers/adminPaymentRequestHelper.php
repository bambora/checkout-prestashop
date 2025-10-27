<?php

class BamboraAdminPaymentRequestHelper
{
    /**
     * Create payment request.
     *
     * @param Bambora $module
     * @param Order $order
     *
     * @return string
     */
    public static function createPaymentRequest($module, $order)
    {
        $html = '';
        try {
            $cart = Cart::getCartByOrderId($order->id);
            $roundingMode = Configuration::get('BAMBORA_ROUNDING_MODE');
            $invoiceAddress = new Address((int) $cart->id_address_invoice);
            $deliveryAddress = new Address((int) $cart->id_address_delivery);
            $bamboraCustomer = BamboraCheckoutHelper::createBamboraCustomer($cart, $invoiceAddress);
            $bamboraOrder = BamboraCheckoutHelper::createBamboraOrder(
                $module,
                $cart,
                $invoiceAddress,
                $deliveryAddress,
                $roundingMode
            );
            $bamboraUrl = BamboraCheckoutHelper::createBamboraUrl($module, true);

            $paymentRequest = new BamboraCheckoutPaymentRequest();
            $paymentRequestParameters = new BamboraCheckoutPaymentRequestParameters(
            );

            $description = Tools::getValue(
                'bambora_pr_create_form_description'
            );
            $paymentRequest->description = BamboraCommonHelper::validateHtmlInput(
                $module,
                $description,
                'message',
                $module->l('Description', 'adminpaymentrequesthelper'),
                false
            );
            $paymentRequest->reference = "PrestashopPaymentRequest{$order->id}";

            $terms = Configuration::get('BAMBORA_TERMS_URL');

            if (isset($terms) && $terms != '') {
                $paymentRequest->termsurl = $terms;
            }

            $paymentRequestParameters->instantcaptureamount = Configuration::get(
                'BAMBORA_INSTANTCAPTURE'
            ) == 1 ? $bamboraOrder->amount : 0;
            $paymentRequestParameters->customer = $bamboraCustomer;
            $paymentRequestParameters->order = $bamboraOrder;
            $paymentRequestParameters->url = $bamboraUrl;

            $language = new Language((int) $cart->id_lang);
            $paymentWindowId = Configuration::get('BAMBORA_PAYMENTWINDOWID');

            $paymentRequestParameters->paymentwindow = new BamboraCheckoutRequestPaymentWindow(
            );
            $paymentRequestParameters->paymentwindow->id = is_numeric(
                $paymentWindowId
            ) ? $paymentWindowId : 1;
            $paymentRequestParameters->paymentwindow->language = str_replace(
                '_',
                '-',
                $language->locale
            );

            $paymentRequest->parameters = $paymentRequestParameters;
            $createPaymentRequest = BamboraApiHelper::createPaymentRequest($paymentRequest);

            if (isset($createPaymentRequest) && $createPaymentRequest->meta->result) {
                BamboraDBHelper::addDbPaymentRequest(
                    $order->id,
                    $cart->id,
                    $createPaymentRequest->id,
                    $createPaymentRequest->url
                );
                $message = $module->l(
                    'Payment request created with ID:',
                    'adminpaymentrequesthelper'
                ) . ' ' . $createPaymentRequest->id;
                $html = $module->displayConfirmation($message);
            } else {
                $errorMessage = isset($createPaymentRequest)
                    ? $createPaymentRequest->meta->message->merchant
                    : 'Unknown error';
                throw new Exception($errorMessage);
            }
        } catch (Exception $e) {
            $html = $module->displayError($e->getMessage());
        }

        return $html;
    }

    /**
     * Delete payment request.
     *
     * @param Bambora $module
     * @param Order $order
     *
     * @return mixed
     *
     * @throws Exception
     */
    public static function deletePaymentRequest($module, $order)
    {
        $html = '';
        try {
            $paymentRequest = BamboraDBHelper::getDbPaymentRequestByOrderId($order->id);
            $paymentRequestId = $paymentRequest['payment_request_id'];
            $deletePaymentRequest = BamboraApiHelper::deletePaymentRequest(
                $paymentRequestId
            );

            if (isset($deletePaymentRequest) && $deletePaymentRequest->meta->result) {
                BamboraDBHelper::deleteDbPaymentRequest($paymentRequestId);
                $message = $module->l(
                    'Payment request deleted. ID:',
                    'adminpaymentrequesthelper'
                ) . ' ' . $paymentRequestId;
                $html = $module->displayConfirmation($message);
            } else {
                $errorMessage = isset($deletePaymentRequest)
                    ? $deletePaymentRequest->meta->message->merchant
                    : 'Unknown error occurred';
                throw new Exception($errorMessage);
            }
        } catch (Exception $e) {
            $html = $module->displayError($e->getMessage());
        }

        return $html;
    }

    /**
     * Send payment request email
     *
     * @param Bambora $module
     * @param Order $order
     *
     * @return mixed
     *
     * @throws Exception
     */
    public static function sendPaymentRequest($module, $order)
    {
        $html = '';

        try {
            $paymentRequest = BamboraDBHelper::getDbPaymentRequestByOrderId($order->id);
            $recipientRequest = new BamboraCheckoutPaymentRequestEmailRecipient();

            $recipientRequest->to = new BamboraCheckoutPaymentRequestEmailRecipientAddress(
            );
            $recipientToName = Tools::getValue(
                'bambora_pr_send_form_recipient_name'
            );
            $recipientRequest->to->name = BamboraCommonHelper::validateHtmlInput(
                $module,
                $recipientToName,
                'name',
                $module->l('Recipient Name', 'adminpaymentrequesthelper'),
                true
            );

            $recipientToEmail = Tools::getValue(
                'bambora_pr_send_form_recipient_email'
            );
            $recipientRequest->to->email = BamboraCommonHelper::validateHtmlInput(
                $module,
                $recipientToEmail,
                'email',
                $module->l('Recipient Email', 'adminpaymentrequesthelper'),
                true
            );

            $recipientRequest->replyto = new BamboraCheckoutPaymentRequestEmailRecipientAddress();
            $replyToName = Tools::getValue(
                'bambora_pr_send_form_replyto_name'
            );
            $recipientRequest->replyto->name = BamboraCommonHelper::validateHtmlInput(
                $module,
                $replyToName,
                'name',
                $module->l('Reply-to Name', 'adminpaymentrequesthelper'),
                true
            );

            $replyToEmail = Tools::getValue(
                'bambora_pr_send_form_replyto_email'
            );
            $recipientRequest->replyto->email = BamboraCommonHelper::validateHtmlInput(
                $module,
                $replyToEmail,
                'email',
                $module->l('Reply-to Email', 'adminpaymentrequesthelper'),
                true
            );

            $recipientMessage = Tools::getValue(
                'bambora_pr_send_form_message'
            );
            $recipientRequest->message = BamboraCommonHelper::validateHtmlInput(
                $module,
                $recipientMessage,
                'message',
                $module->l('Message', 'adminpaymentrequesthelper'),
                false
            );

            $sendPaymentRequestEmailResponse = BamboraApiHelper::sendPaymentRequestEmail(
                $paymentRequest['payment_request_id'],
                $recipientRequest
            );
            if (isset($sendPaymentRequestEmailResponse)
                && $sendPaymentRequestEmailResponse->meta->result
            ) {
                $message = $module->l(
                    'Payment request email sent',
                    'adminpaymentrequesthelper'
                );
                $html = $module->displayConfirmation($message);
            } else {
                $errorMessage = isset($sendPaymentRequestEmailResponse)
                    ? $sendPaymentRequestEmailResponse->meta->message->merchant
                    : 'Unknown error occurred';
                throw new Exception($errorMessage);
            }
        } catch (Exception $e) {
            $html = $module->displayError($e->getMessage());
        }

        return $html;
    }

    /**
     * Display Payment Request Form
     *
     * @param Bambora $module
     * @param Order $order
     *
     * @return string
     */
    public static function renderPaymentRequestFormHtml($module, $order)
    {
        if (!isset($order) || empty($order)) {
            return '';
        }

        $paymentRequest = BamboraDBHelper::getDbPaymentRequestByOrderId($order->id);
        $employee = new Employee($module->bamboraContext->cookie->id_employee);
        $customer = new Customer($order->id_customer);
        $currency = new Currency($order->id_currency);
        $currencyIsoCode = $currency->iso_code;
        $formattedAmount = $module->bamboraContext->currentLocale->formatPrice(
            $order->total_paid,
            $currencyIsoCode
        );
        $adminToken = Tools::getAdminTokenLite('AdminOrders');
        $currentIndex = AdminController::$currentIndex . '&vieworder&id_order=' . $order->id;
        $formAction = "{$currentIndex}&token={$adminToken}";

        return '<div class="card mt-2">
                    <div class="card-header">
                        <h3 class="card-header-title">
                            Worldline Online Checkout - Payment Request
                        </h3>
                    </div>
                    <div class="card-body bambora-card-body">' .
                        self::generateCardPaymentRequestHtml(
                            $module,
                            $paymentRequest,
                            $employee,
                            $customer,
                            $order,
                            $currencyIsoCode,
                            $formattedAmount,
                            $formAction
                        ) .
                    '</div>
                </div>';
    }

    /**
     * Generate Card Payment Request Html
     *
     * @param Bambora $module
     * @param string $paymentRequest
     * @param Employee $employee
     * @param Customer $customer
     * @param string $currencyIsoCode
     * @param string $formattedAmount
     * @param string $formAction
     *
     * @return string
     */
    public static function generateCardPaymentRequestHtml(
        $module,
        $paymentRequest,
        $employee,
        $customer,
        $order,
        $currencyIsoCode,
        $formattedAmount,
        $formAction,
    ) {
        $postKey = rand();
        $_SESSION['bambora-pr-post-key'] = $postKey;

        if (!isset($paymentRequest)) {
            return self::buildCreatePaymentRequestFormHtml(
                $module,
                $currencyIsoCode,
                $formattedAmount,
                $formAction,
                $order->id,
                $postKey
            );
        }

        $paymentRequestDetailed = BamboraApiHelper::getPaymentRequest(
            $paymentRequest['payment_request_id']
        );
        if (!isset($paymentRequestDetailed) || !$paymentRequestDetailed->meta->result) {
            return isset($paymentRequestDetailed)
                ? "An error occurred - {$paymentRequestDetailed->meta->message->merchant}"
                : $module->l(
                    'An unknown error occurred - Could not lookup the Payment Request',
                    'adminpaymentrequesthelper'
                );
        }

        $html = self::buildPaymentRequestInfoFormHtml($module, $paymentRequestDetailed, $formAction, $order->id, $postKey);
        if ($paymentRequestDetailed->status === 'open') {
            $html .= self::buildPaymentRequestSendFormHtml($module, $customer, $employee, $formAction, $order->id, $postKey);
        }

        return $html;
    }

    /**
     * Build Create Payment Request Form Html
     *
     * @param Bambora $module
     * @param string $currencyIsoCode
     * @param string $formattedAmount
     * @param string $formAction
     * @param string $orderId
     * @param string $postKey
     *
     * @return string
     */
    public static function buildCreatePaymentRequestFormHtml($module, $currencyIsoCode, $formattedAmount, $formAction, $orderId, $postKey)
    {
        return '<div class="bambora-pr-create-form">       
                    <table class="bambora-table bambora-pr-create-form-table">
                        <tr>
                            <td colspan="2" class="bambora-table-title">' .
                                $module->l('Create Payment Request', 'adminpaymentrequesthelper') .
                            '</td>
                        </tr>
                        <tr>
                            <td>' . $module->l('Amount', 'adminpaymentrequesthelper') . ':</td>
                            <td>' . $formattedAmount . '</td>
                        </tr>
                        <tr>
                            <td>' . $module->l('Currency', 'adminpaymentrequesthelper') . ':</td>
                            <td>' . $currencyIsoCode . '</td>
                        </tr>
                    </table>
                    <form class="bambora-form" method="post" enctype="multipart/form-data" 
                        action="' . $formAction . '">
                        <input type="hidden" name="createpaymentrequest" value="1">
                        <input type="hidden" name="id_order" value="' . $orderId . '">
                        <input type="hidden" name="bambora-pr-post-key" value="' . $postKey . '"  />
                        <div class="bambora-pr-create-form-description">
                            <div class="bambora-pr-create-form-label">' .
                                $module->l(
                                    'Description',
                                    'adminpaymentrequesthelper'
                                ) .
                            ':</div>
                            <textarea id="bambora-pr-create-form-description" name="bambora_pr_create_form_description"
                            rows="1" maxlength="150" class="textarea-autosize form-control" type="message"></textarea>
                        </div>
                        <div class="bambora-pr-create-form-info">' .
                            $module->l(
                                'Once you have created the Payment Request, 
                                you will be able to send it directly to the customer.',
                                'adminpaymentrequesthelper'
                            ) .
                        '</div>
                        <input id="bambora-pr-create-form-submit" class="btn btn-primary bambora-pr-create-form-button"
                            name="bambora_create_pr_submit" type="submit" value="' .
                                $module->l(
                                    'Create Payment Request',
                                    'adminpaymentrequesthelper'
                                ) . '"/>
                    </form>
                </div>';
    }

    /**
     * Build Payment Request Info Form Html
     *
     * @param Bambora $module
     * @param mixed $paymentRequest
     * @param string $formAction
     * @param string $orderId
     * @param string $postKey
     *
     * @return string
     */
    public static function buildPaymentRequestInfoFormHtml($module, $paymentRequest, $formAction, $orderId, $postKey)
    {
        $currency = $paymentRequest->parameters->order->currency;
        $amount = BamboraCurrencyHelper::convertPriceFromMinorUnits(
            $paymentRequest->parameters->order->amount,
            BamboraCurrencyHelper::getCurrencyMinorunits($currency)
        );
        $formattedAmount = $module->bamboraContext->currentLocale->formatPrice(
            $amount,
            $currency
        );

        $merchantNumber = Configuration::get('BAMBORA_MERCHANTNUMBER');
        $merchantFrontendEndpoint = BamboraApiHelper::MERCHANT_FRONTEND_ENDPOINT;
        $merchantPaymentRequestUrl =
            "{$merchantFrontendEndpoint}/{$merchantNumber}/paymentrequests/{$paymentRequest->id}";

        $html = '<div class="bambora-pr-info-form">       
                    <table class="bambora-table">
                        <tr>
                            <td colspan="2" class="bambora-table-title">' .
                                $module->l('Payment Request', 'adminpaymentrequesthelper') .
                            '</td>
                        </tr>
                        <tr>
                            <td>' . $module->l('Payment Request ID', 'adminpaymentrequesthelper') . ':</td>
                            <td>' . $paymentRequest->id . '</td>
                        </tr>
                        <tr>
                            <td>' . $module->l('Payment Request Url', 'adminpaymentrequesthelper') . ':</td>
                            <td>
                                <a href="' . $paymentRequest->url . '" target="_blank">' .
                                    $paymentRequest->url . '
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td>' . $module->l('Merchant UI Url', 'adminpaymentrequesthelper') . ':</td>
                            <td>
                                <a href="' . $merchantPaymentRequestUrl . '" target="_blank">' .
                                    $merchantPaymentRequestUrl . '
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td>' . $module->l('Created', 'adminpaymentrequesthelper') . ':</td>
                            <td>' .
                                Tools::formatDateStr($paymentRequest->createddate) .
                            '</td>
                        </tr>
                        <tr>
                            <td>' . $module->l('Description', 'adminpaymentrequesthelper') . ':</td>
                            <td>' . $paymentRequest->description . '</td>
                        </tr>
                        <tr>
                            <td>' . $module->l('Status', 'adminpaymentrequesthelper') . ':</td>
                            <td>' . $paymentRequest->status . '</td>
                        </tr>
                        
                        <tr>
                            <td>' . $module->l('Reference', 'adminpaymentrequesthelper') . ':</td>
                            <td>' . $paymentRequest->reference . '</td>
                        </tr>
                        <tr>
                            <td>' . $module->l('Amount', 'adminpaymentrequesthelper') . ':</td>
                            <td>' . $formattedAmount . '</td>
                        </tr>
                        <tr>
                            <td>' . $module->l('Currency', 'adminpaymentrequesthelper') . ':</td>
                            <td>' . $currency . '</td>
                        </tr>
                    </table>';
        if ($paymentRequest->status === 'open') {
            $html .= '<form class="bambora-form" method="post" enctype="multipart/form-data" 
                        action="' . $formAction . '" novalidate onsubmit="return confirm(\''
                            . $module->l(
                                'Do you really want to delete the Payment Request',
                                'adminpaymentrequesthelper'
                            ) .
                        '\');">
                        <input type="hidden" name="deletepaymentrequest" value="1">
                        <input type="hidden" name="id_order" value="' . $orderId . '">
                        <input type="hidden" name="bambora-pr-post-key" value="' . $postKey . '"/>
                        <input id="bambora-pr-info-form-delete-submit" 
                            class="btn btn-primary bambora-pr-info-form-delete-button"
                            name="bambora_pr_info_form_delete_submit" 
                            type="submit" value="' .
                                $module->l(
                                    'Delete Payment Request',
                                    'adminpaymentrequesthelper'
                                ) .
                            '"/>
                    </form>
                </div>';
        }

        return $html;
    }

    /**
     * Build Payment Request Send Form Html
     *
     * @param Bambora $module
     * @param Customer $customer
     * @param Employee $employee
     * @param string $formAction
     * @param string $orderId
     * @param string $postKey
     *
     * @return string
     */
    public static function buildPaymentRequestSendFormHtml(
        $module,
        $customer,
        $employee,
        $formAction,
        $orderId,
        $postKey,
    ) {
        return '<div class="bambora-pr-send-form">
                    <div class="bambora-pr-send-form-title">' .
                        $module->l('Send Payment Request', 'adminpaymentrequesthelper') .
                    '</div>
                    <form class="bambora-form" method="post" enctype="multipart/form-data" 
                        action="' . $formAction . '">
                        <input type="hidden" name="sendpaymentrequest" value="1">
                        <input type="hidden" name="id_order" value="' . $orderId . '">
                        <input type="hidden" name="bambora-pr-post-key" value="' . $postKey . '"/>
                        <div class="bambora-pr-send-form-container">
                            <label class="bambora-pr-send-form-label" 
                                for="bambora_pr_send_form_recipient_name">' .
                                    $module->l('Recipient Name', 'adminpaymentrequesthelper') .
                            '</label>
                            <input type="text" id="bambora-pr-send-form-recipient-name"
                                value="' . "{$customer->firstname} {$customer->lastname}" . '"
                                name="bambora_pr_send_form_recipient_name"
                                required="required"/>
                            
                            <label class="bambora-pr-send-form-label" 
                                for="bambora_pr_send_form_recipient_email">' .
                                    $module->l('Recipient Email', 'adminpaymentrequesthelper') .
                            '</label>
                            <input type="email" id="bambora-pr-send-form-recipient-email"
                                value="' . $customer->email . '"
                                name="bambora_pr_send_form_recipient_email"
                                required="required"/>
                            
                            <label class="bambora-pr-send-form-label" 
                                for="bambora_pr_send_form_replyto_name">' .
                                    $module->l('Reply-to Name', 'adminpaymentrequesthelper') .
                            '</label>
                            <input type="text" id="bambora-pr-send-form-replyto-name"
                                value="' . "{$employee->firstname} {$employee->lastname}" . '"
                                name="bambora_pr_send_form_replyto_name"
                                required="required"/>
                            
                            <label class="bambora-pr-send-form-label" 
                                for="bambora_pr_send_form_replyto_email">' .
                                    $module->l('Reply-to Email', 'adminpaymentrequesthelper') .
                            '</label>
                            <input type="email" id="bambora-pr-send-form-replyto-email"
                                value="' . $employee->email . '"
                                name="bambora_pr_send_form_replyto_email"
                                required="required"/>
                            
                            <label class="bambora-pr-send-form-label" 
                                for="bambora_pr_send_form_message">' .
                                    $module->l('Message', 'adminpaymentrequesthelper') .
                            '</label>
                            <textarea id="bambora-pr-send-form-message" name="bambora_pr_send_form_message"
                            rows="3" class="textarea-autosize form-control" type="message"></textarea>
                        </div>
                        <input id="bambora-pr-send-form-submit" 
                            class="btn btn-primary bambora-pr-send-form-submit-button"
                            name="bambora_pr_send_form_submit" 
                            type="submit" value="' .
                                $module->l(
                                    'Send Payment Request Email',
                                    'adminpaymentrequesthelper'
                                ) .
                            '"/>
                    </form>
                </div>';
    }
}
