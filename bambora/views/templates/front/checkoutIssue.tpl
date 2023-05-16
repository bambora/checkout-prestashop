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

<div class="row">
    <div class="col-xs-12">
        <p class="payment_module">
            <a title="{l s='Pay using Bambora Checkout' mod='bambora'}"
               class="bambora bamborastyle">
                {if $bambora_psVersionIsnewerOrEqualTo160 == false }
                <img src="https://d3r1pwhfz7unl9.cloudfront.net/bambora/bambora_mini_rgb.png"
                     alt="{l s='Pay using Bambora Checkout' mod='bambora'}"
                     style="float:left; margin-left:-10px; margin-top:-16px"/>
                {/if}
                <span>{l s='An issue occured' mod='bambora'}: </span>
                <span>{$bambora_errormessage|escape:'htmlall':'UTF-8'}</span>
            </a>
        </p>
    </div>
</div>
