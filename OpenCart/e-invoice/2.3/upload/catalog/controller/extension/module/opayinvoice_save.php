

<?php
$nInvoice_Type		= $this->session->data['invoice_type'] ;
$nOrder_Id 		= $this->session->data['order_id'] ;
$sCompany_Write 	= $this->session->data['company_write'] ;
$sLove_Code 		= $this->session->data['love_code'] ;
$nInvoice_Status 	= $this->session->data['invoice_status'] ;

if($nInvoice_Status == 1)
{


// 啟用電子發票

	echo '發票資訊：' ;
	if($nInvoice_Type == 3)
	{
		echo '捐贈' ;
		echo ' 愛心碼 ' . $sLove_Code ;
		
		$sCompany_Write = '' ;
	}
	elseif($nInvoice_Type == 2)
	{
		
		echo '公司發票' ;
		echo ' 統一編號 ' . $sCompany_Write ;
		
		$sLove_Code 	= '' ;
	}
	else
	{
		echo '個人發票' ;
		
		$sCompany_Write = '' ;
		$sLove_Code 	= '' ;
	}


	
	// A.刪除invoice_info 資料表過期資料
	
	$nNow_Time  = time() ;
	$nPass_Time = time() - ( 86400 * 30 );
	$sPass_Time = date('Y-m-d H:i:s', $nPass_Time);
	
	// 1.判斷是否有訂單沒有成立 還卡在暫存狀態 取出order_id
	$order_query_tmp = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "order` WHERE date_added < '" . $sPass_Time . "' AND order_status_id = 0 ORDER BY date_added LIMIT 5 " );
	$order_query_tmp = $order_query_tmp->rows ;
	
	// 2.整理 order_id
	$sOrder_Id	= '' ;
	$sOrder_Id_Pro 	= '' ;
	foreach($order_query_tmp as $key => $value)
	{
		$sOrder_Id_Pro = ($sOrder_Id == '' ) ? '' : ',' ;
		$sOrder_Id .= $sOrder_Id_Pro . (int)$value['order_id'] ;
	}
	
	// 3.刪除超過一個月的紀錄
	if($sOrder_Id != '')
	{
		$this->db->query("DELETE FROM `invoice_info` WHERE `order_id` IN ( " . $sOrder_Id . " ) AND createdate < " . $nPass_Time );
	}
	
	// B.資料寫入 invoice_info 資料表
	$this->db->query("INSERT INTO `invoice_info` (`order_id`, `love_code`, `company_write`, `invoice_type`, `createdate`) VALUES ('" . $nOrder_Id . "', '" . $sLove_Code . "', '" . $sCompany_Write . "', '" . $nInvoice_Type . "', '" . $nNow_Time . "' )" );


	echo '<hr>';
}

/*o
$fp = fopen("/var/tmp/invoice.log","a");
fputs($fp,$this->session->data['company_write']."\n");
fclose($fp);
*/

?>





