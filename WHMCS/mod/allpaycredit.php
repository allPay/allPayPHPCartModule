<?php
include("./allpay/mysql_connect.inc.php");

function allpaycredit_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"信用卡繳費"),
	 "MerchantID" => array("FriendlyName" => "歐付寶廠商ID", "Type" => "text","Description" => "請登入API管理系統取得(必填)", "Size" => "10", ),
	 "TradeDesc" => array("FriendlyName" => "交易描述", "Type" => "text","Description" => "必填", "Size" => "50", ),
	 "InvoicePrefix" => array("FriendlyName" => "帳單前綴", "Type" => "text","Value" => "CRE","Description" => "必填", "Size" => "5", ),
	 "NeedExtraPaidInfo" => array("FriendlyName" => "是否需要回傳額外資訊", "Type" => "text","Description" => "1為是0為否", "Size" => "2", ),
     "testmode" => array("FriendlyName" => "測試模式", "Type" => "yesno", "Description" => "勾選後為測試模式", ),
	);
	return $configarray;
}

function allpaycredit_link($params) {

	# Gateway Specific Variables
	$gatewayMerchantID = $params['MerchantID'];
	$gatewayTradeDesc = $params['TradeDesc'];
	$gatewayInvoicePrefix = $params['InvoicePrefix'];
	$gatewayNeedExtraPaidInfo = $params['NeedExtraPaidInfo'];
	$gatewaytestmode = $params['testmode'];
	
	# Invoice Variables
	$invoiceid = $params['invoiceid'];
	$description = $params['description'];
    $amount = $params['amount']; # Format: ##.##
    $currency = $params['currency']; # Currency Code
	$TotalAmount = round($amount); # Format: ##
	
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
	$MerchantTradeNo = $gatewayInvoicePrefix.$invoiceid.'T'.time();

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
		'MerchantTradeNo' => $MerchantTradeNo,
		'MerchantTradeDate' => $nowdate,
		'PaymentType' => 'aio',
		'TotalAmount' => $TotalAmount,
		'TradeDesc' => $gatewayTradeDesc,
		'ItemName' => $description,
		'ReturnURL' => $systemurl.'/modules/gateways/callback/allpaycredit.php',
		'ChoosePayment' => 'Credit',
		'NeedExtraPaidInfo' => $gatewayNeedExtraPaidInfo,
	);
	
	$CheckMacValue = curl_allpaycredit_post($CheckMacValueurl,$post_value);
	
	# 跳轉頁面
	$code = '<form action="'.$posturl.'" method="post">
	<input type=hidden name="MerchantID" value="'.$gatewayMerchantID.'">											<!--廠商ID-->
	<input type=hidden name="MerchantTradeNo" value="'.$MerchantTradeNo.'">											<!--廠商交易編號-->
	<input type=hidden name="MerchantTradeDate" value="'.$nowdate.'">												<!--交易日期-->
	<input type=hidden name="PaymentType" value="aio">																<!--交易類型(勿更改)-->
	<input type=hidden name="TotalAmount" value="'.$TotalAmount.'">													<!--交易金額-->
	<input type=hidden name="TradeDesc" value="'.$gatewayTradeDesc.'">												<!--交易描述-->
	<input type=hidden name="ItemName" value="'.$description.'">													<!--商品名稱-->
	<input type=hidden name="ReturnURL" value="'.$systemurl.'/modules/gateways/callback/allpaycredit.php">			<!--付款成功後POST的地址-->
	<input type=hidden name="ChoosePayment" value="Credit">															<!--付款方式(代碼)-->
	<input type=hidden name="NeedExtraPaidInfo" value="'.$gatewayNeedExtraPaidInfo.'">								<!--帳單回傳額外資訊-->
	<input type=hidden name="CheckMacValue" value="'.$CheckMacValue.'">												<!--檢查碼-->
	<input type="submit" class="btn btn-success btn-sm" value="刷卡">
	</form>';
	return $code;
}

function curl_allpaycredit_post($url,$post)
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