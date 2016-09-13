<?php
/*
 * Copyright (c) 2016. All rights reserved Bambora - www.bambora.com
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
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

    const MODULE_NAME = 'bambora';
    const MODULE_VERSION = '1.4.4';
    const MODULE_AUTHOR = 'Bambora';

	public function __construct()
	{
		$this->name = $this::MODULE_NAME;
		$this->version = $this::MODULE_VERSION;
		$this->author = $this::MODULE_AUTHOR;
		$this->tab = 'payments_gateways';

		$this->currencies = true;
		$this->currencies_mode = 'checkbox';

		parent::__construct();

		if(Configuration::get('BAMBORA_ENABLE_REMOTE_API') == 1 && strlen(Configuration::get('BAMBORA_REMOTE_API_PASSWORD')) <= 0)
			$this->warning = $this->l('You must set Remote API password to use payment requests. Remember to set the password in the Bambora administration under the menu API / Webservices > Access.');

		$this->displayName = 'Bambora Checkout';
		$this->description = $this->l('Accept payments for your products online');
	}

	public function install()
	{

        if(!parent::install()
            OR !Configuration::updateValue('BAMBORA_GOOGLE_PAGEVIEW', '0')
            OR !Configuration::updateValue('BAMBORA_INTEGRATION', '1')
            OR !$this->registerHook('payment')
            OR !$this->registerHook('rightColumn')
            OR !$this->registerHook('adminOrder')
            OR !$this->registerHook('paymentReturn')
            OR !$this->registerHook('updateProduct')
            OR !$this->registerHook('PDFInvoice')
            OR !$this->registerHook('Invoice')
            OR !$this->registerHook('backOfficeHeader')
			OR !$this->registerHook('displayHeader')
            OR !$this->registerHook('displayBackOfficeHeader')
        )
			return false;

		if(!$this->createBamboraTransactionTable())
			return false;

		return true;
	}

	public function uninstall()
	{
		return parent::uninstall();
	}


	public function hookDisplayHeader()
	{
        if ($this->context->controller != null)
		{
			$this->context->controller->addCSS($this->_path.'css/bamboraStyle.css', 'all');
		}
	}
    public function hookBackOfficeHeader($params) {
        $this->context->controller->addCSS($this->_path.'css/bamboraStyle.css', 'all');

    }
    public function hookDisplayBackOfficeHeader($params){
        $this->hookBackOfficeHeader($params);
		$this->BamboraUiMessage = $this->procesRemote($params);
        return "";
    }


	private function createBamboraTransactionTable()
	{
		$table_name = _DB_PREFIX_ . 'bambora_transactions';

		$columns = array
		(
			'id_order' => 'int(10) unsigned NOT NULL',
			'id_cart' => 'int(10) unsigned NOT NULL',
			'bambora_transaction_id' => 'bigint(20) unsigned NOT NULL',
			'bambora_orderid' => 'varchar(20) NOT NULL',
			'card_type' => 'int(4) unsigned NOT NULL DEFAULT 1',
			'currency' => 'varchar(4) NOT NULL',
			'amount' => 'int(10) unsigned NOT NULL',
			'amount_captured' => 'int(10) unsigned NOT NULL DEFAULT 0',
			'amount_credited' => 'int(10) unsigned NOT NULL DEFAULT 0',
			'transfee' => 'int(10) unsigned NOT NULL DEFAULT 0',
			'captured' => 'tinyint(1) NOT NULL DEFAULT 0',
			'credited' => 'tinyint(1) NOT NULL DEFAULT 0',
			'deleted' => 'tinyint(1) NOT NULL DEFAULT 0',
			'date_add' => 'datetime NOT NULL'
		);

		$query = 'CREATE TABLE IF NOT EXISTS `' . $table_name . '` (';

		foreach ($columns as $column_name => $options)
		{
			$query .= '`' . $column_name . '` ' . $options . ', ';
		}

		$query .= ' PRIMARY KEY (`bambora_transaction_id`) )';

		if(!Db::getInstance()->Execute($query))
			return false;

		$i = 0;
		$previous_column = '';
		$query = ' ALTER TABLE `' . $table_name . '` ';

		//Check the database fields
		foreach ($columns as $column_name => $options)
		{
			if(!$this->mysqlColumnExists($table_name, $column_name))
			{
				$query .= ($i > 0 ? ', ' : '') . 'ADD `' . $column_name . '` ' . $options . ($previous_column != '' ? ' AFTER `' . $previous_column . '`' : ' FIRST');
				$i++;
			}
			$previous_column = $column_name;
		}

		if($i > 0)
			if(!Db::getInstance()->Execute($query))
				return false;

		return true;
	}


	private static function mysqlColumnExists($table_name, $column_name, $link = false)
	{
		$result = Db::getInstance()->executeS("SHOW COLUMNS FROM ".$table_name. " LIKE ".$column_name, $link);

		return (count($result) > 0);
	}

	public function recordTransaction($id_order, $id_cart = 0, $transaction_id = 0, $card_id = 0,  $currency = 0, $amount = 0, $transfee = 0, $bambora_order_id=0 )
	{
		if(!$id_order)
			$id_order = 0;

		$captured = (Configuration::get('BAMBORA_INSTANTCAPTURE') ? 1 : 0);

		/* Add transaction id to the order */
		$query = 'INSERT INTO ' . _DB_PREFIX_ . 'bambora_transactions
				(id_order, id_cart, bambora_transaction_id, bambora_orderid, card_type, currency, amount, transfee, captured, date_add)
				VALUES
				(' . $id_order . ', ' . $id_cart . ', ' . $transaction_id. ', '. $bambora_order_id . ', ' . $card_id . ',  \'' . $currency . '\', ' . $amount . ', ' . $transfee . ', ' . $captured  .', NOW())';

		if(!Db::getInstance()->Execute($query))
			return false;

		return true;
	}

	private function setCaptured($transaction_id, $amount)
	{
		$query = ' UPDATE ' . _DB_PREFIX_ . 'bambora_transactions SET `captured` = 1, `amount` = ' . $amount . ' WHERE `bambora_transaction_id` = ' . $transaction_id;
		if(!Db::getInstance()->Execute($query))
			return false;
		return true;
	}

	private function setCredited($transaction_id, $amount)
	{
        if ($amount < 0)
        {
            $amount = $amount *-1;
        }
		$query = ' UPDATE ' . _DB_PREFIX_ . 'bambora_transactions SET `credited` = 1, `amount` = ' . $amount . ' WHERE `bambora_transaction_id` = ' . $transaction_id;
		if(!Db::getInstance()->Execute($query))
			return false;
		return true;
	}

	private function deleteTransaction($transaction_id)
	{
		$query = ' UPDATE ' . _DB_PREFIX_ . 'bambora_transactions SET `deleted` = 1 WHERE `bambora_transaction_id` = ' . $transaction_id;
		if(!Db::getInstance()->Execute($query))
			return false;
		return true;
	}

	public function getContent()
	{
		$output = null;

	    if (Tools::isSubmit('submit'.$this->name))
	    {
	        $bambora_merchantnumber = strval(Tools::getValue('BAMBORA_MERCHANTNUMBER'));
	        if (!$bambora_merchantnumber  || empty($bambora_merchantnumber) || !Validate::isGenericName($bambora_merchantnumber))
	            $output .= $this->displayError( $this->l('Merchant number is required. If you don\'t have one please contact Bambora in order to obtain one!') );
	        else
	        {
				Configuration::updateValue('BAMBORA_MERCHANTNUMBER', Tools::getValue("BAMBORA_MERCHANTNUMBER"));
				Configuration::updateValue('BAMBORA_PAYMENTWINDOWID', Tools::getValue("BAMBORA_PAYMENTWINDOWID"));
				Configuration::updateValue('BAMBORA_ENABLE_REMOTE_API', Tools::getValue("BAMBORA_ENABLE_REMOTE_API"));
				Configuration::updateValue('BAMBORA_INSTANTCAPTURE', Tools::getValue("BAMBORA_INSTANTCAPTURE"));
                Configuration::updateValue('BAMBORA_MD5KEY', Tools::getValue("BAMBORA_MD5KEY"));
                Configuration::updateValue('BAMBORA_ACCESSTOKEN', Tools::getValue("BAMBORA_ACCESSTOKEN"));
                Configuration::updateValue('BAMBORA_WINDOWSTATE', Tools::getValue("BAMBORA_WINDOWSTATE"));
                Configuration::updateValue('BAMBORA_IMMEDIATEREDIRECTTOACCEPT', Tools::getValue("BAMBORA_IMMEDIATEREDIRECTTOACCEPT"));
                if ( Tools::getValue("BAMBORA_SECRETTOKEN") != '**********')
                {
                    Configuration::updateValue('BAMBORA_SECRETTOKEN', Tools::getValue("BAMBORA_SECRETTOKEN"));
                }
                //persisting the base 64 encoded api password

                $apiKey = Tools::getValue("BAMBORA_ACCESSTOKEN"). '@' .Tools::getValue("BAMBORA_MERCHANTNUMBER"). ':' .Configuration::get("BAMBORA_SECRETTOKEN");
                $encodedKey = base64_encode($apiKey);
                Configuration::updateValue('BAMBORA_REMOTE_API_PASSWORD', 'Basic '. $encodedKey);

	            $output .= $this->displayConfirmation($this->l('Settings updated'));
	        }
	    }

	    $output .= $this->displayForm();
        return $output;
	}


    private function displayForm()
    {
        // Get default Language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $switch_options = array(
            array( 'id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')),
            array( 'id' => 'active_off', 'value' => 0, 'label' => $this->l('No')),
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
                    'label' => $this->l('Activate module'),
                    'name' => 'BAMBORA_ENABLE_REMOTE_API',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                 ),
                 array(
                    'type' => 'text',
                    'label' => $this->l('Merchant number'),
                    'name' => 'BAMBORA_MERCHANTNUMBER',
                    'size' => 40,
                    'required' => false,
                    'class' => 'myTest',
                    'id' => 'txtMerchantNo'
                ),
                 array(
                    'type' => 'text',
                    'label' => $this->l('Access token'),
                    'name' => 'BAMBORA_ACCESSTOKEN',
                    'size' => 40,
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Secret token'),
                    'name' => 'BAMBORA_SECRETTOKEN',
                    'size' => 40,
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Payment Window ID'),
                    'name' => 'BAMBORA_PAYMENTWINDOWID',
                    'size' => 40,
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('MD5 key'),
                    'name' => 'BAMBORA_MD5KEY',
                    'size' => 40,
                    'required' => false
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Use instant capture'),
                    'name' => 'BAMBORA_INSTANTCAPTURE',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                ),
                array(
                  'type' => 'switch',
                    'label' => $this->l('Enable immediateredirect'),
                    'name' => 'BAMBORA_IMMEDIATEREDIRECTTOACCEPT',
                    'required' => false,
                    'is_bool' => true,
                    'values' => $switch_options
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Display window as'),
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

        $this->bootstrap = true;
        $helper = new HelperForm();

        //mine
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
        $helper->fields_value['BAMBORA_MD5KEY'] = Configuration::get('BAMBORA_MD5KEY');
        $helper->fields_value['BAMBORA_ENABLE_PAYMENTREQUEST'] = Configuration::get('BAMBORA_ENABLE_PAYMENTREQUEST');
        $helper->fields_value['BAMBORA_ACCESSTOKEN'] = Configuration::get('BAMBORA_ACCESSTOKEN');
        $helper->fields_value['BAMBORA_WINDOWSTATE'] = Configuration::get('BAMBORA_WINDOWSTATE');
        $helper->fields_value['BAMBORA_IMMEDIATEREDIRECTTOACCEPT'] = Configuration::get('BAMBORA_IMMEDIATEREDIRECTTOACCEPT');

        $helper->fields_value['BAMBORA_SECRETTOKEN'] =  strlen(Configuration::get('BAMBORA_SECRETTOKEN')) > 0 ? '**********' : "";

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

    private function buildHelptextForSettings(){

        $html = str_replace("/n",'<br/>','<div class="panel helpContainer">
                        <H3>'.$this->l('Help for settings').'</H3>

                        <H5>'.$this->l('Activate module').'</H5>
                        <p>'.$this->l('Set to "Yes" to enable Bambora payments./n
                            If set to “No”, the Bambora payment option will not be visible to your customers.').'</p>
                        <br/>
                        <H5>'.$this->l('Merchant number').'</H5>
                        <p>'.$this->l('Get your Merchant number from the').' <a href="https://admin.epay.eu/" target="_blank">'.$this->l('Bambora Administration').'</a> '.$this->l('via Settings > Merchant numbers.
                        If you haven\'t got a Merchant number, please contact ').'<a href="http://www.bambora.com/da/dk/bamboraone/" target="_blank">Bambora</a> '.$this->l('to get one.').'
                        <br/> <b>'.$this->l('Note').':</b> '.$this->l('This field is mandatory to enable payments').'
                        </p>
                        <br/>
                        <H5>'.$this->l('Access token').'</H5>
                        <p>'.$this->l('Get your Access token from the').' <a href="https://admin.epay.eu/" target="_blank">'.$this->l('Bambora Administration').'</a> '.$this->l('via Settings > API users. Copy the Access token from the API user into this field.').'
                        <br/>
                        <b>'.$this->l('Note').':</b>'.$this->l(' This field is mandatory in order to enable payments').'</p>
                        <br/>
                        <H5>'.$this->l('Secret token').'</H5>
                        <p>'.$this->l('Get your Secret token from the').' <a href="https://admin.epay.eu/" target="_blank">'.$this->l('Bambora Administration').'</a> '.$this->l('via Settings > API users. The secret token is only displayed once when an API user is created! Please save this token in a safe place as Bambora will not be able to recover it.').'
                        <br/> <b>'.$this->l('Note').': </b> '.$this->l('This field is mandatory in order to enable payments').'</p>
                        <br/>
                        <H5>'.$this->l('Payment Window ID').'</H5>
                        <p>'.$this->l('Choose which payment window to use. You can create multiple payment windows in the').' <a href="https://admin.epay.eu/" target="_blank">'.$this->l('Bambora Administration').'</a> '.$this->l(' via Settings > Payment windows. /n
                            This is useful if you want to show different layouts, payment types or transaction fees for various customers').'</p>
                        <br/>
                        <H5>'.$this->l('MD5 Key').'</H5>
                        <p>'.$this->l('We recommend using MD5 to secure the data sent between your system and Bambora./nIf you have generated a MD5 key in the').' <a href="https://admin.epay.eu/" target="_blank">'.$this->l('Bambora Administration').'</a> '.$this->l('via Settings > Edit merchant, you have to enter the MD5 key here as well.').' /n
                            <b>'.$this->l('Note').': </b>'.$this->l('The keys must be identical in the two systems.').'
                        </p>
                        <br/>
                        <H5>'.$this->l('Use instant capture').'</H5>
                        <p>'.$this->l('Enable this to capture the payment immediately. /n You should only use this setting, if your customer receives the goods immediately e.g. via downloads or services.').'
                        </p>
                         <br/>
                        <H5>'.$this->l('Immediateredirect').'</H5>
                        <p>'.$this->l('Please select if you to go directly to the order confirmation page when payment is completed').'
                        </p>
                        <br/>
                        <H5>'.$this->l('Show window as').'</H5>
                        <p>'.$this->l('Please select if you want the Payment window shown as an overlay or as full screen').'
                        </p>

                   </div>');

        return $html;

    }
    private function merchantErrorMessage($reason)
    {
        $message = "An error occured.";
        if(isset($reason))
        {
            $message .= "<br/>Reason: ". $reason;
        }
        return $message;

    }

    private function getErrormessageUsingJSon($transMeta, $operationsMeta){
        $errMessage = 'Could not lookup the transaction ';
        if($transMeta["result"] == 'false')
        {
            $errMessage .= 'reason: '. $transMeta["message"]["merchant"];
        }else
        {
            if($operationsMeta["result"] == 'false')
            {
                $errMessage .= 'reason: '. $operationsMeta["message"]["merchant"];
            }
        }

        return $errMessage;
    }

    private function getHtmlcontent($transaction, $params)
    {
        $html = "";

        if ( !(Configuration::get('BAMBORA_ENABLE_REMOTE_API')))
        {
            $html .= $this->l('The Remote API is not enabled, please do so in order to start using this module.')
                  . '<br />'
                  .$this ->l('In order to do this, go to the Prestashop administration section, select "Modules and Services" > "Modules and Services". Locate the Bambora module and click the "Configure" button.');
            return $html;
        }
        if (( strlen(Configuration::get('BAMBORA_MERCHANTNUMBER') ) == 0 ) || ( strlen(Configuration::get('BAMBORA_REMOTE_API_PASSWORD') ) == 0 ))
        {
            $html .= $this-> l('Before you start to use the Bambora payment module, you need to configurate it.')
                    . '<br />'
                    .$this-> l('The "Remote API password" and the "Merchant number" should be set.')
                    . '<br />'
                    .$this -> l('In order to do this, go to the Prestashop administration section, select "Modules and Services" > "Modules and Services". Locate the Bambora module and click the "Configure" button.');
            return $html;
        }

		try
		{
            $apiKey = strval(Configuration::get('BAMBORA_REMOTE_API_PASSWORD'));
            $api = new BamboraApi($apiKey);

			$getTransaction = $api->gettransaction($transaction["bambora_transaction_id"]);

            if(!$getTransaction["meta"]["result"])
            {
                return $this->merchantErrorMessage($getTransaction["meta"]["message"]["merchant"]);
            }

            $getTransactionOperation = $api->gettransactionoperations($transaction["bambora_transaction_id"]);

            if(!$getTransactionOperation["meta"]["result"])
            {
                return $this->merchantErrorMessage($getTransactionOperation["meta"]["message"]["merchant"]);
            }

            $transactionInfo = $getTransaction["transaction"];
            $transactionOperations = $getTransactionOperation["transactionoperations"];


            $lastAction = $transactionOperations[0]["action"];
            $lastCreateDate = $transactionOperations[0]["createddate"];

            foreach($transactionOperations as $arr)
            {
                foreach($arr["transactionoperations"] as $op)
                {
                    if ($lastCreateDate < $op["createddate"])
                    {
                        $lastCreateDate = $op["createddate"];
                        $lastAction = $op["action"];
                    }
                }
            }

            $currency_code = $transactionInfo["currency"]["code"];
            $bambora_amount = BamboraCurrency::convertPriceFromMinorUnits($transactionInfo["total"]["authorized"],$transactionInfo["currency"]["minorunits"]);


            $html .= '<div class="row">

                        <div class="col-xs-12 col-md-6 col-lg-4 col-sm-12">'
                            .$this->buildPaymentTable($bambora_amount, $transaction, $lastAction, $transactionInfo)
                            .$this ->buildButtonsForm($transactionInfo, $transaction, $bambora_amount, $lastAction,$currency_code)
                        .'</div>

                        <div class="col-md-6 col-lg-4 hidden-xs hidden-sm">'
                            .$this -> buildTransactionLogtable($transactionOperations)
                        .'</div>

                        <div class="col-lg-4 visible-lg text-center hidden-sm">'
                            .$this -> buildLogodiv(true)
                        .'</div>'
                    .'</div>';

            $html .= '<div class="row visible-xs visible-sm">
                        <div class="col-xs-12 bambora-transaction-log">'
                        .$this -> buildTransactionLogtable($transactionOperations)
                        .'</div>
                     </div>';

        }
		catch (Exception $e)
		{
			$this->displayError($e->getMessage());
		}

        return $html;
    }

    private function buildButtonsForm($transInfo, $transaction, $bambora_amount, $lastAction)
    {
        $html = '';
        if ($transInfo["available"]["capture"] > 0 or $transInfo["available"]["credit"] > 0 or $transInfo["candelete"] == 'true')
        {
            $html .= '<form name="bambora_remote" action="' . $_SERVER["REQUEST_URI"] . '" method="post" class="bambora_displayInline" >'
                . '<input type="hidden" name="bambora_transaction_id" value="' . $transaction["bambora_transaction_id"] . '" />'
                . '<input type="hidden" name="bambora_order_id" value="' . $transaction["id_cart"] . '" />'
                . '<input type="hidden" name="bambora_amount" value="' . $bambora_amount . '" size="' . strlen($bambora_amount) . '" />'
                . '<input type="hidden" name="bambora_currency" value="' . $transInfo["currency"]["code"] . '" />';

            $html .= '<br />';

            $html .= '<div id="divBamboraTransactionControlsContainer" class="bambora-buttons clearfix">';
            $html .= $this ->buildSpinner();

            $minorUnits = $transInfo["currency"]["minorunits"];

            $availableForCapture = BamboraCurrency::convertPriceFromMinorUnits($transInfo["available"]["capture"], $minorUnits);

            $availableForCredit = BamboraCurrency::convertPriceFromMinorUnits($transInfo["available"]["credit"], $minorUnits);

            if ($availableForCapture > 0)
            {
                $html .= $this ->  buildTransactionControlInclTextField('capture' ,  $this->l('Capture'), "btn bambora_confirm_btn", true, $availableForCapture, $transInfo["currency"]["code"]  );
            }

            if($availableForCredit > 0)
            {
                $html .= $this ->  buildTransactionControlInclTextField('credit', $this->l('Refund'), "btn bambora_credit_btn", true, $availableForCredit,$transInfo["currency"]["code"]  );
            }

            if ($transInfo["candelete"] == 'true')
            {
                $html .= $this ->  buildTransactionControl('delete', $this->l('Delete'), "btn bambora_delete_btn");
            }

            $html .= '</div></form>';
        }

        return $html;
    }

    private function getCurrentOrderValue(){
        $order = new Order(Tools::getValue('id_order'));

        if (!Validate::isLoadedObject($order))
        {
            $this->errors[] = Tools::displayError($this->l('The order cannot be found within your database.'));
        }

        $currentOrderValue = floatval($order->getOrdersTotalPaid());
        return $currentOrderValue;
    }

    private function buildTransactionControlInclTextField($type, $value, $class, $addInputField = false, $valueOfInputfield = 0, $currencycode = "")
    {
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
                                <input type="text" title="please fill out a valid number" required="required" name="bambora_'.$type.'_value" value="'.BamboraHelpers::displayPricewithoutCurrency($valueOfInputfield, $this->context ) .'" />
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


    private function buildPaymentTable($bambora_amount, $transaction, $lastAction, $transInfo)
    {

        $html = '<table class="bambora-table">
                   <tr><th>'
                      .$this -> l('Payment')
                  .'</th></tr>'

                .'<tr><td>'. $this -> l('Amount') .':</td>';

        $css = "";
        switch( $lastAction )
        {
            case  'Delete':
                $css = "badge-important bambora-badge-critical";
                break;

            case "Credit":
                $css = "badge-warning";
                break;

            case 'Authorize':
            case 'Capture':
                $css = "badge-success";
                break;

            default:
                $css = "badge-info";
                break;
        }

        $html .='<td><div class="badge bambora-badge-big ' . $css . '" title="'. $this->l("Amount available for capture").'">';

        if ($this-> context -> currency -> iso_code == $transInfo["currency"]["code"] )
        {
            $formatetAmount = Tools::displayPrice($bambora_amount);

            $html .= $formatetAmount .'</div></td></tr>';
        }
        else
        {
            $html .= $bambora_amount .' ' .$transInfo["currency"]["code"] .'</td></tr>';
        }

        $html .='<tr><td>'. $this -> l('Order Id') .':</td><td>'. $transaction["id_cart"].'</td></tr>';

        foreach($transInfo["information"]["primaryaccountnumbers"] as $cardnumber)
        {

            $formattedCardnumber = BamboraHelpers::formatTruncatedCardnumber($cardnumber["number"]);
            $html .= '<tr><td>'.   $this-> l('Cardnumber') .':</td><td>' .$formattedCardnumber .'</td></tr>';
        }

        $html .='<tr><td>'. $this-> l('Status') .':</td>';
        $html .='<td>';

        switch( $lastAction )
        {
            case  'Delete':
                $html .= $this->l('Deleted');
                break;

            case 'Authorize':
                $html .= $this->l('Authorized') ;
                break;

            case 'Credit':
                $html .= $this->l('Credited') ;
                break;

            case 'Capture':
                $html .=$this->l('Captured') ;
                break;

            default:
                $html .=$lastAction;
                break;
        }

        $html .= '</td></tr></table>';

        return $html;
    }

    private function buildLogodiv($makeAslinkToAdminSite = false)
    {
        $html = '<a href="http://admin.epay.eu/Account/Login" alt="" title="' . $this->l('Go to Bambora Merchant') . '" target="_blank">';
        $html .= '<img class="bambora_logo" src="https://d3r1pwhfz7unl9.cloudfront.net/bambora/bambora_black_300px.png" />';
        $html .= '</a>';
        $html .= '<div><a href="http://admin.epay.eu/Account/Login"  alt="" title="' . $this->l('Go to Bambora Merchant') . '" target="_blank">' .$this->l('Go to Bambora Merchant') .'</a></div>';

        return $html;
    }


    private function buildTransactionLogtable($operations){

        $html = "";

        if (count($operations) > 0 )
        {
            $html .= '<table class="bambora-table">
                        <tr>
                            <th colpan="2">' . $this -> l('Transaction log') .'</th>
                        </tr>
                        <tr>
                            <td class="bambora-td-head">' . $this->l('Date') . '</td>
                            <td class="bambora-td-head">' . $this->l('Event') . '</td>
                            <td class="bambora-td-head">' . $this->l('Amount') . '</td>
                        </tr>';

            foreach($operations as $arr)
            {
                $html .=$this -> buildTransactionLogRow($arr);

                foreach($arr["transactionoperations"] as $op)
                {
                    $html .=$this -> buildTransactionLogRow($op);
                }
            }

            $html .= '</table>';
        }

        return $html;
    }

    private function buildTransactionLogRow($operation)
    {

        $html = '<tr>';

        $zone =  date_default_timezone_get();
        $tz = new DateTimeZone($zone);
        $date = str_replace("T", " ",substr($operation["createddate"],0,19));

        $utcDate = new DateTime($date,new DateTimeZone('UTC'));
        $localDate = $utcDate->setTimezone($tz);

        if ($this->context->language->language_code == 'en_US')
        {
            //show date as December 17, 2015, 01:49:45 PM
            $html .= '<td>' . $localDate->format('F d\, Y\, g:i:s a') .'</td>';
        }
        else
        {
            //show date as 17. December 2015, 13:49:45"
            $html .= '<td>' . $localDate->format('d\. F Y\, H:i:s') .'</td>';
        }
        $minorUnits = $operation["currency"]["minorunits"];
        // $html .= '<td>' .$this->l($operation["action"])  .' '. Tools::displayPrice(BamboraCurrency::convertPriceFromMinorUnits($operation["amount"],$minorUnits), null, false, $this->context) ."</td>";

        $html .= '<td>' .$this->l($operation["action"]).'</td>';
        $html .= '<td>' .Tools::displayPrice(BamboraCurrency::convertPriceFromMinorUnits($operation["amount"],$minorUnits), null, false, $this->context).'</td>';


        $html .= '</tr>';

        return $html;
    }

	private function buildBamboraContainerStartTag()
    {
        $return = '<script type="text/javascript" src="'.$this->_path.'js/bamboraScripts.js" charset="UTF-8"></script>';

        if ( _PS_VERSION_ >= "1.6.0.0")
        {
            $return .= '<div class="row" >';
            $return .= '<div class="col-lg-12">';
            $return .= '<div class="panel bambora_widthAllSpace" style="overflow:auto">';
        }
        else
        {
            $return .= '<style type="text/css">
                            .table td{white-space:nowrap;overflow-x:auto;}
                        </style>';
            $return .= '<br /><fieldset><legend><img src="../img/admin/money.gif">Bambora</legend>';
        }

        return $return;
    }

    private function buildBamboraContainerStopTag(){

        $return = '';
        if ( _PS_VERSION_ >= "1.6.0.0")
        {
            $return .= "</div>";
            $return .= "</div>";
            $return .= "</div>";
        }else
        {
            $return .= "</fieldset>";
        }
        return $return;
    }

	private function displayTransactionForm($params, $order)
	{
        $transactions = $this->getStoredTransaction(intval($params["id_order"]));

        $return = $this-> buildBamboraContainerStartTag();

        if (count($transactions) == 0)
        {
            if ($order -> module == 'bambora')
            {
                $return .= 'No payment transaction was found';
                $return .= $this ->buildBamboraContainerStopTag();
                return $return;
            }
        }

        if (count($transactions) > 1)
        {
            $return .= 'too many transactions returned!!!!';
            $return .= $this ->buildBamboraContainerStopTag();
            return $return;
        }

        $order = new Order($params['id_order']);



        // Init Fields form array
        foreach($transactions as $transaction)
        {
            if(isset($transaction["bambora_transaction_id"]) && $transaction["module"] == "bambora")
            {
                $return .= '<div class="panel-heading">';
                if($transaction["card_type"] != "0")
                {
                    $return .= '<img class="bambora_paymentCardLogo" src="https://d3r1pwhfz7unl9.cloudfront.net/paymentlogos/' . $transaction["card_type"] . '.png" alt="' . BamboraHelpers::getCardNameById(intval($transaction["card_type"])) . '" title="' . BamboraHelpers::getCardNameById(intval($transaction["card_type"])) . '" align="middle">';
                }
                $return .= ' Bambora </div>';

                $return .= $this->getHtmlcontent($transaction, $params);
            }
        }

        $return .= $this -> buildBamboraContainerStopTag();

		return $return;
	}


    public function hookPayment($params)
    {
        if (!$this->active)
			return;

		if (!$this->checkCurrency($this->context->cart))
			return;

        $invoiceAddress = new Address(intval($params['cart']->id_address_invoice));
        $deliveryAddress = new Address(intval($params['cart']->id_address_delivery));
        $mobileNumber = $invoiceAddress->phone_mobile != "" ? $invoiceAddress->phone_mobile : $deliveryAddress->phone_mobile;

        $bamboraCustommer = $this->create_bambora_custommer($mobileNumber);
        $bamboraOrder = $this->create_bambora_order($invoiceAddress,$deliveryAddress);
        $bamboraUrl = $this ->create_bambora_url($bamboraOrder->ordernumber, $bamboraOrder->currency, $bamboraOrder->total);

        $request = new BamboraCheckoutRequest();
        $request -> capturemulti = true; //TODO make config
        $request -> customer = $bamboraCustommer;
        $request -> instantcaptureamount =  Configuration::get('BAMBORA_INSTANTCAPTURE') == 1 ? $bamboraOrder -> total : 0;
        $request -> language = str_replace("_","-",$this->context->language->language_code);
        $request -> order = $bamboraOrder;
        $request -> url = $bamboraUrl;

        $paymentWindowId = Configuration::get('BAMBORA_PAYMENTWINDOWID');

        $request->paymentwindowid =  is_numeric($paymentWindowId) ?  $paymentWindowId : 1;
        $apiKey = strval(Configuration::get('BAMBORA_REMOTE_API_PASSWORD'));
        $api = new BamboraApi($apiKey);

        $expressRes = $api -> getcheckoutresponse($request);

        $json = json_decode($expressRes, true);

        if (!$json['meta']['result'])
        {
            $errormessage = $json['meta']['message']['enduser'];
            $this->context->smarty->assign('bambora_errormessage', $errormessage);
            $this->context->smarty->assign( 'this_path_bambora', $this->_path);
            return $this->display(__FILE__ , "checkoutIssue.tpl");
        }

        $bamboraPaymentwindowUrl = $api->getcheckoutpaymentwindowjs();

        $bamboraCheckoutUrl = $json['url'];


        $paymentcardIds = array();
        $paymentcardIds = $api -> getAvaliablePaymentcardidsForMerchant($bamboraOrder->currency,$bamboraOrder->total);

        $this->context->smarty->assign(array('paymentcardIds'=> $paymentcardIds, 'this_path_bambora' => $this->_path));

        $this->context->smarty->assign(array(
            'bambora_paymentwindowurl' => $bamboraPaymentwindowUrl,
            'bambora_windowstate' =>  Configuration::get('BAMBORA_WINDOWSTATE'),
            'bambora_checkouturl' => $bamboraCheckoutUrl,
            'bambora_path' => $this->_path,
            'bambora_psVersionIsnewerOrEqualTo160'=> _PS_VERSION_ >= "1.6.0.0" ? true: false
            ));

        return $this->display(__FILE__ , "checkoutpaymentwindow.tpl");
    }


    private function checkCurrency($cart)
	{
		$currency_order = new Currency((int)($cart->id_currency));
		$currencies_module = $this->getCurrency((int)$cart->id_currency);

		if (is_array($currencies_module))
			foreach ($currencies_module as $currency_module)
				if ($currency_order->id == $currency_module['id_currency'])
					return true;
		return false;
	}

    private function create_bambora_custommer($mobileNumber)
    {

        $bamboraCustommer = new BamboraCustomer();
        $bamboraCustommer ->email = $this->context->customer->email;
        $bamboraCustommer ->phonenumber = $mobileNumber;
        $bamboraCustommer ->phonenumbercountrycode = $this->context->country->call_prefix;
        return $bamboraCustommer;
    }

    private function create_bambora_order($invoiceAddress, $deliveryAddress)
    {
        $bamboraOrder = new BamboraOrder();
        $bamboraOrder->billingaddress = $this->create_bambora_invoice_address($invoiceAddress);

        $bamboraOrder->currency = $this->context->currency->iso_code;
        $bamboraOrder->lines = $this->create_bambora_orderlines($bamboraOrder->currency);

        $bamboraOrder -> ordernumber = (string)$this->context->cart->id;

        $bamboraOrder->shippingaddress = $this->create_bambora_delivery_address($deliveryAddress);

        $minorUnits = BamboraCurrency::getCurrencyMinorunits($bamboraOrder->currency);

        $bamboraOrder -> total = BamboraCurrency::convertPriceToMinorUnits($this->context->cart->getOrderTotal(),$minorUnits);

        $bamboraOrder -> vatamount = BamboraCurrency::convertPriceToMinorUnits($this->context->cart->getOrderTotal() - $this->context->cart->getOrderTotal(false),$minorUnits);

        return $bamboraOrder;
    }
    private function create_bambora_invoice_address($address)
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

    private function create_bambora_delivery_address($address)
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

    private function create_bambora_orderlines($currency)
    {
        $bamboraOrderlines = array();

        $products = $this->context->cart->getproducts();
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
            $line->unit = $this->l("pcs.");
            $line->vat = $product["rate"];

            $bamboraOrderlines[] = $line;
            $lineNumber++;
        }

        //Add shipping as an orderline
        $shippingCostWithTax = $this->context->cart->getTotalShippingCost(null,true,null);
        $shippingCostWithoutTax = $this->context->cart->getTotalShippingCost(null,false,null);
        if($shippingCostWithTax > 0)
        {
            $shippingOrderline = new BamboraOrderLine();
            $shippingOrderline->id = $this->l("shipping.");
            $shippingOrderline->description = $this->l("shipping.");
            $shippingOrderline->quantity = 1;
            $shippingOrderline->unit = $this->l("pcs.");
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






    private function create_bambora_url($orderId,$currency,$amount)
    {
        $bamboraUrl = new BamboraUrl();
        $bamboraUrl->accept = $this->context->link->getModuleLink('bambora', 'validation', array(), true);
        $bamboraUrl->decline = $this->context->link->getPageLink('order', true, null, "step=3");

		$bamboraUrl->callbacks = array();
		$callback = new BamboraCallback();
		$callback->url = $this->context->link->getModuleLink('bambora', 'validation', array('callback' => 1), true);
        $bamboraUrl->callbacks[] = $callback;

        $bamboraUrl->immediateredirecttoaccept = Configuration::get('BAMBORA_IMMEDIATEREDIRECTTOACCEPT') ? 1 : 0;

        return $bamboraUrl;
    }


    function showPaymentWindowInIFrameProvidingUrl($url)
    {
        echo '<br /><iframe id="imdb" width="80%" height="600px" src="'.$url.'"></iframe>';
    }

	public function hookPaymentReturn($params)
	{
		if(!$this->active)
			return;

        $result = Db::getInstance()->getRow('
            SELECT o.`id_order`, o.`module`, e.`id_cart`, e.`bambora_transaction_id`,
                   e.`card_type`,  e.`currency`, e.`amount`, e.`transfee`,
                   e.`captured`, e.`credited`, e.`deleted`,
                   e.`date_add`
            FROM ' . _DB_PREFIX_ . 'bambora_transactions e
            LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON e.`id_cart` = o.`id_cart`
            WHERE o.`id_order` = ' . intval($_GET["id_order"]));


        $this->context->smarty->assign('bambora_completed_paymentText', $this->l('You just completed your payment.'));

        if($result['bambora_transaction_id'])
        {
            $this->context->smarty->assign('bambora_completed_transactionText', $this->l('Your transaction ID for this payment is:'));
            $this->context->smarty->assign('bambora_completed_transactionValue', $result['bambora_transaction_id']);
        }
        if($this->context->customer->email)
        {
            $this->context->smarty->assign('bambora_completed_emailText', $this->l('An confirmation email has been sendt to:'));
            $this->context->smarty->assign('bambora_completed_emailValue',$this->context->customer->email);
        }

		return $this->display(__FILE__ , 'payment_return.tpl');
	}



    function displayAttributeGroupPostProcess($params)
    {
        return "";
    }

    function hookAdminOrder($params) //Called when the order's details are displayed, below the Client Information block
	{
        $html = '';
        $activate_api = Configuration::get('BAMBORA_ENABLE_REMOTE_API');
        if($activate_api == 1)
        {
            if(strlen($this->BamboraUiMessage ->type) > 0)
            {
                $this->buildOverlayMessage($this->BamboraUiMessage ->type,$this->BamboraUiMessage->title, $this->BamboraUiMessage ->message);
            }

            $order = new Order($params['id_order']);

            if ($order->module == 'bambora')
            {
                $html .=  $this->displayTransactionForm($params, $order);
            }
        }
        return $html;
	}


    function buildOverlayMessage($type,$title, $message)
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

    function showCheckmark(){
        $html = '<div class="bambora-circle bambora-checkmark_circle">
                        <div class="bambora-checkmark_stem"></div>
                    </div>';
        return $html;
    }

    function showExclamation(){
        $html = '<div class="bambora-circle bambora-exclamation_circle">
                        <div class="bambora-exclamation_stem"></div>
                        <div class="bambora-exclamation_dot"></div>
                    </div>';
        return $html;
    }

    private function getStoredTransaction($id_order)
    {
        $transactions = Db::getInstance()->executeS('
		SELECT o.`id_order`, o.`module`, e.`id_cart`, e.`bambora_transaction_id`,
			   e.`card_type`, e.`currency`, e.`amount`, e.`transfee`,
			   e.`captured`, e.`credited`, e.`deleted`,
			   e.`date_add`
		FROM ' . _DB_PREFIX_ . 'bambora_transactions e
		LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON e.`id_cart` = o.`id_cart`
		WHERE o.`id_order` = ' .$id_order );
        return $transactions;
    }

    //works!!!!
    public function hookDisplayPDFInvoice($params){

        $transaction = $this->getStoredTransaction($params["object"]->id_order);

        $transactionid = $transaction[0]["bambora_transaction_id"];
        if ($transactionid == null)
        {
            $transactionid = $_GET['txnid'];
        }

        $apiKey = strval(Configuration::get('BAMBORA_REMOTE_API_PASSWORD'));
        $api = new BamboraApi($apiKey);
        $trans = $api->gettransaction($transactionid);

        $transMeta = $api->convertJSonResultToArray($trans, "meta");
        $transInfo = $api->convertJSonResultToArray($trans, "transaction");

        $result = "";
        if ($transMeta["result"])
        {
            $cardname ="card";
            $truncated_cardnumber ="";

            $status = $transInfo["status"];
            foreach( $transInfo["information"]["paymenttypes"] as $paymenttype)
            {
                //we can only handle one paymenttype here - enshure only to take the correct paymenttype here
                $cardname = $paymenttype["displayname"];
            }

            foreach($transInfo["information"]["primaryaccountnumbers"] as $cardnumber)
            {
                //the payment must be performed with just one creditcard, there shouldn't be more than one card here.
                $truncated_cardnumber = $cardnumber["number"];
            }

            $formattedCardnumber = BamboraHelpers::formatTruncatedCardnumber($truncated_cardnumber);

            $result = '<table><tr><td><b>'.$this->l("Payment information").' '. $cardname . '</b></td></tr><tr><td>Status: '. $status .' &#8226; '.$this->l("TransactionId").':' .$transactionid .' &#8226; '.$this -> l('Cardnumber') .': '.$formattedCardnumber .'</td></tr></table>';
        }

        return $result;
    }

    private function getTransctionInfo($bambora_transaction_id)
    {
        $returnArr["success"] = true;
        $returnArr["transinfo"] = null;
        $returnArr["errorMessage"] = null;


    	$apiKey = strval(Configuration::get('BAMBORA_REMOTE_API_PASSWORD'));
        $api = new BamboraApi($apiKey);

	    $transaction = $api->gettransaction($bambora_transaction_id);

        if($transaction["meta"]["result"] == false)
        {
            return $this->merchantErrorMessage($transaction["meta"]["message"]["merchant"]);
        }

        $transinfo = $transaction["transaction"];

        $returnArr["transinfo"] = $transinfo;

        return $returnArr;

    }

	private function procesRemote($params)
	{
		$bamboraUiMessage = new BamboraUiMessage();
        if((Tools::isSubmit('bambora_capture') OR Tools::isSubmit('bambora_credit') OR Tools::isSubmit('bambora_delete')) AND Tools::getIsset('bambora_transaction_id'))
		{
            $result = "";
            try{
                $apiKey = strval(Configuration::get('BAMBORA_REMOTE_API_PASSWORD'));
                $api = new BamboraApi($apiKey);

                $currency = $this->context->currency->iso_code;
                $minorUnits = BamboraCurrency::getCurrencyMinorunits($currency);
                $convertedCaptureValue = 0;
                $convertedCreditValue = 0;

                if(Tools::isSubmit('bambora_capture'))
                {

                    $convertedCaptureValue =  BamboraHelpers::handleUserAmountInput(Tools::getValue('bambora_capture_value'), null, $this->context );

                    if (is_float($convertedCaptureValue))
                    {
                        $result = $api->capture(Tools::getValue('bambora_transaction_id'),BamboraCurrency::convertPriceToMinorUnits($convertedCaptureValue,$minorUnits),  Tools::getValue('bambora_currency'));
                    }else
                    {
                        $bamboraUiMessage->type = $this->l("issue");
                        $bamboraUiMessage->title = $this->l("Inputfield is not a valid number");
                        return $bamboraUiMessage;
                    }
                }
                elseif(Tools::isSubmit('bambora_credit'))
                {
                    $convertedCreditValue =  BamboraHelpers::handleUserAmountInput(Tools::getValue('bambora_credit_value'), null, $this->context );

                    if (is_float(floatval(Tools::getValue('bambora_credit_value'))))
                    {
                        $result = $api->credit( Tools::getValue('bambora_transaction_id'),BamboraCurrency::convertPriceToMinorUnits($convertedCreditValue,$minorUnits),  Tools::getValue('bambora_currency'));
                    }
                    else
                    {
                        $bamboraUiMessage->type = $this->l("issue");
                        $bamboraUiMessage->title = $this->l("Inputfield is not a valid number");
                        return $bamboraUiMessage;
                    }
                }
                elseif(Tools::isSubmit('bambora_delete'))
                {
                    $result = $api->delete( Tools::getValue('bambora_transaction_id'));
                }

                $serviceAnswer = $api ->convertJSonResultToArray($result, "meta");

                if ($serviceAnswer["result"] == true)
                {
                    $temp = $this-> getTransctionInfo(Tools::getValue('bambora_transaction_id'));
                    if ($temp["success"])
                    {
                        $transinfo = $temp["transinfo"];
                        $currentActuallyPaidvalue = BamboraCurrency::convertPriceFromMinorUnits($transinfo["total"]["captured"] - $transinfo["total"]["credited"],$minorUnits);
                        $this -> updateOrderPayment(Tools::getValue('bambora_transaction_id'),$currentActuallyPaidvalue);
                    }

                    if(Tools::isSubmit('bambora_capture'))
                    {
                        $this->setCaptured(Tools::getValue('bambora_transaction_id'), BamboraCurrency::convertPriceToMinorUnits(floatval(Tools::getValue('bambora_amount')),$minorUnits));
                        $captureText = $this->l('The Payment was') . ' ' . $this -> l('captured') .' '.$this->l('successfully');

                        $bamboraUiMessage->type = "capture";
                        $bamboraUiMessage->title = $captureText;

                    }elseif(Tools::isSubmit('bambora_credit'))
                    {
                        $this->setCredited(Tools::getValue('bambora_transaction_id'),BamboraCurrency::convertPriceToMinorUnits(floatval(Tools::getValue('bambora_amount')),$minorUnits));
                        $creditText = $this -> l('The Payment was') . ' ' . $this -> l('refunded') .' '.$this->l('successfully');
                        $bamboraUiMessage->type = "credit";
                        $bamboraUiMessage->title = $creditText;

                    }elseif(Tools::isSubmit('bambora_delete'))
                    {
                        $this->deleteTransaction(Tools::getValue('bambora_transaction_id'));
                        $deleteText = $this->l('The payment was').' '. $this -> l('delete') .' '.$this->l('successfully');
                        $bamboraUiMessage->type = "delete";
                        $bamboraUiMessage->title = $deleteText;
                    }
                }else
                {
                    $bamboraUiMessage->type = "issue";
                    $bamboraUiMessage->title = $this->l("An issue occured, and the operation was not performed.");
                    if ( $serviceAnswer["message"] != null && $serviceAnswer["message"]["merchant"] != null)
                    {
                        $message = $serviceAnswer["message"]["merchant"];

                        if($serviceAnswer["action"] != null && $serviceAnswer["action"]["source"] == "ePayEngine" && ($serviceAnswer["action"]["code"] == "113" || $serviceAnswer["action"]["code"] == "114"))
                        {
                            preg_match_all('!\d+!', $message, $matches);
                            foreach($matches[0] as $match)
                            {
                                $convertedAmount = Tools::displayPrice(BamboraCurrency::convertPriceFromMinorUnits($match,$minorUnits));
                                $message = str_replace($match,$convertedAmount,$message);
                            }
                        }
                        $bamboraUiMessage->message = $message;
                    }
                }
            }
            catch(Exception $e)
            {
                $activate_api = false;
                $this->displayError($e->getMessage());
            }
		}
        return $bamboraUiMessage;
	}

    private function updateOrderPayment($transactionid,$amount)
    {
        $order = new Order(Tools::getValue('id_order'));

        $payments = $order->getOrderPayments();
		$payment = $payments[count($payments) - 1];

        $payment->amount = $amount;
        $payment->transaction_id = $transactionid;
        $payment->save();
        $order->update();
    }

    function buildSpinner(){
        $html = '<div id="bamboraSpinner" class="bamboraButtonFrame bamboraButtonFrameIncreesedsize row"><div class="col-lg-10">'.$this->l("processing").'... </div><div class="col-lg-2"><div class="bambora-spinner"><img src="'.$this->_path.'img/arrows.svg" class="bambora-spinner"></div></div></div>';
        return $html;
    }

    function alertMessage($message)
    {
        $alert = '<script type="text/javascript">alert("'.$message .'");</script>';
        echo $alert;
    }
}