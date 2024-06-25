<?php
/**
 * Copyright (c) 2019. All rights reserved Bambora Online A/S.
 *
 * This program is free software. You are allowed to use the software but NOT
 * allowed to modify the software. It is also not legal to do any changes to the
 * software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test
 * account that you can use to test the module.
 *
 * @author    Bambora Online A/S
 * @copyright Bambora (https://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL
 *            3.0)
 *
 */

include('lib/bamboraApi.php');
include('lib/bamboraHelpers.php');
include('lib/bamboraCurrency.php');

if (!defined('_PS_VERSION_')) {
    exit;
}

class Bambora extends PaymentModule
{
    const MODULE_VERSION = '2.2.0';
    const V15 = '15';
    const V16 = '16';
    const V17 = '17';
    private $apiKey;

    public function __construct()
    {
        $this->name = 'bambora';
        $this->tab = 'payments_gateways';
        $this->version = '2.3.0';
        $this->author = 'Bambora Online A/S';

        $this->ps_versions_compliancy = array(
            'min' => '1.6',
            'max' => _PS_VERSION_
        );
        $this->controllers = array(
            'accept',
            'callback',
            'payment',
            'paymentrequestcallback'
        );
        $this->is_eu_compatible = 1;
        $this->bootstrap = true;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = 'Worldline Checkout';
        $this->description = $this->l(
            'Accept online payments quick and secure by Worldline Checkout'
        );

        if ($this->isPsVersionHigherThan177()) {
            if (!$this->isRegisteredInHook('displayAdminOrderSideBottom')) {
                $this->registerHook('displayAdminOrderSideBottom');
            }
            if (!$this->isRegisteredInHook('displayAdminOrderMainBottom')) {
                $this->registerHook('displayAdminOrderMainBottom');
            }
        }
    }

    #region Install and Setup

    /**
     * Get Ps Version
     *
     * @return string
     */
    public function isPsVersionHigherThan177()
    {
        if (_PS_VERSION_ < "1.7.7.0") {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Install
     *
     * @return boolean
     */
    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('payment')
            || !$this->registerHook('rightColumn')
            || !$this->registerHook('adminOrder')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('PDFInvoice')
            || !$this->registerHook('Invoice')
            || !$this->registerHook('backOfficeHeader')
            || !$this->registerHook('displayHeader')
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('actionOrderStatusPostUpdate')
        ) {
            return false;
        }
        if ($this->getPsVersion() === $this::V17) {
            if (!$this->registerHook('paymentOptions')) {
                return false;
            }
        }
        if ($this->isPsVersionHigherThan177()) {
            if (!$this->registerHook('displayAdminOrderSideBottom')) {
                return false;
            }
            if (!$this->registerHook('displayAdminOrderMainBottom')) {
                return false;
            }
        }
        if (!$this->createBamboraPaymentRequestTable()) {
            return false;
        }


        $tab = new Tab();
        $tab->active = 1;
        $tab->enabled = true;
        $tab->class_name = 'ListPaymentRequests';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Worldline Payment Requests';
        }
        $tab->id_parent = (int)Tab::getIdFromClassName('SELL');
        $tab->module = $this->name;
        $tab->icon = 'payment';
        $tab->add();
        $tab->save();

