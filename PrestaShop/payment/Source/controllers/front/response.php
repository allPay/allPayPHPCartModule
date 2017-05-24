<?php

class AllpayResponseModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	
	public function postProcess()
	{
		# Return URL log
		$this->module->logAllpayMessage('Process allPay feedback');
		
		# Set the default result message
		$result_message = '1|OK';
		$cart_order_id = null;
		$order = null;
		try
		{
			# Include the allPay integration class
			$invoke_result = $this->module->invokeAllpayModule();
			if (!$invoke_result)
			{
				throw new Exception('allPay module is missing.');
			}
			else
			{
				# Retrieve the checkout result
				$aio = new AllInOne();
				$aio->HashKey = Configuration::get('allpay_hash_key');
				$aio->HashIV = Configuration::get('allpay_hash_iv');
				$allpay_feedback = $aio->CheckOutFeedback();
				unset($aio);
				
				# Process allPay feedback
				if (count($allpay_feedback) < 1)
				{
					throw new Exception('Get allPay feedback failed.');
				}
				else
				{
					# Get the cart order id
					$cart_order_id = $this->module->getCartOrderID($allpay_feedback['MerchantTradeNo'], Configuration::get('allpay_merchant_id'));
					
					# Get the cart order amount
					$order = new Order((int)$cart_order_id);
					$cart_amount = (int)$order->total_paid;
					
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
						
						# Get allPay payment method
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
							case PaymentMethod::Alipay:
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
							// case PaymentMethod::BARCODE:
								// if ($return_code != 1 and $return_code != 800 and $return_code != 10100073)
								// {
									// throw new Exception($fail_message);
								// }
								// else
								// {
									// if ($return_code == 10100073)
									// {
										// $comments .= sprintf(
											// $this->module->l('Payment Deadline : %s, BARCODE 1 : %s, BARCODE 2 : %s, BARCODE 3 : %s, ', 'response')
											// , $allpay_feedback['ExpireDate']
											// , $allpay_feedback['Barcode1']
											// , $allpay_feedback['Barcode2']
											// , $allpay_feedback['Barcode3']
										// );
										// $this->module->setOrderComments($cart_order_id, $comments . $get_code_result_comments);
									// }
									// else
									// {
										// if ($order->current_state != $order_create_status_id)
										// {
											// # The order already paid or not in the standard procedure, do nothing
										// }
										// else
										// {
											// $this->module->setOrderComments($cart_order_id, $payment_result_comments);
											// $this->module->updateOrderStatus($cart_order_id, $succeeded_status_id, true);
										// }
									// }
								// }
								// break;
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
