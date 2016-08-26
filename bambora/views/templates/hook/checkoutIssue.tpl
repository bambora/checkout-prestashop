<div class="row">
  <style type="text/css">
    .bambora {
    background: url(../../logo.png) 15px 12px no-repeat $base-box-bg;
    background-color:#FBFBFB;
    }
    
    p.payment_module a.BamboraStyle{
    background: url("{$this_path_bambora}Bambora_1_MINI_RGB.png");
    backgroud-attachment: scroll;
    background-repeat: no-repeat;
    background-position-x:0px;
    background-position-y:20px;
    background-size: auto;
    background-origin: padding-box;
    background-clip: border-box;
    background-color:rgb(251,251,251);
    }

    /*media all*/
    p.payment_module a.BamboraStyle::after {
    display: block;
    content: "\f054"; 
    position: absolute;
    right: 15px;
    margin-top: -11px;
    top: 50%;
    font-family: "FontAwesome";
    font-size: 25px;
    height: 22px;
    width: 14px;
    color: #777;
    }
  </style>
  <div class="col-xs-12">
    <p class="payment_module">
      <a title="{l s='Pay using Bambora' mod='bambora'}" class="bambora bamborastyle">
        {if $bambora_psVersionIsnewerOrEqualTo160 == false }
        <img src="{$this_path_bambora}Bambora_1_MINI_RGB.png" alt="{l s='Pay using Bambora' mod='bambora'}" style="float:left; margin-left:-10px; margin-top:-16px" />
        {/if}
        <span>An issue occured: </span>
        <span>{$bambora_errormessage}</span>        
      </a>
    </p>
  </div>
</div>
