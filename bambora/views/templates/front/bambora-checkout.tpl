{extends "$layout"}

{block name="content"}
    <section>
        <h3>{l s='Thank you for using Worldline Online Checkout' mod='bambora'}</h3>
        <p>{l s='Please wait...' mod='bambora'}</p>

        <script type="text/javascript">
            var checkoutToken = "{$bamboraCheckoutToken|escape:'htmlall':'UTF-8'}";
            var windowState = "{$bamboraWindowState|escape:'htmlall':'UTF-8'}";

            if (windowState === "1") {
                new Bambora.RedirectCheckout(checkoutToken);
            } else {
                var checkout = new Bambora.ModalCheckout(null);
                checkout.on(Bambora.Event.Cancel, function(payload) {
                    window.location.href = payload.declineUrl;
                });
                checkout.on(Bambora.Event.Close, function(payload) {
                    window.location.href = payload.acceptUrl;
                })
                checkout.initialize(checkoutToken).then(function() {
                    checkout.show();
                });
            }
        </script>
    </section>

{/block}