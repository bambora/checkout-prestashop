<?php

class BamboraCheckoutHelper
{
    /**
     * Create Checkout Request
     *
     * @param Bambora $module
     * @param mixed $cart
     *
     * @return BamboraCheckoutRequest
     */
    public static function createCheckoutRequest($module, $cart)
    {
        $invoiceAddress = new Address((int) $cart->id_address_invoice);
        $deliveryAddress = new Address((int) $cart->id_address_delivery);
        $roundingMode = Configuration::get('BAMBORA_ROUNDING_MODE');
        $bamboraCustomer = self::createBamboraCustomer($cart, $invoiceAddress);
        $bamboraOrder = self::createBamboraOrder(
            $module,
            $cart,
            $invoiceAddress,
            $deliveryAddress,
            $roundingMode
        );
        $bamboraUrl = self::createBamboraUrl($module, false);

        $language = new Language((int) $cart->id_lang);

        $request = new BamboraCheckoutRequest();

        $request->customer = $bamboraCustomer;
        $request->instantcaptureamount = Configuration::get(
            'BAMBORA_INSTANTCAPTURE'
        ) == 1 ? $bamboraOrder->amount : 0;
        $request->order = $bamboraOrder;
        $request->url = $bamboraUrl;
        if (Configuration::get('BAMBORA_ALLOW_LOW_VALUE_EXEMPTION')) {
            if ($request->order->amount < BamboraCurrencyHelper::convertPriceToMinorUnits(
                Configuration::get('BAMBORA_LIMIT_FOR_LOW_VALUE_EXEMPTION'),
                BamboraCurrencyHelper::getCurrencyMinorunits(
                    $request->order->currency
                ),
                $roundingMode
            )) {
                $request->securityexemption = 'lowvaluepayment';
                $request->securitylevel = 'none';
            }
        }
        $request->paymentwindow = new BamboraCheckoutRequestPaymentWindow();
        $paymentWindowId = Configuration::get('BAMBORA_PAYMENTWINDOWID');
        $request->paymentwindow->id = is_numeric(
            $paymentWindowId
        ) ? $paymentWindowId : 1;
        $request->paymentwindow->language = str_replace('_', '-', $language->locale);

        return $request;
    }

    /**
     * Create Bambora Customer
     *
     * @param mixed $cart
     * @param mixed $invoiceAddress
     *
     * @return BamboraCustomer
     */
    public static function createBamboraCustomer($cart, $invoiceAddress)
    {
        $mobileNumber = BamboraCommonHelper::getPhoneNumberByAddress($invoiceAddress);
        $country = new Country((int) $invoiceAddress->id_country);
        $customer = new Customer((int) $cart->id_customer);

        $bamboraCustomer = new BamboraCustomer();
        $bamboraCustomer->email = $customer->email;
        $bamboraCustomer->phonenumber = $mobileNumber;
        $bamboraCustomer->phonenumbercountrycode = $country->call_prefix;

        return $bamboraCustomer;
    }

