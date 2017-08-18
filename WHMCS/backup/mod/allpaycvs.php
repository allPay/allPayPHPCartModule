<?php
include("./allpay/mysql_connect.inc.php");

function allpaycvs_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"超商代碼繳費"),
	 "MerchantID" => array("FriendlyName" => "歐付寶廠商ID", "Type" => "text","Description" => "請登入API管理系統取得(必填)", "Size" => "10", ),
	 "TradeDesc" => array("FriendlyName" => "交易描述", "Type" => "text","Description" => "必填", "Size" => "50", ),
	 "ExpireDate" => array("FriendlyName" => "帳單有效期", "Type" => "text","Description" => "必填", "Size" => "2", ),
	 "InvoicePrefix" => array("FriendlyName" => "帳單前綴", "Type" => "text","Value" => "CVS","Description" => "必填", "Size" => "5", ),
	 "Desc_1" => array("FriendlyName" => "交易描述1", "Type" => "text","Value" => "虛擬主機","Description" => "會出現在超商繳費平台螢幕上第一行", "Size" => "20", ),
	 "Desc_2" => array("FriendlyName" => "交易描述2", "Type" => "text","Value" => "超商代碼繳費","Description" => "會出現在超商繳費平台螢幕上第二行", "Size" => "20", ),
     "testmode" => array("FriendlyName" => "測試模式", "Type" => "yesno", "Description" => "勾選後為測試模式", ),
	);
	return $configarray;
}

