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
    public $id;
    public $shippingaddress;
    public $amount;
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
    public $unitpriceinclvat;
    public $unitprice;
    public $unitpricevatamount;
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
    public $order;
    public $url;
    public $paymentwindow;
    public $securityexemption;
    public $securitylevel;
}

class BamboraCheckoutRequestPaymentWindow
{
    public $id;
    public $language;
}

class BamboraCheckoutPaymentRequest
{
    public $reference;
    public $parameters;
    public $description;
    public $termsurl;
}

class BamboraCheckoutPaymentRequestParameters
{
    public $order;
    public $instantcaptureamount;
    public $paymentwindow;
    public $customer;
    public $url;
}

class BamboraCheckoutPaymentRequestEmailRecipient
{
    public $message;
    public $to;
    public $replyto;

}

class BamboraCheckoutPaymentRequestEmailRecipientAddress
{
    public $email;
    public $name;
}
