<?php
/**
 * 888                             888
 * 888                             888
 * 88888b.   8888b.  88888b.d88b.  88888b.   .d88b.  888d888  8888b.
 * 888 "88b     "88b 888 "888 "88b 888 "88b d88""88b 888P"       "88b
 * 888  888 .d888888 888  888  888 888  888 888  888 888     .d888888
 * 888 d88P 888  888 888  888  888 888 d88P Y88..88P 888     888  888
 * 88888P"  "Y888888 888  888  888 88888P"   "Y88P"  888     "Y888888
 *
 * @category    Online Payment Gatway
 * @package     Bambora_Online
 * @author      Bambora Online
 * @copyright   Bambora (http://bambora.com)
 */
class BamboraPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
	public function postProcess()
	{
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
        {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
        {
            if ($module['name'] == 'bambora')
            {
                $authorized = true;
                break;
            }
        }

        if (!$authorized)
        {
            die($this->module->l('This payment method is not available.', 'bambora'));
        }

        //create checkout request
        $bamboraCheckoutRequest = $this->module->createCheckoutRequest($cart);
        $bamboraPaymentData = $this->module->getBamboraPaymentData($bamboraCheckoutRequest);
        $checkoutResponse = $bamboraPaymentData['checkoutResponse'];
        if(!isset($checkoutResponse) || $checkoutResponse['meta']['result'] == false)
        {
            //add error message
            Tools::redirect('index.php?controller=order&step=1');
        }
        if(Configuration::get('BAMBORA_WINDOWSTATE') == 1)
        {
            $this->setRedirectAfter($checkoutResponse['url']);
            return;
        }

        $paymentData = array('bamboraPaymentwindowUrl' => $bamboraPaymentData['paymentWindowUrl'],
                             'bamboraCheckouturl' => $checkoutResponse['url'],
                             'bamboraCancelurl' => $bamboraCheckoutRequest->url->decline
                            );

        $this->context->smarty->assign($paymentData);

        $this->setTemplate('module:bambora/views/templates/front/bamboracheckout17.tpl');
    }
}