<?php

class BamboraSettingsHelper
{
    /**
     * Summary of updateModuleSettings
     *
     * @param Bambora $module
     *
     * @return string
     */
    public static function updateModuleSettings($module)
    {
        $output = '';
        $merchantnumber = (string) Tools::getValue('BAMBORA_MERCHANTNUMBER');
        $accesstoken = (string) Tools::getValue('BAMBORA_ACCESSTOKEN');
        $secrettoken = (string) Tools::getValue('BAMBORA_SECRETTOKEN');
        $allowLowValue = (string) Tools::getValue('BAMBORA_ALLOW_LOW_VALUE_EXEMPTION');
        $limitLowValueExemption = (string) Tools::getValue(
            'BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION'
        );
        if (empty($merchantnumber) || !Validate::isGenericName($merchantnumber)) {
            $output .= $module->displayError(
                'Merchant number ' . $module->l(
                    'is required. If you do not have one please contact Bambora in order to obtain one!',
                    'settingshelper'
                )
            );
        } elseif (empty($accesstoken) || !Validate::isGenericName($accesstoken)) {
            $output .= $module->displayError(
                'Access token ' . $module->l(
                    'is required. If you do not have one please contact Bambora in order to obtain one!',
                    'settingshelper'
                )
            );
        } elseif (!Validate::isGenericName(
            $secrettoken
        ) && empty(Configuration::get('BAMBORA_SECRETTOKEN'))) {
            $output .= $module->displayError(
                'Secret token ' . $module->l(
                    'is required. If you do not have one please contact Bambora in order to obtain one!',
                    'settingshelper'
                )
            );
        } elseif (!Validate::isPrice($limitLowValueExemption) && $allowLowValue) {
            $output .= $module->displayError(
                'Limit Low Value Exemption' . $module->l(
                    'If you allow low value exemptions you need to set a limit here that is valid',
                    'settingshelper'
                )
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
                Tools::getValue('BAMBORA_MD5KEY')
            );
            Configuration::updateValue(
                'BAMBORA_PAYMENTWINDOWID',
                Tools::getValue('BAMBORA_PAYMENTWINDOWID')
            );
            Configuration::updateValue(
                'BAMBORA_TITLE',
                Tools::getValue('BAMBORA_TITLE')
            );
            Configuration::updateValue(
                'BAMBORA_INSTANTCAPTURE',
                Tools::getValue('BAMBORA_INSTANTCAPTURE')
            );
            Configuration::updateValue(
                'BAMBORA_WINDOWSTATE',
                Tools::getValue('BAMBORA_WINDOWSTATE')
            );
            Configuration::updateValue(
                'BAMBORA_IMMEDIATEREDIRECTTOACCEPT',
                Tools::getValue('BAMBORA_IMMEDIATEREDIRECTTOACCEPT')
            );
            Configuration::updateValue(
                'BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT',
                Tools::getValue('BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT')
            );
            Configuration::updateValue(
                'BAMBORA_PDF_DISPLAY_PAYMENTINFO',
                Tools::getValue('BAMBORA_PDF_DISPLAY_PAYMENTINFO')
            );
            Configuration::updateValue(
                'BAMBORA_ADDFEETOSHIPPING',
                Tools::getValue('BAMBORA_ADDFEETOSHIPPING')
            );
            Configuration::updateValue(
                'BAMBORA_CAPTUREONSTATUSCHANGED',
                Tools::getValue('BAMBORA_CAPTUREONSTATUSCHANGED')
            );
            Configuration::updateValue(
                'BAMBORA_CAPTURE_ON_STATUS',
                serialize(Tools::getValue('BAMBORA_CAPTURE_ON_STATUS'))
            );
            Configuration::updateValue(
                'BAMBORA_AUTOCAPTURE_FAILUREEMAIL',
                Tools::getValue('BAMBORA_AUTOCAPTURE_FAILUREEMAIL')
            );
            Configuration::updateValue(
                'BAMBORA_ROUNDING_MODE',
                Tools::getValue('BAMBORA_ROUNDING_MODE')
            );
            Configuration::updateValue(
                'BAMBORA_ALLOW_LOW_VALUE_EXEMPTION',
                Tools::getValue('BAMBORA_ALLOW_LOW_VALUE_EXEMPTION')
            );
            Configuration::updateValue(
                'BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION',
                Tools::getValue('BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION')
            );
            Configuration::updateValue(
                'BAMBORA_TERMS_URL',
                Tools::getValue('BAMBORA_TERMS_URL')
            );
            $output .= $module->displayConfirmation($module->l('Settings updated', 'settingshelper'));
        }

        return $output;
    }

