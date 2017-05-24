<?php
	/**
	 * @copyright  Copyright (c) 2015 AllPay (http://www.allpay.com.tw)
	 * @version 1.0.1021
	 * @author Shawn.Chang
	*/
	
	chdir('../../../../');
	require('includes/application_top.php');
	
	function add_comments($order_id, $status, $comments)
	{
		$sql_data_array = array(
			'orders_id' => (int)$order_id
			, 'orders_status_id' => $status
			, 'date_added' => 'now()'
			, 'customer_notified' => '0'
			, 'comments' => $comments
		);
		
		return tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
	}
	
	function update_order_status($order_id, $status, $comments)
	{
		$sql_data_array = array(
			'orders_status' => $status
			, 'last_modified' => 'now()'
		);
		$sql_condition = 'orders_id = \'' . (int)$order_id . '\'';
		
		if (!tep_db_perform(TABLE_ORDERS, $sql_data_array, 'update', $sql_condition))
		{
			return false;
		}
		else
		{
			return add_comments($order_id, $status, $comments);
		}
	}
	
	function query_column($sql, $column_name)
	{
		$query = tep_db_query($sql);
		if (tep_db_num_rows($query) > 0)
		{
			$fetch_array = tep_db_fetch_array($query);
		}
		tep_db_free_result($query);
		
		return $fetch_array[$column_name];
	}
				
	# Get the translation
	if (!defined(MODULE_PAYMENT_ALLPAY_TITLE_TEXT))
	{
		global $language;
		include(DIR_WS_LANGUAGES . $language . '/modules/payment/allpay.php');
	}
	
	try
	{
		# Load allPay integration module
		require('AllPay.Payment.Integration.php');
		
		# Set the parameters
		$aio = new AllInOne();
		$aio->HashKey = MODULE_PAYMENT_ALLPAY_HASH_KEY;
		$aio->HashIV = MODULE_PAYMENT_ALLPAY_HASH_IV;
		$aio->MerchantID = MODULE_PAYMENT_ALLPAY_MERCHANT_ID;
		
		# Retrieve the check out result
		$allpay_result = $aio->CheckOutFeedback();
		unset($aio);
		
		if(count($allpay_result) < 1)
		{
			throw new Exception('Get allPay feedback failed.');
		}
		else
		{
			# Get osCommerce order id
			$osc_order_id = $allpay_result['MerchantTradeNo'];
			if (MODULE_PAYMENT_ALLPAY_TEST_MODE)
			{
				$osc_order_id = substr($osc_order_id, 14);
			}
			
			# Get osCommerce order
			require(DIR_WS_CLASSES . 'order.php');
			$order = new order($osc_order_id);
			list($osc_currency, $osc_amount) = explode('$', $order->info['total']);
			
			# Get the order status
			global $languages_id;
			$osc_order_status_id = 0;
			$order_status_sql = 'SELECT `orders_status_id` FROM `' . TABLE_ORDERS_STATUS . '`';
			$order_status_sql .= ' WHERE `orders_status_name` = "' . $order->info['orders_status'] . '"';
			$order_status_sql .= ' AND `language_id` = ' . $languages_id . ';';
			$osc_order_status_id = (int)query_column($order_status_sql, 'orders_status_id');
			unset($order);
			
			# Check the amount
			$allpay_amount = $allpay_result['TradeAmt'];
			if (round($osc_amount) != $allpay_amount)
			{
				throw new Exception('Order ' . $osc_order_id . ' amount are not identical.');
			}
			else
			{
				$success_msg = '1|OK';
				
				# Set the common comments
				$comments = sprintf(
					MODULE_PAYMENT_ALLPAY_COMMON_COMMENTS
					, $allpay_result['PaymentType']
					, $allpay_result['TradeDate']
				);
				
				# Set the getting code comments
				$return_code = $allpay_result['RtnCode'];
				$return_message = $allpay_result['RtnMsg'];
				$get_code_result_comments = sprintf(
					MODULE_PAYMENT_ALLPAY_GET_CODE_RESULT_COMMENTS
					, $return_code
					, $return_message
				);
				
				# Set the payment result comments
				$payment_result_comments = sprintf(
					MODULE_PAYMENT_ALLPAY_PAYMENT_RESULT_COMMENTS
					, $return_code
					, $return_message
				);
				
				# Get allPay payment and payment target
				list($allpay_payment_method, $allpay_payment_target) = explode('_', $allpay_result['PaymentType']);
				switch ($allpay_payment_method)
				{
					case 'Credit':
					case 'WebATM':
					case 'Alipay':
					case 'Tenpay':
					case 'TopUpUsed':
						if ($return_code != 1 and $return_code != 800)
						{
							throw new Exception('Order ' . $osc_order_id . ' Exception.(' . $return_code . ': ' . $allpay_result['RtnMsg'] . ')');
						}
						else
						{
							# Only finish the order when the status is processing
							if ($osc_order_status_id != MODULE_PAYMENT_ALLPAY_ORDER_CREATE_STATUS_ID)
							{
								# The order already paid or not in the standard procedure, do nothing
							}
							else
							{
								update_order_status(
									$osc_order_id
									, MODULE_PAYMENT_ALLPAY_PAID_STATUS_ID
									, $payment_result_comments
								);
							}
							
							echo $success_msg;
						}
						break;
					case 'ATM':
						if ($return_code != 1 and $return_code != 2 and $return_code != 800)
						{
							throw new Exception('Order ' . $osc_order_id . ' Exception.(' . $return_code . ': ' . $allpay_result['RtnMsg'] . ')');
						}
						else
						{
							# Set the extra payment info
							if ($return_code == 2)
							{
								$comments .= sprintf(
									MODULE_PAYMENT_ALLPAY_ATM_COMMENTS
									, $allpay_result['BankCode']
									, $allpay_result['vAccount']
									, $allpay_result['ExpireDate']
								);
								$comments .= $get_code_result_comments;
								update_order_status($osc_order_id, $osc_order_status_id, $comments);
							}
							else
							{
								# Only finish the order when the status is processing
								if ($osc_order_status_id != MODULE_PAYMENT_ALLPAY_ORDER_CREATE_STATUS_ID)
								{
									# The order already paid or not in the standard procedure, do nothing
								}
								else
								{
									update_order_status(
										$osc_order_id
										, MODULE_PAYMENT_ALLPAY_PAID_STATUS_ID
										, $payment_result_comments
									);
								}
							}
							
							echo $success_msg;
						}
						break;
					case 'CVS':
						if ($return_code != 1 and $return_code != 800 and $return_code != 10100073)
						{
							throw new Exception('Order ' . $osc_order_id . ' Exception.(' . $return_code . ': ' . $allpay_result['RtnMsg'] . ')');
						}
						else
						{
							if ($return_code == 10100073)
							{
								# Set the extra payment info
								$comments .= sprintf(
									MODULE_PAYMENT_ALLPAY_CVS_COMMENTS
									, $allpay_result['PaymentNo']
									, $allpay_result['ExpireDate']
								);
								$comments .= $get_code_result_comments;
								update_order_status($osc_order_id, $osc_order_status_id, $comments);
							}
							else
							{
								# Only finish the order when the status is processing
								if ($osc_order_status_id != MODULE_PAYMENT_ALLPAY_ORDER_CREATE_STATUS_ID)
								{
									# The order already paid or not in the standard procedure, do nothing
								}
								else
								{
									update_order_status(
										$osc_order_id
										, MODULE_PAYMENT_ALLPAY_PAID_STATUS_ID
										, $payment_result_comments
									);
								}
							}
							
							echo $success_msg;
						}
						break;
					/* case 'BARCODE':
						if ($return_code != 1 and $return_code != 800 and $return_code != 10100073)
						{
							throw new Exception('Order ' . $osc_order_id . ' Exception.(' . $return_code . ': ' . $allpay_result['RtnMsg'] . ')');
						}
						else
						{
							if ($return_code == 10100073)
							{
								# Set the extra payment info
								$comments .= sprintf(
									MODULE_PAYMENT_ALLPAY_BARCODE_COMMENTS
									, $allpay_result['ExpireDate']
									, $allpay_result['Barcode1']
									, $allpay_result['Barcode2']
									, $allpay_result['Barcode3']
								);
								$comments .= $get_code_result_comments;
								update_order_status($osc_order_id, $osc_order_status_id, $comments);
							}
							else
							{
								# Only finish the order when the status is processing
								if ($osc_order_status_id != MODULE_PAYMENT_ALLPAY_ORDER_CREATE_STATUS_ID)
								{
									# The order already paid or not in the standard procedure, do nothing
								}
								else
								{
									update_order_status(
										$osc_order_id
										, MODULE_PAYMENT_ALLPAY_PAID_STATUS_ID
										, $payment_result_comments
									);
								}
							}
							
							echo $success_msg;
						}
						break; */
					default:
						throw new Exception('Invalid payment method of the order ' . $osc_order_id . '.');
						break;
				}
			}
		}
	}
	catch(Exception $e)
	{
		if (isset($osc_order_id))
		{
			update_order_status($osc_order_id, MODULE_PAYMENT_ALLPAY_UNPAID_STATUS_ID, MODULE_PAYMENT_ALLPAY_FAILED_COMMENTS);
		}
		echo '0|' . $e->getMessage();
	}
?>