        return true;
    }

    /**
     * Get Ps Version
     *
     * @return string
     */
    public function getPsVersion()
    {
        if (_PS_VERSION_ < "1.6.0.0") {
            return $this::V15;
        } elseif (_PS_VERSION_ >= "1.6.0.0" && _PS_VERSION_ < "1.7.0.0") {
            return $this::V16;
        } else {
            return $this::V17;
        }
    }

    private function createBamboraPaymentRequestTable()
    {
        $table_name = _DB_PREFIX_ . 'bambora_payment_requests';

        $columns = array(
            'payment_request_id' => 'varchar(255) NOT NULL',
            'id_order' => 'int(10) unsigned NOT NULL',
            'id_cart' => 'int(10) unsigned NOT NULL',
            'payment_request_url' => 'varchar(255) NOT NULL',
            'date_add' => 'datetime NOT NULL',
        );

        $query = 'CREATE TABLE IF NOT EXISTS `' . $table_name . '` (';

        foreach ($columns as $column_name => $options) {
            $query .= '`' . $column_name . '` ' . $options . ', ';
        }

        $query .= ' PRIMARY KEY (`id_order`) )';

        if (!Db::getInstance()->Execute($query)) {
            return false;
        }

        return true;
    }

    /**
     * Uninstall
     *
     * @return boolean
     */
    public function uninstall()
    {
        return parent::uninstall();
    }
    #endregion

    #region Hooks

    /**
     * Get Content
     *
     * @return string
     */
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $merchantnumber = (string)Tools::getValue('BAMBORA_MERCHANTNUMBER');
            $accesstoken = (string)Tools::getValue("BAMBORA_ACCESSTOKEN");
            $secrettoken = (string)Tools::getValue("BAMBORA_SECRETTOKEN");
            $allowLowValue = Tools::getValue("BAMBORA_ALLOW_LOW_VALUE_EXEMPTION");
            $limitLowValueExemption = (string)Tools::getValue(
                "BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION"
            );
            if (empty($merchantnumber) || !Validate::isGenericName(
                    $merchantnumber
                )) {
                $output .= $this->displayError(
                    'Merchant number ' . $this->l(
                        'is required. If you do not have one please contact Bambora in order to obtain one!'
                    )
                );
            } elseif (empty($accesstoken) || !Validate::isGenericName(
                    $accesstoken
                )) {
                $output .= $this->displayError(
                    'Access token ' . $this->l(
                        'is required. If you do not have one please contact Bambora in order to obtain one!'
                    )
                );
            } elseif (!Validate::isGenericName(
                    $secrettoken
                ) && empty(Configuration::get('BAMBORA_SECRETTOKEN'))) {
                $output .= $this->displayError(
                    'Secret token ' . $this->l(
                        'is required. If you do not have one please contact Bambora in order to obtain one!'
                    )
                );
            } elseif (!Validate::isPrice(
                    $limitLowValueExemption
                ) && $allowLowValue) {
                $output .= $this->displayError(
                    'If you allow low value exemptions you need to set a limit here that is valid'
                );
            } else {
                Configuration::updateValue(
                    'BAMBORA_MERCHANTNUMBER',
                    $merchantnumber
                );
                Configuration::updateValue('BAMBORA_ACCESSTOKEN', $accesstoken);
                if (!empty($secrettoken)) {
                    Configuration::updateValue('BAMBORA_SECRETTOKEN', $secrettoken);
                }
                Configuration::updateValue(
                    'BAMBORA_MD5KEY',
                    Tools::getValue("BAMBORA_MD5KEY")
                );
                Configuration::updateValue(
                    'BAMBORA_PAYMENTWINDOWID',
                    Tools::getValue("BAMBORA_PAYMENTWINDOWID")
                );
                Configuration::updateValue(
                    'BAMBORA_TITLE',
                    Tools::getValue("BAMBORA_TITLE")
                );
                Configuration::updateValue(
                    'BAMBORA_INSTANTCAPTURE',
                    Tools::getValue("BAMBORA_INSTANTCAPTURE")
                );
                Configuration::updateValue(
                    'BAMBORA_WINDOWSTATE',
                    Tools::getValue("BAMBORA_WINDOWSTATE")
                );
                Configuration::updateValue(
                    'BAMBORA_IMMEDIATEREDIRECTTOACCEPT',
                    Tools::getValue("BAMBORA_IMMEDIATEREDIRECTTOACCEPT")
                );
                Configuration::updateValue(
                    'BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT',
                    Tools::getValue("BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT")
                );
                Configuration::updateValue(
                    'BAMBORA_ADDFEETOSHIPPING',
                    Tools::getValue("BAMBORA_ADDFEETOSHIPPING")
                );
                Configuration::updateValue(
                    'BAMBORA_CAPTUREONSTATUSCHANGED',
                    Tools::getValue("BAMBORA_CAPTUREONSTATUSCHANGED")
                );
                Configuration::updateValue(
                    'BAMBORA_CAPTURE_ON_STATUS',
                    serialize(Tools::getValue("BAMBORA_CAPTURE_ON_STATUS"))
                );
                Configuration::updateValue(
                    'BAMBORA_AUTOCAPTURE_FAILUREEMAIL',
                    Tools::getValue("BAMBORA_AUTOCAPTURE_FAILUREEMAIL")
                );
                Configuration::updateValue(
                    'BAMBORA_ROUNDING_MODE',
                    Tools::getValue("BAMBORA_ROUNDING_MODE")
                );
                Configuration::updateValue(
                    'BAMBORA_ALLOW_LOW_VALUE_EXEMPTION',
                    Tools::getValue("BAMBORA_ALLOW_LOW_VALUE_EXEMPTION")
                );
                Configuration::updateValue(
                    'BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION',
                    Tools::getValue("BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION")
                );
                Configuration::updateValue(
                    'BAMBORA_TERMS_URL',
                    Tools::getValue("BAMBORA_TERMS_URL")
                );
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        if (!$this->testCredentials()) {
            $output .= $this->displayError(
                'The credentials you have given are not valid. Please check them.'
            );
        }
        $output .= $this->displayForm();

        return $output;
    }

    public function testCredentials()
    {
        $apiKey = $this->getApiKey();
        $api = new BamboraApi($apiKey);

        return $api->testIfValidCredentials();
    }

    /**
     * Get Api Key
     *
     * @return string
     */
    private function getApiKey()
    {
        if (empty($this->apiKey)) {
            $this->apiKey = BamboraHelpers::generateApiKey();
        }

        return $this->apiKey;
    }

    /**
     * Display Form
     *
     * @return string
     */
    private function displayForm()
    {
        // Get default Language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $switch_options = array(
            array('id' => 'active_on', 'value' => 1, 'label' => 'Yes'),
            array('id' => 'active_off', 'value' => 0, 'label' => 'No'),
        );
        $windowstate_options = array(
            array('type' => 2, 'name' => 'Overlay'),
            array('type' => 1, 'name' => 'Fullscreen')
        );
        $statuses = OrderState::getOrderStates($this->context->language->id);
        $selectCaptureStatus = array();
        foreach ($statuses as $status) {
            $selectCaptureStatus[] = array(
                'key' => $status["id_order_state"],
                'name' => $status["name"]
            );
        }
        $rounding_modes = array(
            array('type' => BamboraCurrency::ROUND_DEFAULT, 'name' => 'Default'),
            array('type' => BamboraCurrency::ROUND_UP, 'name' => 'Always up'),
            array('type' => BamboraCurrency::ROUND_DOWN, 'name' => 'Always down')
        );

        // Init Fields form array
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings')
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => 'Merchant number',
                    'name' => 'BAMBORA_MERCHANTNUMBER',
                    'size' => 40,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => 'Access token',
                    'name' => 'BAMBORA_ACCESSTOKEN',
                    'size' => 40,
                    'required' => true
                ),
                array(
                    'type' => 'password',
                    'label' => 'Secret token',
                    'name' => 'BAMBORA_SECRETTOKEN',
                    'size' => 40,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => 'MD5 key',
                    'name' => 'BAMBORA_MD5KEY',
                    'size' => 40,
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => 'Payment Window ID',
                    'name' => 'BAMBORA_PAYMENTWINDOWID',
                    'size' => 40,
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => 'Payment method title',
                    'name' => 'BAMBORA_TITLE',
                    'size' => 40,
                    'required' => false
                ),
                array(
                    'type' => 'select',
                    'label' => 'Window state',
                    'name' => 'BAMBORA_WINDOWSTATE',
                    'required' => false,
                    'options' => array(
                        'query' => $windowstate_options,
                        'id' => 'type',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Instant capture',
                    'name' => 'BAMBORA_INSTANTCAPTURE',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Immediate Redirect',
                    'name' => 'BAMBORA_IMMEDIATEREDIRECTTOACCEPT',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Add Surcharge',
                    'name' => 'BAMBORA_ADDFEETOSHIPPING',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Only show payment logos at checkout',
                    'name' => 'BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Capture payment on status changed',
                    'name' => 'BAMBORA_CAPTUREONSTATUSCHANGED',
                    'is_bool' => true,
                    'required' => false,
                    'values' => $switch_options
                ),
                array(
                    'type' => 'select',
                    'label' => 'Capture on status changed to',
                    'name' => 'BAMBORA_CAPTURE_ON_STATUS[]',
                    'class' => 'chosen',
                    'required' => false,
                    'multiple' => true,
                    'options' => array(
                        'query' => $selectCaptureStatus,
                        'id' => 'key',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => 'Capture on status changed failure e-mail',
                    'name' => 'BAMBORA_AUTOCAPTURE_FAILUREEMAIL',
                    'size' => 40,
                    'required' => false
                ),
                array(
                    'type' => 'select',
                    'label' => 'Rounding mode',
                    'name' => 'BAMBORA_ROUNDING_MODE',
                    'required' => false,
                    'options' => array(
                        'query' => $rounding_modes,
                        'id' => 'type',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Enable Low Value Exemption',
                    'name' => 'BAMBORA_ALLOW_LOW_VALUE_EXEMPTION',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                ),
                array(
                    'type' => 'text',
                    'label' => 'Max Amount for Low Value Exemption',
                    'name' => 'BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION',
                    'size' => 10,
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => 'URL for Terms & Conditions in case you are using this for Payment Requests.',
                    'name' => 'BAMBORA_TERMS_URL',
                    'size' => 10,
                    'required' => false
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right floatRight',
                'style' => 'float:right'
            )

        );

        $helper = new HelperForm();

        $helper->table = $this->table;
        $this->fields_form = array();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName . " v" . $this->version;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite(
                        'AdminModules'
                    ),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['BAMBORA_MERCHANTNUMBER'] = Configuration::get(
            'BAMBORA_MERCHANTNUMBER'
        );
        $helper->fields_value['BAMBORA_WINDOWSTATE'] = Configuration::get(
            'BAMBORA_WINDOWSTATE'
        );
        $helper->fields_value['BAMBORA_PAYMENTWINDOWID'] = Configuration::get(
            'BAMBORA_PAYMENTWINDOWID'
        );
        $helper->fields_value['BAMBORA_TITLE'] = Configuration::get('BAMBORA_TITLE');
        $helper->fields_value['BAMBORA_INSTANTCAPTURE'] = Configuration::get(
            'BAMBORA_INSTANTCAPTURE'
        );
        $helper->fields_value['BAMBORA_ENABLE_PAYMENTREQUEST'] = Configuration::get(
            'BAMBORA_ENABLE_PAYMENTREQUEST'
        );
        $helper->fields_value['BAMBORA_ACCESSTOKEN'] = Configuration::get(
            'BAMBORA_ACCESSTOKEN'
        );
        $helper->fields_value['BAMBORA_IMMEDIATEREDIRECTTOACCEPT'] = Configuration::get(
            'BAMBORA_IMMEDIATEREDIRECTTOACCEPT'
        );
        $helper->fields_value['BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT'] = Configuration::get(
            'BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT'
        );
        $helper->fields_value['BAMBORA_ADDFEETOSHIPPING'] = Configuration::get(
            'BAMBORA_ADDFEETOSHIPPING'
        );
        $helper->fields_value['BAMBORA_MD5KEY'] = Configuration::get(
            'BAMBORA_MD5KEY'
        );
        $helper->fields_value['BAMBORA_SECRETTOKEN'] = Configuration::get(
            'BAMBORA_SECRETTOKEN'
        );
        $helper->fields_value['BAMBORA_CAPTUREONSTATUSCHANGED'] = Configuration::get(
            'BAMBORA_CAPTUREONSTATUSCHANGED'
        );
        $helper->fields_value['BAMBORA_CAPTURE_ON_STATUS[]'] = unserialize(
            Configuration::get('BAMBORA_CAPTURE_ON_STATUS')
        );
        $helper->fields_value['BAMBORA_AUTOCAPTURE_FAILUREEMAIL'] = Configuration::get(
            'BAMBORA_AUTOCAPTURE_FAILUREEMAIL'
        );
        $helper->fields_value['BAMBORA_ROUNDING_MODE'] = Configuration::get(
            'BAMBORA_ROUNDING_MODE'
        );
        $helper->fields_value['BAMBORA_ALLOW_LOW_VALUE_EXEMPTION'] = Configuration::get(
            'BAMBORA_ALLOW_LOW_VALUE_EXEMPTION'
        );
        $helper->fields_value['BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION'] = Configuration::get(
            'BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION'
        );
        $helper->fields_value['BAMBORA_TERMS_URL'] = Configuration::get(
            'BAMBORA_TERMS_URL'
        );
        $html = '<div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-7 col-lg-7 ">'
            . $helper->generateForm($fields_form)
            . '</div>
                    <div class="hidden-xs col-md-5 col-lg-5">'
            . $this->buildHelptextForSettings()
            . '</div>
                 </div>'
            . '<div class="row visible-xs">
                   <div class="col-xs-12 col-sm-12">'
            . $this->buildHelptextForSettings()
            . '</div>
                   </div>';

        return $html;
    }

    /**
     * Build Help Text For Settings
     *
     * @return mixed
     */
    private function buildHelptextForSettings()
    {
        $html = '<div class="panel helpContainer">
                        <H3>Help for settings</H3>
                        <p>Detailed description of these settings are to be found <a href="https://developer.bambora.com/europe/shopping-carts/shopping-carts/prestashop" target="_blank">here</a>.</p>
                        <br />
                        <div>
                            <h4>Merchant number</h4>
                            <p>The number identifying your Worldline merchant account.</p>
                            <p><b>Note: </b>This field is mandatory to enable payments</p>
                        </div>
                        <br />
                        <div>
                            <h4>Access token</h4>
                            <p>The Access token for the API user received from the Worldline administration.</p>
                            <p><b>Note:</b> This field is mandatory in order to enable payments</p>
                        </div>
                        <br />
                        <div>
                            <h4>Secret token</h4>
                            <p>The Secret token for the API user received from the Worldline administration.</p>
                            <p><b>Note: </b>This field is mandatory in order to enable payments.</p>
                        </div>
                        <br />
                        <div>
                            <h4>MD5 Key</h4>
                            <p>The MD5 key is used to stamp data sent between Magento and Worldline to prevent it from being tampered with.</p>
                            <p><b>Note: </b>The MD5 key is optional but if used here, must be the same as in the Worldline administration.</p>
                        </div>
                        <br />
                        <div>
                            <h4>Payment Window ID</h4>
                            <p>The ID of the payment window to use.</p>
                        </div>
                        <br />
                        <div>
                            <h4>Payment method tittle</h4>
                            <p>The title of the payment method visible to the customers</p>
                            <p><b>Note: </b> If left empty the default title will be <b>Worldline Checkout</b>
                        </div>
                        <br />
                        <div>
                            <h4>Window state</h4>
                            <p>Please select if you want the Payment window shown as an overlay or as full screen</p>
                        </div>
                        <br />
                        <div>
                            <h4>Instant capture</h4>
                            <p>Capture the payments at the same time they are authorized. In some countries, this is only permitted if the consumer receives the products right away Ex. digital products.</p>
                        </div>
                        <br />
                        <div>
                            <h4>Immediate Redirect</h4>
                            <p>Immediately redirect your customer back to you shop after the payment completed.</p>
                        </div>
                        <br />
                        <div>
                            <h4>Add Surcharge</h4>
                            <p>Enable this if you want the payment surcharge to be added to the shipping and handling fee</p>
                        </div>
                        </br>
                        <div>
                            <h4>Only show payment logos at checkout</h4>
                            <p>Set to disable the title text and only display payment logos at checkout</p>
                        </div>
                        <br />
                        <div>
                            <h4>Capture payment on status changed</h4>
                            <p>Enable this if you want to be able to capture the payment when the order status is changed</p>
                        </div>
                        <br />
                        <div>
                            <h4>Capture on status changed to</h4>
                            <p>Select the status you want to execute the capture operation when changed to</p>
                            <p><b>Note: </b>You must enable <b>Capture payment on status changed</b></p>
                        </div>
                        <br />
                        <div>
                            <h4>Capture on status changed failure e-mail</h4>
                            <p>If the Capture fails on status changed an e-mail will be sent to this address</p>
                        </div>
                        <br />
                        <div>
                            <h4>Rounding mode</h4>
                            <p>Please select how you want the rounding of the amount sent to the payment system</p>
                        </div>
                          <br />
                        <div>
                            <h4>Enable Low Value Exemption</h4>
                            <p>Allow you as a merchant to let the customer attempt to skip Strong Customer Authentication(SCA) when the value of the order is below your defined limit. <strong>Note:</strong> the liability will be on you as a merchant.</p>
                        </div>
                          <br />
                        <div>
                            <h4>Max Amount for Low Value Exemption</h4>
                            <p>Any amount below this max amount might skip SCA if the issuer would allow it. Recommended amount is about €30 in your local currency. <br /><a href="https://developer.bambora.com/europe/checkout/psd2/lowvalueexemption"  target="_blank">See more information here.</a></p>
                            <p>Only active if <b>"Enable Low Value Exemption"</b> is set to yes.</p>
                        </div>
                         <div>
                            <h4>URL to Terms & Conditions</h4>
                            <p>In case you are using Payment Requests this is where you can set the URL for your terms & conditions</p>
                        </div>
                        
                        <br />
                   </div>';

        return $html;
    }

    /**
     * Hook Display Header
     */
    public function hookDisplayHeader()
    {
        if ($this->context->controller != null) {
            $jsUrl = "https://static.bambora.com/checkout-sdk-web/latest/checkout-sdk-web.min.js";
            $cssPath = "{$this->_path}views/css/bamboraFront.css";
            if ($this->getPsVersion() === $this::V17) {
                $this->context->controller->registerStylesheet(
                    'bambora-front-css',
                    $cssPath,
                    ['media' => 'all']
                );
                $this->context->controller->registerJavascript(
                    'checkout-sdk-web.min', $jsUrl, [
                        'position' => 'head',
                        'server' => 'remote'
                    ]
                );
            } else {
                $this->context->controller->addCSS($cssPath, 'all');
                $this->context->controller->addJS($jsUrl, 'all');
            }
        }
    }

    /**
     * Hook Display BackOffice Header
     *
     * @param mixed $params
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        $this->hookBackOfficeHeader($params);
    }

    /**
     * Hook BackOffice Header
     *
     * @param mixed $params
     */
    public function hookBackOfficeHeader($params)
    {
        if ($this->context->controller != null) {
            $this->context->controller->addCSS(
                $this->_path . 'views/css/bamboraAdmin.css',
                'all'
            );
        }
    }

    /**
     * Hook payment for Prestashop before 1.7
     *
     * @param mixed $params
     *
     * @return mixed
     */
    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $cart = $params['cart'];
        $bamboraCheckoutRequest = $this->createCheckoutRequest($cart);
        $checkoutResponse = $this->getBamboraCheckoutSession(
            $bamboraCheckoutRequest
        );

        if (!isset($checkoutResponse) || $checkoutResponse['meta']['result'] == false) {
            $errormessage = $checkoutResponse['meta']['message']['enduser'];
            $this->context->smarty->assign('bambora_errormessage', $errormessage);
            $this->context->smarty->assign('this_path_bambora', $this->_path);

            return $this->display(__FILE__, "checkoutIssue.tpl");
        }

        $bamboraOrder = $bamboraCheckoutRequest->order;

        $paymentcardIds = $this->getPaymentCardIds(
            $bamboraOrder->currency,
            $bamboraOrder->total
        );

        $callToActionText = Tools::strlen(
            Configuration::get("BAMBORA_TITLE")
        ) > 0 ? Configuration::get("BAMBORA_TITLE") : "Worldline Checkout";

        $paymentData = array(
            'bamboraPaymentCardIds' => $paymentcardIds,
            'bamboraWindowState' => Configuration::get('BAMBORA_WINDOWSTATE'),
            'bamboraCheckoutToken' => $checkoutResponse['token'],
            'bamboraPaymentTitle' => $callToActionText,
            'onlyShowLogoes' => Configuration::get(
                'BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT'
            )
        );

        $this->context->smarty->assign($paymentData);

        return $this->display(__FILE__, "bamboracheckout.tpl");
    }

    /**
     * Check Currency
     *
     * @param mixed $cart
     *
     * @return boolean
     */
    private function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Create Checkout Request
     *
     * @param mixed $cart
     *
     * @return BamboraCheckoutRequest
     */
    public function createCheckoutRequest($cart)
    {
        $invoiceAddress = new Address((int)$cart->id_address_invoice);
        $deliveryAddress = new Address((int)$cart->id_address_delivery);

        $bamboraCustomer = $this->createBamboraCustomer($cart, $invoiceAddress);
        $bamboraOrder = $this->createBamboraOrder(
            $cart,
            $invoiceAddress,
            $deliveryAddress
        );
        $bamboraUrl = $this->createBamboraUrl(false);

        $language = new Language((int)$cart->id_lang);

        $request = new BamboraCheckoutRequest();

        $request->customer = $bamboraCustomer;
        $request->instantcaptureamount = Configuration::get(
            'BAMBORA_INSTANTCAPTURE'
        ) == 1 ? $bamboraOrder->amount : 0;
        $request->order = $bamboraOrder;
        $request->url = $bamboraUrl;
        if (Configuration::get('BAMBORA_ALLOW_LOW_VALUE_EXEMPTION')) {
            if ($request->order->amount < BamboraCurrency::convertPriceToMinorUnits(
                    Configuration::get('BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION'),
                    BamboraCurrency::getCurrencyMinorunits(
                        $request->order->currency
                    ),
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                )) {
                $request->securityexemption = "lowvaluepayment";
                $request->securitylevel = "none";
            }
        }
        $request->paymentwindow = new BamboraCheckoutRequestPaymentWindow();
        $paymentWindowId = Configuration::get('BAMBORA_PAYMENTWINDOWID');
        $request->paymentwindow->id = is_numeric(
            $paymentWindowId
        ) ? $paymentWindowId : 1;
        $request->paymentwindow->language = str_replace("_", "-", $language->locale);

        return $request;
    }

    #endregion

    #region BackOffice

    /**
     * Create Bambora Customer
     *
     * @param mixed $cart
     * @param mixed $invoiceAddress
     *
     * @return BamboraCustomer
     */
    private function createBamboraCustomer($cart, $invoiceAddress)
    {
        $mobileNumber = $this->getPhoneNumber($invoiceAddress);
        $country = new Country((int)$invoiceAddress->id_country);
        $customer = new Customer((int)$cart->id_customer);

        $bamboraCustomer = new BamboraCustomer();
        $bamboraCustomer->email = $customer->email;
        $bamboraCustomer->phonenumber = $mobileNumber;
        $bamboraCustomer->phonenumbercountrycode = $country->call_prefix;

        return $bamboraCustomer;
    }

    /**
     * Get Phone Number
     *
     * @param mixed $invoiceAddress
     *
     * @return mixed
     */
    public function getPhoneNumber($address)
    {
        if ($address->phone_mobile != "" || $address->phone != "") {
            return $address->phone_mobile != "" ? $address->phone_mobile : $address->phone;
        } else {
            return "";
        }
    }

    /**
     * Create Bambora Order
     *
     * @param mixed $cart
     * @param mixed $invoiceAddress
     * @param mixed $deliveryAddress
     *
     * @return BamboraOrder
     */
    private function createBamboraOrder($cart, $invoiceAddress, $deliveryAddress)
    {
        $cartSummary = $cart->getSummaryDetails();
        $bamboraOrder = new BamboraOrder();
        $bamboraOrder->billingaddress = $this->createBamboraAddress($invoiceAddress);

        $currency = new Currency((int)$cart->id_currency);

        $bamboraOrder->currency = $currency->iso_code;
        $bamboraOrder->lines = $this->createBamboraOrderlines(
            $cartSummary,
            $bamboraOrder->currency
        );

        $bamboraOrder->id = (string)$cart->id;

        if ($cartSummary['is_virtual_cart'] === 0) {
            $bamboraOrder->shippingaddress = $this->createBamboraAddress(
                $deliveryAddress
            );
        }
        $minorUnits = BamboraCurrency::getCurrencyMinorunits(
            $bamboraOrder->currency
        );

        $bamboraOrder->amount = BamboraCurrency::convertPriceToMinorUnits(
            $cart->getOrderTotal(),
            $minorUnits,
            Configuration::get('BAMBORA_ROUNDING_MODE')
        );

        $bamboraOrder->vatamount = BamboraCurrency::convertPriceToMinorUnits(
            $cart->getOrderTotal() - $cart->getOrderTotal(false),
            $minorUnits,
            Configuration::get('BAMBORA_ROUNDING_MODE')
        );

        return $bamboraOrder;
    }

    /**
     * Create Bambora Address
     *
     * @param mixed $address
     *
     * @return BamboraAddress
     */
    private function createBamboraAddress($address)
    {
        $address_delivery_country = new Country($address->id_country);
        $iso_code = $address_delivery_country->iso_code;

        $bamboraAddress = new BamboraAddress();
        $bamboraAddress->att = $address->other;
        $bamboraAddress->city = $address->city;
        $bamboraAddress->country = $iso_code;
        $bamboraAddress->firstname = $address->firstname;
        $bamboraAddress->lastname = $address->lastname;
        $bamboraAddress->street = $address->address1;
        $bamboraAddress->zip = $address->postcode;

        return $bamboraAddress;
    }

    /**
     * Create Bambora Order Lines
     *
     * @param mixed $cartSummary
     * @param mixed $currency
     *
     * @return BamboraOrderLine[]
     */
    private function createBamboraOrderlines($cartSummary, $currency)
    {
        $bamboraOrderlines = array();

        $products = $cartSummary['products'];
        $lineNumber = 1;
        $minorUnits = BamboraCurrency::getCurrencyMinorunits($currency);
        foreach ($products as $product) {
            $line = new BamboraOrderLine();
            $line->description = $product["name"];
            $line->id = $product["id_product"];
            $line->linenumber = (string)$lineNumber;
            $line->quantity = (int)$product["cart_quantity"];
            $line->text = $product["name"];
            $line->totalprice = BamboraCurrency::convertPriceToMinorUnits(
                $product["total"],
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->totalpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $product["total_wt"],
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->totalpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                $product["total_wt"] - $product["total"],
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->unitprice = BamboraCurrency::convertPriceToMinorUnits(
                $product["total"] / $line->quantity,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->unitpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $product["total_wt"] / $line->quantity,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->unitpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                ($product["total_wt"] - $product["total"]) / $line->quantity,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );

            $line->unit = $this->l('pcs.');
            $line->vat = $product["rate"];

            $bamboraOrderlines[] = $line;
            $lineNumber++;
        }

        //Add shipping as an orderline
        $shippingCostWithTax = $cartSummary['total_shipping'];
        if ($shippingCostWithTax > 0) {
            $shippingCostWithoutTax = $cartSummary['total_shipping_tax_exc'];
            $carrier = $cartSummary['carrier'];
            $shippingTax = $shippingCostWithTax - $shippingCostWithoutTax;
            $shippingOrderline = new BamboraOrderLine();
            $shippingOrderline->id = $carrier->id_reference;
            $shippingOrderline->description = "{$carrier->name} - {$carrier->delay}";
            $shippingOrderline->quantity = 1;
            $shippingOrderline->unit = $this->l('pcs.');
            $shippingOrderline->linenumber = $lineNumber++;
            $shippingOrderline->totalprice = BamboraCurrency::convertPriceToMinorUnits(
                $shippingCostWithoutTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->totalpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $shippingCostWithTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->totalpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                $shippingTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->unitprice = BamboraCurrency::convertPriceToMinorUnits(
                $shippingCostWithoutTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->unitpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $shippingCostWithTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->unitpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                $shippingTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );


            $shippingOrderline->vat = round(
                $shippingTax / $shippingCostWithoutTax * 100
            );
            $bamboraOrderlines[] = $shippingOrderline;
        }

        //Gift Wrapping
        $wrappingTotal = $cartSummary['total_wrapping'];
        if ($wrappingTotal > 0) {
            $wrappingTotalWithOutTax = $cartSummary['total_wrapping_tax_exc'];
            $wrappingTotalTax = $wrappingTotal - $wrappingTotalWithOutTax;
            $wrappingOrderline = new BamboraOrderLine();
            $wrappingOrderline->id = $this->l('wrapping');
            $wrappingOrderline->description = $this->l('Gift wrapping');
            $wrappingOrderline->quantity = 1;
            $wrappingOrderline->unit = $this->l('pcs.');
            $wrappingOrderline->linenumber = $lineNumber++;
            $wrappingOrderline->totalprice = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotalWithOutTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $wrappingOrderline->totalpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotal,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $wrappingOrderline->totalpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotalTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $wrappingOrderline->unitprice = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotalWithOutTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $wrappingOrderline->unitpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotal,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $wrappingOrderline->unitpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotalTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );

            $wrappingOrderline->vat = round(
                $wrappingTotalTax / $wrappingTotalWithOutTax * 100
            );
            $bamboraOrderlines[] = $wrappingOrderline;
        }

        //Discount
        $discountTotal = $cartSummary['total_discounts'];
        if ($discountTotal > 0) {
            $discountTotalWithOutTax = $cartSummary['total_discounts_tax_exc'];
            $discountTotalTax = $discountTotal - $discountTotalWithOutTax;
            $discountOrderline = new BamboraOrderLine();
            $discountOrderline->id = $this->l('discount');
            $discountOrderline->description = $this->l('Discount');
            $discountOrderline->quantity = 1;
            $discountOrderline->unit = $this->l('pcs.');
            $discountOrderline->linenumber = $lineNumber++;
            $discountOrderline->totalprice = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotalWithOutTax,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->totalpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotal,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->totalpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotalTax,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->unitprice = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotalWithOutTax,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->unitpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotal,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->unitpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotalTax,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->vat = round(
                $discountTotalTax / $discountTotalWithOutTax * 100
            );
            $bamboraOrderlines[] = $discountOrderline;
        }
	    $roundingOrderline = $this->createBamboraOrderlinesRoundingFee( $cartSummary, $minorUnits, $bamboraOrderlines, $lineNumber );
	    if ( $roundingOrderline ) {
		    $bamboraOrderlines[] = $roundingOrderline;
	    }

        return $bamboraOrderlines;
    }

	protected function createBamboraOrderlinesRoundingFee( $cartSummary, $minorUnits, $bamboraOrderlines, $lineNumber ) {


		$cartTotal = BamboraCurrency::convertPriceToMinorUnits($cartSummary['total_price'], $minorUnits, Configuration::get('BAMBORA_ROUNDING_MODE'));
		$bamboraTotal = 0;
		foreach ( $bamboraOrderlines as $orderLine ) {
			$bamboraTotal += $orderLine->quantity * $orderLine->unitpriceinclvat;
		}
		if ( $cartTotal != $bamboraTotal ) {
			$roundingOrderline                      = new BamboraOrderline();
			$roundingOrderline->id                  = $this->l('adjustment');
			$roundingOrderline->totalprice          = $cartTotal - $bamboraTotal;
			$roundingOrderline->totalpriceinclvat   = $cartTotal - $bamboraTotal;
			$roundingOrderline->totalpricevatamount = 0;
			$roundingOrderline->text                = $this->l('Rounding adjustment');
			$roundingOrderline->unitprice           = $cartTotal - $bamboraTotal;
			$roundingOrderline->unitpriceinclvat    = $cartTotal - $bamboraTotal;
			$roundingOrderline->unitpricevatamount  = 0;
			$roundingOrderline->quantity            = 1;
			$roundingOrderline->description         = $this->l('Rounding adjustment');
			$roundingOrderline->linenumber          = $lineNumber++;
			$roundingOrderline->unit                = $this->l('pcs.');
			$roundingOrderline->vat                 = 0.0;
			return $roundingOrderline;
		}

	}

		/**
     * Create Bambora Url
     *
     * @return BamboraUrl
     */
    private function createBamboraUrl($isPaymentRequest = false)
    {
        $bamboraUrl = new BamboraUrl();

        $bamboraUrl->callbacks = array();
        $callback = new BamboraCallback();


        if ($isPaymentRequest) {
            $callback->url = $this->context->link->getModuleLink(
                $this->name,
                'paymentrequestcallback',
                array(),
                true
            );
        } else {
            $bamboraUrl->accept = $this->context->link->getModuleLink(
                $this->name,
                'accept',
                array(),
                true
            );
            $bamboraUrl->decline = $this->context->link->getPageLink(
                'order',
                true,
                null,
                "step=3"
            );
            $callback->url = $this->context->link->getModuleLink(
                $this->name,
                'callback',
                array(),
                true
            );
        }
        $bamboraUrl->callbacks[] = $callback;

        $bamboraUrl->immediateredirecttoaccept = Configuration::get(
            'BAMBORA_IMMEDIATEREDIRECTTOACCEPT'
        ) ? 1 : 0;

        return $bamboraUrl;
    }

    public function getBamboraCheckoutSession($checkoutRequest)
    {
        $apiKey = $this->getApiKey();
        $api = new BamboraApi($apiKey);

        return $api->getcheckoutresponse($checkoutRequest);
    }

    /**
     * Get Payment Card Ids
     *
     * @param mixed $currency
     * @param mixed $amount
     *
     * @return array
     */
    public function getPaymentCardIds($currency, $amount)
    {
        $apiKey = $this->getApiKey();
        $api = new BamboraApi($apiKey);

        return $api->getAvaliablePaymentcardidsForMerchant($currency, $amount);
    }

    /**
     * Hook payment options for Prestashop 1.7
     *
     * @param mixed $params
     *
     * @return PrestaShop\PrestaShop\Core\Payment\PaymentOption[]
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        $cart = $params['cart'];
        if (!$this->checkCurrency($cart)) {
            return;
        }
        $currency = new Currency((int)$cart->id_currency);

        $minorUnits = BamboraCurrency::getCurrencyMinorunits($currency->iso_code);
        $totalAmountMinorunits = BamboraCurrency::convertPriceToMinorUnits(
            $cart->getOrderTotal(),
            $minorUnits,
            Configuration::get('BAMBORA_ROUNDING_MODE')
        );

        $paymentcardIds = $this->getPaymentCardIds(
            $currency->iso_code,
            $totalAmountMinorunits
        );

        $paymentInfoData = array(
            'paymentCardIds' => $paymentcardIds,
            'onlyShowLogoes' => Configuration::get(
                'BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT'
            )
        );
        $this->context->smarty->assign($paymentInfoData);

        $callToActionText = Tools::strlen(
            Configuration::get("BAMBORA_TITLE")
        ) > 0 ? Configuration::get("BAMBORA_TITLE") : "Worldline Checkout";

        $bamboraPaymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption(
        );
        $bamboraPaymentOption->setCallToActionText($callToActionText)
            ->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    'payment',
                    array(),
                    true
                )
            )
            ->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:bambora/views/templates/front/paymentinfo.tpl'
                )
            );

        $paymentOptions = array();
        $paymentOptions[] = $bamboraPaymentOption;

        return $paymentOptions;
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

        $order = null;
        if ($this->getPsVersion() === $this::V17) {
            $order = $params['order'];
        } else {
            $order = $params['objOrder'];
        }

        $payment = $order->getOrderPayments();
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

        return $this->display(__FILE__, 'views/templates/front/payment_return.tpl');
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
        if (isset($order) && $order->module == $this->name) {
            if (!$this->isPsVersionHigherThan177()) {
                $bamboraUiMessage = $this->processRemote();
                if (isset($bamboraUiMessage)) {
                    $this->buildOverlayMessage(
                        $bamboraUiMessage->type,
                        $bamboraUiMessage->title,
                        $bamboraUiMessage->message
                    );
                }

                if ($order->module == $this->name) {
                    $html .= $this->displayTransactionForm($order);
                }
            } else {
                $containPaymentWithTransactionId = false;
                $payments = $order->getOrderPayments();
                foreach ($payments as $payment) {
                    if (!empty($payment->transaction_id)) {
                        $containPaymentWithTransactionId = true;
                        break;
                    }
                }
                $apiKey = $this->getApiKey();
                $api = new BamboraApi($apiKey);
                $hasPaymentRequestPermissions = $api->checkIfMerchantHasPaymentRequestCreatePermissions(
                );
                if (!$containPaymentWithTransactionId && $hasPaymentRequestPermissions) {
                    if (Tools::isSubmit('createpaymentrequest')) {
                        if (!$this->getDbPaymentRequestByOrderId($order->id)) {
                            $html .= $this->createPaymentRequest($order);
                        } else {
                            $html = $this->displayError(
                                "There is already a payment request for this order."
                            );
                        }
                    }
                    if (Tools::isSubmit('deletepaymentrequest')) {
                        $html .= $this->deletePaymentRequest($order);
                    }
                    if (Tools::isSubmit('sendpaymentrequest')) {
                        $html .= $this->sendPaymentRequest($order);
                    }
                    $html .= $this->displayPaymentRequestForm($params);
                }
            }
        }

        return $html;
    }

    /**
     * Process Remote
     *
     * @return BamboraUiMessage|null
     */
    private function processRemote()
    {
        $bamboraUiMessage = null;
        if ((Tools::isSubmit('bambora-capture') || Tools::isSubmit(
                    'bambora-credit'
                ) || Tools::isSubmit('bambora-delete')) && Tools::getIsset(
                'bambora-transaction-id'
            ) && Tools::getIsset('bambora-currency-code')) {
            $bamboraUiMessage = new BamboraUiMessage();
            $result = "";
            try {
                $transactionId = Tools::getValue("bambora-transaction-id");
                $currencyCode = Tools::getValue("bambora-currency-code");
                $minorUnits = BamboraCurrency::getCurrencyMinorunits($currencyCode);
                $orderId = Tools::getValue("bambora-order-id");
                $apiKey = $this->getApiKey();
                $api = new BamboraApi($apiKey);

                if (Tools::isSubmit('bambora-capture')) {
                    $captureInputValue = Tools::getValue('bambora-capture-value');

                    $amountSanitized = str_replace(',', '.', $captureInputValue);
                    $amount = (float)$amountSanitized;
                    if (is_float($amount)) {
                        $amountMinorunits = BamboraCurrency::convertPriceToMinorUnits(
                            $amount,
                            $minorUnits,
                            Configuration::get('BAMBORA_ROUNDING_MODE')
                        );
                        $result = $api->capture(
                            $transactionId,
                            $amountMinorunits,
                            $currencyCode,
                            null
                        );
                    } else {
                        $bamboraUiMessage->type = 'issue';
                        $bamboraUiMessage->title = $this->l(
                            'Inputfield is not a valid number'
                        );

                        return $bamboraUiMessage;
                    }
                } elseif (Tools::isSubmit('bambora-credit')) {
                    $captureInputValue = Tools::getValue('bambora-credit-value');

                    $amountSanitized = str_replace(',', '.', $captureInputValue);
                    $amount = (float)$amountSanitized;
                    if (is_float($amount)) {
                        $amountMinorunits = BamboraCurrency::convertPriceToMinorUnits(
                            $amount,
                            $minorUnits,
                            Configuration::get('BAMBORA_ROUNDING_MODE')
                        );
                        $isCollectorBank = Tools::getValue('bambora-isCollector');
                        if ($isCollectorBank) {
                            $result = $api->credit(
                                $transactionId,
                                $amountMinorunits,
                                $currencyCode
                            );
                        } else {
                            $invoiceLine = $this->buildCreditInvoiceLine(
                                $amountMinorunits
                            );
                            $result = $api->credit(
                                $transactionId,
                                $amountMinorunits,
                                $currencyCode,
                                $invoiceLine
                            );
                        }
                    } else {
                        $bamboraUiMessage->type = 'issue';
                        $bamboraUiMessage->title = $this->l(
                            'Inputfield is not a valid number'
                        );

                        return $bamboraUiMessage;
                    }
                } elseif (Tools::isSubmit('bambora-delete')) {
                    $result = $api->delete($transactionId);
                }

                if ($result["meta"]["result"] === true) {
                    $logText = "";
                    if (Tools::isSubmit('bambora-capture')) {
                        $captureText = $this->l(
                            'The Payment was captured successfully'
                        );
                        $bamboraUiMessage->type = "capture";
                        $bamboraUiMessage->title = $captureText;
                        $logText = $captureText;
                    } elseif (Tools::isSubmit('bambora-credit')) {
                        $creditText = $this->l(
                            'The Payment was refunded successfully'
                        );
                        $bamboraUiMessage->type = "credit";
                        $bamboraUiMessage->title = $creditText;
                        $logText = $creditText;
                    } elseif (Tools::isSubmit('bambora-delete')) {
                        $deleteText = $this->l(
                            'The Payment was deleted successfully'
                        );
                        $bamboraUiMessage->type = "delete";
                        $bamboraUiMessage->title = $deleteText;
                        $logText = $deleteText;
                    }
                    //For Audit log

                    $employee = $this->context->employee;

                    $logText .= " :: OrderId: " . $orderId . " TransactionId: " . $transactionId . " Employee: " . $employee->firstname . " " . $employee->lastname . " " . $employee->email;
                    $this->writeLogEntry($logText, 1);
                } else {
                    $bamboraUiMessage->type = "issue";
                    $bamboraUiMessage->title = $this->l(
                        'An issue occurred, and the operation was not performed.'
                    );
                    if (isset($result["message"]) && isset($result["message"]["merchant"])) {
                        $message = $result["message"]["merchant"];

                        if (isset($message["action"]) && $result["action"]["source"] == "ePayEngine" && ($result["action"]["code"] == "113" || $result["action"]["code"] == "114")) {
                            preg_match_all('!\d+!', $message, $matches);
                            foreach ($matches[0] as $match) {
                                $convertedAmount = Tools::displayPrice(
                                    (float)BamboraCurrency::convertPriceFromMinorUnits(
                                        $match,
                                        $minorUnits
                                    )
                                );
                                $message = str_replace(
                                    $match,
                                    $convertedAmount,
                                    $message
                                );
                            }
                        }
                        $bamboraUiMessage->message = $message;
                    }
                }
            } catch (Exception $e) {
                $this->displayError($e->getMessage());
            }
        }

        return $bamboraUiMessage;
    }

    /**
     * Build Credit Invoice Line
     *
     * @param mixed $amount
     *
     * @return array
     */
    private function buildCreditInvoiceLine($amount)
    {
        $result = array(
            "description" => "Prestashop credit item",
            "id" => "1",
            "linenumber" => "1",
            "quantity" => 1,
            "text" => "Prestashop credit item",
            "totalprice" => $amount,
            "totalpriceinclvat" => $amount,
            "totalpricevatamount" => 0,
            "unit" => "pcs.",
            "vat" => 0
        );

        return $result;
    }

    public function writeLogEntry($message, $severity)
    {
        if ($this->getPsVersion() === Bambora::V15) {
            Logger::addLog($message, $severity);
        } else {
            PrestaShopLogger::addLog($message, $severity);
        }
    }

    /**
     * Build Overlay Message
     *
     * @param mixed $type
     * @param mixed $title
     * @param mixed $message
     */
    private function buildOverlayMessage($type, $title, $message)
    {
        $html = '
            <a id="bambora-inline" href="#data"></a>
            <div id="bambora-overlay"><div id="data" class="row bambora-overlay-data"><div id="bambora-message" class="col-lg-12">';

        if ($type == "issue") {
            $html .= $this->showExclamation();
        } else {
            $html .= $this->showCheckmark();
        }
        $html .= '<div id="bambora-overlay-message-container">';

        if (Tools::strlen($message) > 0) {
            $html .= '<p id="bambora-overlay-message-title-with-message">' . $title . '</p>';
            $html .= '<hr><p id="bambora-overlay-message-message">' . $message . '</p>';
        } else {
            $html .= '<p id="bambora-overlay-message-title">' . $title . '</p>';
        }

        $html .= '</div></div></div></div>';

        echo $html;
    }

    /**
     * Show Exclamation
     *
     * @return string
     */
    private function showExclamation()
    {
        $html = '<div class="bambora-circle bambora-exclamation-circle">
                        <div class="bambora-exclamation-stem"></div>
                        <div class="bambora-exclamation-dot"></div>
                    </div>';

        return $html;
    }

    /**
     * Show Checkmark
     *
     * @return string
     */
    private function showCheckmark()
    {
        $html = '<div class="bambora-circle bambora-checkmark-circle">
                        <div class="bambora-checkmark-stem"></div>
                    </div>';

        return $html;
    }

    /**
     * Display Transaction Form
     *
     * @param mixed $order
     *
     * @return string
     */
    private function displayTransactionForm($order)
    {
        $payments = $order->getOrderPayments();
        $transactionId = $payments[0]->transaction_id;

        $html = $this->buildBamboraContainerStartTag();

        if (empty($transactionId)) {
            $html .= '<div class="card-header"><h3 class="card-header-title">Worldline Checkout</h3></div><div class=card-body"><div class="info-block"> No payment transaction was found</div></div>';
            $html .= '<br>';
            $html .= $this->buildBamboraContainerStopTag();

            return $html;
        }
        $cardBrand = $payments[0]->card_brand;

        $html .= $this->buildBamboraContainerHeaderTag($cardBrand);

        $html .= $this->getHtmlcontent($transactionId, $order);

        $html .= $this->buildBamboraContainerStopTag();

        return $html;
    }

    /**
     * Build Bambora Container Start Tag
     *
     * @return string
     */
    private function buildBamboraContainerStartTag()
    {
        if ($this->isPsVersionHigherThan177()) {
            $html = '<script type="text/javascript" src="' . $this->_path . 'views/js/bamboraScripts177.js" charset="UTF-8"></script>';
        } else {
            $html = '<script type="text/javascript" src="' . $this->_path . 'views/js/bamboraScripts.js" charset="UTF-8"></script>';
        }
        if ($this->getPsVersion() === $this::V15) {
            $html .= '<style type="text/css">
                            .table td{white-space:nowrap;overflow-x:auto;}
                        </style>';
            $html .= '<br /><fieldset><legend><img src="../img/admin/money.gif">Worldline Checkout</legend>';
        } elseif ($this->getPsVersion() === $this::V16) {
            $html .= '<div class="row" >';
            $html .= '<div class="col-lg-12">';
            $html .= '<div class="panel bambora-width-all-space" style="overflow:auto">';
        } elseif ($this->isPsVersionHigherThan177()) {
            $html .= '<div class="card mt-2>';
            $html .= '<div class="card-body">';
        } else {
            $html .= '<div class="row" >';
            $html .= '<div class="col-lg-12">';
            $html .= '<div class="panel bambora-width-all-space" style="overflow:auto">';
        }

        return $html;
    }

    /**
     * Build Bambora Container Stop Tag
     *
     * @return string
     */
    private function buildBamboraContainerStopTag()
    {
        if ($this->getPsVersion() === $this::V15) {
            $html = "</fieldset>";
        } elseif ($this->isPsVersionHigherThan177()) {
            $html = "</div>";
            $html .= "</div>";
        } else {
            $html = "</div>";
            $html .= "</div>";
            $html .= "</div>";
        }

        return $html;
    }

    /**
     * Build Bambora Container Header Tag
     *
     * @param string $cardBrand
     *
     * @return string
     */
    private function buildBamboraContainerHeaderTag($cardBrand)
    {
        if ($this->isPsVersionHigherThan177()) {
            $html = '<div class="card-header"> <h3 class="card-header-title">';
            if (!empty($cardBrand)) {
                $html .= "Worldline Checkout ({$cardBrand})";
            } else {
                $html .= "Worldline Checkout";
            }
            $html .= '</h3></div>';
        } else {
            $html = '<div class="panel-heading">';
            if (!empty($cardBrand)) {
                $html .= "Worldline Checkout ({$cardBrand})";
            } else {
                $html .= "Worldline Checkout";
            }
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Get Html Content
     *
     * @param mixed $transactionId
     * @param mixed $order
     *
     * @return string
     */
    private function getHtmlcontent($transactionId, $order)
    {
        $html = "";

        try {
            $apiKey = $this->getApiKey();
            $api = new BamboraApi($apiKey);

            $bamboraTransaction = $api->gettransaction($transactionId);

            if (!$bamboraTransaction["meta"]["result"]) {
                return $this->merchantErrorMessage(
                    $bamboraTransaction["meta"]["message"]["merchant"]
                );
            }

            $bamboraTransactionOperations = $api->gettransactionoperations(
                $transactionId
            );

            if (!$bamboraTransactionOperations["meta"]["result"]) {
                return $this->merchantErrorMessage(
                    $bamboraTransactionOperations["meta"]["message"]["merchant"]
                );
            }

            $transactionInfo = $bamboraTransaction["transaction"];
            $transactionOperations = $bamboraTransactionOperations["transactionoperations"];
            $currency = new Currency($order->id_currency);
            $iso_code = $currency->iso_code;
            if ($this->isPsVersionHigherThan177()) {
                $html .= '<div class="card-body">

                        <div>'
                    . $this->buildPaymentTable(
                        $transactionInfo,
                        $iso_code,
                        $transactionOperations
                    )
                    . $this->buildButtonsForm($transactionInfo)
                    . '</div>

                        <div class="">'
                    . $this->createCheckoutTransactionOperationsHtml(
                        $transactionOperations,
                        $iso_code
                    )
                    . '</div>
                      </div>'
                    . '</div>';
            } else {
                $html .= '<div class="row">

                        <div class="col-xs-12 col-sm-12 col-md-4 col-lg-3">'
                    . $this->buildPaymentTable(
                        $transactionInfo,
                        $iso_code,
                        $transactionOperations
                    )
                    . $this->buildButtonsForm($transactionInfo)
                    . '</div>

                        <div class="col-xs-12 col-sm-12 col-md-8 col-lg-6">'
                    . $this->createCheckoutTransactionOperationsHtml(
                        $transactionOperations,
                        $currency
                    )
                    . '</div>

                        <div class="col-lg-3 text-center hidden-xs hidden-sm hidden-md">'
                    . $this->buildLogodiv()
                    . '</div>'
                    . '</div>';
            }
        } catch (Exception $e) {
            $this->displayError($e->getMessage());
        }

        return $html;
    }

    /**
     * Merchant Error Message
     *
     * @param mixed $reason
     *
     * @return string
     */
    private function merchantErrorMessage($reason)
    {
        $message = "An error occured.";
        if (isset($reason)) {
            $message .= "<br />Reason: " . $reason;
        }

        return $message;
    }

    /**
     * Build Payment Table
     *
     * @param mixed $transactionInfo
     * @param mixed $currency
     * @param mixed $transactionOperations
     *
     * @return string
     */
    private function buildPaymentTable(
        $transactionInfo,
        $currency,
        $transactionOperations
    ) {
        $html = '<table class="bambora-table">';
        $html .= '<tr><td colspan="2" class="bambora-table-title"><strong>' . $this->l(
                'Payment'
            ) . '</strong></td></tr>';
        $card_group_id = $transactionInfo["information"]["paymenttypes"][0]["groupid"];
        $card_name = $transactionInfo["information"]["paymenttypes"][0]["displayname"];

        $html .= '<img class="bambora_paymenttype_img" src="https://d3r1pwhfz7unl9.cloudfront.net/paymentlogos/' . $card_group_id . '.svg" alt="' . $card_name . '" title="' . $card_name . '" />';
        if (isset($transactionOperations[0]["transactionoperations"][0]["acquirerdata"][0]["key"])) {
            if ($transactionOperations[0]["transactionoperations"][0]["acquirerdata"][0]["key"] == "nordeaepaymentfi.customerbank") {
                $bank_name = $transactionOperations[0]["transactionoperations"][0]["acquirerdata"][0]["value"];
            }
            if (isset($bank_name) && $bank_name != "") {
                $html .= '<img style="max-height:51px;clear: both;" src="https://d3r1pwhfz7unl9.cloudfront.net/paymentlogos/bank-' . $bank_name . '.svg" alt="' . $bank_name . '" title="' . $bank_name . '" />';
            }
        }
        if (isset($transactionInfo["information"]["wallets"][0]["name"])) {
            $wallet_name = $transactionInfo["information"]["wallets"][0]["name"];
            if ($wallet_name == "MobilePay") {
                $wallet_img = "13.svg";
            }
            if ($wallet_name == "Vipps") {
                $wallet_img = "14.svg";
            }
            if ($wallet_name == "GooglePay") {
                $wallet_img  = "22.svg";
                $wallet_name = "Google Pay";
            }
            if ($wallet_name == "ApplePay") {
                $wallet_img  = "21.svg";
                $wallet_name = "Apple Pay";
            }
            if (isset($wallet_img)) {
                $html .= '&nbsp;<img style="max-height:51px;clear: both;" src="https://d3r1pwhfz7unl9.cloudfront.net/paymentlogos/' . $wallet_img . '" alt="' . $wallet_name . '" title="' . $wallet_name . '" />';
            }
        }
        $html .= '<tr><td>' . $this->l('Amount') . ':</td>';

        $html .= '<td><div class="badge bambora-badge-big badge-success" title="' . $this->l(
                'Amount available for capture'
            ) . '">';


        $amount = BamboraCurrency::convertPriceFromMinorUnits(
            $transactionInfo["total"]["authorized"],
            $transactionInfo["currency"]["minorunits"]
        );

        //$formattedAmount = Tools::displayPrice((float)$amount, $currency);
        $formattedAmount = $this->context->currentLocale->formatPrice(
            $amount,
            $currency
        );

        $html .= $formattedAmount . '</div></td></tr>';
        $html .= '<tr><td>' . $this->l(
                'Order Id'
            ) . ':</td><td>' . $transactionInfo["orderid"] . '</td></tr>';
        $formattedCardnumber = "";
        if (isset($transactionInfo["information"]["primaryaccountnumbers"][0])) {
            $formattedCardnumber = BamboraHelpers::formatTruncatedCardnumber(
                $transactionInfo["information"]["primaryaccountnumbers"][0]["number"]
            );
        }
        if ($formattedCardnumber != "") {
            $html .= '<tr><td>' . $this->l(
                    'Cardnumber'
                ) . ':</td><td>' . $formattedCardnumber . '</td></tr>';
        }

        if (isset($transactionInfo["information"]["acquirerreferences"][0])) {
            $html .= '<tr><td>' . $this->l(
                    'Acquirer Reference'
                ) . ':</td><td>' . $transactionInfo["information"]["acquirerreferences"][0]["reference"] . '</td></tr>';
        }

        $html .= '<tr><td>' . $this->l(
                'Status'
            ) . ':</td><td>' . $this->checkoutStatus(
                $transactionInfo["status"]
            ) . '</td></tr>';

        if (isset($transactionInfo["information"]["ecis"])) {
            $eci = $this->getLowestECI($transactionInfo["information"]["ecis"]);
            if ($eci != "") {
                $html .= '<tr><td>' . $this->l(
                        'ECI'
                    ) . ':</td><td>' . $eci . '</td></tr>';
            }
        }

        if (isset($transactionInfo["information"]["exemptions"])) {
            $distinctExemptions = $this->getDistinctExemptions(
                $transactionInfo["information"]["exemptions"]
            );
            if ($distinctExemptions != "" && $distinctExemptions != null) {
                $html .= '<tr><td>' . $this->l(
                        'Exemptions'
                    ) . ':</td><td>' . $this->getDistinctExemptions(
                        $transactionInfo["information"]["exemptions"]
                    ) . '</td></tr>';
            }
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Set the first letter to uppercase
     *
     * @param string $status
     *
     * @return string
     */
    private function checkoutStatus($status)
    {
        if (!isset($status)) {
            return "";
        }
        $firstLetter = Tools::substr($status, 0, 1);
        $firstLetterToUpper = Tools::strtoupper($firstLetter);
        $result = str_replace($firstLetter, $firstLetterToUpper, $status);

        return $result;
    }

    #endregion

    #region FrontOffice

    private function getLowestECI($ecis)
    {
        foreach ($ecis as $eci) {
            $eciValues[] = $eci['value'];
        }

        return min($eciValues);
    }

    private function getDistinctExemptions($exemptions)
    {
        $exemptionValues = null;
        if (isset($exemptions)) {
            foreach ($exemptions as $exemption) {
                $exemptionValues[] = $exemption['value'];
            }
        }


        return implode(",", array_unique($exemptionValues));
    }

    private function buildButtonsForm($transactionInfo)
    {
        $html = '';
        if ($transactionInfo["available"]["capture"] > 0 || $transactionInfo["available"]["credit"] > 0 || $transactionInfo["candelete"] == 'true') {
            $html .= '<form name="bambora-remote" action="' . $_SERVER["REQUEST_URI"] . '" method="post" class="bambora-display-inline" id="bambora-action" >'
                . '<input type="hidden" name="bambora-transaction-id" value="' . $transactionInfo["id"] . '" />'
                . '<input type="hidden" name="bambora-order-id" value="' . $transactionInfo["orderid"] . '" />'
                . '<input type="hidden" name="bambora-currency-code" value="' . $transactionInfo["currency"]["code"] . '" />';

            $html .= '<br />';

            $html .= '<div id="bambora-transaction-controls-container" class="bambora-buttons clearfix">';
            $html .= $this->buildSpinner();

            $minorUnits = $transactionInfo["currency"]["minorunits"];

            $availableForCapture = BamboraCurrency::convertPriceFromMinorUnits(
                $transactionInfo["available"]["capture"],
                $minorUnits
            );

            $availableForCredit = BamboraCurrency::convertPriceFromMinorUnits(
                $transactionInfo["available"]["credit"],
                $minorUnits
            );

            if ($this->isCollectorBank(
                $transactionInfo
            )) { //Walley (old Collector Bank)
                $editable = false;
            } else {
                $editable = true;
            }

            if ($availableForCapture > 0) {
                $html .= $this->buildTransactionControlInclTextField(
                    'capture',
                    $this->l('Capture'),
                    "btn bambora-confirm-btn",
                    true,
                    $availableForCapture,
                    $transactionInfo["currency"]["code"],
                    $editable
                );
            }

            if ($availableForCredit > 0) {
                $html .= $this->buildTransactionControlInclTextField(
                    'credit',
                    $this->l('Refund'),
                    "btn bambora-credit-btn",
                    true,
                    $availableForCredit,
                    $transactionInfo["currency"]["code"],
                    $editable
                );
            }

            if ($transactionInfo["candelete"] == 'true') {
                $html .= $this->buildTransactionControl(
                    'delete',
                    $this->l('Delete'),
                    "btn bambora-delete-btn"
                );
            }

            $html .= '</div></form>';
            $html .= '<div id="bambora-format-error" class="alert alert-danger"><strong>' . $this->l(
                    'Warning'
                ) . ' </strong>' . $this->l(
                    'The amount you entered was in the wrong format. Please try again!'
                ) . '</div>';
        }

        return $html;
    }

    /**
     * Build Spinner
     *
     * @return string
     */
    private function buildSpinner()
    {
        $html = '<div id="bambora-spinner" class="bambora-button-frame bambora-button-frame-increesed-size row"><div class="col-lg-10">' . $this->l(
                'Working'
            ) . '... </div><div class="col-lg-2"><div class="bambora-spinner"><img src="' . $this->_path . 'views/img/arrows.svg" class="bambora-spinner"></div></div></div>';

        return $html;
    }

    /**
     * Check if transaction is for Walley (old Collector Bank)
     *
     * @param mixed $transactionInfo
     *
     * @return boolean
     */

    private function isCollectorBank($transactionInfo)
    {
        if (isset($transactionInfo["information"]["paymenttypes"][0])) {
            $paymentTypesGroupId = $transactionInfo["information"]["paymenttypes"][0]["groupid"];
            $paymentTypesId = $transactionInfo["information"]["paymenttypes"][0]["id"];
            if ($paymentTypesGroupId == 19 && $paymentTypesId == 40) { //Walley (Collector Bank)
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Build Transaction Control Incl Text Field
     *
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
    private function buildTransactionControlInclTextField(
        $type,
        $value,
        $class,
        $addInputField = false,
        $valueOfInputfield = 0,
        $currencycode = "",
        $editable = true
    ) {
        $tooltip = $this->l('Example: 1234.56');
        if (!$editable) {
            $readonly = "readonly";
            $tooltip = '';
            if ($type == "credit") {
                $tooltip = $this->l(
                    'With Payment Provider Walley only full refund is possible here. For partial refund, please use Bambora Merchant Portal.'
                );
            }
            if ($type == "capture") {
                $tooltip = $this->l(
                    'With Payment Provider Walley only full capture is possible here. For partial capture, please use Bambora Merchant Portal.'
                );
            }
        } else {
            $readonly = "";
        }
        if ($editable) {
            $isCollector = 0;
        } else {
            $isCollector = 1;
        }
        if ($this->isPsVersionHigherThan177()) {
            $html = '<div style="float:left;">
                    <div style="margin-left:20px;"  style="float:left;">
                        <div class="bambora-confirm-frame" style="float:left; padding-right: 10px;">
                            <input  class="' . $class . '" name="unhide-' . $type . '" type="button" value="' . Tools::strtoupper(
                    $value
                ) . '"   />
                       </div>
                        <div class="row bambora-hidden bambora-button-frame-177" data-hasinputfield="' . $addInputField . '" style="">
                            <div class="col-xs-2 " style="float:left; padding-right: 10px;padding-top:5px;">
                                <a class="bambora-cancel" style="float:left;"></a>
                             </div>
                             <div class="col-xs-5" style="float:left; padding-right: 10px; padding-top:5px;">
                                <input name ="bambora-isCollector" value ="' . $isCollector . '" type="hidden"/>
                                <input id="bambora-action-input" type="text"   required="required" ' . $readonly . ' name="bambora-' . $type . '-value" value="' . $valueOfInputfield . '"  />
                               <p>' . $currencycode . '</p>
                               <p><em>' . $tooltip . '</em></p>
                            </div>
                             <div class="col-xs-5" style="float:left; padding-right: 10px;">
                                <input class="' . $class . '" name="bambora-' . $type . '" type="submit" value="' . Tools::strtoupper(
                    $value
                ) . '"  />
                             </div>
                         </div>
                      </div>
                   </div>';
        } else {
            $html = '<div>
                    <div class="bambora-float-left">
                        <div class="bambora-confirm-frame">
                            <input  class="' . $class . '" name="unhide-' . $type . '" type="button" value="' . Tools::strtoupper(
                    $value
                ) . '" />
                       </div>
                        <div class="bambora-button-frame bambora-hidden bambora-button-frame-increesed-size row" data-hasinputfield="' . $addInputField . '">'
                . '<div class="col-xs-3">
                                <a class="bambora-cancel"></a>
                             </div>
                             <div class="col-xs-5">
                                <input name ="bambora-isCollector" value ="' . $isCollector . '" type="hidden"/>
                                <input id="bambora-action-input" type="text" data-toggle="tooltip" title="' . $tooltip . '" required="required" ' . $readonly . ' name="bambora-' . $type . '-value" value="' . $valueOfInputfield . '" />
                               <p>' . $currencycode . '</p>
                            </div>
                             <div class="col-xs-4">
                                <input class="' . $class . '" name="bambora-' . $type . '" type="submit" value="' . Tools::strtoupper(
                    $value
                ) . '" />
                             </div>
                         </div>
                      </div>
                   </div>';
        }

        return $html;
    }

    /**
     * Build Transaction Control
     *
     * @param mixed $type
     * @param mixed $value
     * @param mixed $class
     *
     * @return string
     */
    private function buildTransactionControl($type, $value, $class)
    {
        if ($this->isPsVersionHigherThan177()) {
            $html = '<div style="float:left;"> 
                   <div style="margin-left:20px;">';
            $html .= '<div class="bambora-confirm-frame" style="float:left;">
                           <input  class="' . $class . '" name="unhide-' . $type . '" type="button" value="' . Tools::strtoupper(
                    $value
                ) . '" />
                      </div>
                      <div class="bambora-button-frame bambora-hidden bambora-normalSize row" data-hasinputfield="false" style="float:left;">
                           <div class="col-xs-6" style="float:left;">
                               <a class="bambora-cancel"></a>
                          </div>
                          <div class="col-xs-6" style="float:left;">
                             <input class="' . $class . '" name="bambora-' . $type . '" type="submit" value="' . Tools::strtoupper(
                    $value
                ) . '" />
                          </div>
                      </div>
                   </div>
                </div>';
        } else {
            $html = '<div>
                   <div class="bambora-float-left">';
            $html .= '<div class="bambora-confirm-frame">
                           <input  class="' . $class . '" name="unhide-' . $type . '" type="button" value="' . Tools::strtoupper(
                    $value
                ) . '" />
                      </div>
                      <div class="bambora-button-frame bambora-hidden bambora-normalSize row" data-hasinputfield="false">
                           <div class="col-xs-6">
                               <a class="bambora-cancel"></a>
                          </div>
                          <div class="col-xs-6">
                             <input class="' . $class . '" name="bambora-' . $type . '" type="submit" value="' . Tools::strtoupper(
                    $value
                ) . '" />
                          </div>
                      </div>
                   </div>
                </div>';
        }

        return $html;
    }

    /**
     * Create Checkout Transaction Operations Html
     *
     * @param mixed $transactionOperations
     * @param mixed $currency
     *
     * @return string
     */
    private function createCheckoutTransactionOperationsHtml(
        $transactionOperations,
        $currency
    ) {
        $res = "<table class='bambora-operations-table' border='0' width='100%'>";
        $res .= '<tr><td colspan="6" class="bambora-table-title"><strong>' . $this->l(
                'Transaction Operations'
            ) . '</strong></td></tr>';
        $res .= '<th>' . $this->l('Date') . '</th>';
        $res .= '<th>' . $this->l('Action') . '</th>';
        $res .= '<th>' . $this->l('Amount') . '</th>';
        $res .= '<th>' . $this->l('Operation ID') . '</th>';
        $res .= '<th>' . $this->l('Parent Operation ID') . '</th>';

        $res .= $this->createTransactionOperationItems(
            $transactionOperations,
            $currency
        );

        $res .= '</table>';

        return $res;
    }

    /**
     * Create Transaction Operation Items
     *
     * @param mixed $transactionOperations
     * @param mixed $currency
     *
     * @return string
     */
    private function createTransactionOperationItems(
        $transactionOperations,
        $currency
    ) {
        $res = "";
        foreach ($transactionOperations as $operation) {
            $eventInfo = BamboraHelpers::getEventText($operation);
            if ($eventInfo['description'] != null) {
                $res .= '<tr>';
                $date = str_replace(
                    "T",
                    " ",
                    Tools::substr($operation["createddate"], 0, 19)
                );
                $res .= '<td>' . Tools::displayDate($date) . '</td>';
                $res .= '<td><strong>' . $eventInfo['title'] . '</strong></td>';
                if ($operation["amount"] > 0) {
                    $amount = BamboraCurrency::convertPriceFromMinorUnits(
                        $operation["amount"],
                        $operation["currency"]["minorunits"]
                    );
                    $res .= '<td>' . $this->context->currentLocale->formatPrice(
                            $amount,
                            $currency
                        ) . '</td>';
                } else {
                    $res .= '<td> &nbsp; </td>';
                }

                $res .= '<td>' . $operation["id"] . '</td>';

                if (key_exists(
                        "parenttransactionoperationid",
                        $operation
                    ) && $operation["parenttransactionoperationid"] > 0) {
                    $res .= '<td>' . $operation["parenttransactionoperationid"] . '</td>';
                } else {
                    $res .= '<td> - </td>';
                }

                $res .= '</tr>';
                $res .= '<tr>';
                $res .= '<td colspan="5"><i>' . $eventInfo['description'] . '</i></td>';
                $res .= '</tr>';
                if (key_exists("transactionoperations", $operation) && count(
                        $operation["transactionoperations"]
                    ) > 0) {
                    $res .= $this->createTransactionOperationItems(
                        $operation["transactionoperations"],
                        $currency
                    );
                }
            } else {
                if (key_exists("transactionoperations", $operation) && count(
                        $operation["transactionoperations"]
                    ) > 0) {
                    $res .= $this->createTransactionOperationItems(
                        $operation["transactionoperations"],
                        $currency
                    );
                }
            }
        }
        $res = str_replace("CollectorBank", "Walley", $res);

        return $res;
    }

    /**
     * Build Logo Div
     *
     * @return string
     */
    private function buildLogodiv()
    {
        $html = '<a href="https://merchant.bambora.com" alt="" title="' . $this->l(
                'Go to Bambora Merchant Administration'
            ) . '" target="_blank">';
        $html .= '<img class="bambora-logo" src="https://d3r1pwhfz7unl9.cloudfront.net/bambora/worldline-logo.svg" width="150px;" />';
        $html .= '</a>';
        $html .= '<br/><div class="worldline-info">' . $this->l(
                'Bambora will now be known as Worldline. Together, we create digital payments for a trusted world.'
            ) . ' </div>';
        $html .= '<div><br/><a href="https://merchant.bambora.com"  alt="" title="' . $this->l(
                'Go to Bambora Merchant Administration'
            ) . '" target="_blank">' . $this->l(
                'Go to Bambora Merchant Administration'
            ) . '</a></div>';

        return $html;
    }

    /**
     * Get paymentRequest  from the database with order id.
     *
     * @param mixed $id_order
     *
     * @return mixed
     */
    private function getDbPaymentRequestByOrderId($id_order)
    {
        $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'bambora_payment_requests WHERE id_order = ' . pSQL(
                $id_order
            );

        return $this->getDbPaymentRequests($query);
    }

    /**
     * Get db paymentRequest for query.
     *
     * @param mixed $query
     *
     * @return mixed
     */
    private function getDbPaymentRequests($query)
    {
        $paymentRequests = Db::getInstance()->executeS($query);

        if (!isset($paymentRequests) || count(
                $paymentRequests
            ) === 0 || !isset($paymentRequests[0]['payment_request_id'])) {
            return false;
        }

        return $paymentRequests[0];
    }

    /**
     * Create payment request.
     *
     * @param Order $order
     *
     * @return mixed
     * @throws Exception
     *
     */
    private function createPaymentRequest($order)
    {
        $html = '';

        try {
            $cart = Cart::getCartByOrderId($order->id);
            $currency = new Currency($order->id_currency);
            $invoiceAddress = new Address((int)$cart->id_address_invoice);
            $deliveryAddress = new Address((int)$cart->id_address_delivery);
            $bamboraCustomer = $this->createBamboraCustomer($cart, $invoiceAddress);
            $bamboraOrder = $this->createBamboraOrder(
                $cart,
                $invoiceAddress,
                $deliveryAddress
            );
            $bamboraUrl = $this->createBamboraUrl(true);


            $paymentRequest = new BamboraCheckoutPaymentRequest();
            $paymentRequestParameters = new BamboraCheckoutPaymentRequestParameters(
            );
            $paymentRequest->description = Tools::getValue(
                'bambora_paymentrequest_requester_description'
            );
            $paymentRequest->reference = "PrestashopPaymentRequest" . $order->id;


            $terms = Configuration::get('BAMBORA_TERMS_URL');

            if (isset($terms) && $terms != "") {
                $paymentRequest->termsurl = $terms;
            }


            $paymentRequestParameters->instantcaptureamount = Configuration::get(
                'BAMBORA_INSTANTCAPTURE'
            ) == 1 ? $bamboraOrder->amount : 0;
            $paymentRequestParameters->customer = $bamboraCustomer;
            $paymentRequestParameters->order = $bamboraOrder;
            $paymentRequestParameters->url = $bamboraUrl;


            $language = new Language((int)$cart->id_lang);
            $paymentWindowId = Configuration::get('BAMBORA_PAYMENTWINDOWID');

            $paymentRequestParameters->paymentwindow = new BamboraCheckoutRequestPaymentWindow(
            );
            $paymentRequestParameters->paymentwindow->id = is_numeric(
                $paymentWindowId
            ) ? $paymentWindowId : 1;
            $paymentRequestParameters->paymentwindow->language = str_replace(
                "_",
                "-",
                $language->locale
            );

            $paymentRequest->parameters = $paymentRequestParameters;

            $apiKey = $this->getApiKey();
            $api = new BamboraApi($apiKey);
            $jsonData = json_encode($paymentRequest);
            $createPaymentRequest = $api->createPaymentRequest($jsonData);


            if ($createPaymentRequest['meta']['result']) {
                $message = $this->l(
                        'Payment request created with ID:'
                    ) . $createPaymentRequest['id'];
                $msg = new Message();
                $this->addDbPaymentRequest(
                    $order->id,
                    $cart->id,
                    $createPaymentRequest['id'],
                    $createPaymentRequest['url']
                );
                $message = strip_tags($message, '<br><a></a>');
                if (Validate::isCleanHtml($message)) {
                    $msg->message = $message;
                    $msg->id_order = (int)$order->id;
                    $msg->private = 1;
                    $msg->add();
                }
                $html = $this->displayConfirmation($this->l($message));
            } else {
                throw new Exception($createPaymentRequest['meta']['message']);
            }
        } catch (Exception $e) {
            $html = $this->displayError($e->getMessage());
        }

        return $html;
    }

    /**
     * Add the transaction to the database.
     *
     * @param mixed $id_order
     * @param mixed $id_cart
     * @param mixed $payment_request_id
     * @param mixed $url
     *
     * @return bool
     */
    public function addDbPaymentRequest(
        $id_order,
        $id_cart,
        $payment_request_id,
        $url
    ) {
        $query = 'INSERT INTO ' . _DB_PREFIX_ . 'bambora_payment_requests
                (id_order, id_cart, payment_request_id, payment_request_url, date_add)
                VALUES
                (' . pSQL($id_order) . ', ' . pSQL($id_cart) . ', \'' . pSQL(
                $payment_request_id
            ) . '\',  \'' . pSQL($url) . '\', NOW() )';

        return $this->executePaymentRequestDbQuery($query);
    }

    /**
     * Execute database query.
     *
     * @param mixed $query
     *
     * @return bool
     */
    private function executePaymentRequestDbQuery($query)
    {
        try {
            if (!Db::getInstance()->Execute($query)) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    #endregion

    #region Common

    /**
     * Delete payment request.
     *
     * @param Order $order
     *
     * @return mixed
     * @throws Exception
     *
     */
    private function deletePaymentRequest($order)
    {
        $html = '';

        try {
            $paymentRequest = $this->getDbPaymentRequestByOrderId($order->id);

            $apiKey = $this->getApiKey();
            $api = new BamboraApi($apiKey);
            $deletePaymentRequest = $api->deletePaymentRequest(
                $paymentRequest['payment_request_id']
            );

            if ($deletePaymentRequest['meta']['result']) {
                $message = $this->l(
                        'Payment request deleted. ID:'
                    ) . $paymentRequest['payment_request_id'];
                $msg = new Message();

                $this->deleteDbPaymentRequest($paymentRequest['payment_request_id']);
                $message = strip_tags($message, '<br><a></a>');
                if (Validate::isCleanHtml($message)) {
                    $msg->message = $message;
                    $msg->id_order = (int)$order->id;
                    $msg->private = 1;
                    $msg->add();
                }
                $html = $this->displayConfirmation($this->l($message));
            } else {
                throw new Exception($deletePaymentRequest['meta']['message']);
            }
        } catch (Exception $e) {
            $html = $this->displayError($e->getMessage());
        }

        return $html;
    }

    /**
     * Delete a payment request from db
     *
     * @param mixed $payment_request_id
     *
     * @return bool
     */
    public function deleteDbPaymentRequest($payment_request_id)
    {
        if (!$payment_request_id) {
            return false;
        }

        $query = 'DELETE FROM ' . _DB_PREFIX_ . 'bambora_payment_requests WHERE payment_request_id="' . pSQL(
                $payment_request_id
            ) . '"';

        return $this->executePaymentRequestDbQuery($query);
    }

    /**
     * Send payment request email
     *
     * @param Order $order
     *
     * @return mixed
     * @throws Exception
     *
     */
    private function sendPaymentRequest($order)
    {
        $html = '';

        try {
            $paymentRequest = $this->getDbPaymentRequestByOrderId($order->id);
            $recipient = new BamboraCheckoutPaymentRequestEmailRecipient();

            $recipient->replyto = new BamboraCheckoutPaymentRequestEmailRecipientAddress(
            );


            $recipient->replyto->name = Tools::getValue(
                'bambora_paymentrequest_replyto_name'
            );
            $recipient->replyto->email = Tools::getValue(
                'bambora_paymentrequest_replyto_email'
            );
            $recipient->message = Tools::getValue(
                'bambora_paymentrequest_email_message'
            );
            $recipient->to = new BamboraCheckoutPaymentRequestEmailRecipientAddress(
            );
            $recipient->to->email = Tools::getValue(
                'bambora_paymentrequest_recipient_email'
            );
            $recipient->to->name = Tools::getValue(
                'bambora_paymentrequest_recipient_name'
            );
            $jsonData = json_encode($recipient);
            $apiKey = $this->getApiKey();
            $api = new BamboraApi($apiKey);
            $sendPaymentRequestEmail = $api->sendPaymentRequestEmail(
                $paymentRequest['payment_request_id'],
                $jsonData
            );
            if ($sendPaymentRequestEmail['meta']['result']) {
                $message = $this->l('Payment request email sent');
                $msg = new Message();
                $message = strip_tags($message, '<br><a></a>');
                if (Validate::isCleanHtml($message)) {
                    $msg->message = $message;
                    $msg->id_order = (int)$order->id;
                    $msg->private = 1;
                    $msg->add();
                }
                $html = $this->displayConfirmation($this->l($message));
            } else {
                throw new Exception($sendPaymentRequestEmail['meta']['message']);
            }
        } catch (Exception $e) {
            $html = $this->displayError($e->getMessage());
        }

        return $html;
    }

    /**
     * Display the payment request form.
     *
     * @param mixed $params
     *
     * @return mixed
     */
    private function displayPaymentRequestForm($params)
    {
        $order = new Order($params['id_order']);


        $paymentRequest = $this->getDbPaymentRequestByOrderId($order->id);
        $employee = new Employee($this->context->cookie->id_employee);
        $currency = new Currency($order->id_currency);
        $customer = new Customer($order->id_customer);
        $currencyIsoCode = $currency->iso_code;
        // Get default Language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form_create = array();

        $fields_form_send_email = array();

        $fields_form_delete = array();
        $fields_form_create['form'] = array(
            'title' => $this->l('Create Payment Request'),
            'legend' => array(
                'title' => $this->l('Create Payment Request'),
                'size' => 20,
                'class' => 'header',
                'icon' => 'icon-cogs'
            ),
            'input' => array(
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Description'),
                    'name' => 'bambora_paymentrequest_requester_description',
                    'rows' => 3,
                    'cols' => 50,
                    'size' => 150,
                    'required' => false,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Amount'),
                    'name' => 'bambora_paymentrequest_amount',
                    'size' => 20,
                    'suffix' => $currencyIsoCode,
                    'required' => true,
                    'readonly' => true,
                )
            ),

            'submit' => array(
                'title' => $this->l('Create Payment Request'),
                'id' => 'bambora_paymentrequest_submit',
                'class' => 'button bambora-payment-request btn btn-primary pull-right',
                'icon' => 'process-icon-mail-reply',
            ),
        );


        $fields_form_send_email['form'] = array(
            'title' => $this->l('Send Payment Request by Email'),
            'legend' => array(
                'title' => $this->l('Send Email with Payment Request')
            ),
            'input' => array(

                array(
                    'type' => 'text',
                    'label' => $this->l('Recipient Name'),
                    'name' => 'bambora_paymentrequest_recipient_name',
                    'size' => 20,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Recipient E-mail'),
                    'name' => 'bambora_paymentrequest_recipient_email',
                    'size' => 20,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Reply-to Name'),
                    'name' => 'bambora_paymentrequest_replyto_name',
                    'size' => 20,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Reply-to Email'),
                    'name' => 'bambora_paymentrequest_replyto_email',
                    'size' => 20,
                    'required' => true,
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Message'),
                    'name' => 'bambora_paymentrequest_email_message',
                    'class' => 'js-text-with-length-counter',
                    'size' => 20,
                    'required' => true,
                )
            ),

            'submit' => array(
                'title' => $this->l('Send Payment Request Email'),
                'id' => 'bambora_paymentrequest_submit_send_email',
                'class' => 'button bambora-payment-request btn btn-primary',
                'icon' => 'process-icon-mail-reply',
            ),
        );

        $fields_form_delete['form'] = array(
            'title' => $this->l('Delete Payment Request'),
            'legend' => array(
                'title' => $this->l('Delete Payment Request'),
                'size' => 20,
                'class' => 'header material-icons',
                'icon' => 'icn_delete'
            ),
            'input' => array(),

            'submit' => array(
                'title' => $this->l('Delete Payment Request'),
                'id' => 'bambora_paymentrequest_submit_delete',
                'class' => 'button bambora-payment-request btn btn-secondary delete-btn',
                'icon' => 'icon-delete material-icons',
            ),
        );


        $helper = new HelperForm();
        $helper->base_folder = _PS_ADMIN_DIR_ . '/themes/default/template/helpers/form/';
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminOrders');
        $helper->currentIndex = AdminController::$currentIndex . '&vieworder&id_order=' . $params['id_order'];
        $helper->identifier = 'id_order';
        $helper->id = $params['id_order'];


        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->show_toolbar = false;

        // Load current value
        $helper->fields_value['bambora_paymentrequest_replyto_name'] = Tools::getValue(
            'bambora_paymentrequest_replyto_name'
        ) ? Tools::getValue(
            'bambora_paymentrequest_replyto_name'
        ) : $employee->firstname . ' ' . $employee->lastname;
        $helper->fields_value['bambora_paymentrequest_email_message'] = Tools::getValue(
            'bambora_paymentrequest_email_message'
        );

        $helper->fields_value['bambora_paymentrequest_recipient_name'] = $customer->firstname . ' ' . $customer->lastname;
        $helper->fields_value['bambora_paymentrequest_recipient_email'] = $customer->email;

        $helper->fields_value['bambora_paymentrequest_replyto_email'] = $employee->email;


        $helper->fields_value['bambora_paymentrequest_amount'] = number_format(
            $order->total_paid,
            2,
            '.',
            ''
        );
        $html = '<div class="card bambora_paymentrequest"><div class="card-header"> <h3 class="card-header-title">';

        $html .= "Worldline Checkout Payment Request";

        $html .= '</h3></div><div class="card-body"><div class="row"><div class="col-6">';

        // $html .= '<div id="bambora_paymentrequest_format_error" class="alert alert-danger"><strong>' . $this->l('Warning') . ' </strong>' . $this->l('The amount you entered was in the wrong format. Please try again!') . '</div>';
        if ($paymentRequest) {
            $html .= '<b>' . $this->l(
                    'Payment Request ID'
                ) . ':</b> ' . $paymentRequest['payment_request_id'] . '</a><br/>';


            $apiKey = $this->getApiKey();
            $api = new BamboraApi($apiKey);
            $paymentRequestDetails = $api->getPaymentRequest(
                $paymentRequest['payment_request_id']
            );
            $html .= "<b>" . $this->l('Created') . ":</b> " . Tools::formatDateStr(
                    $paymentRequestDetails['createddate']
                ) . "<br/>";
            $html .= "<b>" . $this->l(
                    'Description'
                ) . ":</b> " . $paymentRequestDetails['description'] . "<br/>";
            $html .= "<b>" . $this->l(
                    'Status',
                    'bambora'
                ) . ":</b> " . $paymentRequestDetails['status'] . "<br/>";
            $html .= "<b>" . $this->l(
                    'Reference'
                ) . ":</b> " . $paymentRequestDetails['reference'] . "<br/>";

            $currency = $paymentRequestDetails['parameters']['order']['currency'];

            $amount = BamboraCurrency::convertPriceFromMinorUnits(
                $paymentRequestDetails['parameters']['order']['amount'],
                BamboraCurrency::getCurrencyMinorunits($currency)
            );

            try {
                $formattedAmount = $this->context->currentLocale->formatPrice(
                    $amount,
                    $currency
                );
                $html .= "<b>Amount:</b> " . $formattedAmount . "<br/>";
            } catch (LocalizationException $e) {
            }
            $html .= '<a class="bambora-preview-payment-request btn" href="' . $paymentRequest['payment_request_url'] . '" target="_blank" >' . $this->l(
                    'Preview'
                ) . '</a><br/>';


            $merchantPaymentRequestUrl = "https://merchant.bambora.com/" . Configuration::get(
                    'BAMBORA_MERCHANTNUMBER'
                ) . "/paymentrequests/" . $paymentRequest['payment_request_id'];

            $html .= '<a href="' . $merchantPaymentRequestUrl . '" target="_blank" >' . $this->l(
                    'Link to Payment Request on Merchant UI'
                ) . '</a><br/>';

            $html .= "<br/><br/> </div><div class='col-6'>";
            $helper->submit_action = 'sendpaymentrequest';

            $helper->title = 'SEND EMAIL';
            $html .= $helper->generateForm(array($fields_form_send_email));
            $html .= "<br/><br/> </div><div class='col-3'>";
            $helper->title = 'DELETE REQUEST';
            $helper->submit_action = 'deletepaymentrequest';
            $html .= $helper->generateForm(array($fields_form_delete));
            $html .= "</div></div>";
        } else {
            $helper->title = 'CREATE REQUEST';
            $helper->submit_action = 'createpaymentrequest';
            $html .= $helper->generateForm(array($fields_form_create));
        }

        $html .= "

</div></div>";

        return $html;
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
        $html = '';

        $bamboraUiMessage = $this->processRemote();
        if (isset($bamboraUiMessage)) {
            $this->buildOverlayMessage(
                $bamboraUiMessage->type,
                $bamboraUiMessage->title,
                $bamboraUiMessage->message
            );
        }

        $order = new Order($params['id_order']);

        if ($order->module == $this->name) {
            $html .= $this->displayTransactionForm($order);
        }

        return $html;
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
        if ($order->module == $this->name) {
            $html = '
        <div class="card mt-2 d-print-none">
            <div class="card-header">
                    <h3 class="card-header-title">
                    Worldline Merchant Administration
                     </h3>
            </div>
            <div class="card-body">';
            $html .= $this->buildLogodiv();
            $html .= '
            </div>
        </div>';
        } else {
            $html = "";
        }

        return $html;
    }

    /**
     * Try to capture the payment when the status of the order is changed.
     *
     * @param mixed $params
     *
     * @return mixed
     * @throws Exception
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        if (Configuration::get('BAMBORA_CAPTUREONSTATUSCHANGED') == 1) {
            try {
                $newOrderStatus = $params['newOrderStatus'];
                $order = new Order($params['id_order']);
                $allowedOrderStatuses = unserialize(
                    Configuration::get('BAMBORA_CAPTURE_ON_STATUS')
                );
                if (is_array(
                        $allowedOrderStatuses
                    ) && $order->module == $this->name && in_array(
                        $newOrderStatus->id,
                        $allowedOrderStatuses
                    )) {
                    $payment = $order->getOrderPayments();
                    $transaction_Id = count(
                        $payment
                    ) > 0 ? $payment[0]->transaction_id : null;

                    if (!isset($transaction_Id)) {
                        throw new Exception("No Bambora transactionId found");
                    }

                    $currency = new Currency((int)$order->id_currency);
                    $currencyCode = $currency->iso_code;
                    $minorUnits = BamboraCurrency::getCurrencyMinorunits(
                        $currencyCode
                    );
                    $amountInMinorUnits = BamboraCurrency::convertPriceToMinorUnits(
                        $payment[0]->amount,
                        $minorUnits,
                        Configuration::get('BAMBORA_ROUNDING_MODE')
                    );
                    $currency = "";

                    $apiKey = $this->getApiKey();
                    $api = new BamboraApi($apiKey);
                    $captureResponse = $api->capture(
                        $transaction_Id,
                        $amountInMinorUnits,
                        $currencyCode,
                        null
                    );

                    if (!isset($captureResponse) || !$captureResponse["meta"]["result"]) {
                        $errorMessage = isset($captureResponse) ? $captureResponse["meta"]["message"]["merchant"] : "Could not connect to Bambora";
                        throw new Exception($errorMessage);
                    }

                    $message = "Auto Capture was successful";
                    $this->createStatusChangesMessage($params["id_order"], $message);
                }
            } catch (Exception $e) {
                $message = "Auto Capture failed with message: " . $e->getMessage();
                $this->createStatusChangesMessage($params["id_order"], $message);
                $id_lang = (int)$this->context->language->id;
                $dir_mail = dirname(__FILE__) . '/mails/';
                $mailTo = Configuration::get('BAMBORA_AUTOCAPTURE_FAILUREEMAIL');
                Mail::Send(
                    $id_lang,
                    'autocapturefailed',
                    'Auto capture of ' . $params['id_order'] . ' failed',
                    array('{message}' => $e->getMessage()),
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

        return "";
    }

    /**
     * Create and add a private order message
     *
     * @param int $orderId
     * @param string $message
     */
    private function createStatusChangesMessage($orderId, $message)
    {
        $msg = new Message();
        $message = strip_tags($message, '<br>');
        if (Validate::isCleanHtml($message)) {
            $msg->name = "Worldline Checkout";
            $msg->message = $message;
            $msg->id_order = (int)$orderId;
            $msg->private = 1;
            $msg->add();
        }
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
        $invoice = $params["object"];
        $order = new Order($invoice->id_order);

        $payments = $order->getOrderPayments();
        if (count($payments) === 0) {
            return "";
        }


        if (isset($payments[0]->transaction_id) && $payments[0]->transaction_id != "") {
            $transactionId = $payments[0]->transaction_id;
        } else {
            if (Tools::getIsset('txnid')) {
                $transactionId = Tools::getValue("txnid");
            } else {
                $transactionId = "";
            }
        }

        if (isset($payments[0]->card_brand) && $payments[0]->card_brand != "" && $payments[0]->card_brand != "Direct Banking") {
            $paymentType = $payments[0]->card_brand;
        } else {
            if ((strpos(
                        $payments[0]->payment_method,
                        "Bambora Online Checkout"
                    ) === 0) || (strpos(
                        $payments[0]->payment_method,
                        "Worldline Checkout"
                    ) === 0)) {
                $apiKey = BamboraHelpers::generateApiKey();
                $api = new BamboraApi($apiKey);
                $bamboraTransaction = $api->gettransaction($transactionId);
                $bamboraTransactionInfo = $bamboraTransaction['transaction'];
                if (isset($bamboraTransactionInfo['information']['paymenttypes'][0])) {
                    $paymentType = $bamboraTransactionInfo['information']['paymenttypes'][0]['displayname'];
                } else {
                    $paymentType = $payments[0]->payment_method;
                }
                if (isset($bamboraTransactionInfo["information"]["acquirerreferences"][0])) {
                    $acquirerReference = $bamboraTransactionInfo["information"]["acquirerreferences"][0]["reference"];
                }
            } else {
                $paymentType = $payments[0]->payment_method;
            }
        }
        if (isset($payments[0]->card_number) && $payments[0]->card_number != "") {
            $truncatedCardNumber = $payments[0]->card_number;
        } else {
            if (Tools::getIsset('cardno')) {
                $truncatedCardNumber = Tools::getValue("cardno");
            } else {
                $truncatedCardNumber = "";
            }
        }
        $formattedCardnumber = BamboraHelpers::formatTruncatedCardnumber(
            $truncatedCardNumber
        );

        $result = '<table>';
        $result .= '<tr><td colspan="2"><strong>' . $this->l(
                'Payment information'
            ) . '</strong></td></tr>';
        $result .= '<tr><td>' . $this->l(
                'Transaction id'
            ) . ':</td><td>' . $transactionId . '</td></tr>';

        if (isset($acquirerReference) && $acquirerReference != "") {
            $result .= '<tr><td>' . $this->l(
                    'Acquirer Reference'
                ) . ':</td><td>' . $acquirerReference . '</td></tr>';
        }

        $result .= '<tr><td>' . $this->l(
                'Payment Type'
            ) . ':</td><td>' . $paymentType . '</td></tr>';

        if ($formattedCardnumber != "") {
            $result .= '<tr><td>' . $this->l(
                    'Card Number'
                ) . ':</td><td>' . $formattedCardnumber . '</td></tr>';
        }


        $result .= '</table>';

        return $result;
    }

    private function showLogo()
    {
        $html = '<div class="bambora-logo">
                        <div class="bambora-exclamation-stem"></div>
                        <div class="bambora-exclamation-dot"></div>
                    </div>';

        return $html;
    }

    /**
     * Create Bambora Order Lines
     *
     * @param int $order
     * @param mixed $currency
     *
     * @return BamboraOrderLine[]
     */

    private function createBamboraOrderlinesFromOrder($id_order, $currency)
    {
        $bamboraOrderlines = array();

        $orderDetails = OrderDetail::getList((int)$id_order);

        $lineNumber = 1;
        $minorUnits = BamboraCurrency::getCurrencyMinorunits($currency);

        foreach ($orderDetails as $product) {
            $line = new BamboraOrderLine();
            $line->description = $product["product_name"];
            $line->id = $product["product_id"];
            $line->linenumber = (string)$lineNumber;
            $line->quantity = (int)$product["product_quantity"];
            $line->text = $product["product_name"];
            $line->totalprice = BamboraCurrency::convertPriceToMinorUnits(
                $product["total_price_tax_excl"],
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->totalpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $product["total_price_tax_incl"],
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->totalpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                $product["total_price_tax_incl"] - $product["total_price_tax_excl"],
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->unitprice = BamboraCurrency::convertPriceToMinorUnits(
                $product["total_price_tax_excl"] / $line->quantity,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->unitpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $product["total_price_tax_incl"] / $line->quantity,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->unitpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                ($product["total_price_tax_incl"] - $product["total_price_tax_excl"]) / $line->quantity,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $line->unit = $this->l('pcs.');
            $line->vat = round($line->totalpricevatamount / $line->totalprice * 100);
            $bamboraOrderlines[] = $line;
            $lineNumber++;
        }
        $order = new Order((int)$id_order);
        //Add shipping as an orderline
        $shippingCostWithTax = $order->total_shipping_tax_incl;
        if ($shippingCostWithTax > 0) {
            $shippingCostWithoutTax = $order->total_shipping_tax_excl;

            $carrier = new Carrier($order->id_carrier, $order->id_lang);
            $shippingTax = $shippingCostWithTax - $shippingCostWithoutTax;
            $shippingOrderline = new BamboraOrderLine();
            $shippingOrderline->id = $carrier->id_reference;

            $shippingOrderline->description = "{$carrier->name} - {$carrier->delay}";
            $shippingOrderline->quantity = 1;
            $shippingOrderline->unit = $this->l('pcs.');
            $shippingOrderline->linenumber = $lineNumber++;
            $shippingOrderline->totalprice = BamboraCurrency::convertPriceToMinorUnits(
                $shippingCostWithoutTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->totalpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $shippingCostWithTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->totalpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                $shippingTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->unitprice = BamboraCurrency::convertPriceToMinorUnits(
                $shippingCostWithoutTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->unitpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $shippingCostWithTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->unitpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                $shippingTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $shippingOrderline->vat = round(
                $shippingTax / $shippingCostWithoutTax * 100
            );
            $bamboraOrderlines[] = $shippingOrderline;
        }

        //Gift Wrapping
        $wrappingTotal = $order->total_wrapping_tax_incl;
        if ($wrappingTotal > 0) {
            $wrappingTotalWithOutTax = $order->total_wrapping_tax_excl;
            $wrappingTotalTax = $wrappingTotal - $wrappingTotalWithOutTax;
            $wrappingOrderline = new BamboraOrderLine();
            $wrappingOrderline->id = $this->l('wrapping');
            $wrappingOrderline->description = $this->l('Gift wrapping');
            $wrappingOrderline->quantity = 1;
            $wrappingOrderline->unit = $this->l('pcs.');
            $wrappingOrderline->linenumber = $lineNumber++;
            $wrappingOrderline->totalprice = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotalWithOutTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $wrappingOrderline->totalpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotal,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $wrappingOrderline->totalpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotalTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $wrappingOrderline->unitprice = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotalWithOutTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $wrappingOrderline->unitpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotal,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );
            $wrappingOrderline->unitpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                $wrappingTotalTax,
                $minorUnits,
                Configuration::get('BAMBORA_ROUNDING_MODE')
            );

            $wrappingOrderline->vat = round(
                $wrappingTotalTax / $wrappingTotalWithOutTax * 100
            );
            $bamboraOrderlines[] = $wrappingOrderline;
        }

        //Discount
        $discountTotal = $order->total_discounts_tax_incl;
        if ($discountTotal > 0) {
            $discountTotalWithOutTax = $order->total_discounts_tax_excl;
            $discountTotalTax = $discountTotal - $discountTotalWithOutTax;
            $discountOrderline = new BamboraOrderLine();
            $discountOrderline->id = $this->l('discount');
            $discountOrderline->description = $this->l('Discount');
            $discountOrderline->quantity = 1;
            $discountOrderline->unit = $this->l('pcs.');
            $discountOrderline->linenumber = $lineNumber++;
            $discountOrderline->totalprice = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotalWithOutTax,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->totalpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotal,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->totalpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotalTax,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->unitprice = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotalWithOutTax,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->unitpriceinclvat = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotal,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->unitpricevatamount = BamboraCurrency::convertPriceToMinorUnits(
                    $discountTotalTax,
                    $minorUnits,
                    Configuration::get('BAMBORA_ROUNDING_MODE')
                ) * -1;
            $discountOrderline->vat = round(
                $discountTotalTax / $discountTotalWithOutTax * 100
            );
            $bamboraOrderlines[] = $discountOrderline;
        }


        return $bamboraOrderlines;
    }

    /**
     * Get paymentRequest  from the database with cart id.
     *
     * @param mixed $id_cart
     *
     * @return mixed
     */
    private function getDbPaymentRequestByCartId($id_cart)
    {
        $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'bambora_payment_requests WHERE id_cart = ' . pSQL(
                $id_cart
            );

        return $this->getDbPaymentRequests($query);
    }

    #endregion

}