    /**
     * Summary of renderSettingsFormHtml
     *
     * @param Context $context
     * @param Bambora $module
     *
     * @return string
     */
    public static function renderSettingsFormHtml($module)
    {
        // Get default Language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $switch_options = [
            ['id' => 'active_on', 'value' => 1, 'label' => 'Yes'],
            ['id' => 'active_off', 'value' => 0, 'label' => 'No'],
        ];
        $windowstate_options = [
            ['type' => 2, 'name' => 'Overlay'],
            ['type' => 1, 'name' => 'Fullscreen'],
        ];
        $statuses = OrderState::getOrderStates($module->bamboraContext->language->id);
        $selectCaptureStatus = [];
        foreach ($statuses as $status) {
            $selectCaptureStatus[] = [
                'key' => $status['id_order_state'],
                'name' => $status['name'],
            ];
        }
        $rounding_modes = [
            ['type' => BamboraCurrencyHelper::ROUND_DEFAULT, 'name' => 'Default'],
            ['type' => BamboraCurrencyHelper::ROUND_UP, 'name' => 'Always up'],
            ['type' => BamboraCurrencyHelper::ROUND_DOWN, 'name' => 'Always down'],
        ];

        // Init Fields form array
        $fields_form = [];
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $module->l('Settings', 'settingshelper'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => 'Merchant number',
                    'name' => 'BAMBORA_MERCHANTNUMBER',
                    'size' => 40,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => 'Access token',
                    'name' => 'BAMBORA_ACCESSTOKEN',
                    'size' => 40,
                    'required' => true,
                ],
                [
                    'type' => 'password',
                    'label' => 'Secret token',
                    'name' => 'BAMBORA_SECRETTOKEN',
                    'size' => 40,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => 'MD5 key',
                    'name' => 'BAMBORA_MD5KEY',
                    'size' => 40,
                    'required' => false,
                ],
                [
                    'type' => 'text',
                    'label' => 'Payment Window ID',
                    'name' => 'BAMBORA_PAYMENTWINDOWID',
                    'size' => 40,
                    'required' => false,
                ],
                [
                    'type' => 'text',
                    'label' => 'Payment method title',
                    'name' => 'BAMBORA_TITLE',
                    'size' => 40,
                    'required' => false,
                ],
                [
                    'type' => 'select',
                    'label' => 'Window state',
                    'name' => 'BAMBORA_WINDOWSTATE',
                    'required' => false,
                    'options' => [
                        'query' => $windowstate_options,
                        'id' => 'type',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => 'Instant capture',
                    'name' => 'BAMBORA_INSTANTCAPTURE',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options,
                ],
                [
                    'type' => 'switch',
                    'label' => 'Immediate Redirect',
                    'name' => 'BAMBORA_IMMEDIATEREDIRECTTOACCEPT',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options,
                ],
                [
                    'type' => 'switch',
                    'label' => 'Add Surcharge',
                    'name' => 'BAMBORA_ADDFEETOSHIPPING',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options,
                ],
                [
                    'type' => 'switch',
                    'label' => 'Only show payment logos at checkout',
                    'name' => 'BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options,
                ],
                [
                    'type' => 'switch',
                    'label' => 'Display Payment Infomation on PDF',
                    'name' => 'BAMBORA_PDF_DISPLAY_PAYMENTINFO',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options,
                ],
                [
                    'type' => 'switch',
                    'label' => 'Capture payment on status changed',
                    'name' => 'BAMBORA_CAPTUREONSTATUSCHANGED',
                    'is_bool' => true,
                    'required' => false,
                    'values' => $switch_options,
                ],
                [
                    'type' => 'select',
                    'label' => 'Capture on status changed to',
                    'name' => 'BAMBORA_CAPTURE_ON_STATUS[]',
                    'class' => 'chosen',
                    'required' => false,
                    'multiple' => true,
                    'options' => [
                        'query' => $selectCaptureStatus,
                        'id' => 'key',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => 'Capture on status changed failure e-mail',
                    'name' => 'BAMBORA_AUTOCAPTURE_FAILUREEMAIL',
                    'size' => 40,
                    'required' => false,
                ],
                [
                    'type' => 'select',
                    'label' => 'Rounding mode',
                    'name' => 'BAMBORA_ROUNDING_MODE',
                    'required' => false,
                    'options' => [
                        'query' => $rounding_modes,
                        'id' => 'type',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => 'Enable Low Value Exemption',
                    'name' => 'BAMBORA_ALLOW_LOW_VALUE_EXEMPTION',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options,
                ],
                [
                    'type' => 'text',
                    'label' => 'Max Amount for Low Value Exemption',
                    'name' => 'BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION',
                    'size' => 10,
                    'required' => false,
                ],
                [
                    'type' => 'text',
                    'label' => 'URL for Terms & Conditions in case you are using this for Payment Requests.',
                    'name' => 'BAMBORA_TERMS_URL',
                    'size' => 10,
                    'required' => false,
                ],
            ],
            'submit' => [
                'title' => $module->l('Save', 'settingshelper'),
                'class' => 'btn btn-default pull-right floatRight',
                'style' => 'float:right',
            ],
        ];

        $helper = new HelperForm();
        $helper->table = 'module';

        // Module, token and currentIndex
        $helper->module = $module;
        $helper->name_controller = $module->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $module->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = "{$module->displayName} v{$module->version}";
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = "submit{$module->name}";
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $module->l('Save', 'settingshelper'),
                'href' => AdminController::$currentIndex . '&configure=' . $module->name . '&save' . $module->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite(
                    'AdminModules'
                ),
                'desc' => $module->l('Back to list', 'settingshelper'),
            ],
        ];

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
        $helper->fields_value['BAMBORA_PDF_DISPLAY_PAYMENTINFO'] = Configuration::get(
            'BAMBORA_PDF_DISPLAY_PAYMENTINFO'
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
                    <div class="col-xs-12 col-sm-12 col-md-7 col-lg-7 ">' .
                        $helper->generateForm($fields_form) .
                    '</div>
                    <div class="hidden-xs col-md-5 col-lg-5">' .
                        self::buildSettingsHelpTextHtml() .
                    '</div>
                </div>' .
                '<div class="row visible-xs">
                    <div class="col-xs-12 col-sm-12">' .
                        self::buildSettingsHelpTextHtml() .
                    '</div>
                </div>';

        return $html;
    }

    /**
     * Build Help Text For Settings
     *
     * @return mixed
     */
    public static function buildSettingsHelpTextHtml()
    {
        return '<div class="panel helpContainer bambora-settings-help-text">
                    <div class="panel-heading">Help for settings</div>
                    <p>Detailed description of these settings are to be found <a href="https://developer.bambora.com/europe/shopping-carts/shopping-carts/prestashop" target="_blank">here</a>.</p>
                    <div>
                        <h4>Merchant number</h4>
                        <p>The number identifying your Worldline merchant account.</p>
                        <p><b>Note: </b>This field is mandatory to enable payments</p>
                    </div>
                    <div>
                        <h4>Access token</h4>
                        <p>The Access token for the API user received from the Worldline administration.</p>
                        <p><b>Note:</b> This field is mandatory in order to enable payments</p>
                    </div>
                    <div>
                        <h4>Secret token</h4>
                        <p>The Secret token for the API user received from the Worldline administration.</p>
                        <p><b>Note: </b>This field is mandatory in order to enable payments.</p>
                    </div>
                    <div>
                        <h4>MD5 Key</h4>
                        <p>The MD5 key is used to stamp data sent between Magento and Worldline to prevent it from being tampered with.</p>
                        <p><b>Note: </b>The MD5 key is optional but if used here, must be the same as in the Worldline administration.</p>
                    </div>
                    <div>
                        <h4>Payment Window ID</h4>
                        <p>The ID of the payment window to use.</p>
                    </div>
                    <div>
                        <h4>Payment method tittle</h4>
                        <p>The title of the payment method visible to the customers</p>
                        <p><b>Note: </b> If left empty the default title will be <b>Worldline Online Checkout</b>
                    </div>
                    <div>
                        <h4>Window state</h4>
                        <p>Please select if you want the Payment window shown as an overlay or as full screen</p>
                    </div>
                    <div>
                        <h4>Instant capture</h4>
                        <p>Capture the payments at the same time they are authorized. In some countries, this is only permitted if the consumer receives the products right away Ex. digital products.</p>
                    </div>
                    <div>
                        <h4>Immediate Redirect</h4>
                        <p>Immediately redirect your customer back to you shop after the payment completed.</p>
                    </div>
                    <div>
                        <h4>Add Surcharge</h4>
                        <p>Enable this if you want the payment surcharge to be added to the shipping and handling fee</p>
                    </div>
                    <div>
                        <h4>Only show payment logos at checkout</h4>
                        <p>Set to disable the title text and only display payment logos at checkout</p>
                    </div>
                    <div>
                        <h4>Display Payment Infomation on PDF</h4>
                        <p>Set to display the payment information on generated PDFs</p>
                    </div>
                    <div>
                        <h4>Capture payment on status changed</h4>
                        <p>Enable this if you want to be able to capture the payment when the order status is changed</p>
                    </div>
                    <div>
                        <h4>Capture on status changed to</h4>
                        <p>Select the status you want to execute the capture operation when changed to</p>
                        <p><b>Note: </b>You must enable <b>Capture payment on status changed</b></p>
                    </div>
                    <div>
                        <h4>Capture on status changed failure e-mail</h4>
                        <p>If the Capture fails on status changed an e-mail will be sent to this address</p>
                    </div>
                    <div>
                        <h4>Rounding mode</h4>
                        <p>Please select how you want the rounding of the amount sent to the payment system</p>
                    </div>
                    <div>
                        <h4>Enable Low Value Exemption</h4>
                        <p>Allow you as a merchant to let the customer attempt to skip Strong Customer Authentication(SCA) when the value of the order is below your defined limit. <strong>Note:</strong> the liability will be on you as a merchant.</p>
                    </div>
                    <div>
                        <h4>Max Amount for Low Value Exemption</h4>
                        <p>Any amount below this max amount might skip SCA if the issuer would allow it. Recommended amount is about €30 in your local currency. <br /><a href="https://developer.bambora.com/europe/checkout/psd2/lowvalueexemption"  target="_blank">See more information here.</a></p>
                        <p>Only active if <b>"Enable Low Value Exemption"</b> is set to yes.</p>
                    </div>
                        <div>
                        <h4>URL to Terms & Conditions</h4>
                        <p>In case you are using Payment Requests this is where you can set the URL for your terms & conditions</p>
                    </div>
                </div>';
    }
}
