<?php

include __DIR__ . '/baseaction.php';
class BamboraPaymentRequestCallbackModuleFrontController extends BaseAction
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
            $message = $this->processAction(true, $cart, 'paymentrequest', $responseCode);
        } else {
            $message = empty($message) ? $this->l('Unknown error', 'paymentrequestcallback') : $message;
            $this->createErrorLogMessage($message, 3, $cart);
        }

        header('', true, $responseCode);
        exit($message);
    }
}
