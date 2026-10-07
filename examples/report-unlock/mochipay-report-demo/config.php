<?php
// PHP 7.0+; keep credentials in private server environment configuration.
define('MOCHIPAY_BASE_URL', getenv('MOCHIPAY_URL') ?: 'https://mochi.bz');
define('MOCHIPAY_API_KEY', getenv('MOCHIPAY_API_KEY') ?: 'YOUR_API_KEY');
define('MOCHIPAY_API_SECRET', getenv('MOCHIPAY_API_SECRET') ?: 'YOUR_API_SECRET');
define('REPORT_PUBLIC_URL', getenv('MOCHIPAY_REPORT_PUBLIC_URL') ?: '');
// Required persistent directory OUTSIDE the document root. Never use a public folder.
define('REPORT_STORAGE', getenv('MOCHIPAY_REPORT_STORAGE') ?: '');
define('REPORT_ACCESS_CODE', getenv('MOCHIPAY_REPORT_ACCESS_CODE') ?: '');
define('REPORT_PRICE', '10.00');
define('REPORT_CURRENCY', 'USD');
define('REPORT_DEFAULT_MODE', 'ON_SITE');
define('REPORT_METHODS', 'USDT_TRC20,USDC_ERC20,BTC_BITCOIN,ETH_ERC20,SOL_SOLANA');
