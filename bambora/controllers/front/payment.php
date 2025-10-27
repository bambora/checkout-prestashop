<?php

class BamboraPaymentModuleFrontController extends ModuleFrontController
{
    /** @var Bambora */
    private $bamboraModule;

    public function __construct()
    {
        parent::__construct();
        $this->bamboraModule = $this->module;
    }

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || !$this->module->active) {
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
            exit(
                $this->module->l(
                    'This payment method is not available.',
                    'payment'
                )
            );
        }

        // create checkout request
        $bamboraCheckoutRequest = BamboraCheckoutHelper::createCheckoutRequest($this->bamboraModule, $cart);
        $checkoutResponse = BamboraApiHelper::getCheckoutResponse(
            $bamboraCheckoutRequest
        );
        if (!isset($checkoutResponse) || !$checkoutResponse->meta->result) {
            // add error message
            Tools::redirect('index.php?controller=order&step=1');
        }

        $paymentData = [
            'bamboraWindowState' => Configuration::get('BAMBORA_WINDOWSTATE'),
            'bamboraCheckoutToken' => $checkoutResponse->token,
        ];

        $this->context->smarty->assign($paymentData);

        $this->setTemplate(
            'module:bambora/views/templates/front/bambora-checkout.tpl'
        );
    }
}
