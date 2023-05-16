<?php
/**
 * Copyright (c) 2019. All rights reserved Bambora Online A/S.
 *
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test account that you can use to test the module.
 *
 * @author    Bambora Online A/S
 * @copyright Bambora (https://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 *
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
            $message = $this->processAction(false, $cart, $responseCode);
        } else {
            $message = empty($message) ? $this->l("Unknown error") : $message;
            $this->createLogMessage($message, 3, $cart);
        }

        $header = "X-EPay-System: " . BamboraHelpers::getModuleHeaderInfo();
        header($header, true, $responseCode);
        die($message);
    }
}
