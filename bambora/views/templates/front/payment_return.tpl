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

<div class="bambora-compleated-container">
  
  <div class="icon-check"></div>
  <p id="bambora_completed_payment">
    <b>{$bambora_completed_paymentText|escape:'htmlall':'UTF-8'}</b>
  </p>
  <p>
      {if isset($bambora_completed_transactionText) && isset($bambora_completed_transactionValue)}{$bambora_completed_transactionText|escape:'htmlall':'UTF-8'} <b> {$bambora_completed_transactionValue|escape:'htmlall':'UTF-8'}</b>{/if}
    <br/>
      {if isset($bambora_completed_emailText) && isset($bambora_completed_emailValue)}{$bambora_completed_emailText|escape:'htmlall':'UTF-8'} <b> {$bambora_completed_emailValue|escape:'htmlall':'UTF-8'}</b>{/if}
  </p>
</div>