<div class="bambora-compleated-container">

    <div class="icon-check"></div>
    <p id="bambora_completed_payment">
        <b>{$bambora_completed_paymentText|escape:'htmlall':'UTF-8'}</b>
    </p>
    <p>
        {if isset($bambora_completed_transactionText) &&
        isset($bambora_completed_transactionValue)}{$bambora_completed_transactionText|escape:'htmlall':'UTF-8'}
        <b> {$bambora_completed_transactionValue|escape:'htmlall':'UTF-8'}</b>{/if}
        <br/>
        {if isset($bambora_completed_emailText) &&
        isset($bambora_completed_emailValue)}{$bambora_completed_emailText|escape:'htmlall':'UTF-8'}
        <b> {$bambora_completed_emailValue|escape:'htmlall':'UTF-8'}</b>{/if}
    </p>
</div>