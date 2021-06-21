<?php
if (!defined('IN_DISCUZ')) exit('Access Denied');
include_once("sdk.class.php");

$ac    = isset($_REQUEST['ac']) ? $_REQUEST['ac'] : '';
$mugglepay = $_G['cache']['plugin']['MugglePayForDiscuz'];

$app = new MugglePayForDiscuz();

switch ($ac) {
    case 'pay':
        $rst = $app->pay();
        break;

    case 'check':
        $rst = $app->check();
        break;
}

echo $rst;