function allpaycvs_link($params) {

	# Gateway Specific Variables
	$gatewayMerchantID = $params['MerchantID'];
	$gatewayTradeDesc = $params['TradeDesc'];
	$gatewayExpireDate = $params['ExpireDate'];
	$gatewayInvoicePrefix = $params['InvoicePrefix'];
	$gatewayDesc_1 = $params['Desc_1'];
	$gatewayDesc_2 = $params['Desc_2'];
	$gatewaytestmode = $params['testmode'];
	
	# Invoice Variables
	$invoiceid = $params['invoiceid'];
	$description = $params['description'];
    $amount = $params['amount']; # Format: ##.##
    $currency = $params['currency']; # Currency Code
	$TotalAmount = (int)$amount; # Format: ##
	
	# Client Variables
	$firstname = $params['clientdetails']['firstname'];
	$lastname = $params['clientdetails']['lastname'];
	$email = $params['clientdetails']['email'];
	$address1 = $params['clientdetails']['address1'];
	$address2 = $params['clientdetails']['address2'];
	$city = $params['clientdetails']['city'];
	$state = $params['clientdetails']['state'];
	$postcode = $params['clientdetails']['postcode'];
	$country = $params['clientdetails']['country'];
	$phone = $params['clientdetails']['phone'];
	$nowdate = date("Y/m/d H:i:s");

	# System Variables
	$companyname = $params['companyname'];
	$systemurl = $params['systemurl'];
	$currency = $params['currency'];

	# 是否為測試模式
    if ($gatewaytestmode == "on") {
        $posturl = "http://payment-stage.allpay.com.tw/Cashier/AioCheckOut";
		$gatewayMerchantID = 2000132;
		$CheckMacValueurl = "http://payment-stage.allpay.com.tw/AioHelper/GenCheckMacValue";
    } else {
        $posturl = "https://payment.allpay.com.tw/Cashier/AioCheckOut";
		$CheckMacValueurl = "https://payment.allpay.com.tw/AioHelper/GenCheckMacValue";
    }
	
	# 產生檢查碼
	$post_value= array(
		'MerchantID' => $gatewayMerchantID,
		'MerchantTradeNo' => $gatewayInvoicePrefix.$invoiceid,
		'MerchantTradeDate' => $nowdate,
		'PaymentType' => 'aio',
		'TotalAmount' => $TotalAmount,
		'TradeDesc' => $gatewayTradeDesc,
		'ItemName' => $description,
		'ReturnURL' => $systemurl.'/modules/gateways/callback/allpaycvs.php',
		'ChoosePayment' => 'CVS',
		'ChooseSubPayment' => 'CVS',
		'ExpireDate' => $gatewayExpireDate,
		'Desc_1' => $gatewayDesc_1,
		'Desc_2' => $gatewayDesc_2,
		'Desc_3' => '繳費人：'.$lastname.$firstname,
		'Desc_4' => '帳單編號：'.$invoiceid,
		'PaymentInfoURL' => $systemurl.'/modules/gateways/allpay/allpaygetcvs.php',
		'ClientRedirectURL' => $systemurl.'/viewinvoice.php?id='.$invoiceid,
	);
	
	$CheckMacValue = curl_allpaycvs_post($CheckMacValueurl,$post_value);
	
	# 跳轉頁面
	$sql = "SELECT * FROM mod_allpay where MerchantTradeNo = '".$gatewayInvoicePrefix."$invoiceid'";
	$result = mysql_query($sql);
	$row = @mysql_fetch_row($result);
	if($row==null){
		$code = '<form action="'.$posturl.'" method="post">
		<input type=hidden name="MerchantID" value="'.$gatewayMerchantID.'">											<!--廠商ID-->
		<input type=hidden name="MerchantTradeNo" value="'.$gatewayInvoicePrefix.$invoiceid.'">							<!--廠商交易編號-->
		<input type=hidden name="MerchantTradeDate" value="'.$nowdate.'">												<!--交易日期-->
		<input type=hidden name="PaymentType" value="aio">																<!--交易類型(勿更改)-->
		<input type=hidden name="TotalAmount" value="'.$TotalAmount.'">													<!--交易金額-->
		<input type=hidden name="TradeDesc" value="'.$gatewayTradeDesc.'">												<!--交易描述-->
		<input type=hidden name="ItemName" value="'.$description.'">													<!--商品名稱-->
		<input type=hidden name="ReturnURL" value="'.$systemurl.'/modules/gateways/callback/allpaycvs.php">	<!--付款成功後POST的地址-->
		<input type=hidden name="ChoosePayment" value="CVS">															<!--付款方式(代碼)-->
		<input type=hidden name="CheckMacValue" value="'.$CheckMacValue.'">												<!--檢查碼-->
		<input type=hidden name="ChooseSubPayment" value="CVS">															<!--支付方式-->
		<input type=hidden name="ExpireDate" value="'.$gatewayExpireDate.'">											<!--帳單有效期-->
		<input type=hidden name="Desc_1" value="'.$gatewayDesc_1.'">													<!--交易描述1-->
		<input type=hidden name="Desc_2" value="'.$gatewayDesc_2.'">													<!--交易描述2-->
		<input type=hidden name="Desc_3" value="繳費人：'.$lastname.$firstname.'">										<!--交易描述3-->
		<input type=hidden name="Desc_4" value="帳單編號：'.$invoiceid.'">												<!--交易描述4-->
		<input type=hidden name="PaymentInfoURL" value="'.$systemurl.'/modules/gateways/allpay/allpaygetcvs.php">		<!--回傳給WHMCS取號通知-->
		<input type=hidden name="ClientRedirectURL" value="'.$systemurl.'/viewinvoice.php?id='.$invoiceid.'">			<!--付款完成顯示訂單-->
		<input type="submit" class="btn btn-success btn-sm" value="產生繳費代碼">
		</form>';
	}else{
		$code = '<p>帳單編號：'.$row[1].'</p><p>繳費代碼：'.$row[7].'</p><p>繳費期限：'.$row[11].'</p><p>繳費金額：'.$row[4].'</p>';
	}
	return $code;
}

function curl_allpaycvs_post($url,$post)
{
	 $ch = curl_init();
	 curl_setopt($ch, CURLOPT_URL, $url);
	 curl_setopt($ch, CURLOPT_POST,true);
	 curl_setopt($ch, CURLOPT_CUSTOMREQUEST,'POST');
	 curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	 curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	 $result = curl_exec($ch);
	 curl_close ($ch);
	 return $result;
}
?>