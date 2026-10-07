<?php
require 'includes/application_top.php';
$orderId = isset($_GET['local_order_id']) ? (int)$_GET['local_order_id'] : 0;
$customer = isset($_SESSION['customer_id']) ? (int)$_SESSION['customer_id'] : 0;
$owned = $db->Execute('SELECT orders_id FROM '.TABLE_ORDERS.' WHERE orders_id='.$orderId.' AND customers_id='.$customer);
if ($orderId > 0 && $customer > 0 && !$owned->EOF) {
    $_SESSION['order_number_created']=$orderId;
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS,'','SSL'));
}
zen_redirect(zen_href_link(FILENAME_LOGIN,'','SSL'));
