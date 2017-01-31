<?php
/**
 * Bambora Online 2017
 *
 * @author    Bambora Online
 * @copyright Bambora (http://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
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
