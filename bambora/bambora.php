<?php

include 'helpers/adminCommonHelper.php';
include 'helpers/adminPaymentRequestHelper.php';
include 'helpers/adminTransactionHelper.php';
include 'helpers/apiHelper.php';
include 'helpers/commonHelper.php';
include 'helpers/checkoutHelper.php';
include 'helpers/currencyHelper.php';
include 'helpers/dbHelper.php';
include 'helpers/settingsHelper.php';
include 'models/models.php';

if (!defined('_PS_VERSION_')) {
    exit;
}
class Bambora extends PaymentModule
{
    /**
     * @var string
     */
    public const MODULE_VERSION = '3.0.0';

    /** @var Context */
    public $bamboraContext;
    /** @var string */
    public $modulePath;
    /**
     * @var string
     */
    private $apiKey;

    public function __construct()
    {
        $this->name = 'bambora';
        $this->tab = 'payments_gateways';
        $this->version = Bambora::MODULE_VERSION;
        $this->author = 'Bambora Online A/S';

        $this->ps_versions_compliancy = [
            'min' => '8.2',
            'max' => _PS_VERSION_,
        ];
        $this->controllers = [
            'accept',
            'callback',
            'payment',
            'paymentrequestcallback',
        ];
        $this->is_eu_compatible = 1;
        $this->bootstrap = true;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->displayName = 'Worldline Online Checkout';
        $this->description = $this->l(
            'Accept online payments quick and secure by Worldline Online Checkout'
        );

        if (!$this->isRegisteredInHook('displayAdminOrderSideBottom')) {
            $this->registerHook('displayAdminOrderSideBottom');
        }

        if (!$this->isRegisteredInHook('displayAdminOrderMainBottom')) {
            $this->registerHook('displayAdminOrderMainBottom');
        }

        parent::__construct();
        $this->bamboraContext = $this->context;
        $this->modulePath = $this->_path;
    }

    /* Region Install and Setup */

