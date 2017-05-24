<?php
	defined('_JEXEC') or die('Restricted access');
	
	class plgHikashoppaymentallpay_credit extends hikashopPaymentPlugin {
		var $accepted_currencies = array('TWD');
        
		var $multiple = true;
		var $name = 'allpay_credit';
		var $pluginConfig = array(
			'allpay_merchant_id' => array('商店代號', 'input'),
			'allpay_hash_key' => array('HashKey', 'input'),
			'allpay_hash_iv' => array('HashIV', 'input'),
			'allpay_created_status' => array('訂單建立狀態', 'orderstatus'),
			'allpay_succeed_status' => array('付款成功狀態', 'orderstatus'),
			'allpay_failed_status' => array('付款失敗狀態', 'orderstatus')
		);
        
        function getPaymentDefaultValues(&$element) {
            $element->payment_name = '[歐付寶]信用卡(一次付清)';
            $element->payment_description = '您可透過此付款方式進行信用卡(一次付清)付款';
            $element->payment_params->allpay_merchant_id = '2000132';
            $element->payment_params->allpay_hash_key = '5294y06JbISpM5x9';
            $element->payment_params->allpay_hash_iv = 'v77hoKGq4kWxNNIS';
            $element->payment_params->allpay_created_status = 'created';
            $element->payment_params->allpay_succeed_status = 'confirmed';
            $element->payment_params->allpay_failed_status = 'cancelled';
        }
        
        function onPaymentConfiguration(&$element) {
            parent::onPaymentConfiguration($element);
            $app = JFactory::getApplication();
            
            if (empty($element->payment_params->allpay_merchant_id)) {
                $app->enqueueMessage('商店代號不可為空值!', 'error');
                return false;
            }
            
            if (empty($element->payment_params->allpay_hash_key)) {
                $app->enqueueMessage('HashKey不可為空值!', 'error');
                return false;
            }
            
            if (empty($element->payment_params->allpay_hash_iv)) {
                $app->enqueueMessage('HashIV不可為空值!', 'error');
                return false;
            }
            
        }
        
        function onPaymentNotification(&$statuses) {
            $this->invokeExt(JPATH_PLUGINS . '/hikashoppayment/' . $this->name . '/');
            $this->writeToLog(LogMsg::RESP_DES);
            $this->writeToLog('_POST:' . "\n" . print_r($_POST, true));
            
            $filter = JFilterInput::getInstance();
            $ignore_params = array('hikashop_payment_notification_plugin');
            foreach ($_POST as $key => $value) {
                if (in_array($key, $ignore_params)) {
                    unset($_POST[$key]);
                } else {
                    $key = $filter->clean($key);
                    $value = JRequest::getString($key);
                    $_POST[$key] = $value;
                }
            }
            
            $history = new stdClass();
            $history->notified = 1;
            $history->amount = 0;
            $history->data = '';
            $res_msg = '1|OK';
            $cart_order_id = '';
            $update_status = null;
            try {
                $allpay_params_str = $this->loadAllpayPaymentParams();
                $allpay_params = @unserialize($allpay_params_str);
                $merchant_id = $allpay_params->allpay_merchant_id;
                $hash_key = $allpay_params->allpay_hash_key;
                $hash_iv = $allpay_params->allpay_hash_iv;
                $created_status = $allpay_params->allpay_created_status;
                $succeed_status = $allpay_params->allpay_succeed_status;
                $failed_status = $allpay_params->allpay_failed_status;
                
                $AIO = new AllInOne();
                $ACE = new AllpayCartExt($merchant_id);
                
                $AIO->MerchantID = $merchant_id;
                $AIO->HashKey = $hash_key;
                $AIO->HashIV = $hash_iv;
                $checkout_feedback = $AIO->CheckOutFeedback();
                if (empty($checkout_feedback)) {
                    throw new Exception(ErrorMsg::C_FD_EMPTY);
                }
                $rtn_code = $checkout_feedback['RtnCode'];
                $rtn_msg = $checkout_feedback['RtnMsg'];
                $payment_method = $ACE->parsePayment($checkout_feedback['PaymentType']);
                $merchant_trade_no = $checkout_feedback['MerchantTradeNo'];
                $cart_order_number = $ACE->getCartOrderID($merchant_trade_no);
                $cart_order_id = $this->getAllpayOrderID($cart_order_number);
                $order_info = $this->getOrder((int)@$cart_order_id);
                $cart_order_total = $ACE->roundAmt($order_info->order_full_price);
                $history->amount = $cart_order_total;
                
                $AIO->ServiceURL = $ACE->getServiceURL(URLType::QUERY_ORDER);
                $AIO->Query['MerchantTradeNo'] = $merchant_trade_no;
                $query_feedback = $AIO->QueryTradeInfo();
                if (empty($query_feedback)) {
                    throw new Exception(ErrorMsg::Q_FD_EMPTY);
                }
                $query_trade_amount = $query_feedback['TradeAmt'];
                $query_payment_type = $query_feedback['PaymentType'];
                
                $ACE->validAmount($cart_order_total, $checkout_feedback['TradeAmt'], $query_trade_amount);
                $query_payment = $ACE->parsePayment($query_payment_type);
                $ACE->validPayment($payment_method, $query_payment);
                $ACE->validStatus($created_status, $order_info->order_status);
                
                $comment_tpl = $ACE->getCommentTpl($payment_method, $rtn_code);
                $history->data = $ACE->getComment($payment_method, $comment_tpl, $checkout_feedback);
                
                $is_getcode = $ACE->isGetCode($payment_method, $rtn_code);
                $is_paid = $ACE->isPaid($rtn_code);
                if ($is_getcode) {
                    $update_status = null;
                    $history->notified = 0;
                } else {
                    if ($is_paid) {
                        $update_status = $succeed_status;
                    } else {
                        $update_status = $failed_status;
                    }
                }
            } catch (Exception $e) {
                $exception_msg = $e->getMessage();
                $res_msg = '0|' . $exception_msg;
                $update_status = null;
                if (!empty($ACE)) {
                    $fail_tpl = $ACE->getTpl('fail');
                    $history->data = $ACE->getFailComment($exception_msg, $fail_tpl);
                }
            }
            
            $this->modifyOrder($cart_order_id, $update_status, $history, null, $allpay_params_str);
            
            echo $res_msg;
            exit;
        }
        
		function onAfterOrderConfirm(&$order,&$methods,$method_id) {
            parent::onAfterOrderConfirm($order, $methods, $method_id);
            
            $order_id = $order->order_number;
            $payment_type = $order->order_payment_method;
            $hikashop_checkour_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout';
            $return_url = $hikashop_checkour_url . '&task=notify&notif_payment=' . $this->name . '&tmpl=component&lang=' . $this->locale . $this->url_itemid;
            $client_back_url = $hikashop_checkour_url . '&task=after_end&order_id=' . $order->order_id . $this->url_itemid;
            
            $this->invokeExt(JPATH_PLUGINS . '/hikashoppayment/' . $this->name . '/');
            $merchant_id = $this->payment_params->allpay_merchant_id;
            $choose_installment = 0;
            try {
                $AIO = new AllInOne();
                $ACE = new AllpayCartExt($merchant_id);
                
                $service_url = $ACE->getServiceURL(URLType::CREATE_ORDER);
                $merchant_trade_no = $ACE->getMerchantTradeNo($order_id);
                $order_total = $ACE->roundAmt($order->cart->full_total->prices[0]->price_value_with_tax);
                
                $AIO->MerchantID = $merchant_id;
                $AIO->ServiceURL = $service_url;
                $AIO->Send['MerchantTradeNo'] = $merchant_trade_no;
                $AIO->HashKey = $this->payment_params->allpay_hash_key;
                $AIO->HashIV = $this->payment_params->allpay_hash_iv;
                $AIO->Send['ReturnURL'] = $return_url;
                $AIO->Send['ClientBackURL'] = $client_back_url;
                $AIO->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
                $AIO->Send['TradeDesc'] = 'allpay_module_hikashop_1_0_1';
                $AIO->Send['TotalAmount'] = $order_total;
                $AIO->Send['Items'] = array();
                array_push(
                    $AIO->Send['Items'],
                    array(
                        'Name' => '網路商品一批',
                        'Price' => $order_total,
                        'Currency' => $this->currency->currency_code,
                        'Quantity' => 1,
                        'URL' => ''
                    )
                );
                $type_pieces = explode('_', $payment_type);
                $AIO->Send['ChoosePayment'] = $ACE->getPayment($type_pieces[1]);
                if (isset($type_pieces[2])) {
                    $choose_installment = $type_pieces[2];
                }
                $params = array(
                    'Installment' => $choose_installment,
                    'TotalAmount' => $AIO->Send['TotalAmount'],
                    'ReturnURL' => $AIO->Send['ReturnURL']
                );
                $AIO->SendExtend = $ACE->setSendExt($AIO->Send['ChoosePayment'], $params);
                
                $red_html = $AIO->CheckOut(null);
                echo $red_html;
                exit;
            } catch(Exception $e) {
                $app = JFactory::getApplication();
                $app->enqueueMessage($e->getMessage(), 'error');
				return false;
            }
		}
	
        private function invokeExt($ext_dir) {
            $sdk_res = include_once($ext_dir . 'AllPay.Payment.Integration.php');
            $ext_res = include_once($ext_dir . 'allpay_cart_ext.php');
            return ($sdk_res and $ext_res);
        }
    
        private function loadAllpayPaymentParams() {
            $name = $this->name;
            $payment_params = '';
            $db = JFactory::getDBO();
            $where = array('payment_type=' . $db->Quote($name), 'payment_published="1"');

            $app = JFactory::getApplication();
            if (!$app->isAdmin()){
                hikashop_addACLFilters($where, 'payment_access');
            }

            $where = ' WHERE '.implode(' AND ', $where);
            
            $db->setQuery('SELECT payment_params FROM `#__hikashop_payment`' . $where . ' ORDER BY payment_ordering');
            $db_result = $db->loadObjectList();
            $payment_params = $db_result[0]->payment_params;

            return $payment_params;
        }
    
        private function getAllpayOrderID($order_number) {
            $name = $this->name;
            $allpay_order_id = '';
            $db = JFactory::getDBO();
            $where = array('order_payment_method=' . $db->Quote($name), 'order_number=' . $db->Quote($order_number));

            $app = JFactory::getApplication();
            if (!$app->isAdmin()){
                hikashop_addACLFilters($where, 'payment_access');
            }

            $where = ' WHERE '.implode(' AND ', $where);
            $db->setQuery('SELECT order_id FROM `#__hikashop_order`'.$where.' limit 1');
            $db_result = $db->loadObjectList();
            $allpay_order_id = $db_result[0]->order_id;
            
            return $allpay_order_id;
        }
    }
?>
