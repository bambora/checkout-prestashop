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

<section>
    <div class="bambora_section_container">
        {if $onlyShowLogoes != true}
        <p class="bambora_section_text">{l s='You have chosen to pay for the order online. Once you have completed your order, you will be transferred to the Bambora Online Checkout. Here you need to process your payment. Once payment is completed, you will automatically be returned to our shop.' mod='bambora'}</p>
        {/if}
        <div class="bambora_paymentlogos">
            {if $paymentCardIds|@count gt 0}
            {foreach from=$paymentCardIds key=k item=v}
            <img src="{'https://d3r1pwhfz7unl9.cloudfront.net/paymentlogos/cardid.svg'|replace:'cardid': $v|escape:'htmlall':'UTF-8'}"/>
            {/foreach}
            {/if}
        </div>
    </div>
</section>