<?php

class ListPaymentRequestsController extends ModuleAdminController
{


    public function initContent()
    {
        parent::initContent();
        $template_file = _PS_MODULE_DIR_ . 'bambora/views/templates/admin/listpaymentrequests.tpl';
        $content = $this->context->smarty->fetch($template_file);
        $apiKey = BamboraHelpers::generateApiKey();
        $api = new BamboraApi($apiKey);

        $hasPaymentRequestPermissions = $api->checkIfMerchantHasPaymentRequestCreatePermissions();

        if (!$hasPaymentRequestPermissions) {
            $content .= $this->module->l(
                'Your merchant account does not have Payment Requests enabled yet. Please contact Bambora Support if you want to enable it.'
            );
        } else {
            $orders_url = Context::getContext()->link->getAdminLink('AdminOrders');
            $url_str = parse_url($orders_url, PHP_URL_PATH);
            $query_str = parse_url($orders_url, PHP_URL_QUERY);

            $page = isset($_GET['page']) ? $_GET['page'] : 1;

            $limit = 20;
            $countPR = 0;


            try {
                $countPR = BamboraHelpers::getNumberOfPaymentRequests();
            } catch (PrestaShopDatabaseException $e) {
                error_log($e->getMessage());
            }

            if ($countPR > 0) {
                $total_pages = ceil($countPR / $limit);
                $paymentRequests = null;
                try {
                    $paymentRequests = BamboraHelpers::listPaymentRequests(
                        $limit,
                        $page
                    );
                } catch (PrestaShopDatabaseException $e) {
                    error_log($e->getMessage());
                }

                if (!$paymentRequests) {
                    $content .= $this->module->l('No payment requests yet.');
                } else {
                    $content .= "<table style='padding:10px;'><tr>" .
                        "<td style='padding:10px;'><b>" . $this->module->l(
                            "Order ID"
                        ) . "</b></td>" .
                        "<td style='padding:10px;'><b>" . $this->module->l(
                            "Payment Request Id"
                        ) . "</b></td>" .
                        "<td style='padding:10px;'><b>" . $this->module->l(
                            "Description"
                        ) . "</b></td>" .
                        "<td style='padding:10px;'><b>" . $this->module->l(
                            "Reference"
                        ) . "</b></td>" .
                        "<td style='padding:10px;'><b>" . $this->module->l(
                            "Status"
                        ) . "</b></td>" .
                        "<td style='padding:10px;'><b>" . $this->module->l(
                            "Amount"
                        ) . "</b></td>" .

                        "</tr>";
                    foreach ($paymentRequests as $paymentRequest) {
                        $order_id = $paymentRequest['id_order'];
                        $order_url = $url_str . $order_id . "/view?" . $query_str;
                        $payment_request_id = $paymentRequest['payment_request_id'];
                        $payment_request_url = $paymentRequest['payment_request_url'];
                        $paymentRequestDetails = $api->getPaymentRequest(
                            $payment_request_id
                        );
                        $prDescription = "";
                        $prStatus = "";
                        $prReference = "";
                        $prAmount = "";
                        if (isset($paymentRequestDetails) && $paymentRequestDetails['meta']['result']) {
                            $prDescription = $paymentRequestDetails['description'];
                            $prStatus = $paymentRequestDetails['status'];
                            $prReference = $paymentRequestDetails['reference'];

                            if (isset($paymentRequestDetails['parameters']['order']['amount']) && isset($paymentRequestDetails['parameters']['order']['currency'])) {
                                $amount = BamboraCurrency::convertPriceFromMinorUnits(
                                    $paymentRequestDetails['parameters']['order']['amount'],
                                    BamboraCurrency::getCurrencyMinorunits(
                                        $paymentRequestDetails['parameters']['order']['currency']
                                    )
                                );
                                $formattedAmount = $this->context->currentLocale->formatPrice(
                                    $amount,
                                    $paymentRequestDetails['parameters']['order']['currency']
                                );
                                $prAmount = $formattedAmount;
                            }
                        }

                        $content .= "<tr>" .
                            "<td style='padding:10px;'><a href='" . $order_url . "'>" . $order_id . "</a></td>" .
                            "<td style='padding:10px;'><a href='" . $payment_request_url . "' target='_blank'>" . $payment_request_id . "</a></td>" .
                            "<td style='padding:10px;'>" . $prDescription . "</td>" .
                            "<td style='padding:10px;'>" . $prReference . "</td>" .
                            "<td style='padding:10px;'>" . $prStatus . "</td>" .
                            "<td style='padding:10px;'>" . $prAmount . "</td>" .
                            "</tr>";
                    }
                    $content .= "</table>";
                    $query = $_GET;
                    $url_str = $_SERVER['PHP_SELF'];

                    $pagLink = "<ul style='list-style: none;'>";
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $query['page'] = $i;
                        $query_result = http_build_query($query);
                        if ($i == $page) {
                            $style = "font-weight:800;";
                        } else {
                            $style = "font-weight:400;";
                        }
                        $pagLink .= "<li style='display: inline'><a style='padding:10px;" . $style . "' href='" . $url_str . "?" . $query_result . "'>" . $i . "</a></li>";
                    }
                    $pagLink .= "</ul>";

                    $content .= $pagLink;
                }
            } else {
                $content .= $this->module->l('No payment requests yet.');
            }
        }


        $this->context->smarty->assign(array(
            'content' => $content,
        ));
    }
}
