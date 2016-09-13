<div class="bambora_paymentwindow_container">

  <script type="text/javascript" >

    (function (n, t, i, r, u, f, e) { n[u] = n[u] || function() {
    (n[u].q = n[u].q || []).push(arguments)}; f = t.createElement(i);
    e = t.getElementsByTagName(i)[0]; f.async = 1; f.src = r; e.parentNode.insertBefore(f, e)
    })(window, document, "script", "{$bambora_paymentwindowurl|escape:'htmlall':'UTF-8'}", "bam");


    var options = {
      'windowstate': {$bambora_windowstate}
    }


    function openPaymentWindow(url)
    {
      bam('open', url, options);
    }

  </script> 

  <p class="payment_module">
    <a title="{l s='Pay using Bambora' mod='bambora'}" href="javascript:openPaymentWindow('{$bambora_checkouturl|escape:'htmlall':'UTF-8'}')" class="bambora_payment_content">
      {if $bambora_psVersionIsnewerOrEqualTo160 == false }
		<img src="https://d3r1pwhfz7unl9.cloudfront.net/bambora/bambora_mini_rgb.png" alt="{l s='Pay using Bambora' mod='bambora'}" style="float:left; margin-left:-10px; margin-top:-16px" />
      {/if}
      <span id="bambora_card_logos" style="margin-left:20px">
        {if $paymentcardIds|@count gt 0}
          {foreach from=$paymentcardIds key=k item=v}
            <img style="padding: 2px 0px; width: 45px;" src="{'https://d3r1pwhfz7unl9.cloudfront.net/paymentlogos/cardid.png'|replace:'cardid': $v}"/>
        {/foreach}
        {else}
          {l s='Pay using Bambora' mod='bambora'}
        {/if}
      </span>
    </a>
  </p>
</div>