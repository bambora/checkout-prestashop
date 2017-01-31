<?php
/**
 * Bambora Online 2017
 *
 * @author    Bambora Online
 * @copyright Bambora (http://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class BamboraPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 ||
            $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 ||
            !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'bambora') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'bambora'));
        }

        //create checkout request
        $bamboraCheckoutRequest = $this->module->createCheckoutRequest($cart);
        $bamboraPaymentData = $this->module->getBamboraPaymentData($bamboraCheckoutRequest);
        $checkoutResponse = $bamboraPaymentData['checkoutResponse'];
        if (!isset($checkoutResponse) || $checkoutResponse['meta']['result'] == false) {
            //add error message
            Tools::redirect('index.php?controller=order&step=1');
        }
        if (Configuration::get('BAMBORA_WINDOWSTATE') == 1) {
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
