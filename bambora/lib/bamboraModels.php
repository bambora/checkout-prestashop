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