    /**
     * Create Bambora Order
     *
     * @param Bambora $module
     * @param mixed $cart
     * @param mixed $invoiceAddress
     * @param mixed $deliveryAddress
     * @param mixed $roundingMode
     *
     * @return BamboraOrder
     */
    public static function createBamboraOrder($module, $cart, $invoiceAddress, $deliveryAddress, $roundingMode)
    {
        $cartSummary = $cart->getSummaryDetails();
        $bamboraOrder = new BamboraOrder();
        $bamboraOrder->billingaddress = self::createBamboraAddress($invoiceAddress);

        $currency = new Currency((int) $cart->id_currency);

        $bamboraOrder->currency = $currency->iso_code;
        $bamboraOrder->lines = self::createBamboraOrderlines(
            $module,
            $cartSummary,
            $bamboraOrder->currency,
            $roundingMode
        );

        $bamboraOrder->id = (string) $cart->id;

        if ($cartSummary['is_virtual_cart'] === 0) {
            $bamboraOrder->shippingaddress = self::createBamboraAddress(
                $deliveryAddress
            );
        }
        $minorUnits = BamboraCurrencyHelper::getCurrencyMinorunits(
            $bamboraOrder->currency
        );

        $bamboraOrder->amount = BamboraCurrencyHelper::convertPriceToMinorUnits(
            $cart->getOrderTotal(),
            $minorUnits,
            $roundingMode
        );

        $bamboraOrder->vatamount = BamboraCurrencyHelper::convertPriceToMinorUnits(
            $cart->getOrderTotal() - $cart->getOrderTotal(false),
            $minorUnits,
            $roundingMode
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
    public static function createBamboraAddress($address)
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
     * @param Bambora $module
     * @param mixed $cartSummary
     * @param mixed $currency
     * @param mixed $roundingMode
     *
     * @return BamboraOrderLine[]
     */
    public static function createBamboraOrderlines($module, $cartSummary, $currency, $roundingMode)
    {
        $bamboraOrderlines = [];

        $products = $cartSummary['products'];
        $lineNumber = 1;
        $minorUnits = BamboraCurrencyHelper::getCurrencyMinorunits($currency);
        foreach ($products as $product) {
            $line = new BamboraOrderLine();
            $line->description = $product['name'];
            $line->id = $product['id_product'];
            $line->linenumber = (string) $lineNumber;
            $line->quantity = (int) $product['cart_quantity'];
            $line->text = $product['name'];
            $line->totalprice = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $product['total'],
                $minorUnits,
                $roundingMode
            );
            $line->totalpriceinclvat = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $product['total_wt'],
                $minorUnits,
                $roundingMode
            );
            $line->totalpricevatamount = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $product['total_wt'] - $product['total'],
                $minorUnits,
                $roundingMode
            );
            $line->unitprice = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $product['total'] / $line->quantity,
                $minorUnits,
                $roundingMode
            );
            $line->unitpriceinclvat = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $product['total_wt'] / $line->quantity,
                $minorUnits,
                $roundingMode
            );
            $line->unitpricevatamount = BamboraCurrencyHelper::convertPriceToMinorUnits(
                ($product['total_wt'] - $product['total']) / $line->quantity,
                $minorUnits,
                $roundingMode
            );

            $line->unit = $module->l('pcs.', 'checkouthelper');
            $line->vat = $product['rate'];

            $bamboraOrderlines[] = $line;
            ++$lineNumber;
        }

        // Add shipping as an orderline
        $shippingCostWithTax = $cartSummary['total_shipping'];
        if ($shippingCostWithTax > 0) {
            $shippingCostWithoutTax = $cartSummary['total_shipping_tax_exc'];
            $carrier = $cartSummary['carrier'];
            $shippingTax = $shippingCostWithTax - $shippingCostWithoutTax;
            $shippingOrderline = new BamboraOrderLine();
            $shippingOrderline->id = $carrier->id_reference;
            $shippingOrderline->description = "{$carrier->name} - {$carrier->delay}";
            $shippingOrderline->quantity = 1;
            $shippingOrderline->unit = $module->l('pcs.', 'checkouthelper');
            $shippingOrderline->linenumber = $lineNumber++;
            $shippingOrderline->totalprice = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $shippingCostWithoutTax,
                $minorUnits,
                $roundingMode
            );
            $shippingOrderline->totalpriceinclvat = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $shippingCostWithTax,
                $minorUnits,
                $roundingMode
            );
            $shippingOrderline->totalpricevatamount = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $shippingTax,
                $minorUnits,
                $roundingMode
            );
            $shippingOrderline->unitprice = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $shippingCostWithoutTax,
                $minorUnits,
                $roundingMode
            );
            $shippingOrderline->unitpriceinclvat = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $shippingCostWithTax,
                $minorUnits,
                $roundingMode
            );
            $shippingOrderline->unitpricevatamount = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $shippingTax,
                $minorUnits,
                $roundingMode
            );

            $shippingOrderline->vat = round(
                $shippingTax / $shippingCostWithoutTax * 100
            );
            $bamboraOrderlines[] = $shippingOrderline;
        }

        // Gift Wrapping
        $wrappingTotal = $cartSummary['total_wrapping'];
        if ($wrappingTotal > 0) {
            $wrappingTotalWithOutTax = $cartSummary['total_wrapping_tax_exc'];
            $wrappingTotalTax = $wrappingTotal - $wrappingTotalWithOutTax;
            $wrappingOrderline = new BamboraOrderLine();
            $wrappingOrderline->id = $module->l('wrapping', 'checkouthelper');
            $wrappingOrderline->description = $module->l('Gift wrapping', 'checkouthelper');
            $wrappingOrderline->quantity = 1;
            $wrappingOrderline->unit = $module->l('pcs.', 'checkouthelper');
            $wrappingOrderline->linenumber = $lineNumber++;
            $wrappingOrderline->totalprice = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $wrappingTotalWithOutTax,
                $minorUnits,
                $roundingMode
            );
            $wrappingOrderline->totalpriceinclvat = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $wrappingTotal,
                $minorUnits,
                $roundingMode
            );
            $wrappingOrderline->totalpricevatamount = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $wrappingTotalTax,
                $minorUnits,
                $roundingMode
            );
            $wrappingOrderline->unitprice = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $wrappingTotalWithOutTax,
                $minorUnits,
                $roundingMode
            );
            $wrappingOrderline->unitpriceinclvat = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $wrappingTotal,
                $minorUnits,
                $roundingMode
            );
            $wrappingOrderline->unitpricevatamount = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $wrappingTotalTax,
                $minorUnits,
                $roundingMode
            );

            $wrappingOrderline->vat = round(
                $wrappingTotalTax / $wrappingTotalWithOutTax * 100
            );
            $bamboraOrderlines[] = $wrappingOrderline;
        }

        // Discount
        $discountTotal = $cartSummary['total_discounts'];
        if ($discountTotal > 0) {
            $discountTotalWithOutTax = $cartSummary['total_discounts_tax_exc'];
            $discountTotalTax = $discountTotal - $discountTotalWithOutTax;
            $discountOrderline = new BamboraOrderLine();
            $discountOrderline->id = $module->l('discount', 'checkouthelper');
            $discountOrderline->description = $module->l('Discount', 'checkouthelper');
            $discountOrderline->quantity = 1;
            $discountOrderline->unit = $module->l('pcs.', 'checkouthelper');
            $discountOrderline->linenumber = $lineNumber++;
            $discountOrderline->totalprice = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $discountTotalWithOutTax,
                $minorUnits,
                $roundingMode
            ) * -1;
            $discountOrderline->totalpriceinclvat = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $discountTotal,
                $minorUnits,
                $roundingMode
            ) * -1;
            $discountOrderline->totalpricevatamount = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $discountTotalTax,
                $minorUnits,
                $roundingMode
            ) * -1;
            $discountOrderline->unitprice = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $discountTotalWithOutTax,
                $minorUnits,
                $roundingMode
            ) * -1;
            $discountOrderline->unitpriceinclvat = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $discountTotal,
                $minorUnits,
                $roundingMode
            ) * -1;
            $discountOrderline->unitpricevatamount = BamboraCurrencyHelper::convertPriceToMinorUnits(
                $discountTotalTax,
                $minorUnits,
                $roundingMode
            ) * -1;
            $discountOrderline->vat = round(
                $discountTotalTax / $discountTotalWithOutTax * 100
            );
            $bamboraOrderlines[] = $discountOrderline;
        }
        $roundingOrderline = self::createBamboraOrderlinesRoundingFee($module, $cartSummary, $minorUnits, $bamboraOrderlines, $lineNumber, $roundingMode);
        if (isset($roundingOrderline)) {
            $bamboraOrderlines[] = $roundingOrderline;
        }

        return $bamboraOrderlines;
    }

    /**
     * Create Bambora Orderline Rounding fee item
     *
     * @param Bambora $module
     * @param mixed $cartSummary
     * @param mixed $minorUnits
     * @param mixed $bamboraOrderlines
     * @param mixed $lineNumber
     * @param mixed $roundingMode
     *
     * @return BamboraOrderline|null
     */
    public static function createBamboraOrderlinesRoundingFee(
        $module,
        $cartSummary,
        $minorUnits,
        $bamboraOrderlines,
        $lineNumber,
        $roundingMode,
    ) {
        $cartTotal = BamboraCurrencyHelper::convertPriceToMinorUnits(
            $cartSummary['total_price'],
            $minorUnits,
            $roundingMode
        );
        $bamboraTotal = 0;
        foreach ($bamboraOrderlines as $orderLine) {
            $bamboraTotal += $orderLine->quantity * $orderLine->unitpriceinclvat;
        }
        if ($cartTotal != $bamboraTotal) {
            $roundingOrderline = new BamboraOrderline();
            $roundingOrderline->id = $module->l('adjustment', 'checkouthelper');
            $roundingOrderline->totalprice = $cartTotal - $bamboraTotal;
            $roundingOrderline->totalpriceinclvat = $cartTotal - $bamboraTotal;
            $roundingOrderline->totalpricevatamount = 0;
            $roundingOrderline->text = $module->l('Rounding adjustment', 'checkouthelper');
            $roundingOrderline->unitprice = $cartTotal - $bamboraTotal;
            $roundingOrderline->unitpriceinclvat = $cartTotal - $bamboraTotal;
            $roundingOrderline->unitpricevatamount = 0;
            $roundingOrderline->quantity = 1;
            $roundingOrderline->description = $module->l('Rounding adjustment', 'checkouthelper');
            $roundingOrderline->linenumber = $lineNumber++;
            $roundingOrderline->unit = $module->l('pcs.', 'checkouthelper');
            $roundingOrderline->vat = 0.0;

            return $roundingOrderline;
        }

        return null;
    }

    /**
     * Create Bambora Url
     *
     * @param Bambora $module
     *
     * @return BamboraUrl
     */
    public static function createBamboraUrl($module, $isPaymentRequest = false)
    {
        $bamboraUrl = new BamboraUrl();

        $bamboraUrl->callbacks = [];
        $callback = new BamboraCallback();

        if ($isPaymentRequest) {
            $callback->url = $module->bamboraContext->link->getModuleLink(
                $module->name,
                'paymentrequestcallback',
                [],
                true
            );
        } else {
            $bamboraUrl->accept = $module->bamboraContext->link->getModuleLink(
                $module->name,
                'accept',
                [],
                true
            );
            $bamboraUrl->decline = $module->bamboraContext->link->getPageLink(
                'order',
                true,
                null,
                'step=3'
            );
            $callback->url = $module->bamboraContext->link->getModuleLink(
                $module->name,
                'callback',
                [],
                true
            );
        }
        $bamboraUrl->callbacks[] = $callback;

        $bamboraUrl->immediateredirecttoaccept = Configuration::get(
            'BAMBORA_IMMEDIATEREDIRECTTOACCEPT'
        ) ? 1 : 0;

        return $bamboraUrl;
    }

    /**
     * Build Credit Invoice Line
     *
     * @param mixed $amount
     *
     * @return array
     */
    public static function buildCreditInvoiceLine($amount)
    {
        return [
            'description' => 'Prestashop credit item',
            'id' => '1',
            'linenumber' => '1',
            'quantity' => 1,
            'text' => 'Prestashop credit item',
            'totalprice' => $amount,
            'totalpriceinclvat' => $amount,
            'totalpricevatamount' => 0,
            'unit' => 'pcs.',
            'vat' => 0,
        ];
    }
}
