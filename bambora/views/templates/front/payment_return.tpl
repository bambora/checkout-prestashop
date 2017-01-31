{*
* Bambora Online 2017
*
* @author    Bambora Online
* @copyright Bambora (http://bambora.com)
* @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
*
*}

<div class="bambora-compleated-container">
  
  <div class="icon-check"></div>
  <p id="bambora_completed_payment">
    <b>{$bambora_completed_paymentText|escape:'htmlall':'UTF-8'}</b>
  </p>
  <p>
    {$bambora_completed_transactionText|escape:'htmlall':'UTF-8'} <b> {$bambora_completed_transactionValue|escape:'htmlall':'UTF-8'}</b>
    <br/>
    {$bambora_completed_emailText|escape:'htmlall':'UTF-8'} <b> {$bambora_completed_emailValue|escape:'htmlall':'UTF-8'}</b>
  </p>
</div>