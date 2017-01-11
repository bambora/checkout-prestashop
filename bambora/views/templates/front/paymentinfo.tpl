<section>
  {if $onlyShowLogoes != true}
  <p>{l s='You have chosen to pay for the order online. Once you have completed your order, you will be transferred to the Bambora Checkout. Here you need to process your payment. Once payment is completed, you will automatically be returned to our shop.' mod='bambora'}</p>
  {/if}
  <span class="bambora_paymentlogos_new">
    {if $paymentCardIds|@count gt 0}
    {foreach from=$paymentCardIds key=k item=v}
    <img src="{'https://d3r1pwhfz7unl9.cloudfront.net/paymentlogos/cardid.png'|replace:'cardid': $v}"/>
    {/foreach}
    {else}
    {l s='Pay using Bambora Checkout' mod='bambora'}
    {/if}
  </span>
</section>