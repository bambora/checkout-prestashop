<?php

include __DIR__ . '/baseaction.php';

class BamboraCallbackModuleFrontController extends BaseAction
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $message = '';
        $responseCode = 400;
        $cart = null;
        if ($this->validateAction($message, $cart)) {
            $message = $this->processAction(false, $cart, 'callback', $responseCode);
        } else {
            $message = empty($message) ? $this->l('Unknown error', 'callback') : $message;
            $this->createErrorLogMessage($message, 3, $cart);
        }

        header('', true, $responseCode);
        exit($message);
    }
}
