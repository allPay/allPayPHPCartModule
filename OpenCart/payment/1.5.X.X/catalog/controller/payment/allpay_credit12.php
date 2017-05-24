<?php

include_once 'AllPay.Payment.Integration.php';

class ControllerPaymentAllpayCredit12 extends Controller {

    private $paymentSubfix = 'credit12';

    protected function index() {
        $this->language->load('payment/allpay_payment');
        $this->load->model('checkout/order');

        $oOrderInfo = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $isTestMode = $this->config->get('allpay_' . $this->paymentSubfix . '_test_mode');
        $szTemplatePath = $this->config->get('config_template') . '/template/payment/allpay_payment.tpl';
        $szPaymentButton = $this->language->get('button_confirm');
        $szPaymentForm = '';

        $oPayment = new AllInOne();
        $oPayment->ServiceURL = ($isTestMode ? 'https://payment-stage.allpay.com.tw/Cashier/AioCheckOut' : 'https://payment.allpay.com.tw/Cashier/AioCheckOut');
        $oPayment->HashKey = $this->config->get('allpay_' . $this->paymentSubfix . '_hash_key');
        $oPayment->HashIV = $this->config->get('allpay_' . $this->paymentSubfix . '_hash_iv');
        $oPayment->MerchantID = $this->config->get('allpay_' . $this->paymentSubfix . '_merchant_id');

        $oPayment->Send['ReturnURL'] = $this->url->link('payment/allpay_' . $this->paymentSubfix . '/callback', '', 'SSL');
        $oPayment->Send['ClientBackURL'] = $this->url->link('common/home', '', 'SSL');
        $oPayment->Send['OrderResultURL'] = $this->url->link('payment/allpay_' . $this->paymentSubfix . '/result', '', 'SSL');
        $oPayment->Send['MerchantTradeNo'] = ($isTestMode ? $this->config->get('allpay_' . $this->paymentSubfix . '_test_fix') : '') . $this->session->data['order_id'];
        $oPayment->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
        $oPayment->Send['TotalAmount'] = intval(round($oOrderInfo['total']));
        $oPayment->Send['TradeDesc'] = "allpay_module_opencart_1.0.1021";
        $oPayment->Send['ChoosePayment'] = PaymentMethod::Credit;
        $oPayment->Send['Remark'] = '';
        $oPayment->Send['ChooseSubPayment'] = PaymentMethodItem::None;
        $oPayment->Send['NeedExtraPaidInfo'] = ExtraPaymentInfo::No;
        
        array_push($oPayment->Send['Items'], array('Name' => $this->language->get('text_commodity_group'), 'Price' => $oPayment->Send['TotalAmount'], 'Currency' => $_SESSION['currency'], 'Quantity' => 1, 'URL' => ''));
        
        $oPayment->SendExtend['CreditInstallment'] = 12;
        $oPayment->SendExtend['InstallmentAmount'] = $oPayment->Send['TotalAmount'];
        $oPayment->SendExtend['Redeem'] = false;
        $oPayment->SendExtend['UnionPay'] = false;

        try {
            $szPaymentForm = $oPayment->CheckOutString($szPaymentButton);
        } catch (Exception $e) {
            $szPaymentForm = '<script language="text/javascript">alert("' . $e->getMessage() . '");</script>';
        }

        $this->data['allpay_payment_form'] = $szPaymentForm;
        $this->data['total'] = $oPayment->Send['TotalAmount'];
        $this->data['text_payment'] = sprintf($this->language->get('text_payment'), $this->language->get('text_' . $this->paymentSubfix . '_title'));
        $this->data['text_instruction'] = $this->language->get('text_instruction');
        $this->data['text_total_error'] = $this->language->get('text_total_error');
        $this->data['allpay_payment_description'] = nl2br($this->config->get('allpay_' . $this->paymentSubfix . '_description_' . $this->config->get('config_language_id')));
        $this->data['continue'] = $this->url->link('payment/allpay_' . $this->paymentSubfix . '/confirm', '', 'SSL');

        if (isset($this->session->data['doubleclick'])) {
            unset($this->session->data['doubleclick']);
        }

        if (file_exists(DIR_TEMPLATE . $szTemplatePath)) {
            $this->template = $szTemplatePath;
        } else {
            $this->template = 'default/template/payment/allpay_payment.tpl';
        }

        $this->render();
    }

