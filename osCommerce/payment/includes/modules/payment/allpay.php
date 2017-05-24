<?php
	/**
	 * @copyright  Copyright (c) 2015 AllPay (http://www.allpay.com.tw)
	 * @version 1.0.1021
	 * @author Shawn.Chang
	*/
	
	class allpay
	{
		var $code, $title, $description, $enabled;
		
		# Necessary functions
		function allpay()
		{
			$this->code = 'allpay';
      $this->title = MODULE_PAYMENT_ALLPAY_TITLE_TEXT;
      $this->description = MODULE_PAYMENT_ALLPAY_DESC_TEXT;
      $this->enabled = ((MODULE_PAYMENT_ALLPAY_ENABLE_STATUS == 'True') ? true : false);
			
			if ((int)MODULE_PAYMENT_ALLPAY_ORDER_CREATE_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_ALLPAY_ORDER_CREATE_STATUS_ID;
      }
			
			global $order;
      if (is_object($order)) $this->update_status();
			
			$this->form_action_url = tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');
		}

    function install()
		{
			$config_list = $this->get_allpay_config();
			$insert_fields = array(
				'configuration_title'
				, 'configuration_key'
				, 'configuration_value'
				, 'configuration_description'
				, 'configuration_group_id'
				, 'set_function'
				, 'use_function'
			);
			foreach ($config_list as $sort_order => $config_row)
			{
				$insert_columns = 'sort_order, date_added, configuration_group_id';
				$insert_values = '"' . $sort_order++ . '", now(), "6"';
				$comma = ', ';
				foreach ($insert_fields as $insert_field)
				{
					if (isset($config_row[$insert_field]))
					{
						$insert_columns .= $comma . $insert_field;
						$insert_values .= $comma . '"' . $config_row[$insert_field] . '"';
					}
				}
				$insert_sql = 'INSERT INTO ' . TABLE_CONFIGURATION . ' (' . $insert_columns . ')';
				$insert_sql .= ' VALUES (' . $insert_values . ');';
				tep_db_query($insert_sql);
			}
   }

    function remove()
		{
			$delete_sql = 'DELETE FROM ' . TABLE_CONFIGURATION;
			$delete_sql .= ' WHERE configuration_key in ("' . implode('", "', $this->keys()) . '");';
      tep_db_query($delete_sql);
    }
		
		function check()
		{
      if (!isset($this->_check)) {
				$select_sql = 'SELECT configuration_value FROM ' . TABLE_CONFIGURATION;
				$select_sql .= ' WHERE configuration_key = "MODULE_PAYMENT_ALLPAY_ENABLE_STATUS";';
				$check_query = tep_db_query($select_sql);
        $this->_check = tep_db_num_rows($check_query);
      }
			
      return $this->_check;
    }
		
		function keys()
		{
			$config_list = $this->get_allpay_config();
			$keys = array();
			foreach ($config_list as $config_row)
			{
				array_push($keys, $config_row['configuration_key']);
			}
			
			return $keys;
		}
		
		function javascript_validation()
		{
      return false;
    }
		
		function selection()
		{
			$selection = array(
				'id' => $this->code
        , 'module' => $this->title
			);
			
			# Installments javascript
			$js = set_html('<script type="text/javascript">');
			$js .= set_html('  function enable_installments()');
			$js .= set_html('  {');
			$js .= set_html('    var choose_payment = $("select[name=choose_payment]");');
			$js .= set_html('    var choose_installment = $("select[name=choose_installment]");');
			$js .= set_html('    if (choose_payment.val() == "Credit")');
			$js .= set_html('    {');
			$js .= set_html('      choose_installment.removeAttr("disabled");');
			$js .= set_html('    }');
			$js .= set_html('    else');
			$js .= set_html('    {');
			$js .= set_html('      choose_installment[0].selectedIndex = 0;');
			$js .= set_html('      choose_installment.attr("disabled", true);');
			$js .= set_html('    }');
			$js .= set_html('  }');
			$js .= set_html('  $(function() {');
			$js .= set_html('    enable_installments();');
			$js .= set_html('    $("select[name=choose_payment]").change(function(){');
			$js .= set_html('      enable_installments();');
			$js .= set_html('    });');
			$js .= set_html('  });');
			$js .= set_html('</script>');
			
			# Get the available payments
			$selection['fields'] = array();
			array_push(
				$selection['fields']
				, array(
					'title' => MODULE_PAYMENT_ALLPAY_CHOOSE_PAYMENT_TITLE . '&nbsp;:&nbsp;'
					, 'field' => tep_draw_pull_down_menu('choose_payment', $this->get_selection_payments(MODULE_PAYMENT_ALLPAY_AVAILABLE_PAYMENTS)) . $js
				)
			);
			
			# Get the credit installments
			if (MODULE_PAYMENT_ALLPAY_AVAILABLE_INSTALLMENTS)
			{
				array_push(
					$selection['fields']
					, array(
						'title' => MODULE_PAYMENT_ALLPAY_CHOOSE_INSTALLMENT_TITLE . '&nbsp;:&nbsp;'
						, 'field' => tep_draw_pull_down_menu(
							'choose_installment', $this->get_selection_field('0,' . MODULE_PAYMENT_ALLPAY_AVAILABLE_INSTALLMENTS))
					)
				);
			}
			
			return $selection;
    }
		
		function pre_confirmation_check()
		{
      return false;
    }
		
		function confirmation()
		{
			$choose_payment = $this->check_payment($_POST['choose_payment']);
			$confirmation = array(
				'title' => $this->title
				, 'fields' => array()
			);
			array_push(
				$confirmation['fields']
				, array('title' => MODULE_PAYMENT_ALLPAY_CHOOSE_PAYMENT_TITLE . ' : ', 'field' => get_payment_description($choose_payment))
			);
			
			$choose_installment = $this->check_installment($_POST['choose_installment'], $choose_payment);
			if ($choose_installment > 0)
			{
				array_push(
					$confirmation['fields']
					, array('title' => MODULE_PAYMENT_ALLPAY_CHOOSE_INSTALLMENT_TITLE . ' : ', 'field' => $choose_installment)
				);
			}
			
			return $confirmation;
    }
		
		function process_button()
		{
			$choose_payment = $this->check_payment($_POST['choose_payment']);
			$process_button_string = tep_draw_hidden_field('choose_payment', $choose_payment);
			$process_button_string .= tep_draw_hidden_field('choose_installment', $this->check_installment($_POST['choose_installment'], $choose_payment));
			
			return $process_button_string;
		}
		
		function before_process()
		{
			global $order, $merchant_trade_no;
			
			# Set the chosen allPay payment
			$choose_payment = $this->check_payment($_POST['choose_payment']);
			$payment_method = '-' . get_payment_description($choose_payment);
			$choose_installment = (int)($this->check_installment($_POST['choose_installment'], $choose_payment));
			if ($choose_installment > 0)
			{
				$payment_method .= '-' . $choose_installment . MODULE_PAYMENT_ALLPAY_INSTALLMENT;
			}
			$order->info['payment_method'] .= $payment_method;
			
			return false;
		}
		
		function after_process()
		{
			header('Content-Type: text/html; charset=utf-8');
			try
			{
				# Load allPay integration module
				require(DIR_FS_CATALOG . 'ext/modules/payment/allpay/AllPay.Payment.Integration.php');
				
				# Set allPay parameters
				$aio = new AllInOne();
				$aio->Send['MerchantTradeNo'] = '';
				if (MODULE_PAYMENT_ALLPAY_TEST_MODE)
				{
					$service_url = 'https://payment-stage.allpay.com.tw/Cashier/AioCheckOut';
					$aio->Send['MerchantTradeNo'] = date('YmdHis');
				}
				else
				{
					$service_url = 'https://payment.allpay.com.tw/Cashier/AioCheckOut';
				}
				$aio->MerchantID = MODULE_PAYMENT_ALLPAY_MERCHANT_ID;
				$aio->HashKey = MODULE_PAYMENT_ALLPAY_HASH_KEY;
				$aio->HashIV = MODULE_PAYMENT_ALLPAY_HASH_IV;
				$aio->ServiceURL = $service_url;
				$aio->Send['ReturnURL'] = tep_href_link('ext/modules/payment/allpay/response.php', '', 'SSL');
				$aio->Send['ClientBackURL'] = tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL');
				
				global $insert_id;
				$aio->Send['MerchantTradeNo'] .= $insert_id;
				$aio->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
				
				# Set the product info
				global $order;
				$total_price = round($order->info['total']);
				array_push(
					$aio->Send['Items']
					, array(
						'Name' => MODULE_PAYMENT_ALLPAY_PRODUCT_NAME
						, 'Price' => $total_price
						, 'Currency' => $order->info['currency']
						, 'Quantity' => 1
					)
				);
				$aio->Send['TotalAmount'] = $total_price;
				$aio->Send['TradeDesc'] = 'allpay_module_oscommerce_1.1.1021';
				
				# Set the payment
				$choose_payment = $this->check_payment($_POST['choose_payment']);
				$aio->Send['ChoosePayment'] = $choose_payment;
				
				# Set the parameters by payment
				$choose_installment = 0;
				switch ($aio->Send['ChoosePayment'])
				{
					case 'ATM':
						$aio->SendExtend['ExpireDate'] = 3;
						$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
						break;
					case 'CVS':
					// case 'BARCODE':
						$aio->SendExtend['Desc_1'] = '';
						$aio->SendExtend['Desc_2'] = '';
						$aio->SendExtend['Desc_3'] = '';
						$aio->SendExtend['Desc_4'] = '';
						$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
						break;
					case 'Alipay':
						$customer = $order->customer;
						$aio->SendExtend['Email'] = $customer['email_address'];
						$aio->SendExtend['PhoneNo'] = $customer['telephone'];
						$aio->SendExtend['UserName'] = $customer['firstname'] . ' ' . $customer['lastname'];
						break;
					case 'Tenpay':
						$aio->SendExtend['ExpireTime'] = date('Y/m/d H:i:s', strtotime('+3 days'));
						break;
					case 'Credit':
						# Do not support UnionPay
						$aio->SendExtend['UnionPay'] = false;
						
						$choose_installment = $this->check_installment($_POST['choose_installment'], $choose_payment);
						
						# Credit installment parameters
						if ($choose_installment > 0)
						{
							$aio->SendExtend['CreditInstallment'] = $choose_installment;
							$aio->SendExtend['InstallmentAmount'] = $aio->Send['TotalAmount'];
							$aio->SendExtend['Redeem'] = false;
						}
						break;
				}
				
				global $cart;
				$cart->reset(true);
				
				# Unregister session variables used during checkout
				tep_session_unregister('sendto');
				tep_session_unregister('billto');
				tep_session_unregister('shipping');
				tep_session_unregister('payment');
				tep_session_unregister('comments');
				
				$aio->CheckOut();
			}
			catch(Exception $e)
			{
				tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode($e->getMessage()), 'SSL'));
			}
			exit;
		}
		
		
		# Optional functions
		function update_status()
		{
			global $order;
			
			# Check the payment enabled zone
			if ( ($this->enabled == true) and ((int)MODULE_PAYMENT_ALLPAY_PAYMENT_ZONE > 0))
			{
				$check_flag = false;
				$check_sql = 'SELECT `zone_id` FROM `' . TABLE_ZONES_TO_GEO_ZONES . '`';
				$check_sql .= ' WHERE `geo_zone_id` = "' . MODULE_PAYMENT_ALLPAY_PAYMENT_ZONE . '"';
				$check_sql .= ' AND `zone_country_id` = "' . $order->delivery['country']['id'] . '"';
				$check_sql .= ' AND `zone_country_id` = "' . $order->delivery['country']['id'] . '"';
				$check_query = tep_db_query($check_sql);
				while ($check = tep_db_fetch_array($check_query))
				{
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
        }
				
				if (!$check_flag)
				{
          $this->enabled = false;
        }
			}
		}
				
		# Custom functions
		function get_allpay_config()
		{
			# Get the translation
			if (!defined(MODULE_PAYMENT_ALLPAY_TITLE_TEXT))
			{
				global $language;
				include(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/payment/allpay.php');
			}
			
			$require_mark = '<span style=\"color: #F00;\">*</span>';
			$allpay_config = array(
				array(
					'configuration_title' => MODULE_PAYMENT_ALLPAY_ENABLE_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_ENABLE_STATUS'
					, 'configuration_value' => 'True'
					, 'configuration_description' => ''
					, 'set_function' => 'tep_cfg_select_option(array(\'True\', \'False\'), '
				)
				, array(
					'configuration_title' => MODULE_PAYMENT_ALLPAY_TEST_MODE_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_TEST_MODE'
					, 'configuration_value' => 'True'
					, 'configuration_description' => MODULE_PAYMENT_ALLPAY_TEST_MODE_DESC_TEXT
					, 'set_function' => 'tep_cfg_select_option(array(\'True\', \'False\'), '
				)
				, array(
					'configuration_title' => $require_mark . MODULE_PAYMENT_ALLPAY_MERCHANT_ID_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_MERCHANT_ID'
					, 'configuration_value' => ''
					, 'configuration_description' => '(' . MODULE_PAYMENT_ALLPAY_REQUIRE_FIELD_TEXT . ')'
				)
				, array(
					'configuration_title' => $require_mark . MODULE_PAYMENT_ALLPAY_HASH_KEY_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_HASH_KEY'
					, 'configuration_value' => ''
					, 'configuration_description' => '(' . MODULE_PAYMENT_ALLPAY_REQUIRE_FIELD_TEXT . ')'
				)
				, array(
					'configuration_title' => $require_mark . MODULE_PAYMENT_ALLPAY_HASH_IV_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_HASH_IV'
					, 'configuration_value' => ''
					, 'configuration_description' => '(' . MODULE_PAYMENT_ALLPAY_REQUIRE_FIELD_TEXT . ')'
				)
				, array(
					'configuration_title' => MODULE_PAYMENT_ALLPAY_ORDER_CREATE_STATUS_ID_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_ORDER_CREATE_STATUS_ID'
					, 'configuration_value' => ''
					, 'configuration_description' => ''
					, 'set_function' => 'tep_cfg_pull_down_order_statuses('
					, 'use_function' => 'tep_get_order_status_name'
				)
				, array(
					'configuration_title' => MODULE_PAYMENT_ALLPAY_PAID_STATUS_ID_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_PAID_STATUS_ID'
					, 'configuration_value' => ''
					, 'configuration_description' => ''
					, 'set_function' => 'tep_cfg_pull_down_order_statuses('
					, 'use_function' => 'tep_get_order_status_name'
				)
				, array(
					'configuration_title' => MODULE_PAYMENT_ALLPAY_UNPAID_STATUS_ID_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_UNPAID_STATUS_ID'
					, 'configuration_value' => ''
					, 'configuration_description' => ''
					, 'set_function' => 'tep_cfg_pull_down_order_statuses('
					, 'use_function' => 'tep_get_order_status_name'
				)
				, array(
					'configuration_title' => MODULE_PAYMENT_ALLPAY_AVAILABLE_PAYMENTS_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_AVAILABLE_PAYMENTS'
					, 'configuration_value' => ''
					, 'configuration_description' => ''
					, 'use_function' => 'allpay_display_multi_config'
					, 'set_function' => 'allpay_checkbox_payments('
				)
				, array(
					'configuration_title' => MODULE_PAYMENT_ALLPAY_AVAILABLE_INSTALLMENTS_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_AVAILABLE_INSTALLMENTS'
					, 'configuration_value' => ''
					, 'configuration_description' => ''
					, 'use_function' => 'allpay_display_multi_config'
					, 'set_function' => 'allpay_checkbox_installments('
				)
				, array(
					'configuration_title' => MODULE_PAYMENT_ALLPAY_SORT_ORDER_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_SORT_ORDER'
					, 'configuration_value' => ''
					, 'configuration_description' => ''
				)
				, array(
					'configuration_title' => MODULE_PAYMENT_ALLPAY_PAYMENT_ZONE_TEXT
					, 'configuration_key' => 'MODULE_PAYMENT_ALLPAY_PAYMENT_ZONE'
					, 'configuration_value' => ''
					, 'configuration_description' => MODULE_PAYMENT_ALLPAY_PAYMENT_ZONE_DESC_TEXT . '.'
					, 'use_function' => 'tep_get_zone_class_title'
					, 'set_function' => 'tep_cfg_pull_down_zone_classes('
				)
			);
			
			return $allpay_config;
		}
	
		function get_selection_payments($available_payments)
		{
			$payments_array = explode(',', $available_payments);
			$selection_payments = array();
			foreach($payments_array as $payment)
			{
				array_push($selection_payments, array('id' => $payment, 'text' => get_payment_description($payment)));
			}
			
			return $selection_payments;
		}
	
		function get_selection_field($source_string)
		{
			$value_array = explode(',', $source_string);
			$selection_field = array();
			foreach($value_array as $value)
			{
				if ($value != '')
				{
					array_push($selection_field, array('id' => $value, 'text' => $value));
				}
			}
			
			return $selection_field;
		}
	
		function check_payment($choose_payment)
		{
			if (!in_array($choose_payment, get_allpay_payments()))
			{
				$choose_payment = '';
			}
				
			return $choose_payment;
		}
		
		function check_installment($choose_installment, $choose_payment)
		{
			$installment = 0;
			if ($choose_payment == 'Credit')
			{
				if (in_array($choose_installment, get_allpay_credit_installments()))
				{
					$installment = $choose_installment;
				}
			}
			
			return $installment;
		}
	}
	
	# Custom functions
	define('PAYMENT_CHECKBOX_NAME', 'allpay_payments');
	define('PAYMENT_HIDDEN_NAME', 'available_payments');
	define('INSTALLMENT_CHECKBOX_NAME', 'credit_installments');
	define('INSTALLMENT_HIDDEN_NAME', 'available_installments');
	function get_allpay_payments()
	{
		return array(
			'Credit'
			, 'WebATM'
			, 'ATM'
			, 'CVS'
			// , 'BARCODE'
			, 'Alipay'
			, 'Tenpay'
			, 'TopUpUsed'
		);
	}
	
	function get_payment_description($payment)
	{
		$payment_desc = array(
			'Credit' => MODULE_PAYMENT_ALLPAY_CREDIT
			, 'WebATM' => MODULE_PAYMENT_ALLPAY_WEBATM
			, 'ATM' => MODULE_PAYMENT_ALLPAY_ATM
			, 'CVS' => MODULE_PAYMENT_ALLPAY_CVS
			// , 'BARCODE' => MODULE_PAYMENT_ALLPAY_BARCODE
			, 'Alipay' => MODULE_PAYMENT_ALLPAY_ALIPAY
			, 'Tenpay' => MODULE_PAYMENT_ALLPAY_TENPAY
			, 'TopUpUsed' => MODULE_PAYMENT_ALLPAY_TOPUPUSED
		);
		
		return $payment_desc[$payment];
	}
	
	function set_html($html_content)
	{
		return $html_content . "\n";
	}
	
	function allpay_display_multi_config($config_values)
	{
		return nl2br(implode(" / ", explode(',', $config_values)));
	}
	
	function allpay_checkbox_payments($payments, $config_key)
	{
		$payments_list = explode(',', $payments);
		$output = '';
		$allpay_payments = get_allpay_payments();
		foreach($allpay_payments as $payment)
		{
			$tmp_output = tep_draw_checkbox_field(PAYMENT_CHECKBOX_NAME . '[]', $payment, in_array($payment, $payments_list));
			$tmp_output .= '&nbsp;' . tep_output_string(get_payment_description($payment)) . '<br />' . "\n";
			$output .= set_html($tmp_output);
		}
		$output .= tep_draw_hidden_field('configuration[' . $config_key . ']', '', 'id="' . PAYMENT_HIDDEN_NAME . '"');
		$js_function = 'update_payments_config';
		$output .= set_html('<script type="text/javascript">');
		$output .= set_html('  function ' . $js_function . '()');
		$output .= set_html('  {');
		$output .= set_html('    var hidden_value = "";');
		$output .= set_html('    if($("input[name=\'' . PAYMENT_CHECKBOX_NAME . '[]\']").length > 0)');
		$output .= set_html('    {');
		$output .= set_html('      var comma = "";');
		$output .= set_html('      $("input[name=\'' . PAYMENT_CHECKBOX_NAME . '[]\']:checked").each(function() {');
		$output .= set_html('        hidden_value += comma + $(this).attr("value");');
		$output .= set_html('        comma = ",";');
		$output .= set_html('      });');
		$output .= set_html('      $("#' . PAYMENT_HIDDEN_NAME . '").val(hidden_value);');
		$output .= set_html('    }');
		$output .= set_html('    if (hidden_value.indexOf("Credit") < 0)');
		$output .= set_html('    {');
		$output .= set_html('      $("input[name=\'' . INSTALLMENT_CHECKBOX_NAME . '[]\']").each(function() {');
		$output .= set_html('        $(this).prop("checked", false);');
		$output .= set_html('      });');
		$output .= set_html('      $("input[name=\'' . INSTALLMENT_CHECKBOX_NAME . '[]\']").attr("disabled", true);');
		$output .= set_html('      $("input[name=\'' . INSTALLMENT_CHECKBOX_NAME . '[]\']").trigger("change")');
		$output .= set_html('    }');
		$output .= set_html('    else');
		$output .= set_html('    {');
		$output .= set_html('      $("input[name=\'' . INSTALLMENT_CHECKBOX_NAME . '[]\']").removeAttr("disabled")');
		$output .= set_html('    }');
		$output .= set_html('  }');
		$output .= get_intergrate_checkbox_js($js_function, PAYMENT_CHECKBOX_NAME);
		$output .= set_html('</script>');
		
		return $output;
	}
	

	function get_allpay_credit_installments()
	{
		return array('3', '6', '12', '18', '24');
	}
	
	function allpay_checkbox_installments($installments, $config_key)
	{
		$installments_list = explode(',', $installments);
		$output = '';
		$credit_installments = get_allpay_credit_installments();
		foreach($credit_installments as $installment)
		{
			$tmp_output = tep_draw_checkbox_field(INSTALLMENT_CHECKBOX_NAME . '[]', $installment, in_array($installment, $installments_list));
			$tmp_output .= '&nbsp;' . tep_output_string($installment) . '<br />' . "\n";
			$output .= set_html($tmp_output);
		}
		$output .= tep_draw_hidden_field('configuration[' . $config_key . ']', '', 'id="' . INSTALLMENT_HIDDEN_NAME . '"');
		$js_function = 'update_installments_config';
		$output .= set_html('<script type="text/javascript">');
		$output .= set_html('  function ' . $js_function . '()');
		$output .= set_html('  {');
		$output .= set_html('    if($("input[name=\'' . INSTALLMENT_CHECKBOX_NAME . '[]\']").length > 0)');
		$output .= set_html('    {');
		$output .= set_html('      var hidden_value = "";');
		$output .= set_html('      var comma = "";');
		$output .= set_html('      $("input[name=\'' . INSTALLMENT_CHECKBOX_NAME . '[]\']:checked").each(function() {');
		$output .= set_html('        hidden_value += comma + $(this).attr("value");');
		$output .= set_html('        comma = ",";');
		$output .= set_html('      });');
		$output .= set_html('      $("#' . INSTALLMENT_HIDDEN_NAME . '").val(hidden_value);');
		$output .= set_html('    }');
		$output .= set_html('  }');
		$output .= get_intergrate_checkbox_js($js_function, INSTALLMENT_CHECKBOX_NAME);
		$output .= set_html('</script>');
		
		return $output;
	}
	
	function get_intergrate_checkbox_js($js_function, $checkbox_name)
	{
		$output .= set_html('  $(function() {');
		$output .= set_html('    ' . $js_function . '();');
		$output .= set_html('    $("input[name=\'' . $checkbox_name . '[]\']").change(function() {');
		$output .= set_html('      ' . $js_function . '();');
		$output .= set_html('    });');
		$output .= set_html('  });');
		
		return $output;
	}
	
?>