    /**
     * Install Module
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('displayHeader')
            || !$this->registerHook('adminOrder')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('PDFInvoice')
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('displayAdminOrderSideBottom')
            || !$this->registerHook('displayAdminOrderMainBottom')
            || !$this->registerHook('actionOrderStatusPostUpdate')
            || !BamboraDBHelper::createBamboraPaymentRequestTable()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Uninstall Module
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Get Module Settings Content (Override from parent).
     *
     * @return string
     */
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $output .= BamboraSettingsHelper::updateModuleSettings($this);
        }
        $merchantNumber = Configuration::get('BAMBORA_MERCHANTNUMBER');
        $isValidCredentials = BamboraApiHelper::isValidCredentials($merchantNumber);
        if (!$isValidCredentials) {
            $output .= $this->displayError(
                $this->l('The credentials you have given are not valid. Please check them.')
            );
        } else {
            $output .= $this->displayConfirmation(
                $this->l('The credentials provided are valid.')
            );
        }
        $output .= BamboraSettingsHelper::renderSettingsFormHtml($this);

        return $output;
    }

    /**
     * Hook payment options
     *
     * @param mixed $params
     *
     * @return PrestaShop\PrestaShop\Core\Payment\PaymentOption[]
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }
        $cart = $params['cart'];
        if (!BamboraCommonHelper::checkCurrency($this, $cart)) {
            return [];
        }
        $currency = new Currency((int) $cart->id_currency);

        $minorUnits = BamboraCurrencyHelper::getCurrencyMinorunits($currency->iso_code);
        $totalAmountMinorunits = BamboraCurrencyHelper::convertPriceToMinorUnits(
            $cart->getOrderTotal(),
            $minorUnits,
            Configuration::get('BAMBORA_ROUNDING_MODE')
        );

        $paymentcardIds = BamboraApiHelper::getAvaliablePaymentCardIdsForMerchant(
            $currency->iso_code,
            $totalAmountMinorunits
        );

        $paymentInfoData = [
            'paymentCardIds' => $paymentcardIds,
            'onlyShowLogoes' => Configuration::get(
                'BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT'
            ),
        ];
        $this->context->smarty->assign($paymentInfoData);

        $bamoraTitle = Configuration::get('BAMBORA_TITLE');
        $callToActionText = empty($bamoraTitle) ? 'Worldline Online Checkout' : $bamoraTitle;

        $bamboraPaymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption(
        );
        $bamboraPaymentOption->setCallToActionText($callToActionText)
            ->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    'payment',
                    [],
                    true
                )
            )
            ->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:bambora/views/templates/front/payment-info.tpl'
                )
            );

        $paymentOptions = [];
        $paymentOptions[] = $bamboraPaymentOption;

        return $paymentOptions;
    }

    /**
     * Hook Display Header
     */
    public function hookDisplayHeader()
    {
        if ($this->context->controller != null) {
            $this->context->controller->registerStylesheet(
                'bambora-front-css',
                "{$this->_path}views/css/bambora-front.css",
                [
                    'media' => 'all',
                ]
            );
            $this->context->controller->registerJavascript(
                'checkout-sdk-web.min',
                BamboraApiHelper::getCheckoutSDKWebUrl(),
                [
                    'position' => 'head',
                    'server' => 'remote',
                ]
            );
        }
    }

    /**
     * Hook Admin Order
     *
     * @param mixed $params
     *
     * @return string
     */
    public function hookAdminOrder($params)
    {
        $html = '';
        $order = new Order($params['id_order']);
        if (isset($order) && $order->module === $this->name) {
            $payments = $order->getOrderPayments();
            foreach ($payments as $payment) {
                if (!empty($payment->transaction_id)) {
                    return $html;
                }
            }
            $hasPaymentRequestPermissions = BamboraApiHelper::hasPaymentRequestCreatePermissions();
            if ($hasPaymentRequestPermissions) {
                if (Tools::getIsset('bambora-pr-post-key')
                    && Tools::getValue('bambora-pr-post-key') == $_SESSION['bambora-pr-post-key']) {
                    if (Tools::isSubmit('createpaymentrequest')) {
                        $existingPaymentRequest = BamboraDBHelper::getDbPaymentRequestByOrderId($order->id);
                        if (empty($existingPaymentRequest)) {
                            $html .= BamboraAdminPaymentRequestHelper::createPaymentRequest($this, $order);
                        } else {
                            $html = $this->displayError(
                                $this->l('There is already a payment request for this order.')
                            );
                        }
                    }
                    if (Tools::isSubmit('deletepaymentrequest')) {
                        $html .= BamboraAdminPaymentRequestHelper::deletePaymentRequest($this, $order);
                    }
                    if (Tools::isSubmit('sendpaymentrequest')) {
                        $html .= BamboraAdminPaymentRequestHelper::sendPaymentRequest($this, $order);
                    }
                }
                $html .= BamboraAdminPaymentRequestHelper::renderPaymentRequestFormHtml($this, $order);
            }
        }

        return $html;
    }

    /**
     * Hook Payment Return
     *
     * @param mixed $params
     *
     * @return mixed
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $order = $params['order'];
        if (!isset($order) || $order->module != $this->name) {
            return '';
        }
        $payment = $order->getOrderPayments();
        if (empty($payment)) {
            return '';
        }
        $transactionId = $payment[0]->transaction_id;
        $this->context->smarty->assign(
            'bambora_completed_paymentText',
            $this->l('You completed your payment.')
        );

        if ($transactionId) {
            $this->context->smarty->assign(
                'bambora_completed_transactionText',
                $this->l('Your transaction ID for this payment is:')
            );
            $this->context->smarty->assign(
                'bambora_completed_transactionValue',
                $transactionId
            );
        }

        $customer = new Customer($order->id_customer);
        if ($customer->email) {
            $this->context->smarty->assign(
                'bambora_completed_emailText',
                $this->l('An confirmation email has been sent to:')
            );
            $this->context->smarty->assign(
                'bambora_completed_emailValue',
                $customer->email
            );
        }

        return $this->display(__FILE__, 'views/templates/front/payment-return.tpl');
    }

    /**
     * Hook Display PDF Invoice
     *
     * @param mixed $params
     *
     * @return string
     */
    public function hookDisplayPDFInvoice($params)
    {
        $invoice = $params['object'];
        $order = new Order($invoice->id_order);

        if (!isset($order)
            || $order->module !== $this->name
            || Configuration::get('BAMBORA_PDF_DISPLAY_PAYMENTINFO') == 0) {
            return '';
        }

        $payments = $order->getOrderPayments();
        if (empty($payments)) {
            return '';
        }

        $transactionId = $payments[0]->transaction_id;
        if (empty($transactionId)) {
            $transactionId = Tools::getIsset('txnid') ? Tools::getValue('txnid') : '';
        }

        $truncatedCardNumber = $payments[0]->card_number;
        if (empty($truncatedCardNumber)) {
            $truncatedCardNumber = Tools::getIsset('cardno') ? Tools::getValue('cardno') : '';
        }

        $formattedCardnumber = BamboraCommonHelper::formatTruncatedCardnumber(
            $truncatedCardNumber
        );

        return BamboraAdminCommonHelper::renderPDFInvoiceHtml(
            $this,
            $transactionId,
            $formattedCardnumber
        );
    }

    /**
     * Hook Display BackOffice Header
     *
     * @param mixed $params
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        if ($this->context->controller != null) {
            $cssPath = "{$this->_path}views/css/bambora-admin.css";
            $this->context->controller->addCSS(
                $cssPath,
                'all'
            );
            $jsPath = "{$this->_path}views/js/bambora-admin.js";
            $this->context->controller->addJS($jsPath);
        }
    }

    /**
     * Hook Admin Order Side Bottom
     *
     * @param mixed $params
     *
     * @return string
     */
    public function hookDisplayAdminOrderSideBottom($params)
    {
        $order = new Order($params['id_order']);
        if (!isset($order) || $order->module !== $this->name) {
            return '';
        }

        return BamboraAdminCommonHelper::renderDisplayAdminOrderSideBottomHtml($this);
    }

    /**
     * Hook Admin Order Main Bottom
     *
     * @param mixed $params
     *
     * @return string
     */
    public function hookDisplayAdminOrderMainBottom($params)
    {
        $order = new Order($params['id_order']);
        if (!isset($order) || $order->module !== $this->name) {
            return '';
        }

        $html = '';
        $bamboraUiMessage = BamboraAdminCommonHelper::processRemoteAction($this);
        if (isset($bamboraUiMessage)) {
            $html = BamboraAdminTransactionHelper::buildCardTransactionOverlayMessageHtml(
                $bamboraUiMessage->type,
                $bamboraUiMessage->title,
                $bamboraUiMessage->message
            );
        }
        $html .= BamboraAdminTransactionHelper::renderTransactionCardHtml($this, $order);

        return $html;
    }

    /**
     * Try to capture the payment when the status of the order is changed.
     *
     * @param mixed $params
     *
     * @return void
     *
     * @throws Exception
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $newOrderStatus = $params['newOrderStatus'];
        $order = new Order($params['id_order']);

        if (isset($order)
            && $order->module === $this->name
            && Configuration::get('BAMBORA_CAPTUREONSTATUSCHANGED') == 1) {
            try {
                $allowedOrderStatuses = unserialize(
                    Configuration::get('BAMBORA_CAPTURE_ON_STATUS')
                );
                if (is_array($allowedOrderStatuses)
                        && in_array(
                            $newOrderStatus->id,
                            $allowedOrderStatuses
                        )
                ) {
                    $payment = $order->getOrderPayments();
                    $transactionId = !empty($payment) ? $payment[0]->transaction_id : null;

                    if (!isset($transactionId)) {
                        throw new Exception('No Bambora TransactionId found');
                    }

                    $currency = new Currency((int) $order->id_currency);
                    $currencyCode = $currency->iso_code;
                    $minorUnits = BamboraCurrencyHelper::getCurrencyMinorunits(
                        $currencyCode
                    );
                    $amountInMinorUnits = BamboraCurrencyHelper::convertPriceToMinorUnits(
                        $payment[0]->amount,
                        $minorUnits,
                        Configuration::get('BAMBORA_ROUNDING_MODE')
                    );
                    $currency = '';

                    $captureRequest = new BamboraCaptureRequest();
                    $captureRequest->amount = $amountInMinorUnits;
                    $captureRequest->currency = $currencyCode;

                    $captureResponse = BamboraApiHelper::capture($transactionId, $captureRequest);

                    if (!isset($captureResponse) || !$captureResponse->meta->result) {
                        $errorMessage = isset($captureResponse)
                            ? $captureResponse->meta->message->merchant
                            : 'Could not connect to Bambora';
                        throw new Exception($errorMessage);
                    }

                    $message = 'Auto Capture was successful';
                    BamboraAdminCommonHelper::createStatusChangesMessage($params['id_order'], $message);
                }
            } catch (Exception $e) {
                $message = 'Auto Capture failed with message: ' . $e->getMessage();
                BamboraAdminCommonHelper::createStatusChangesMessage($params['id_order'], $message);
                $id_lang = (int) $this->context->language->id;
                $dir_mail = dirname(__FILE__) . '/mails/';
                $mailTo = Configuration::get('BAMBORA_AUTOCAPTURE_FAILUREEMAIL');
                Mail::Send(
                    $id_lang,
                    'autocapturefailed',
                    'Auto capture of ' . $params['id_order'] . ' failed',
                    ['{message}' => $e->getMessage()],
                    $mailTo,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $dir_mail
                );
            }
        }
    }
}
