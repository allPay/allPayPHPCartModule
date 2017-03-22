-- SQL Dump
--
-- 產生時間： 2015 年 08 月 23 日 01:10
-- 伺服器版本: 5.5.41-MariaDB

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- --------------------------------------------------------

--
-- 資料表結構 `mod_allpay`
--

CREATE TABLE IF NOT EXISTS `mod_allpay` (
  `MerchantID` varchar(10) NOT NULL,
  `MerchantTradeNo` varchar(100) NOT NULL,
  `RtnCode` int(20) NOT NULL,
  `RtnMsg` varchar(500) NOT NULL,
  `TradeAmt` double NOT NULL,
  `TradeDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `TradeNo` varchar(20) NOT NULL,
  `PaymentNo` varchar(20) NOT NULL,
  `Barcode1` varchar(20) NOT NULL,
  `Barcode2` varchar(20) NOT NULL,
  `Barcode3` varchar(20) NOT NULL,
  `ExpireDate` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `PaymentType` varchar(20) NOT NULL,
  `CheckMacValue` varchar(64) NOT NULL,
  `Paid` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 資料表索引 `mod_allpay`
--
ALTER TABLE `mod_allpay`
  ADD UNIQUE KEY `MerchantTradeNo` (`MerchantTradeNo`);

