<?php

include("./mysql_connect.inc.php");


$MerchantID = $_POST['MerchantID'];
$MerchantTradeNo = $_POST['MerchantTradeNo'];
$RtnCode = $_POST['RtnCode'];
$RtnMsg = $_POST['RtnMsg'];
$TradeNo = $_POST['TradeNo'];
$TradeAmt = $_POST['TradeAmt'];
$PaymentType = $_POST['PaymentType'];
$TradeDate = $_POST['TradeDate'];
$CheckMacValue = $_POST['CheckMacValue'];
$PaymentNo = $_POST['PaymentNo'];
$ExpireDate = $_POST['ExpireDate'];
$Barcode1 = $_POST['Barcode1'];
$Barcode2 = $_POST['Barcode2'];
$Barcode3 = $_POST['Barcode3'];

$sql = "insert into mod_allpay (MerchantID, MerchantTradeNo, RtnCode, RtnMsg, TradeNo, TradeAmt, PaymentType, TradeDate, CheckMacValue, PaymentNo, ExpireDate, Barcode1, Barcode2, Barcode3, Paid) values ('$MerchantID', '$MerchantTradeNo', '$RtnCode', '$RtnMsg', '$TradeNo', '$TradeAmt', '$PaymentType', '$TradeDate', '$CheckMacValue', '$PaymentNo', '$ExpireDate', '$Barcode1', '$Barcode2', '$Barcode3', 0)";

if(mysql_query($sql))
{
	echo '<div>POST成功！</div>';
}
else
{
	echo '<div>POST失敗！</div>';
}
?>