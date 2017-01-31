{*
* Bambora Online 2017
*
* @author    Bambora Online
* @copyright Bambora (http://bambora.com)
* @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
*
*}

{extends "$layout"}

{block name="content"}
<div>
    <p class="alert alert-warning warning">{l s='Your payment failed because of' mod='bambora'} <strong>"{$paymenterror|escape:'htmlall':'UTF-8'}"</strong>
    <br/>
    {l s='Please contact the shop to correct the error and complete your payment.' mod='bambora'}
    </p>
</div>
{/block}