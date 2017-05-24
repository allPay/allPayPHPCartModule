<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

defined('_JEXEC') or die('Restricted access');

if (!class_exists('AllInOne')) {
    require_once('AllPay.Payment.Integration.php');
}
if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentAllPayTenpay extends vmPSPlugin {

    /**
     * Class construct.(必要)
     * @param type $subject
     * @param type $config
     */
    function __construct(& $subject, $config) {
        parent::__construct($subject, $config);

        $this->_loggable = TRUE;
        $this->_debug = TRUE;
        //$this->tableFields = array_keys($this->getTableSQLFields());
        $this->setConfigParameterable($this->_configTableFieldName, $this->getVarsToPush());
    }

    /**
     * Declare plugin parameters.(必要)。
     * @param type $name
     * @param type $id
     * @param type $data
     * @return type
     */
    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    /**
     * Save plugin parameters into table.(必要)。
     * @param type $name
     * @param type $id
     * @param type $table
     * @return type
     */
    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * Get payment currrency.(必要)。
     * @param type $virtuemart_paymentmethod_id
     * @param type $paymentCurrencyId
     * @return boolean
     */
    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
        if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return FALSE;
        }
        $this->getPaymentCurrency($this->_currentMethod);
        $paymentCurrencyId = $this->_currentMethod->payment_currency;
    }

    /**
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type(必要)
     * @param VirtueMartCart $cart
     * @param array $cart_prices
     * @param type $paymentCounter
     * @return type
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /**
     * List payment methods selection(必要)
     * @param VirtueMartCart $cart
     * @param type $selected
     * @param type $htmlIn
     * @return type
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     * This event is fired after the payment method has been selected.(必要)
     * @param VirtueMartCart $cart
     * @return type
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
        return $this->OnSelectCheck($cart);
    }

    /**
     * Calculate the price (value, tax_id) of the selected method, It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.(必要)
     * @param VirtueMartCart $cart
     * @param array $cart_prices
     * @param type $cart_prices_name
     * @return type
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.(必要)
     * @param type $virtuemart_order_id
     * @param type $virtuemart_paymentmethod_id
     * @param type $payment_name
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * Display stored payment data for an order.(必要)
     * @param type $virtuemart_order_id
     * @param type $virtuemart_payment_id
     * @return string
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return null;
        }
        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id) )) {
            return null;
        }

        $html = '';
        //$html .= '<table class="adminlist">' . "\n";
        //$html .= $this->getHtmlHeaderBE();
        //$html .= $this->getHtmlRowBE('TENPAY_PAYMENT_NAME', $paymentTable->payment_name);
        //$html .= $this->getHtmlRowBE('TENPAY_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        //$html .= '</table>' . "\n";
        return $html;
    }

    /**
     * Confirm and send order to payment.(必要)
     * @param type $cart
     * @param type $order
     * @return boolean
     */
    function plgVmConfirmedOrder($cart, $order) {
        if (!($this->_currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return FALSE;
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        if (!class_exists('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        }

        $oCurrency = CurrencyDisplay::getInstance('', $order['details']['BT']->virtuemart_vendor_id);

        if ($order) {
            try {
                $oPayment = new AllInOne();
                $oPayment->ServiceURL = ($this->_currentMethod->test_mode == 'yes' ? 'https://payment-stage.allpay.com.tw/Cashier/AioCheckOut/V2' : 'https://payment.allpay.com.tw/Cashier/AioCheckOut/V2');
                $oPayment->HashKey = $this->_currentMethod->hash_key;
                $oPayment->HashIV = $this->_currentMethod->hash_iv;
                $oPayment->MerchantID = $this->_currentMethod->merchant_id;

                $oPayment->Send['ReturnURL'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt('Itemid')) . '&bg=1';
                $oPayment->Send['ClientBackURL'] = JURI::root();
                $oPayment->Send['OrderResultURL'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt('Itemid'));
                $oPayment->Send['MerchantTradeNo'] = ($this->_currentMethod->test_mode == 'yes' ? $this->_currentMethod->test_prefix : '') . $order['details']['BT']->order_number;
                $oPayment->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
                $oPayment->Send['TotalAmount'] = (int) $order['details']['BT']->order_total;
                $oPayment->Send['TradeDesc'] = "Allpay_VirtueMart_Plugin";
                $oPayment->Send['ChoosePayment'] = PaymentMethod::Tenpay;
                $oPayment->Send['Remark'] = '';
                $oPayment->Send['ChooseSubPayment'] = PaymentMethodItem::None;
                $oPayment->Send['NeedExtraPaidInfo'] = ExtraPaymentInfo::No;
                $oPayment->Send['DeviceSource'] = DeviceType::PC;

                array_push($oPayment->Send['Items'], array(
                    'Name' => vmText::_('VMPAYMENT_TENPAY_ORDER_INFO'),
                    'Price' => $oPayment->Send['TotalAmount'],
                    'Currency' => $oCurrency->getSymbol(),
                    'Quantity' => 1,
                    'URL' => ''
                ));

                $oPayment->SendExtend['ExpireTime'] = date("Y/m/d H:i:s", mktime(date("H"), date("i"), date("s"), date("m"), date("d") + 3, date("Y")));

                $szHtml = '<table class="vmorder-done">' . "\n";
                $szHtml .= $this->getHtmlRow('TENPAY_PAYMENT_METHOD', $this->renderPluginName($this->_currentMethod), "vmorder-done-payinfo");
                $szHtml .= $this->getHtmlRow('TENPAY_ORDER_NUMBER', $order['details']['BT']->order_number, "vmorder-done-nr");
                $szHtml .= $this->getHtmlRow('TENPAY_ORDER_TOTAL', $oCurrency->priceDisplay($order['details']['BT']->order_total), "vmorder-done-amount");
                $szHtml .= $this->getHtmlRow('TENPAY_RESPONSE', vmText::_('VMPAYMENT_TENPAY_RESPONSE_PROCESSING') . $oPayment->CheckOutString('結帳'));
                $szHtml .= '</table>' . "\n";

                JRequest::setVar('html', $szHtml);

                $this->setInConfirmOrder($cart);
                $cart->emptyCart();
            } catch (Exception $e) {
                vmError($e->getMessage());
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Receive and process with response data from AllPay.(必要)
     * @param type $html
     * @return boolean
     */
    function plgVmOnPaymentResponseReceived(&$html) {
        $szStatus = 'P';
        $szMessage = '1|OK';
        // the payment itself should send the parameter needed.
        $nPaymentMethodID = vRequest::getInt('pm', 0);
        $szOrderNumber = vRequest::getString('on', 0);
        $nVirtueMartOrderID = 0;
        $isBackground = vRequest::getBool('bg', 0);

        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        if (!class_exists('shopFunctionsF')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        if (!class_exists('CurrencyDisplay')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        VmConfig::loadJLang('com_virtuemart_orders', TRUE);

        if (!($this->_currentMethod = $this->getVmPluginMethod($nPaymentMethodID))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return NULL;
        }
        if (!($nVirtueMartOrderID = VirtueMartModelOrders::getOrderIdByOrderNumber($szOrderNumber))) {
            return NULL;
        }

        $oPayment = new AllInOne();
        $oPayment->ServiceURL = ($this->_currentMethod->test_mode == 'yes' ? 'https://payment-stage.allpay.com.tw/Cashier/QueryTradeInfo/V2' : 'https://payment.allpay.com.tw/Cashier/QueryTradeInfo/V2');
        $oPayment->HashKey = $this->_currentMethod->hash_key;
        $oPayment->HashIV = $this->_currentMethod->hash_iv;

        try {
            // 取得回傳參數。
            $arFeedback = $oPayment->CheckOutFeedback();
            // 檢核與變更訂單狀態。
            if (sizeof($arFeedback) > 0) {
                $szOrderID = $arFeedback['MerchantTradeNo'];
                $szOrderID = ($this->_currentMethod->test_mode == 'yes' ? str_replace($this->_currentMethod->test_prefix, '', $szOrderID) : $szOrderID);
                $deTradeAmount = $arFeedback['TradeAmt'];
                $szReturnCode = $arFeedback['RtnCode'];
                $szReturnMessgae = $arFeedback['RtnMsg'];
                // 查詢系統訂單。
                //VmConfig::loadJLang('com_virtuemart');
                $modelOrder = VmModel::getModel('orders');
                $oOrder = $modelOrder->getOrder($nVirtueMartOrderID);
                $oCurrency = CurrencyDisplay::getInstance('', $oOrder['details']['BT']->virtuemart_vendor_id);

                $deTotalAmount = (int) $oOrder['details']['BT']->order_total;
                $szOrderStatus = $oOrder['details']['BT']->order_status;
                // 核對訂單金額。
                if ($deTradeAmount == $deTotalAmount) {
                    // 當訂單回傳狀態為無異常，更新訂單資料與新增訂單歷程。
                    if ($szReturnCode == 1 || $szReturnCode == 800) {
                        $szComment = vmText::_('VMPAYMENT_TENPAY_RESPONSE_SUCCESS');
                        // 更新訂單資料與新增訂單歷程。
                        if ($szStatus == $szOrderStatus) {
                            // 新增訂單通知處理歷程，更新訂單狀態。
                            $oOrder['order_status'] = 'U';
                            $oOrder['customer_notified'] = 1;
                            $oOrder['comments'] = $szComment;
                            $modelOrder->updateStatusForOneOrder($nVirtueMartOrderID, $oOrder, TRUE);
                        } else {
                            // 訂單已處理，無須再處理。
                        }

                        $html = '<table class="vmorder-done">' . "\n";
                        $html .= $this->getHtmlRow('TENPAY_PAYMENT_INFO', $this->renderPluginName($this->_currentMethod), "vmorder-done-payinfo");
                        $html .= $this->getHtmlRow('TENPAY_ORDER_NUMBER', $szOrderID, "vmorder-done-nr");
                        $html .= $this->getHtmlRow('TENPAY_ORDER_TOTAL', $oCurrency->priceDisplay($oOrder['details']['BT']->order_total), "vmorder-done-amount");
                        $html .= $this->getHtmlRow('TENPAY_RESPONSE', $szComment . ' ' . vmText::_('VMPAYMENT_TENPAY_RESPONSE_PENDING'));
                        $html .= '</table>' . "\n";
                    } else {
                        throw new Exception("Order '$szOrderID' Exception.($szReturnCode: $szReturnMessgae)");
                    }
                } else {
                    throw new Exception("0|Compare '$szOrderID' Order Amount Fail.");
                }
            } else {
                throw new Exception("Order('$szOrderID') Not Found at AllPay.");
            }
        } catch (Exception $e) {
            // 背景訊息
            $szMessage = '0|' . $e->getMessage();
            // 前景訊息
            $html = '<table class="vmorder-done">' . "\n";
            $html .= $this->getHtmlRow('TENPAY_PAYMENT_INFO', $this->renderPluginName($this->_currentMethod), "vmorder-done-payinfo");
            $html .= $this->getHtmlRow('TENPAY_ORDER_NUMBER', $szOrderNumber, "vmorder-done-nr");
            $html .= $this->getHtmlRow('TENPAY_RESPONSE', vmText::_('VMPAYMENT_TENPAY_RESPONSE_FAIL'));
            $html .= '</table>' . "\n";
            // 顯示錯誤
            vmError($html);
        }
        // 背景顯示訊息
        if ($isBackground) {
            echo $szMessage;
            exit;
        }

        return TRUE;
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method(必要)
     * @param VirtueMartCart $cart
     * @param int $activeMethod
     * @param array $cart_prices
     * @return bool
     */
    protected function checkConditions($cart, $activeMethod, $cart_prices) {
        $this->convert_condition_amount($activeMethod);

        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
        $amount = $this->getCartAmount($cart_prices);
        $amount_cond = ($amount >= $activeMethod->min_amount AND $amount <= $activeMethod->max_amount
                OR ( $activeMethod->min_amount <= $amount AND ( $activeMethod->max_amount == 0)));

        $countries = array();
        if (!empty($activeMethod->countries)) {
            if (!is_array($activeMethod->countries)) {
                $countries[0] = $activeMethod->countries;
            } else {
                $countries = $activeMethod->countries;
            }
        }
        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }
        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            if ($amount_cond) {
                return TRUE;
            }
        }

        return FALSE;
    }

}
