<section>
    <div class="bambora_section_container">
        {if $onlyShowLogoes != true}
            <p class="bambora_section_text">
                {l s='You have chosen to pay for the order online. Once you have completed your order, you will be transferred to the Worldline Online Checkout. Here you need to process your payment. Once payment is completed, you will automatically be returned to our shop.' mod='bambora'}
            </p>
        {/if}
        <div class="bambora_paymentlogos">
            {if $paymentCardIds|@count gt 0}
                {foreach from=$paymentCardIds key=k item=v}
                    <img
                        src="{'https://static.bambora.com/assets/paymentlogos/cardid.svg'|replace:'cardid': $v|escape:'htmlall':'UTF-8'}" />
                {/foreach}
            {/if}
        </div>
    </div>
</section>