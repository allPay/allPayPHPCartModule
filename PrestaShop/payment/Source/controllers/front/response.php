<?php

class OpayResponseModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    
    public function postProcess()
    {
        # Return URL log
        $this->module->logAllpayMessage('Process O\'Pay feedback');
        
        # Set the default result message
        $result_message = '1|OK';
        $cart_order_id = null;
        $order = null;
        try
        {
            # Include the O'Pay integration class
            $invoke_result = $this->module->invokeAllpayModule();
            if (!$invoke_result)
            {
                throw new Exception('O\'Pay module is missing.');
            }
            else
            {
                # Retrieve the checkout result
                $aio = new AllInOne();
                $aio->HashKey = Configuration::get('opay_hash_key');
                $aio->HashIV = Configuration::get('opay_hash_iv');
                $aio->EncryptType = EncryptType::ENC_SHA256;
                $allpay_feedback = $aio->CheckOutFeedback();
                unset($aio);
                
                # Process O\'Pay feedback
                if (count($allpay_feedback) < 1)
                {
                    throw new Exception('Get O\'Pay feedback failed.');
                }
                else
                {
                    # Get the cart order id
                    $cart_order_id = $this->module->getCartOrderID($allpay_feedback['MerchantTradeNo'], Configuration::get('opay_merchant_id'));
                    
                    # Get the cart order amount
                    $order = new Order((int)$cart_order_id);
                    $cart_amount = $this->module->formatOrderTotal($order->total_paid);
                    
                    # Check the amounts
                    $allpay_amount = $allpay_feedback['TradeAmt'];
                    if ($cart_amount != $allpay_amount)
                    {
                        throw new Exception(sprintf('Order %s amount are not identical.', $cart_order_id));
                    }
                    else
                    {
                        # Set the common comments
                        $comments = sprintf(
                            $this->module->l('Payment Method : %s, Trade Time : %s, ',  'response')
                            , $allpay_feedback['PaymentType']
                            , $allpay_feedback['TradeDate']
                        );
                        
                        # Set the getting code comments
                        $return_message = $allpay_feedback['RtnMsg'];
                        $return_code = $allpay_feedback['RtnCode'];
                        $get_code_result_comments = sprintf(
                            $this->module->l('Getting Code Result : (%s)%s', 'response')
                            , $return_code
                            , $return_message
                        );
                        
                        # Set the payment result comments
                        $payment_result_comments = sprintf(
                            $this->module->l('Payment Result : (%s)%s', 'response')
                            , $return_code
                            , $return_message
                        );
                        
                        # Get O'Pay payment method
                        $type_pieces = explode('_', $allpay_feedback['PaymentType']);
                        $allpay_payment_method = $type_pieces[0];
                        
                        # Update the order status and comments
                        $fail_message = sprintf('Order %s Exception.(%s: %s)', $cart_order_id, $return_code, $return_message);
                        $created_status_id = $this->module->getOrderStatusID('created');
                        $succeeded_status_id = $this->module->getOrderStatusID('succeeded');
                        $order_current_status = (int)$order->getCurrentState();
                        switch($allpay_payment_method)
                        {
                            case PaymentMethod::Credit:
                            case PaymentMethod::WebATM:
                            case PaymentMethod::Tenpay:
                            case PaymentMethod::TopUpUsed:
                                if ($return_code != 1 and $return_code != 800)
                                {
                                    throw new Exception($fail_message);
                                }
                                else
                                {
                                    if ($order_current_status != $created_status_id)
                                    {
                                        # The order already paid or not in the standard procedure, do nothing
                                    }
                                    else
                                    {
                                        $this->module->setOrderComments($cart_order_id, $payment_result_comments);
                                        $this->module->updateOrderStatus($cart_order_id, $succeeded_status_id, true);
                                    }
                                }
                                break;
                            case PaymentMethod::ATM:
                                if ($return_code != 1 and $return_code != 2 and $return_code != 800)
                                {
                                    throw new Exception($fail_message);
                                }
                                else
                                {
                                    if ($return_code == 2)
                                    {
                                        # Set the getting code result
                                        $comments .= sprintf(
                                            $this->module->l('Bank Code : %s, Virtual Account : %s, Payment Deadline : %s, ', 'response')
                                            , $allpay_feedback['BankCode']
                                            , $allpay_feedback['vAccount']
                                            , $allpay_feedback['ExpireDate']
                                        );
                                        $this->module->setOrderComments($cart_order_id, $comments . $get_code_result_comments);
                                    }
                                    else
                                    {
                                        if ($order_current_status != $created_status_id)
                                        {
                                            # The order already paid or not in the standard procedure, do nothing
                                        }
                                        else
                                        {
                                            $this->module->setOrderComments($cart_order_id, $payment_result_comments);
                                            $this->module->updateOrderStatus($cart_order_id, $succeeded_status_id, true);
                                        }
                                    }
                                }
                                break;
                            case PaymentMethod::CVS:
                                if ($return_code != 1 and $return_code != 800 and $return_code != 10100073)
                                {
                                    throw new Exception($fail_message);
                                }
                                else
                                {
                                    if ($return_code == 10100073)
                                    {
                                        $comments .= sprintf(
                                            $this->module->l('Trade Code : %s, Payment Deadline : %s, ', 'response')
                                            , $allpay_feedback['PaymentNo']
                                            , $allpay_feedback['ExpireDate']
                                        );
                                        $this->module->setOrderComments($cart_order_id, $comments . $get_code_result_comments);
                                    }
                                    else
                                    {
                                        if ($order_current_status != $created_status_id)
                                        {
                                            # The order already paid or not in the standard procedure, do nothing
                                        }
                                        else
                                        {
                                            $this->module->setOrderComments($cart_order_id, $payment_result_comments);
                                            $this->module->updateOrderStatus($cart_order_id, $succeeded_status_id, true);
                                        }
                                    }
                                }
                                break;
                            default:
                                throw new Exception(sprintf('Order %s, payment method is invalid.', $cart_order_id));
                                break;
                        }
                    }
                }
            }
        }
        catch(Exception $e)
        {
            $error = $e->getMessage();
            if (!empty($order))
            {
                $failed_status_id = $this->module->getOrderStatusID('failed');
                $comments = sprintf($this->module->l('Paid Failed, Error : %s', 'response'), $error);
                $this->module->setOrderComments($cart_order_id, $comments);
                $this->module->updateOrderStatus($cart_order_id, $failed_status_id, true);
            }
            
            # Set the failure result
            $result_message = '0|' . $error;
        }
        
        # Return URL log
        $this->module->logAllpayMessage('Order ' . $cart_order_id . ' process result : ' . $result_message, true);
        
        echo $result_message;
        exit;
    }
}
