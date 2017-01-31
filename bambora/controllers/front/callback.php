<?php
/**
 * Bambora Online 2017
 *
 * @author    Bambora Online
 * @copyright Bambora (http://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
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
        if ($this->validateAction($message, $cart)) {
            $message = $this->processAction($cart, $responseCode);
        } else {
            $message = empty($message) ? $this->l("Unknown error") : $message;
            $this->createLogMessage($message, 3, $cart);
        }

        $header = "X-EPay-System: ". BamboraHelpers::getModuleHeaderInfo();
        header($header, true, $responseCode);
        die($message);
    }
}
