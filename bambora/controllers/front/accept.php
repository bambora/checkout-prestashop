<?php

include 'baseAction.php';
class BamboraAcceptModuleFrontController extends BaseAction
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $message = '';
        $responseCode = '400';
        $cart = null;
        if ($this->validateAction($message, $cart)) {
            /* Wait for callback */
            for ($i = 0; $i < 10; ++$i) {
                if (isset($cart) && $cart->orderExists()) {
                    $this->redirectToAccept($cart);

                    return;
                }
                sleep(1);
            }
            $this->processAction(false, $cart, 'accept', $responseCode);
            $this->redirectToAccept($cart);
        } else {
            $message = empty($message) ? $this->l('Unknown error', 'accept') : $message;
            $this->createErrorLogMessage($message, 3, $cart);
            Context::getContext()->smarty->assign('paymenterror', $message);
            $this->setTemplate(
                'module:bambora/views/templates/front/payment-error.tpl'
            );
        }
    }

    /**
     * Redirect To Accept
     *
     * @param mixed $cart
     */
    private function redirectToAccept($cart)
    {
        $order_id = Order::getIdByCartId($cart->id);
        $url = "order-confirmation.php?key={$cart->secure_key}&id_cart={$cart->id}&id_module={$this->module->id}&id_order={$order_id}";
        Tools::redirect($url);
    }
}
