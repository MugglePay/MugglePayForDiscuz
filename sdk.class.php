<?php

class MugglePayForDiscuz
{

    private $url = 'https://api.mugglepay.com/v1/';

    public function pay()
    {
        global $_G, $mugglepay;
        if ($_POST['money'] <= 0) return 'money error';
        $out_trade_no = date('YmdHis') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $data['merchant_order_id'] = $out_trade_no;
        $data['price_amount'] = (float)$_POST['money'];
        $data['price_currency'] = 'CNY';
        $data['title'] = '支付单号' . $out_trade_no;
        $data['description'] = '充值：' . $_POST['money'];
        $data['description'] .= '  元';
        $data['callback_url'] = trim($_G['siteurl'] . 'source/plugin/MugglePayForDiscuz/notify.php');
        $data['success_url'] = $_G['siteurl'];
        $data['cancel_url'] = $data['success_url'];
        $type = $_POST['type'];
        if ($type === 'wechat' || $type === 'alipay') {
            $data['pay_currency'] = strtoupper($type);
        }
        $str_to_sign = $this->prepareSignId($out_trade_no);
        $data['token'] = $this->sign($str_to_sign);
        $this->insert([
            'mchid' => $mugglepay['mchid'],
            'total_fee' => $_POST['money'] * 100,
            'out_trade_no' => $out_trade_no,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'notify_url' => trim($_G['siteurl'] . 'source/plugin/MugglePayForDiscuz/notify.php'),
        ]);
        $result = json_decode($this->httpPost($data), true);

        if ($result['status'] === 200 || $result['status'] === 201) {
            $result['payment_url'] .= '&lang=zh';
            return json_encode(['url' => $result['payment_url'], 'code' => 1, 'out_trade_no' => $out_trade_no]);
        }
    }

    public function prepareSignId($tradeno)
    {
        global $mugglepay;
        $data_sign = array();
        $data_sign['merchant_order_id'] = $tradeno;
        $data_sign['secret'] = $mugglepay['mchid'];
        ksort($data_sign);
        return http_build_query($data_sign);
    }

    public function insert($arr)
    {
        global $_G, $mugglepay;
        $data = array(
            'orderid' => $arr['out_trade_no'],
            'status' => 1,
            'uid' => $_G['uid'],
            'amount' => $arr["total_fee"] / 100 * $mugglepay['integral_proportion'],
            'price' => $arr["total_fee"] / 100,
            'submitdate' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'],
        );

        C::t('forum_order')->insert($data);
        return;
    }

    public function check()
    {
        $orderid = $_GET['orderid'];
        $order = DB::fetch_first("select * from " . DB::table('forum_order') . " where orderid='" . $orderid . "' and status=2");
        if ($order) {
            return 'paid';
        } else {
            return 'notpaid';
        }
    }

    public function sign($data)
    {
        global $mugglepay;
        return strtolower(md5(md5($data) . $mugglepay['mchid']));
    }

    public function httpPost($data, $type = 'pay')
    {
        global $mugglepay;
        $headers = array('content-type: application/json', 'token: ' . $mugglepay['mchid']);
        $curl = curl_init();
        if ($type === 'pay') {
            $this->url .= 'orders';
            curl_setopt($curl, CURLOPT_URL, $this->url);
            curl_setopt($curl, CURLOPT_POST, 1);
            $data_string = json_encode($data);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        } elseif ($type === 'query') {
            $this->url .= 'orders/merchant_order_id/status?id=' . $data['merchant_order_id'];
            curl_setopt($curl, CURLOPT_URL, $this->url);
            curl_setopt($curl, CURLOPT_HTTPGET, 1);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
    }

}

?>
