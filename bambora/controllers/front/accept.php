<?php
/**
 * Bambora Online 2017
 *
 * @author    Bambora Online
 * @copyright Bambora (http://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

include('baseaction.php');

class BamboraAcceptModuleFrontController extends BaseAction
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $message = "";
        $responseCode = '400';
        $cart = null;
        if ($this->validateAction($message, $cart)) {
            /* Wait for callback */
            for ($i = 0; $i < 10; $i++) {
                if ($cart->orderExists()) {
                    $this->redirectToAccept($cart);
                    return;
                }
                sleep(1);
            }
            $message = $this->processAction($cart, $responseCode);
            $this->redirectToAccept($cart);
        } else {
            $message = empty($message) ? $this->l("Unknown error") : $message;
            $this->createLogMessage($message, 3, $cart);
            Context::getContext()->smarty->assign('paymenterror', $message);
            if ($this->module->getPsVersion() === Bambora::V17) {
                $this->setTemplate('module:bambora/views/templates/front/paymenterror17.tpl');
            } else {
                $this->setTemplate('paymenterror.tpl');
            }
        }
    }

    /**
     * Redirect To Accept
     *
     * @param mixed $cart
     */
    private function redirectToAccept($cart)
    {
        Tools::redirectLink(__PS_BASE_URI__. 'order-confirmation.php?key='. $cart->secure_key. '&id_cart='. (int)$cart->id. '&id_module='. (int)$this->module->id. '&id_order='. (int)Order::getOrderByCartId($cart->id));
    }
}
