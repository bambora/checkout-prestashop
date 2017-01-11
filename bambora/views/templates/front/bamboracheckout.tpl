<div class="bambora_paymentwindow_container">

  <script type="text/javascript" >

    (function (n, t, i, r, u, f, e) { n[u] = n[u] || function() {
    (n[u].q = n[u].q || []).push(arguments)}; f = t.createElement(i);
    e = t.getElementsByTagName(i)[0]; f.async = 1; f.src = r; e.parentNode.insertBefore(f, e)
    })(window, document, "script", "{$bamboraPaymentwindowUrl|escape:'htmlall':'UTF-8'}", "bam");

    var options = {
    'windowstate': {$bamboraWindowState},
    }

    function openPaymentWindow(url)
    {
    bam('open', url, options);
    }
  </script>
  <p class="payment_module">
    <a class="bamboracheckout"  title="{l s='Pay using Bambora Checkout' mod='bambora'}" href="javascript:openPaymentWindow('{$bamboraCheckouturl|escape:'htmlall':'UTF-8'}')">
      {if $onlyShowLogoes == false}
      {l s='Pay using Bambora Checkout' mod='bambora'}
      {/if}
      <span class="bambora_paymentlogos">
        {if $bamboraPaymentCardIds|@count gt 0}
        {foreach from=$bamboraPaymentCardIds key=k item=v}
        <img src="{'https://d3r1pwhfz7unl9.cloudfront.net/paymentlogos/cardid.png'|replace:'cardid': $v}"/>
        {/foreach}
        {/if}
      </span>
    </a>
  </p>
</div>