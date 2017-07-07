<?php
/**
 * Copyright (c) 2017. All rights reserved Bambora Online A/S.
 *
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test account that you can use to test the module.
 *
 * @author    Bambora Online A/S
 * @copyright Bambora (http://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 */

class BamboraCustomer
{
    public $email;
    public $phonenumber;
    public $phonenumbercountrycode;
}
class BamboraOrder
{
    public $billingaddress;
    public $currency;
    public $lines;
    public $ordernumber;
    public $shippingaddress;
    public $total;
    public $vatamount;
}
class BamboraAddress
{
    public $att;
    public $city;
    public $country;
    public $firstname;
    public $lastname;
    public $street;
    public $zip;
}
class BamboraOrderLine
{
    public $description;
    public $id;
    public $linenumber;
    public $quantity;
    public $text;
    public $totalprice;
    public $totalpriceinclvat;
    public $totalpricevatamount;
    public $unit;
    public $vat;
}
class BamboraUrl
{
    public $accept;
    public $callbacks;
    public $decline;
}
class BamboraCallback
{
    public $url;
}
class BamboraUiMessage
{
    public $type;
    public $title;
    public $message;
}
class BamboraCheckoutRequest
{
    public $customer;
    public $instantcaptureamount;
    public $language;
    public $order;
    public $url;
    public $paymentwindowid;
}
