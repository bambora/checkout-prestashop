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
        if($this->validateAction($message, $cart))
        {
            /* Wait for callback */
            for ($i = 0; $i < 10; $i++)
            {
                if($cart->orderExists())
                {
                    $this->redirectToAccept($cart);
                    return;
                }
                sleep(1);
            }
            $message = $this->processAction($cart, $responseCode);
            $this->redirectToAccept($cart);
        }
        else
        {
            $message = empty($message) ? $this->l("Unknown error") : $message;
            $this->createLogMessage($message, 3, $cart);
            Context::getContext()->smarty->assign('paymenterror', $message);
            if($this->module->getPsVersion() === Bambora::V17)
            {
                $this->setTemplate('module:bambora/views/templates/front/paymenterror17.tpl');
            }
            else
            {
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
        Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$cart->secure_key.'&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.(int)Order::getOrderByCartId($cart->id));
    }
}