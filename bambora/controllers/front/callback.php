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

class BamboraCallbackModuleFrontController extends BaseAction
{
	/**
     * @see FrontController::postProcess()
     */
	public function postProcess()
	{
        $message = "";
        $responseCode = 400;
        $cart = null;
        if($this->validateAction($message, $cart))
        {
            $message = $this->processAction($cart, $responseCode);
        }
        else
        {
            $message = empty($message) ? $this->l("Unknown error") : $message;
            $this->createLogMessage($message, 3, $cart);
        }

        $header = "X-EPay-System: ". BamboraHelpers::getModuleHeaderInfo();
        header($header, true, $responseCode);
        die($message);
    }
}