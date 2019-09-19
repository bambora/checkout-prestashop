{*
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
*}

<div class="bambora_paymentwindow_container">

  <script src="https://static.bambora.com/checkout-sdk-web/latest/checkout-sdk-web.min.js"></script>

  <script type="text/javascript">
    var checkoutToken = "{$bamboraCheckoutToken|escape:'htmlall':'UTF-8'}";
    var windowState = {$bamboraWindowState|escape:'htmlall':'UTF-8'};
    if(windowState !== 1) {
      var checkout = new Bambora.ModalCheckout(null);
      checkout.on(Bambora.Event.Close, function(payload) {
        window.location.href = payload.acceptUrl;
      });
      checkout.initialize(checkoutToken)
    } 

    function openBamboraCheckout(){
      if(windowState === 1) {
        new Bambora.RedirectCheckout(checkoutToken);
      } else {
        checkout.show();
      }
    }
  </script>

  <p class="payment_module">
    <a class="bamboracheckout"  title="{$bamboraPaymentTitle|escape:'htmlall':'UTF-8'}" href="javascript:openBamboraCheckout()">
      {if $onlyShowLogoes == false}
      {$bamboraPaymentTitle|escape:'htmlall':'UTF-8'}
      {/if}
      <span class="bambora_paymentlogos">
        {if $bamboraPaymentCardIds|@count gt 0}
        {foreach from=$bamboraPaymentCardIds key=k item=v}
        <img src="{'https://d3r1pwhfz7unl9.cloudfront.net/paymentlogos/cardid.svg'|replace:'cardid':$v|escape:'htmlall':'UTF-8'}"/>
        {/foreach}
        {/if}
      </span>
    </a>
  </p>
</div>