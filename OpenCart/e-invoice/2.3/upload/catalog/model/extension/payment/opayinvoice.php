<?php
class ModelExtensionPaymentOpayinvoice extends Model {
	public function getMethod($address, $total)
	{
		$method_data = array();
		return $method_data;
	}
	
	// 判斷電子發票啟用狀態
	public function get_invoice_status()
	{
		$nInvoice_Status = $this->config->get('opayinvoice_status');
		return $nInvoice_Status;
	}
	
	// 判斷電子發票是否啟動自動開立
	public function get_invoice_autoissue()
	{
		$nInvoice_Autoissue = $this->config->get('opayinvoice_autoissue');
		return $nInvoice_Autoissue;
	}
	
	// 判斷電子發票SDK是否存在
	public function check_invoice_sdk()
	{
		$sFile_Name =  dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'controller'.DIRECTORY_SEPARATOR.'extension'.DIRECTORY_SEPARATOR.'payment'.DIRECTORY_SEPARATOR.'AllPay_Invoice.php' ;
		return (file_exists($sFile_Name)) ? $sFile_Name : false ;	
	}
	
	
	// 自動開立發票
	public function createInvoiceNo($order_id = 0, $sCheck_Invoice_SDK)
	{	
		// 1.參數初始化
		define('WEB_MESSAGE_NEW_LINE',	'|');		// 前端頁面訊息顯示換行標示語法

		$sMsg				= '' ;
		$sMsg_P2 			= '' ;		// 金額有差異提醒
		$bError 			= false ; 	// 判斷各參數是否有錯誤，沒有錯誤才可以開發票
		
		// 2.取出開立相關參數
		
		// *連線資訊
		$sOpayinvoice_Url_Issue	= $this->config->get('opayinvoice_url');			// 一般開立網址
		$nOpayinvoice_Mid 		= $this->config->get('opayinvoice_mid') ;			// 廠商代號
		$sOpayinvoice_Hashkey 	= $this->config->get('opayinvoice_hashkey');			// 金鑰
		$sOpayinvoice_Hashiv 		= $this->config->get('opayinvoice_hashiv') ;			// 向量
		
		// *訂單資訊
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'" );
		$aOrder_Info_Tmp = $query->rows[0] ;
		
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "'" );
		$aOrder_Product_Tmp = $query->rows ;
		
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "'" );
		$aOrder_Total_Tmp = $query->rows ;

		// *統編與愛心碼資訊
		$query_invoice = $this->db->query("SELECT * FROM invoice_info WHERE order_id = '" . (int)$order_id . "'" );
		
		
		// 3.判斷資料正確性

		// *URL判斷是否有值
		if($sOpayinvoice_Url_Issue == '')
		{
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '請填寫發票傳送網址。';
		}
		
		// *MID判斷是否有值
		if($nOpayinvoice_Mid == '')
		{
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '請填寫商店代號(Merchant ID)。';
		}
		
		// *HASHKEY判斷是否有值
		if($sOpayinvoice_Hashkey == '')
		{
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '請填寫金鑰(Hash Key)。';
		}
		
		// *HASHIV判斷是否有值
		if($sOpayinvoice_Hashiv == '')
		{
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '請填寫向量(Hash IV)。';
		}
		
		
		// 判斷是否開過發票
		if($aOrder_Info_Tmp['invoice_no'] != '0' )
		{
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '已存在發票紀錄，無法再次開立。';
		}
		
		// 開立發票資訊
		if( $query_invoice->num_rows == 0 )
		{
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '開立發票資訊不存在。';
		}
		else
		{
			$aInvoice_Info = $query_invoice->rows[0] ;
		}

		// 判斷商品是否存在
		if(count($aOrder_Product_Tmp) < 0)
		{
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . ' 該訂單編號不存在商品，不允許開立發票。';
		}
		else
		{
			// 判斷商品是否含小數點
			foreach( $aOrder_Product_Tmp as $key => $value)
			{
				if ( !strstr($value['price'], '.00') )
				{
					$sMsg_P2 .= ( empty($sMsg_P2) ? '' : WEB_MESSAGE_NEW_LINE ) . '提醒：商品 ' . $value['name'] . ' 金額存在小數點，將以無條件進位開立發票。';
				}
			}
		}
		
		if(!$bError)
		{
			
			$sLove_Code 			= '' ;
			$nDonation			= '2' ;
			$nPrint				= '0' ;
			$sCustomerIdentifier		= '' ;
			
			if($aInvoice_Info['invoice_type'] == 1)
			{
				$nDonation 		= '2' ;					// 不捐贈
				$nPrint			= '0' ;
				$sCustomerIdentifier	= '' ;
			}
			elseif($aInvoice_Info['invoice_type'] == 2)
			{
				$nDonation 		= '2' ;					// 公司發票 不捐贈
				$nPrint			= '1' ;					// 公司發票 強制列印
				$sCustomerIdentifier	= $aInvoice_Info['company_write'] ;	// 公司統一編號
			}
			elseif($aInvoice_Info['invoice_type'] == 3)
			{
				$nDonation 		= '1' ;
				$nPrint			= '0' ;
				$sLove_Code 		= $aInvoice_Info['love_code'] ;
				$sCustomerIdentifier	= '' ;
			}
			else
			{
				$nDonation 		= '2' ;
				$nPrint			= '0' ;
				$sLove_Code 		= '' ;
				$sCustomerIdentifier	= '' ;	
			}
			
			// 4.送出參數
			try
			{
				include_once($sCheck_Invoice_SDK);
				$opay_invoice = new AllInvoice ;
				
				// A.寫入基本介接參數
				$opay_invoice->Invoice_Method 			= 'INVOICE' ;
				$opay_invoice->Invoice_Url 				= $sOpayinvoice_Url_Issue ;
				$opay_invoice->MerchantID 				= $nOpayinvoice_Mid ;
				$opay_invoice->HashKey 				    = $sOpayinvoice_Hashkey ;
				$opay_invoice->HashIV 				= $sOpayinvoice_Hashiv ;
				
				// B.送出開立發票參數
				$aItems	= array();
				
				// *算出商品各別金額
				$nSub_Total_Real = 0 ;	// 實際無條進位小計
				
				foreach( $aOrder_Product_Tmp as $key => $value)
				{
					$nQuantity 	= ceil($value['quantity']) ;
					$nPrice		= ceil($value['price']) ;
					$nTotal		= $nQuantity * $nPrice	 ; 				// 各商品小計

					$nSub_Total_Real = $nSub_Total_Real + $nTotal ;				// 計算發票總金額
					
				 	$sProduct_Name 	= $value['name'] ;
				 	$sProduct_Note 	= $value['model'] . '-' . $value['product_id'] ;
				 	
					array_push($opay_invoice->Send['Items'], array('ItemName' => $sProduct_Name, 'ItemCount' => $nQuantity, 'ItemWord' => '批', 'ItemPrice' => $nPrice, 'ItemTaxType' => 1, 'ItemAmount' => $nTotal, 'ItemRemark' => $sProduct_Note )) ;
				}
				
				//
				
				// *找出sub-total
				$nSub_Total = 0 ;
				foreach( $aOrder_Total_Tmp as $key2 => $value2)
				{
					if($value2['code'] == 'sub_total')
					{
						$nSub_Total = (int) $value2['value'];
						break;
					}	
				}
				
				// 無條件位後加總有差異
				if($nSub_Total != $nSub_Total_Real )
				{
					$sMsg_P2 .= ( empty($sMsg_P2) ? '' : WEB_MESSAGE_NEW_LINE ) . '歐付寶電子發票開立，實際金額 $' . $nSub_Total . '， 無條件進位後 $' . $nSub_Total_Real;
				}
				
				
				// $RelateNumber 	= 'OPAY'. date('YmdHis') . rand(1000000000,2147483647) ; // 產生測試用自訂訂單編號
				$RelateNumber	= $order_id ;
				
				$opay_invoice->Send['RelateNumber'] 			= $RelateNumber ;
				$opay_invoice->Send['CustomerID'] 			= '' ;
				$opay_invoice->Send['CustomerIdentifier'] 		= $sCustomerIdentifier ;
				$opay_invoice->Send['CustomerName'] 			= $aOrder_Info_Tmp['firstname'] ;
				$opay_invoice->Send['CustomerAddr'] 			= $aOrder_Info_Tmp['payment_country'] . $aOrder_Info_Tmp['payment_postcode'] . $aOrder_Info_Tmp['payment_city'] . $aOrder_Info_Tmp['payment_address_1'] . $aOrder_Info_Tmp['payment_address_2'];
				$opay_invoice->Send['CustomerPhone'] 			= $aOrder_Info_Tmp['telephone'] ;
				$opay_invoice->Send['CustomerEmail'] 			= $aOrder_Info_Tmp['email'] ;
				$opay_invoice->Send['ClearanceMark'] 			= '' ;
				$opay_invoice->Send['Print'] 				= $nPrint ;
				$opay_invoice->Send['Donation'] 			= $nDonation ;
				$opay_invoice->Send['LoveCode'] 			= $sLove_Code ;
				$opay_invoice->Send['CarruerType'] 			= '' ;
				$opay_invoice->Send['CarruerNum'] 			= '' ;
				$opay_invoice->Send['TaxType'] 			= 1 ;
				$opay_invoice->Send['SalesAmount'] 			= $nSub_Total_Real ;	
				$opay_invoice->Send['InvType'] 			= '07' ;
				$opay_invoice->Send['vat'] 				= '' ;
				$opay_invoice->Send['InvoiceRemark'] 			= 'OC2_OPayInvoice_1.0.0706' ;
				
				// C.送出與返回
				$aReturn_Info = $opay_invoice->Check_Out();
							
	
			}catch (Exception $e)
			{
				// 例外錯誤處理。
				$sMsg = $e->getMessage();
			}
			
			
			// 5.有錯誤訊息或回傳狀態RtnCode不等於1 則不寫入DB
			if( $sMsg != '' || !isset($aReturn_Info['RtnCode']) || $aReturn_Info['RtnCode'] != 1 )
			{
				$sMsg .= '歐付寶電子發票自動開立訊息' ;
				$sMsg .= (isset($aReturn_Info)) ? print_r($aReturn_Info, true) : '' ; 
				
				// A.寫入LOG
				$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$aOrder_Info_Tmp['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsg) . "', date_added = NOW()");
			}
			else
			{
				// 無條件進位 金額有差異，寫入LOG提醒管理員
				if( $sMsg_P2 != '' )
				{
					$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$aOrder_Info_Tmp['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsg_P2) . "', date_added = NOW()");
				} 
				
				// A.更新發票號碼欄位
				$invoice_no 		= $aReturn_Info['InvoiceNumber'] ;
				
				// B.整理發票號碼並寫入DB
				$sInvoice_No_Pre 	= substr($invoice_no ,0 ,2 ) ;
				$sInvoice_No 		= substr($invoice_no ,2) ; 
				
				// C.回傳資訊轉陣列提供history資料寫入
				$sMsg .= '歐付寶電子發票自動開立訊息' ;
				$sMsg	.= print_r($aReturn_Info, true);
				
				$this->db->query("UPDATE `" . DB_PREFIX . "order` SET invoice_no = '" . $sInvoice_No . "', invoice_prefix = '" . $this->db->escape($sInvoice_No_Pre) . "' WHERE order_id = '" . (int)$order_id . "'");
				$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$aOrder_Info_Tmp['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsg) . "', date_added = NOW()");
				$this->db->query("DELETE FROM `invoice_info` WHERE `order_id` = " . (int)$order_id );
			}	

		}
		else
		{
			// A.寫入LOG
			$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$aOrder_Info_Tmp['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsg) . "', date_added = NOW()");

		}
		
		
		return $sMsg ;

	}
	
}
?>