    public function confirm() {
        $this->load->model('checkout/order');
        $this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('allpay_' . $this->paymentSubfix . '_order_status_id'), "");
        $this->cart->clear();
    }

    public function callback() {
        $szStatus = $this->config->get('allpay_' . $this->paymentSubfix . '_order_status_id');
        $szFinishStatus = $this->config->get('allpay_' . $this->paymentSubfix . '_order_finish_status_id');
        $szMessage = $this->feedback($szStatus, $szFinishStatus);

        print $szMessage;
    }

    public function result() {
        $szStatus = $this->config->get('allpay_' . $this->paymentSubfix . '_order_status_id');
        $szFinishStatus = $this->config->get('allpay_' . $this->paymentSubfix . '_order_finish_status_id');
        $szMessage = $this->feedback($szStatus, $szFinishStatus);

        if ($szMessage == '1|OK') {
            $this->redirect($this->url->link('checkout/success'));
        } else {
            $this->redirect($this->url->link('payment/allpay_error'));
        }
    }

    private function feedback($status, $finishStatus) {
        global $log;
        $this->language->load('payment/allpay_payment');
        $this->load->model('checkout/order');
        $isTestMode = $this->config->get('allpay_' . $this->paymentSubfix . '_test_mode');
        $szStatus = $status;
        $szFinishStatus = $finishStatus;
        $szMessage = '1|OK';

        $oPayment = new AllInOne();
        $oPayment->ServiceURL = ($isTestMode ? 'https://payment-stage.allpay.com.tw/Cashier/QueryTradeInfo' : 'https://payment.allpay.com.tw/Cashier/QueryTradeInfo');
        $oPayment->HashKey = $this->config->get('allpay_' . $this->paymentSubfix . '_hash_key');
        $oPayment->HashIV = $this->config->get('allpay_' . $this->paymentSubfix . '_hash_iv');

        try {
            // 取得回傳參數。
            $arFeedback = $oPayment->CheckOutFeedback();
            // 檢核與變更訂單狀態。
            if (sizeof($arFeedback) > 0) {
                $szOrderID = $arFeedback['MerchantTradeNo'];
                $szOrderID = ($isTestMode ? str_replace($this->config->get('allpay_' . $this->paymentSubfix . '_test_fix'), '', $szOrderID) : $szOrderID);
                $deTradeAmount = $arFeedback['TradeAmt'];
                $szReturnCode = $arFeedback['RtnCode'];
                $szReturnMessgae = $arFeedback['RtnMsg'];
                // 查詢系統訂單。
                $oOrderInfo = $this->model_checkout_order->getOrder($szOrderID);
                $deTotalAmount = intval(round($oOrderInfo['total']));
                $szOrderStatus = $oOrderInfo['order_status_id'];
                // 核對訂單金額。
                if ($deTradeAmount == $deTotalAmount) {
                    // 當訂單回傳狀態為無異常，更新訂單資料與新增訂單歷程。
                    if ($szReturnCode == 1 || $szReturnCode == 800) {
                        $szComment = "$szReturnCode: $szReturnMessgae";
                        // 更新訂單資料與新增訂單歷程。
                        if ($szStatus == $szOrderStatus) {
                            // 新增訂單通知處理歷程。
                            $this->db->query("INSERT INTO " . DB_PREFIX . "order_history (order_id, order_status_id, notify, comment, date_added) values ('$szOrderID', '$szFinishStatus', '1', '$szComment', NOW())");
                            // 更新訂單狀態。
                            $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '$szFinishStatus', date_modified = NOW() WHERE order_id = '$szOrderID' AND order_status_id = '$szStatus'");
                        } else {
                            // 訂單已處理，無須再處理。
                        }
                    } else {
                        $szMessage = "0|Order '$szOrderID' Exception.($szReturnCode: $szReturnMessgae)";

                        $log->write($szMessage);
                    }
                } else {
                    $szMessage = '0|Compare "' . $szOrderID . '" Order Amount Fail.';

                    $log->write($szMessage);
                }
            } else {
                $szMessage = '0|"' . $szOrderID . '" Order Not Found at AllPay.';

                $log->write($szMessage);
            }
        } catch (Exception $e) {
            $log->write($e->getMessage());

            $szMessage = '0|' . $e->getMessage();
        }

        return $szMessage;
    }

}
