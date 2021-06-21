<?php
if(!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class plugin_MugglePayForDiscuz{

    function global_usernav_extra3(){
        $url = 'home.php?mod=spacecp&ac=plugin&op=credit&id=MugglePayForDiscuz:MugglePayForDiscuz';
        return " <a href='{$url}' style='color:red;'>充值</a> ";
    }

}
