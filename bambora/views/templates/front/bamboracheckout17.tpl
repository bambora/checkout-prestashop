{extends "$layout"}

{block name="content"}
<section>
  <h3>{l s='Thank you for using Bambora Checkout' mod='bambora'}</h3>
  <p>{l s='Please wait...' mod='bambora'}</p>

  <script type="text/javascript">
    (function (n, t, i, r, u, f, e) { n[u] = n[u] || function() {
    (n[u].q = n[u].q || []).push(arguments)}; f = t.createElement(i);
    e = t.getElementsByTagName(i)[0]; f.async = 1; f.src = r; e.parentNode.insertBefore(f, e)
    })(window, document, "script", "{$bamboraPaymentwindowUrl|escape:'htmlall':'UTF-8'}", "bam");

    var onClose = function(){
    window.location.href = "{$bamboraCancelurl}";
    };

    var options = {
    'windowstate': 2,
    'onClose': onClose
    }

    bam('open', '{$bamboraCheckouturl|escape:'htmlall':'UTF-8'}', options);
  </script>
</section>

{/block}