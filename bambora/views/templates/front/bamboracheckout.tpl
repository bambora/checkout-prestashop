{*
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
*}

<div class="bambora_paymentwindow_container">

  <script type="text/javascript">
    if(!document.getElementById("bambora-paymentwindow-script")){
      (function (n, t, i, r, u, f, e) { n[u] = n[u] || function() {
      (n[u].q = n[u].q || []).push(arguments)}; f = t.createElement(i);
        e = t.getElementsByTagName(i)[0]; f.async = 1; f.src = r; f.id = "bambora-paymentwindow-script"; e.parentNode.insertBefore(f, e)
        })(window, document, "script", "{$bamboraPaymentwindowUrl|escape:'htmlall':'UTF-8'}", "bam");
    }

    var options = {
		'windowstate': {$bamboraWindowState|escape:'htmlall':'UTF-8'},
    }

    function openPaymentWindow(url)
    {
		bam('open', url, options);
    }

  </script>
  <p class="payment_module">
    <a class="bamboracheckout"  title="{$bamboraPaymentTitle|escape:'htmlall':'UTF-8'}" href="javascript:openPaymentWindow('{$bamboraCheckouturl|escape:'htmlall':'UTF-8'}')">
      {if $onlyShowLogoes == false}
      {$bamboraPaymentTitle|escape:'htmlall':'UTF-8'}
      {/if}
      <span class="bambora_paymentlogos">
        {if $bamboraPaymentCardIds|@count gt 0}
        {foreach from=$bamboraPaymentCardIds key=k item=v}
        <img src="{'https://d3r1pwhfz7unl9.cloudfront.net/paymentlogos/cardid.png'|replace:'cardid':$v|escape:'htmlall':'UTF-8'}"/>
        {/foreach}
        {/if}
      </span>
    </a>
  </p>
</div>