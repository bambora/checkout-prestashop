<?php
/**
 * 888                             888
 * 888                             888
 * 88888b.   8888b.  88888b.d88b.  88888b.   .d88b.  888d888  8888b.
 * 888 "88b     "88b 888 "888 "88b 888 "88b d88""88b 888P"       "88b
 * 888  888 .d888888 888  888  888 888  888 888  888 888     .d888888
 * 888 d88P 888  888 888  888  888 888 d88P Y88..88P 888     888  888
 * 88888P"  "Y888888 888  888  888 88888P"   "Y88P"  888     "Y888888
 *
 * @category    Online Payment Gatway
 * @package     Bambora_Online
 * @author      Bambora Online
 * @copyright   Bambora (http://bambora.com)
 */

include('lib/bamboraApi.php');
include('lib/bamboraHelpers.php');
include('lib/bamboraCurrency.php');

if (!defined('_PS_VERSION_'))
	exit;

class Bambora extends PaymentModule
{
	private $_html = '';
	private $_postErrors = array();
    private $_apiKey;

    const MODULE_NAME = 'bambora';
    const MODULE_VERSION = '1.5.0';
    const MODULE_AUTHOR = 'Bambora';

    const V15 = '15';
    const V16 = '16';
    const V17 = '17';

	public function __construct()
	{
		$this->name = $this::MODULE_NAME;
		$this->tab = 'payments_gateways';
        $this->version = $this::MODULE_VERSION;
		$this->author = $this::MODULE_AUTHOR;

        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
        $this->controllers = array('accept', 'callback', 'payment');
        $this->is_eu_compatible = 1;
        $this->bootstrap = true;

		$this->currencies = true;
		$this->currencies_mode = 'checkbox';

		parent::__construct();

		$this->displayName = 'Bambora Checkout';
		$this->description = $this->l('Use Bambora Checkout payment gateway to accept payments for your products online');
	}

