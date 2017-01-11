<div class="row">
  <div class="col-xs-12">
    <p class="payment_module">
      <a title="{l s='Pay using Bambora Checkout' mod='bambora'}" class="bambora bamborastyle">
        {if $bambora_psVersionIsnewerOrEqualTo160 == false }
        <img src="https://d3r1pwhfz7unl9.cloudfront.net/bambora/bambora_mini_rgb.png" alt="{l s='Pay using Bambora Checkout' mod='bambora'}" style="float:left; margin-left:-10px; margin-top:-16px" />
        {/if}
        <span>{l s='An issue occured' mod='bambora'}: </span>
        <span>{$bambora_errormessage}</span>        
      </a>
    </p>
  </div>
</div>
