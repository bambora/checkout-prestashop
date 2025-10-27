<?php

class BamboraCustomer
{
    /** @var string */
    public $email;
    /** @var string */
    public $phonenumber;
    /** @var string|int */
    public $phonenumbercountrycode;
}

class BamboraOrder
{
    /** @var BamboraAddress */
    public $billingaddress;
    /** @var string */
    public $currency;
    /** @var BamboraOrderLine[] */
    public $lines;
    /** @var string */
    public $id;
    /** @var BamboraAddress */
    public $shippingaddress;
    /** @var string|int */
    public $amount;
    /** @var string|int */
    public $vatamount;
}

class BamboraAddress
{
    /** @var string */
    public $att;
    /** @var string */
    public $city;
    /** @var string */
    public $country;
    /** @var string */
    public $firstname;
    /** @var string */
    public $lastname;
    /** @var string */
    public $street;
    /** @var string */
    public $zip;
}

class BamboraOrderLine
{
    /** @var string */
    public $description;
    /** @var string */
    public $id;
    /** @var string|int */
    public $linenumber;
    /** @var string|int */
    public $quantity;
    /** @var string */
    public $text;
    /** @var string|int */
    public $totalprice;
    /** @var string|int */
    public $totalpriceinclvat;
    /** @var string|int */
    public $totalpricevatamount;
    /** @var string */
    public $unit;
    /** @var string|int */
    public $unitpriceinclvat;
    /** @var string|int */
    public $unitprice;
    /** @var string|int */
    public $unitpricevatamount;
    /** @var string|int */
    public $vat;
}

class BamboraUrl
{
    /** @var string */
    public $accept;
    /** @var BamboraCallback[] */
    public $callbacks;
    /** @var string|int */
    public $decline;
}

class BamboraCallback
{
    /** @var string */
    public $url;
}

class BamboraUiMessage
{
    /** @var string */
    public $type;
    /** @var string */
    public $title;
    /** @var string */
    public $message;
}

class BamboraCheckoutRequest
{
    /** @var BamboraCustomer */
    public $customer;
    /** @var int|string */
    public $instantcaptureamount;
    /** @var BamboraOrder */
    public $order;
    /** @var string */
    public $url;
    /** @var BamboraCheckoutRequestPaymentWindow */
    public $paymentwindow;
    /** @var string */
    public $securityexemption;
    /** @var string */
    public $securitylevel;
}

class BamboraCaptureRequest
{
    /** @var int|string */
    public $amount;

    /** @var string */
    public $currency;

    /** @var BamboraOrderLine[]|null */
    public $invoicelines;
}

class BamboraCreditRequest
{
    /** @var int|string */
    public $amount;

    /** @var string */
    public $currency;

    /** @var BamboraOrderLine[]|null */
    public $invoicelines;
}

class BamboraCheckoutRequestPaymentWindow
{
    /** @var string|int */
    public $id;
    /** @var string */
    public $language;
}

class BamboraCheckoutPaymentRequest
{
    /** @var string */
    public $reference;
    /** @var BamboraCheckoutPaymentRequestParameters */
    public $parameters;
    /** @var string */
    public $description;
    /** @var string */
    public $termsurl;
}

class BamboraCheckoutPaymentRequestParameters
{
    /** @var BamboraOrder */
    public $order;
    /** @var string|int */
    public $instantcaptureamount;
    /** @var BamboraCheckoutRequestPaymentWindow */
    public $paymentwindow;
    /** @var BamboraCustomer */
    public $customer;
    /** @var string */
    public $url;
}

class BamboraCheckoutPaymentRequestEmailRecipient
{
    /** @var string */
    public $message;
    /** @var BamboraCheckoutPaymentRequestEmailRecipientAddress */
    public $to;
    /** @var BamboraCheckoutPaymentRequestEmailRecipientAddress */
    public $replyto;
}

class BamboraCheckoutPaymentRequestEmailRecipientAddress
{
    /** @var string */
    public $email;
    /** @var string */
    public $name;
}