    #region Install and Setup
	/**
     * Install
     *
     * @return boolean
     */
	public function install()
	{
        if(!parent::install()
            || !$this->registerHook('payment')
            || !$this->registerHook('rightColumn')
            || !$this->registerHook('adminOrder')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('PDFInvoice')
            || !$this->registerHook('Invoice')
            || !$this->registerHook('backOfficeHeader')
            || !$this->registerHook('displayHeader')
            || !$this->registerHook('displayBackOfficeHeader')
        )
        {
            return false;
        }
        if($this->getPsVersion() === $this::V17)
        {
            if(!$this->registerHook('paymentOptions'))
            {
                return false;
            }
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

	/**
     * Hook Display Header
     */
	public function hookDisplayHeader()
	{
        if ($this->context->controller != null)
		{
			$this->context->controller->addCSS($this->_path.'css/bamboraFront.css', 'all');
		}
	}

    /**
     * Hook BackOffice Header
     *
     * @param mixed $params
     */
    public function hookBackOfficeHeader($params)
    {
        if ($this->context->controller != null)
		{
            $this->context->controller->addCSS($this->_path.'css/bamboraAdmin.css', 'all');
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
     * Get Content
     *
     * @return string
     */
	public function getContent()
	{
		$output = null;

	    if (Tools::isSubmit('submit'.$this->name))
	    {
	        $merchantnumber = strval(Tools::getValue('BAMBORA_MERCHANTNUMBER'));
            $accesstoken = strval(Tools::getValue("BAMBORA_ACCESSTOKEN"));
            $secrettoken = strval(Tools::getValue("BAMBORA_SECRETTOKEN"));
	        if (empty($merchantnumber) || !Validate::isGenericName($merchantnumber))
            {
                $output .= $this->displayError('Merchant number '.$this->l('is required. If you don\'t have one please contact Bambora in order to obtain one!') );
            }
            elseif(empty($accesstoken) || !Validate::isGenericName($accesstoken))
            {
                $output .= $this->displayError('Access token '.$this->l('is required. If you don\'t have one please contact Bambora in order to obtain one!') );
            }
            elseif(empty($secrettoken) || !Validate::isGenericName($secrettoken))
            {
                $output .= $this->displayError('Secret token '.$this->l('is required. If you don\'t have one please contact Bambora in order to obtain one!') );
            }
	        else
	        {
				Configuration::updateValue('BAMBORA_MERCHANTNUMBER', $merchantnumber);
				Configuration::updateValue('BAMBORA_ACCESSTOKEN', $accesstoken);
                Configuration::updateValue('BAMBORA_SECRETTOKEN', $secrettoken);
                Configuration::updateValue('BAMBORA_MD5KEY', Tools::getValue("BAMBORA_MD5KEY"));
                Configuration::updateValue('BAMBORA_PAYMENTWINDOWID', Tools::getValue("BAMBORA_PAYMENTWINDOWID"));
				Configuration::updateValue('BAMBORA_ENABLE_REMOTE_API', Tools::getValue("BAMBORA_ENABLE_REMOTE_API"));
				Configuration::updateValue('BAMBORA_INSTANTCAPTURE', Tools::getValue("BAMBORA_INSTANTCAPTURE"));
                Configuration::updateValue('BAMBORA_WINDOWSTATE', Tools::getValue("BAMBORA_WINDOWSTATE"));
                Configuration::updateValue('BAMBORA_IMMEDIATEREDIRECTTOACCEPT', Tools::getValue("BAMBORA_IMMEDIATEREDIRECTTOACCEPT"));
                Configuration::updateValue('BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT', Tools::getValue("BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT"));
                Configuration::updateValue('BAMBORA_ADDFEETOSHIPPING', Tools::getValue("BAMBORA_ADDFEETOSHIPPING"));

	            $output .= $this->displayConfirmation($this->l('Settings updated'));
	        }
	    }

	    $output .= $this->displayForm();
        return $output;
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
            array( 'id' => 'active_on', 'value' => 1, 'label' => 'Yes'),
            array( 'id' => 'active_off', 'value' => 0, 'label' => 'No'),
        );
        $windowstate_options=  array(
            array( 'type' => 2, 'name' => 'Overlay' ),
            array( 'type' => 1, 'name' => 'Fullscreen' )
        );

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings')
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => 'Activate module',
                    'name' => 'BAMBORA_ENABLE_REMOTE_API',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                 ),
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
                    'type' => 'text',
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
                    'type' => 'switch',
                    'label' => 'Instant capture',
                    'name' => 'BAMBORA_INSTANTCAPTURE',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                ),
                array(
                  'type' => 'switch',
                    'label' => 'Immediateredirect',
                    'name' => 'BAMBORA_IMMEDIATEREDIRECTTOACCEPT',
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
                    'label' => 'Add surcharge fee to shipping',
                    'name' => 'BAMBORA_ADDFEETOSHIPPING',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                ),
                array(
                    'type' => 'select',
                    'label' => 'Display window as',
                    'name' => 'BAMBORA_WINDOWSTATE',
                    'required' => false,
                    'options' => array(
                       'query' => $windowstate_options,
                       'id' => 'type',
                       'name' => 'name'
                    )
                )

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
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName . " v" . $this->version;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['BAMBORA_MERCHANTNUMBER'] = Configuration::get('BAMBORA_MERCHANTNUMBER');
        $helper->fields_value['BAMBORA_WINDOWSTATE'] = Configuration::get('BAMBORA_WINDOWSTATE');
        $helper->fields_value['BAMBORA_PAYMENTWINDOWID'] = Configuration::get('BAMBORA_PAYMENTWINDOWID');
        $helper->fields_value['BAMBORA_ENABLE_REMOTE_API'] = Configuration::get('BAMBORA_ENABLE_REMOTE_API');
        $helper->fields_value['BAMBORA_INSTANTCAPTURE'] = Configuration::get('BAMBORA_INSTANTCAPTURE');
        $helper->fields_value['BAMBORA_ENABLE_PAYMENTREQUEST'] = Configuration::get('BAMBORA_ENABLE_PAYMENTREQUEST');
        $helper->fields_value['BAMBORA_ACCESSTOKEN'] = Configuration::get('BAMBORA_ACCESSTOKEN');
        $helper->fields_value['BAMBORA_IMMEDIATEREDIRECTTOACCEPT'] = Configuration::get('BAMBORA_IMMEDIATEREDIRECTTOACCEPT');
        $helper->fields_value['BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT'] = Configuration::get('BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT');
        $helper->fields_value['BAMBORA_ADDFEETOSHIPPING'] =  Configuration::get('BAMBORA_ADDFEETOSHIPPING');
        $helper->fields_value['BAMBORA_MD5KEY'] = Configuration::get('BAMBORA_MD5KEY');
        $helper->fields_value['BAMBORA_SECRETTOKEN'] = Configuration::get('BAMBORA_SECRETTOKEN');

        $html =   '<div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-7 col-lg-7 ">'
                           .$helper->generateForm($fields_form)
                    .'</div>
                    <div class="hidden-xs col-md-5 col-lg-5">'
                    . $this -> buildHelptextForSettings()
                    .'</div>
                 </div>'
               .'<div class="row visible-xs">
                   <div class="col-xs-12 col-sm-12">'
                    . $this -> buildHelptextForSettings()
                    .'</div>
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
                        <div>
                            <H5>Activate module</H5>
                            <p>Set to "Yes" to enable Bambora payments.<br/>If set to "No", the Bambora payment option will not be visible to your customers</p>
                        </div>
                        <br/>
                        <div>
                            <H5>Merchant number</H5>
                            <p>Get your Merchant number from the <a href="https://admin.epay.eu/" target="_blank">Bambora Administration</a> via Settings > Merchant numbers. If you haven\'t got a Merchant number, please contact <a href="http://www.bambora.com/" target="_blank">Bambora</a> to get one.</p>
                            <p><b>Note: </b>This field is mandatory to enable payments</p>
                        </div>
                        <br/>
                        <div>
                            <H5>Access token</H5>
                            <p>Get your Access token from the <a href="https://admin.epay.eu/" target="_blank"> Bambora Administration</a> via Settings > API users. Copy the Access token from the API user into this field</p>
                            <p><b>Note:</b> This field is mandatory in order to enable payments</p>
                        </div>
                        <br/>
                        <div>
                            <H5>Secret token</H5>
                            <p>Get your Secret token from the <a href="https://admin.epay.eu/" target="_blank">Bambora Administration</a> via Settings > API users. The secret token is only displayed once when an API user is created! Please save this token in a safe place as Bambora will not be able to recover it.</p>
                            <p><b>Note: </b>This field is mandatory in order to enable payments.</p>
                        </div>
                        <br/>
                        <div>
                            <H5>MD5 Key</H5>
                            <p>We recommend using MD5 to secure the data sent between your system and Bambora.<br/>If you have generated a MD5 key in the <a href="https://admin.epay.eu/" target="_blank">Bambora Administration</a> via Settings > Edit merchant, you have to enter the MD5 key here as well.</p>
                            <p><b>Note: </b>The keys must be identical in the two systems.</p>
                        </div>
                        <br/>
                        <div>
                            <H5>Payment Window ID</H5>
                            <p>Choose which payment window to use. You can create multiple payment windows in the <a href="https://admin.epay.eu/" target="_blank">Bambora Administration</a> via Settings > Payment windows. </p>
                        </div>
                        <br/>
                        <div>
                            <H5>Instant capture</H5>
                            <p>Enable this to capture the payment immediately. <br/> You should only use this setting, if your customer receives the goods immediately e.g. via downloads or services.</p>
                        </div>
                        <br/>
                        <div>
                            <H5>Immediateredirect</H5>
                            <p>Please select if you to go directly to the order confirmation page when payment is completed</p>
                        </div>
                        <br/>
                        <div>
                            <H5>Only show payment logos at checkout</H5>
                            <p>By enabling this the text will disappear from the payment option</p>
                        </div>
                        <br/>
                        <div>
                            <H5>Add Surcharge fee to shipping</H5>
                            <p>Enable this if you want the payment surcharge fee to be added to the shipping and handling fee</p>
                        </div>
                        <br/>
                        <div>
                            <H5>Display window as</H5>
                            <p>Please select if you want the Payment window shown as an overlay or as full screen</p>
                        </div>
                   </div>';

        return $html;
    }
    #endregion

    #region front office

    /**
     * Hook payment options for Prestashop 1.7
     *
     * @param mixed $params
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
        $currency = new Currency(intval($cart->id_currency));

        $minorUnits = BamboraCurrency::getCurrencyMinorunits($currency->iso_code);
        $totalAmountMinorunits = BamboraCurrency::convertPriceToMinorUnits($cart->getOrderTotal(),$minorUnits);

        $paymentcardIds = $this->getPaymentCardIds($currency->iso_code, $totalAmountMinorunits);

        $paymentInfoData = array('paymentCardIds' => $paymentcardIds,
                                 'onlyShowLogoes' => Configuration::get('BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT')
                                );
        $this->context->smarty->assign($paymentInfoData);

        $bamboraPaymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $bamboraPaymentOption->setCallToActionText("Bambora Checkout")
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setAdditionalInformation($this->context->smarty->fetch('module:bambora/views/templates/front/paymentinfo.tpl'));

        $paymentOptions = array();
        $paymentOptions[] = $bamboraPaymentOption;

        return $paymentOptions;
    }

    /**
     * Hook payment for Prestashop before 1.7
     *
     * @param mixed $params
     * @return mixed
     */
    public function hookPayment($params)
    {
        if (!$this->active)
			return;

		if (!$this->checkCurrency($params['cart']))
			return;

        $cart = $params['cart'];
        $bamboraCheckoutRequest = $this->createCheckoutRequest($cart);
        $bamboraPaymentData = $this->getBamboraPaymentData($bamboraCheckoutRequest);

        $checkoutResponse = $bamboraPaymentData['checkoutResponse'];
        if(!isset($checkoutResponse) || $checkoutResponse['meta']['result'] == false)
        {
            $errormessage = $checkoutResponse['meta']['message']['enduser'];
            $this->context->smarty->assign('bambora_errormessage', $errormessage);
            $this->context->smarty->assign( 'this_path_bambora', $this->_path);
            return $this->display(__FILE__ , "checkoutIssue.tpl");
        }

        $bamboraOrder = $bamboraCheckoutRequest->order;

        $paymentcardIds = $this->getPaymentCardIds($bamboraOrder->currency, $bamboraOrder->total);

        $paymentData = array('bamboraPaymentCardIds' => $paymentcardIds,
                             'bamboraPaymentwindowUrl' => $bamboraPaymentData['paymentWindowUrl'],
                             'bamboraWindowState' => Configuration::get('BAMBORA_WINDOWSTATE'),
                             'bamboraCheckouturl' => $checkoutResponse['url'],
                             'onlyShowLogoes' => Configuration::get('BAMBORA_ONLYSHOWPAYMENTLOGOESATCHECKOUT')
                             );

        $this->context->smarty->assign($paymentData);

        return $this->display(__FILE__ , "bamboracheckout.tpl");
    }

    /**
     * Check Currency
     *
     * @param mixed $cart
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
     * Get Payment Card Ids
     *
     * @param mixed $currency
     * @param mixed $amount
     * @return array
     */
    public function getPaymentCardIds($currency, $amount)
    {
        $apiKey = $this->getApiKey();
        $api = new BamboraApi($apiKey);
        return $api->getAvaliablePaymentcardidsForMerchant($currency, $amount);
    }

    public function getBamboraPaymentData($checkoutRequest)
    {
        $apiKey = $this->getApiKey();
        $api = new BamboraApi($apiKey);

        $checkoutResponse = $api->getcheckoutresponse($checkoutRequest);

        $bamboraPaymentwindowUrl = $api->getcheckoutpaymentwindowjs();
        $paymentData = array('checkoutResponse' => $checkoutResponse,
                             'paymentWindowUrl' => $bamboraPaymentwindowUrl
                             );

        return $paymentData;
    }

    /**
     * Create Checkout Request
     *
     * @param mixed $cart
     * @return BamboraCheckoutRequest
     */
    public function createCheckoutRequest($cart)
    {
        $invoiceAddress = new Address(intval($cart->id_address_invoice));
        $deliveryAddress = new Address(intval($cart->id_address_delivery));

        $bamboraCustommer = $this->createBamboraCustommer($cart, $invoiceAddress);
        $bamboraOrder = $this->createBamboraOrder($cart, $invoiceAddress, $deliveryAddress);
        $bamboraUrl = $this->createBamboraUrl($bamboraOrder->ordernumber, $bamboraOrder->currency, $bamboraOrder->total);

        $language = new Language(intval($cart->id_lang));

        $request = new BamboraCheckoutRequest();
        $request->customer = $bamboraCustommer;
        $request->instantcaptureamount =  Configuration::get('BAMBORA_INSTANTCAPTURE') == 1 ? $bamboraOrder->total : 0;
        $request->language = str_replace("_","-",$language->language_code);
        $request->order = $bamboraOrder;
        $request->url = $bamboraUrl;

        $paymentWindowId = Configuration::get('BAMBORA_PAYMENTWINDOWID');

        $request->paymentwindowid = is_numeric($paymentWindowId) ?  $paymentWindowId : 1;

        return $request;
    }

    /**
     * Create Bambora Custommer
     *
     * @param mixed $cart
     * @param mixed $invoiceAddress
     * @return BamboraCustomer
     */
    private function createBamboraCustommer($cart, $invoiceAddress)
    {
        $mobileNumber = $this->getPhoneNumber($invoiceAddress);
        $country = new Country(intval($invoiceAddress->id_country));
        $customer = new Customer(intval($cart->id_customer));

        $bamboraCustommer = new BamboraCustomer();
        $bamboraCustommer->email = $customer->email;
        $bamboraCustommer->phonenumber = $mobileNumber;
        $bamboraCustommer->phonenumbercountrycode = $country->call_prefix;
        return $bamboraCustommer;
    }

    /**
     * Get Phone Number
     *
     * @param mixed $invoiceAddress
     * @return mixed
     */
    public function getPhoneNumber($address)
    {
        if($address->phone_mobile != "" || $address->phone != "")
        {
            return $address->phone_mobile != "" ? $address->phone_mobile : $address->phone;
        }
        else
        {
            return "";
        }
    }

    /**
     * Create Bambora Order
     *
     * @param mixed $cart
     * @param mixed $invoiceAddress
     * @param mixed $deliveryAddress
     * @return BamboraOrder
     */
    private function createBamboraOrder($cart, $invoiceAddress, $deliveryAddress)
    {
        $bamboraOrder = new BamboraOrder();
        $bamboraOrder->billingaddress = $this->createBamboraAddress($invoiceAddress);

        $currency = new Currency(intval($cart->id_currency));

        $bamboraOrder->currency = $currency->iso_code;
        $bamboraOrder->lines = $this->createBamboraOrderlines($cart, $bamboraOrder->currency);

        $bamboraOrder->ordernumber = (string)$cart->id;

        $bamboraOrder->shippingaddress = $this->createBamboraAddress($deliveryAddress);

        $minorUnits = BamboraCurrency::getCurrencyMinorunits($bamboraOrder->currency);

        $bamboraOrder->total = BamboraCurrency::convertPriceToMinorUnits($cart->getOrderTotal(),$minorUnits);

        $bamboraOrder->vatamount = BamboraCurrency::convertPriceToMinorUnits($cart->getOrderTotal() - $cart->getOrderTotal(false),$minorUnits);

        return $bamboraOrder;
    }

    /**
     * Create Bambora Address
     *
     * @param mixed $address
     * @return BamboraAddress
     */
    private function createBamboraAddress($address)
    {
        $bamboraAddress = new BamboraAddress();
        $bamboraAddress->att = $address->other;
        $bamboraAddress->city = $address->city;
        $bamboraAddress->country = $address->country;
        $bamboraAddress->firstname = $address->firstname;
        $bamboraAddress->lastname = $address->lastname;
        $bamboraAddress->street = $address->address1;
        $bamboraAddress->zip = $address->postcode;

        return $bamboraAddress;
    }


    /**
     * Create Bambora Order Lines
     *
     * @param mixed $cart
     * @param mixed $currency
     * @return BamboraOrderLine[]
     */
    private function createBamboraOrderlines($cart, $currency)
    {
        $bamboraOrderlines = array();

        $products = $cart->getproducts();
        $lineNumber = 1;
        $minorUnits = BamboraCurrency::getCurrencyMinorunits($currency);
        foreach($products as $product)
        {
            $line = new BamboraOrderLine();
            $line->description = $product["name"];
            $line->id = $product["id_product"];
            $line->linenumber = (string)$lineNumber;
            $line->quantity = intval($product["cart_quantity"]);
            $line->text = $product["name"];
            $line->totalprice = BamboraCurrency::convertPriceToMinorUnits($product["total"],$minorUnits);
            $line->totalpriceinclvat = BamboraCurrency::convertPriceToMinorUnits($product["total_wt"],$minorUnits);
            $line->totalpricevatamount = BamboraCurrency::convertPriceToMinorUnits($product["total_wt"] - $product["total"],$minorUnits);
            $line->unit = $this->l('pcs.');
            $line->vat = $product["rate"];

            $bamboraOrderlines[] = $line;
            $lineNumber++;
        }

        //Add shipping as an orderline
        $shippingCostWithTax = $cart->getTotalShippingCost(null,true,null);
        $shippingCostWithoutTax = $cart->getTotalShippingCost(null,false,null);
        if($shippingCostWithTax > 0)
        {
            $shippingOrderline = new BamboraOrderLine();
            $shippingOrderline->id = $this->l('shipping.');
            $shippingOrderline->description = $this->l('shipping.');
            $shippingOrderline->quantity = 1;
            $shippingOrderline->unit = $this->l('pcs.');
            $shippingOrderline->linenumber = $lineNumber++;
            $shippingOrderline->totalprice = BamboraCurrency::convertPriceToMinorUnits($shippingCostWithoutTax, $minorUnits);
            $shippingOrderline->totalpriceinclvat = BamboraCurrency::convertPriceToMinorUnits($shippingCostWithTax, $minorUnits);
            $shippingTax = $shippingCostWithTax - $shippingCostWithoutTax;
            $shippingOrderline->totalpricevatamount = BamboraCurrency::convertPriceToMinorUnits($shippingTax, $minorUnits);
            $shippingOrderline->vat = round( $shippingTax / $shippingCostWithoutTax * 100);
            $bamboraOrderlines[] = $shippingOrderline;
        }
        return $bamboraOrderlines;
    }

    /**
     * Create Bambora Url
     *
     * @param mixed $orderId
     * @param mixed $currency
     * @param mixed $amount
     * @return BamboraUrl
     */
    private function createBamboraUrl($orderId,$currency,$amount)
    {
        $bamboraUrl = new BamboraUrl();
        $bamboraUrl->accept = $this->context->link->getModuleLink('bambora', 'accept', array(), true);
        $bamboraUrl->decline = $this->context->link->getPageLink('order', true, null, "step=3");

		$bamboraUrl->callbacks = array();
		$callback = new BamboraCallback();
		$callback->url = $this->context->link->getModuleLink('bambora', 'callback', array(), true);
        $bamboraUrl->callbacks[] = $callback;

        $bamboraUrl->immediateredirecttoaccept = Configuration::get('BAMBORA_IMMEDIATEREDIRECTTOACCEPT') ? 1 : 0;

        return $bamboraUrl;
    }

    /**
     * Hook Payment Return
     *
     * @param mixed $params
     * @return mixed
     */
    public function hookPaymentReturn($params)
	{
		if(!$this->active)
        {
			return;
        }

        $order = null;
        if ($this->getPsVersion() === $this::V17)
        {
            $order = $params['order'];
        }
        else
        {
            $order = $params['objOrder'];
        }

        $payment = $order->getOrderPayments();
        $transactionId = $payment[0]->transaction_id;
        $this->context->smarty->assign('bambora_completed_paymentText', $this->l('You completed your payment.'));

        if($transactionId)
        {
            $this->context->smarty->assign('bambora_completed_transactionText', $this->l('Your transaction ID for this payment is:'));
            $this->context->smarty->assign('bambora_completed_transactionValue', $transactionId);
        }

        $customer = new Customer($order->id_customer);

        if($customer->email)
        {
            $this->context->smarty->assign('bambora_completed_emailText', $this->l('An confirmation email has been sendt to:'));
            $this->context->smarty->assign('bambora_completed_emailValue',$customer->email);
        }

		return $this->display(__FILE__ , 'views/templates/front/payment_return.tpl');
	}

    #endregion

    #region admin

    /**
     * Hook Admin Order
     *
     * @param mixed $params
     * @return string
     */
    public function hookAdminOrder($params)
    {
        $html = '';

        $bamboraUiMessage = $this->processRemote($params);
        if(isset($bamboraUiMessage))
        {
            $this->buildOverlayMessage($bamboraUiMessage->type, $bamboraUiMessage->title, $bamboraUiMessage->message);
        }

        $order = new Order($params['id_order']);

        if ($order->module == 'bambora')
        {
            $html .=  $this->displayTransactionForm($order);
        }

        return $html;
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
            <a id="bambora_inline" href="#data"></a>
            <div id="bambora_overlay"><div id="data" class="row bambora_overlay_data"><div id="bambora_message" class="col-lg-12">' ;

        if($type == "issue")
        {
            $html .= $this->showExclamation();
        }
        else
        {
            $html .= $this->showCheckmark();
        }
        $html .='<div id="bambora_overlay_message_container">';

        if(strlen($message) > 0)
        {
            $html .='<p id="bambora_overlay_message_title_with_message">'.$title.'</p>';
            $html .= '<hr><p id="bambora_overlay_message_message">'. $message .'</p>';
        }
        else
        {
            $html .='<p id="bambora_overlay_message_title">'.$title.'</p>';
        }

        $html .= '</div></div></div></div>';

        echo $html;
    }

    /**
     * Display Transaction Form
     *
     * @param mixed $order
     * @return string
     */
    private function displayTransactionForm($order)
	{
        $payments = $order->getOrderPayments();
		$transactionId = $payments[0]->transaction_id;

        $html = $this-> buildBamboraContainerStartTag();

        if (empty($transactionId))
        {

            $html .= 'No payment transaction was found';
            $html .= $this->buildBamboraContainerStopTag();
            return $html;

        }
        $cardBrand = $payments[0]->card_brand;

        $html .= '<div class="panel-heading">';
        if(!empty($cardBrand))
        {
            $html .= "Bambora Checkout ({$cardBrand})";
        }
        else
        {
            $html .= "Bambora Checkout";
        }
        $html .= '</div>';

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
        $html = '<script type="text/javascript" src="'.$this->_path.'js/bamboraScripts.js" charset="UTF-8"></script>';
        if($this->getPsVersion() === $this::V15)
        {
            $html .= '<style type="text/css">
                            .table td{white-space:nowrap;overflow-x:auto;}
                        </style>';
            $html .= '<br /><fieldset><legend><img src="../img/admin/money.gif">Bambora Checkout</legend>';
        }
        else
        {
            $html .= '<div class="row" >';
            $html .= '<div class="col-lg-12">';
            $html .= '<div class="panel bambora_widthAllSpace" style="overflow:auto">';
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
        if ($this->getPsVersion() === $this::V15)
        {
            $html = "</fieldset>";
        }
        else
        {
            $html = "</div>";
            $html .= "</div>";
            $html .= "</div>";

        }

        return $html;
    }

    /**
     * Get Html Content
     *
     * @param mixed $transactionId
     * @param mixed $order
     * @return string
     */
    private function getHtmlcontent($transactionId, $order)
    {
        $html = "";
		try
		{
            $apiKey = $this->getApiKey();
            $api = new BamboraApi($apiKey);

			$bamboraTransaction = $api->gettransaction($transactionId);

            if(!$bamboraTransaction["meta"]["result"])
            {
                return $this->merchantErrorMessage($bamboraTransaction["meta"]["message"]["merchant"]);
            }

            $bamboraTransactionOperations = $api->gettransactionoperations($transactionId);

            if(!$bamboraTransactionOperations["meta"]["result"])
            {
                return $this->merchantErrorMessage($bamboraTransactionOperations["meta"]["message"]["merchant"]);
            }

            $transactionInfo = $bamboraTransaction["transaction"];
            $transactionOperations = $bamboraTransactionOperations["transactionoperations"];
            $currency = new Currency($order->id_currency);
            $html .= '<div class="row">

                        <div class="col-xs-12 col-md-4 col-lg-4 col-sm-12">'
                            .$this->buildPaymentTable($transactionInfo, $currency)
                            .$this->buildButtonsForm($transactionInfo)
                        .'</div>

                        <div class="col-md-8 col-lg-6 hidden-xs hidden-sm">'
                            .$this->createCheckoutTransactionOperationsHtml($transactionOperations, $currency)
                        .'</div>

                        <div class="col-lg-2 visible-lg text-center hidden-sm">'
                            .$this->buildLogodiv(true)
                        .'</div>'
                    .'</div>';
        }
		catch (Exception $e)
		{
			$this->displayError($e->getMessage());
		}

        return $html;
    }

    /**
     * Build Payment Table
     *
     * @param mixed $transactionInfo
     * @param mixed $currency
     * @return string
     */
    private function buildPaymentTable($transactionInfo, $currency)
    {
        $html = '<table class="bambora-table">
                 <tr><th>'.$this->l('Payment').'</th></tr>'
                .'<tr><td>'. $this->l('Amount') .':</td>';

        $html .='<td><div class="badge bambora-badge-big badge-success" title="'. $this->l('Amount available for capture').'">';

        $amount = BamboraCurrency::convertPriceFromMinorUnits($transactionInfo["total"]["authorized"], $transactionInfo["currency"]["minorunits"]);

        $formatetAmount = Tools::displayPrice($amount, $currency);

        $html .= $formatetAmount .'</div></td></tr>';

        $html .='<tr><td>'. $this -> l('Order Id') .':</td><td>'. $transactionInfo["orderid"].'</td></tr>';

        $formattedCardnumber = BamboraHelpers::formatTruncatedCardnumber($transactionInfo["information"]["primaryaccountnumbers"][0]["number"]);
        $html .= '<tr><td>'. $this->l('Cardnumber').':</td><td>' .$formattedCardnumber .'</td></tr>';

        $html .='<tr><td>'. $this->l('Status') .':</td><td>'. $this->checkoutStatus($transactionInfo["status"]).'</td></tr>';

        $html .= '</table>';

        return $html;
    }

    /**
     * Set the first letter to uppercase
     *
     * @param string $status
     * @return string
     */
    private function checkoutStatus($status)
    {
        if(!isset($status))
        {
            return "";
        }
        $firstLetter = substr($status,0,1);
        $firstLetterToUpper = strtoupper($firstLetter);
        $result = str_replace($firstLetter,$firstLetterToUpper,$status);

        return $result;
    }

    private function buildButtonsForm($transactionInfo)
    {
        $html = '';
        if ($transactionInfo["available"]["capture"] > 0 || $transactionInfo["available"]["credit"] > 0 || $transactionInfo["candelete"] == 'true')
        {
            $html .= '<form name="bambora_remote" action="' . $_SERVER["REQUEST_URI"] . '" method="post" class="bambora_displayInline" id="bambora-action" >'
                . '<input type="hidden" name="bambora_transaction_id" value="' . $transactionInfo["id"] . '" />'
                . '<input type="hidden" name="bambora_order_id" value="' . $transactionInfo["orderid"] . '" />'
                . '<input type="hidden" name="bambora_currency_code" value="' . $transactionInfo["currency"]["code"] . '" />';

            $html .= '<br />';

            $html .= '<div id="divBamboraTransactionControlsContainer" class="bambora-buttons clearfix">';
            $html .= $this->buildSpinner();

            $minorUnits = $transactionInfo["currency"]["minorunits"];

            $availableForCapture = BamboraCurrency::convertPriceFromMinorUnits($transactionInfo["available"]["capture"], $minorUnits);

            $availableForCredit = BamboraCurrency::convertPriceFromMinorUnits($transactionInfo["available"]["credit"], $minorUnits);

            if ($availableForCapture > 0)
            {
                $html .= $this->buildTransactionControlInclTextField('capture', $this->l('Capture'), "btn bambora_confirm_btn", true, $availableForCapture, $transactionInfo["currency"]["code"]);
            }

            if($availableForCredit > 0)
            {
                $html .= $this->buildTransactionControlInclTextField('credit', $this->l('Refund'), "btn bambora_credit_btn", true, $availableForCredit, $transactionInfo["currency"]["code"]);
            }

            if ($transactionInfo["candelete"] == 'true')
            {
                $html .= $this->buildTransactionControl('delete', $this->l('Delete'), "btn bambora_delete_btn");
            }

            $html .= '</div></form>';
            $html .= '<div id="bambora-format-error" class="alert alert-danger"><strong>'.$this->l('Warning').' </strong>'.$this->l('The amount you entered was in the wrong format. Please try again!').'</div>';
        }

        return $html;
    }

    /**
     * Create Checkout Transaction Operations Html
     *
     * @param mixed $transactionOperations
     * @param mixed $currency
     * @return string
     */
    private function createCheckoutTransactionOperationsHtml($transactionOperations, $currency)
    {
        $res = '<br/>';
        $res .= "<table class='bambora_operations_table' border='0' width='100%'>";
        $res .= '<tr><td colspan="6" class="bambora_operations_title"><strong>'.$this->l('Transaction Operations'). '</strong></td></tr>';
        $res .= '<th>'.$this->l('Date').'</th>';
        $res .= '<th>'.$this->l('Action').'</th>';
        $res .= '<th>'.$this->l('Amount').'</th>';
        $res .= '<th>'.$this->l('ECI').'</th>';
        $res .= '<th>'.$this->l('Operation ID').'</th>';
        $res .= '<th>'.$this->l('Parent Operation ID').'</th>';

        $res .= $this->createTranactionOperationItems($transactionOperations, $currency);

        $res .= '</table>';

        return $res;
    }

    /**
     * Create Tranaction Operation Items
     *
     * @param mixed $transactionOperations
     * @param mixed $currency
     * @return string
     */
    private function createTranactionOperationItems($transactionOperations, $currency)
    {
        $res = "";
        foreach($transactionOperations as $operation)
        {
            $res .= '<tr>';
            $date = str_replace("T", " ",substr($operation["createddate"], 0, 19));
            $res .= '<td>' . Tools::displayDate($date).'</td>' ;
            $res .= '<td>' . $operation["action"]  .'</td>';
            if($operation["amount"] > 0)
            {
                $amount = BamboraCurrency::convertPriceFromMinorUnits($operation["amount"], $operation["currency"]["minorunits"]);
                $res .= '<td>' .Tools::displayPrice($amount, $currency) . '</td>';
            }
            else
            {
                $res .= '<td> - </td>';
            }

            if(key_exists("ecis",$operation) && is_array($operation["ecis"]) && count($operation["ecis"])> 0)
            {
                $res .= '<td>' . $operation["ecis"][0]["value"] .'</td>';
            }
            else
            {
                $res .= '<td> - </td>';
            }

            $res .= '<td>' . $operation["id"] . '</td>';

            if(key_exists("parenttransactionoperationid",$operation) &&  $operation["parenttransactionoperationid"] > 0)
            {
                $res .= '<td>' . $operation["parenttransactionoperationid"] .'</td>';
            }
            else
            {
                $res .= '<td> - </td>';
            }

            if(key_exists("transactionoperations", $operation) && count($operation["transactionoperations"]) > 0)
            {
                $res .= $this->createTranactionOperationItems($operation["transactionoperations"], $currency);
            }
            $res .= '</tr>';
        }

        return $res;
    }

    /**
     * Build Logo Div
     *
     * @param mixed $makeAslinkToAdminSite
     * @return string
     */
    private function buildLogodiv($makeAslinkToAdminSite = false)
    {
        $html = '<a href="https://admin.epay.eu/Account/Login" alt="" title="' . $this->l('Go to Bambora Merchant Administration') . '" target="_blank">';
        $html .= '<img class="bambora_logo" src="https://d3r1pwhfz7unl9.cloudfront.net/bambora/bambora_black_300px.png" />';
        $html .= '</a>';
        $html .= '<div><a href="https://admin.epay.eu/Account/Login"  alt="" title="' . $this->l('Go to Bambora Merchant Administration') . '" target="_blank">' .$this->l('Go to Bambora Merchant Administration') .'</a></div>';

        return $html;
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
     * @return string
     */
    private function buildTransactionControlInclTextField($type, $value, $class, $addInputField = false, $valueOfInputfield = 0, $currencycode = "")
    {
        $tooltip = $this->l('Example: 1234.56');
        $html = '<div>
                    <div class="bambora_floatLeft">
                        <div class="bambora_confirm_frame" >
                            <input  class="'.$class.'" name="unhide_'.$type.'" type="button" value="' . strtoupper($value) . '"  />
                       </div>
                        <div class="bamboraButtonFrame bambora_hidden bamboraButtonFrameIncreesedsize row" data-hasinputfield="'.$addInputField .'">'
                            .'<div class="col-xs-3">
                                <a class="bambora_cancel"></a>
                             </div>
                             <div class="col-xs-5">
                                <input id="bambora-action-input" type="text" data-toggle="tooltip" title="'.$tooltip.'" required="required" name="bambora_'.$type.'_value" value="'.$valueOfInputfield .'" />
                               <p>'. $currencycode.'</p>
                            </div>
                             <div class="col-xs-4">
                                <input class="'.$class.'" name="bambora_'.$type.'" type="submit" value="' .strtoupper($value) . '" />
                             </div>
                         </div>
                      </div>
                   </div>';

        return $html;
    }

    /**
     * Build Transaction Control
     *
     * @param mixed $type
     * @param mixed $value
     * @param mixed $class
     * @return string
     */
    private function buildTransactionControl($type, $value, $class)
    {
        $html = '<div>
                   <div class="bambora_floatLeft">
                       <div class="bambora_confirm_frame" >
                           <input  class="'.$class.'" name="unhide_'.$type.'" type="button" value="' .strtoupper($value) . '"  />
                      </div>
                      <div class="bamboraButtonFrame bambora_hidden bambora-normalSize row" data-hasinputfield="false">
                           <div class="col-xs-6">
                               <a class="bambora_cancel"></a>
                          </div>
                          <div class="col-xs-6">
                             <input class="'.$class.'" name="bambora_'.$type.'" type="submit" value="'  .strtoupper($value) . '" />
                          </div>
                      </div>
                   </div>
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
        $html = '<div class="bambora-circle bambora-checkmark_circle">
                        <div class="bambora-checkmark_stem"></div>
                    </div>';

        return $html;
    }

    /**
     * Show Exclamation
     *
     * @return string
     */
    private function showExclamation()
    {
        $html = '<div class="bambora-circle bambora-exclamation_circle">
                        <div class="bambora-exclamation_stem"></div>
                        <div class="bambora-exclamation_dot"></div>
                    </div>';

        return $html;
    }

    /**
     * Process Remote
     *
     * @param mixed $params
     * @return BamboraUiMessage|null
     */
    private function processRemote($params)
	{
        $bamboraUiMessage = null;
        if((Tools::isSubmit('bambora_capture') || Tools::isSubmit('bambora_credit') || Tools::isSubmit('bambora_delete')) && Tools::getIsset('bambora_transaction_id') && Tools::getIsset('bambora_currency_code'))
		{
            $bamboraUiMessage = new BamboraUiMessage();
            $result = "";
            try
            {
                $transactionId = Tools::getValue("bambora_transaction_id");
                $currencyCode = Tools::getValue("bambora_currency_code");
                $minorUnits = BamboraCurrency::getCurrencyMinorunits($currencyCode);

                $apiKey = $this->getApiKey();
                $api = new BamboraApi($apiKey);

                if(Tools::isSubmit('bambora_capture'))
                {
                    $captureInputValue = Tools::getValue('bambora_capture_value');

                    $amountSanitized = str_replace(',','.', $captureInputValue);
                    $amount = floatval($amountSanitized);
                    if(is_float($amount))
                    {
                        $amountMinorunits = BamboraCurrency::convertPriceToMinorUnits($amount, $minorUnits);

                        $result = $api->capture($transactionId, $amountMinorunits, $currencyCode);
                    }
                    else
                    {
                        $bamboraUiMessage->type = 'issue';
                        $bamboraUiMessage->title = $this->l('Inputfield is not a valid number');
                        return $bamboraUiMessage;
                    }
                }
                elseif(Tools::isSubmit('bambora_credit'))
                {
                    $captureInputValue = Tools::getValue('bambora_credit_value');

                    $amountSanitized = str_replace(',','.', $captureInputValue);
                    $amount = floatval($amountSanitized);
                    if(is_float($amount))
                    {
                        $amountMinorunits = BamboraCurrency::convertPriceToMinorUnits($amount, $minorUnits);

                        $invoiceLine = $this->buildCreditInvoiceLine($amountMinorunits);

                        $result = $api->credit($transactionId, $amountMinorunits, $currencyCode, $invoiceLine);
                    }
                    else
                    {
                        $bamboraUiMessage->type = 'issue';
                        $bamboraUiMessage->title = $this->l('Inputfield is not a valid number');
                        return $bamboraUiMessage;
                    }
                }
                elseif(Tools::isSubmit('bambora_delete'))
                {
                    $result = $api->delete($transactionId);
                }

                if ($result["meta"]["result"] === true)
                {
                    if(Tools::isSubmit('bambora_capture'))
                    {
                        $captureText = $this->l('The Payment was') . ' ' . $this -> l('captured') .' '.$this->l('successfully');
                        $bamboraUiMessage->type = "capture";
                        $bamboraUiMessage->title = $captureText;

                    }
                    elseif(Tools::isSubmit('bambora_credit'))
                    {
                        $creditText = $this -> l('The Payment was') . ' ' . $this -> l('refunded') .' '.$this->l('successfully');
                        $bamboraUiMessage->type = "credit";
                        $bamboraUiMessage->title = $creditText;

                    }
                    elseif(Tools::isSubmit('bambora_delete'))
                    {
                        $deleteText = $this->l('The Payment was').' '. $this -> l('delete') .' '.$this->l('successfully');
                        $bamboraUiMessage->type = "delete";
                        $bamboraUiMessage->title = $deleteText;
                    }
                }
                else
                {
                    $bamboraUiMessage->type = "issue";
                    $bamboraUiMessage->title = $this->l('An issue occured, and the operation was not performed.');
                    if (isset($result["message"]) && isset($result["message"]["merchant"]))
                    {
                        $message = $result["message"]["merchant"];

                        if(isset($message["action"]) && $result["action"]["source"] == "ePayEngine" && ($result["action"]["code"] == "113" || $result["action"]["code"] == "114"))
                        {
                            preg_match_all('!\d+!', $message, $matches);
                            foreach($matches[0] as $match)
                            {
                                $convertedAmount = Tools::displayPrice(BamboraCurrency::convertPriceFromMinorUnits($match, $minorUnits));
                                $message = str_replace($match, $convertedAmount, $message);
                            }
                        }
                        $bamboraUiMessage->message = $message;
                    }
                }
            }
            catch(Exception $e)
            {
                $this->displayError($e->getMessage());
            }
		}

        return $bamboraUiMessage;
	}

    /**
     * Build Credit Invoice Line
     *
     * @param mixed $amount
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
            "unit"=>"pcs.",
            "vat"=>0
            );

        return $result;
    }

    /**
     * Build Spinner
     *
     * @return string
     */
    private function buildSpinner()
    {
        $html = '<div id="bamboraSpinner" class="bamboraButtonFrame bamboraButtonFrameIncreesedsize row"><div class="col-lg-10">'.$this->l('Working').'... </div><div class="col-lg-2"><div class="bambora-spinner"><img src="'.$this->_path.'img/arrows.svg" class="bambora-spinner"></div></div></div>';

        return $html;
    }

    /**
     * Merchant Error Message
     *
     * @param mixed $reason
     * @return string
     */
    private function merchantErrorMessage($reason)
    {
        $message = "An error occured.";
        if(isset($reason))
        {
            $message .= "<br/>Reason: ". $reason;
        }

        return $message;
    }

    #endregion

    #region Display PDF

    /**
     * Hook Display PDF Invoice
     *
     * @param mixed $params
     * @return string
     */
    public function hookDisplayPDFInvoice($params)
    {
        $invoice = $params["object"];
        $order = new Order($invoice->id_order);
        $payments = $order->getOrderPayments();
        if(count($payments) === 0)
        {
            return "";
        }

        $transactionId = $payments[0]->transaction_id;
        $paymentType = $payments[0]->card_brand;
        $truncatedCardNumber = $payments[0]->card_number;

        $formattedCardnumber = BamboraHelpers::formatTruncatedCardnumber($truncatedCardNumber);

        $result = '<table>';
        $result .= '<tr><td colspan="2"><strong>'.$this->l('Payment information').'</strong></td></tr>';
        $result .= '<tr><td>'.$this->l('Transaction id').':</td><td>' .$transactionId .'</td></tr>';
        $result .= '<tr><td>'.$this->l('Payment type').':</td><td>' .$paymentType .'</td></tr>';
        $result .= '<tr><td>'.$this->l('Card number').':</td><td>' .$formattedCardnumber .'</td></tr>';
        $result .= '</table>';

        return $result;
    }

    #endregion

    #region General

    /**
     * Get Api Key
     *
     * @return string
     */
    private function getApiKey()
    {
        if(empty($this->_apiKey))
        {
            $this->_apiKey = BamboraHelpers::generateApiKey();
        }

        return $this->_apiKey;
    }

    /**
     * Get Ps Version
     *
     * @return string
     */
    public function getPsVersion()
    {
        if(_PS_VERSION_ < "1.6.0.0")
        {
            return $this::V15;
        }
        elseif(_PS_VERSION_ >= "1.6.0.0" && _PS_VERSION_ < "1.7.0.0")
        {
            return $this::V16;
        }
        else
        {
            return $this::V17;
        }
    }

    #endregion
}