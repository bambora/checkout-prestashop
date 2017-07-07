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

<div>
  <p class="alert alert-warning warning">
    {l s='Your payment failed because of' mod='bambora'} <strong>"{$paymenterror|escape:'htmlall':'UTF-8'}"</strong>
    <br/>
    {l s='Please contact the shop to correct the error and complete your payment.' mod='bambora'}
  </p>
</div>
