<?php
header("Content-type: text/html; charset=utf-8");
require '../../class/class_core.php';
require '../../function/function_forum.php';
$discuz = C::app();
$discuz->init();
loadcache('plugin');
$mugglepay = $_G['cache']['plugin']['MugglePayForDiscuz'];

$inputString = file_get_contents('php://input', 'r');
$inputStripped = str_replace(array("\r", "\n", "\t", "\v"), '', $inputString);
$inputJSON = json_decode($inputStripped, true); //convert JSON into array
$data = array();
if ($inputJSON !== null) {
    $data['status'] = $inputJSON['status'];
    $data['order_id'] = $inputJSON['order_id'];
    $data['merchant_order_id'] = $inputJSON['merchant_order_id'];
    $data['price_amount'] = $inputJSON['price_amount'];
    $data['price_currency'] = $inputJSON['price_currency'];
    $data['created_at_t'] = $inputJSON['created_at_t'];
}

// 准备待签名数据
$str_to_sign = prepareSignId($inputJSON['merchant_order_id']);
$resultVerify = verify($str_to_sign, $inputJSON['token']);
$isPaid = $data !== null && $data['status'] !== null && $data['status'] === 'PAID';
$orderid = $inputJSON['merchant_order_id'];
$order = DB::fetch_first("select * from " . DB::table('forum_order') . " where orderid='" . $inputJSON['merchant_order_id']."'");

if ($resultVerify && $isPaid && $order) {
    if ($order['status'] === 1) {
        // 更新订单状态
        $data = array('status' => 2, 'confirmdate' => time());
        $where = array('orderid' => $orderid);
        DB::update('forum_order', $data, $where);

        // 更新用户积分
        updatemembercount($order['uid'], array($_G['setting']['creditstrans'] => $order['amount']), true, '', 1, '', '微信支付充值');

        // 积分消息提醒
        notification_add($order['uid'], 'system', 'addfunds', array(
            'orderid' => $order['orderid'],
            'price' => $order['price'],
            'from_id' => 0,
            'from_idtype' => 'buycredit',
            'value' => $_G['setting']['extcredits'][$_G['setting']['creditstrans']]['title'] . ' ' . $order['amount'] . ' ' . $_G['setting']['extcredits'][$_G['setting']['creditstrans']]['unit'],
        ), 1);
    }
    $return = [];
    $return['status'] = 200;
    echo json_encode($return);
} else {
    // echo 'FAIL';
    $return = [];
    $return['status'] = 400;
    echo json_encode($return);
}

function prepareSignId($tradeno)
{
    global $mugglepay;
    $data_sign = array();
    $data_sign['merchant_order_id'] = $tradeno;
    $data_sign['secret'] = $mugglepay['mchid'];
    ksort($data_sign);
    return http_build_query($data_sign);
}

function verify($data, $signature)
{
    global $mugglepay;
    $mySign = strtolower(md5(md5($data) . $mugglepay['mchid']));
    return $mySign === $signature;
}

?>
