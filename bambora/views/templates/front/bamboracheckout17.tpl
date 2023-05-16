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

{extends "$layout"}

{block name="content"}
<section>
    <h3>{l s='Thank you for using Bambora Online Checkout' mod='bambora'}</h3>
    <p>{l s='Please wait...' mod='bambora'}</p>

    <script type="text/javascript">
        var checkoutToken = "{$bamboraCheckoutToken|escape:'htmlall':'UTF-8'}";
        var windowState = "{$bamboraWindowState|escape:'htmlall':'UTF-8'}";

        if (windowState === 1) {
            new Bambora.RedirectCheckout(checkoutToken);
        } else {
            var checkout = new Bambora.ModalCheckout(null);
            checkout.on(Bambora.Event.Cancel, function (payload) {
                window.location.href = payload.declineUrl;
            });
            checkout.on(Bambora.Event.Close, function (payload) {
                window.location.href = payload.acceptUrl;
            })
            checkout.initialize(checkoutToken).then(function () {
                checkout.show();
            });
        }
    </script>
</section>

{/block}