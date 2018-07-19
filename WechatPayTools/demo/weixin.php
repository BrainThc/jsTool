<?php

/**
 * Created by PhpStorm.
 * User: developer
 * Date: 2016/3/27
 * Time: 16:16
 */
class Weixin
{

    public function createQrcode($orderId)
    {
        $payUrl = "weixin://wxpay/bizpayurl?";
        $key = '602555595b5267a1db0566e6c201a4e5';

        $appid= 'wx8a196b7699d49419';
        $mch_id='1287936701';
        $product_id = $orderId;
        $time_stamp = time();
        $nonce_str = md5(mt_rand(1000,9999).time().mt_rand(1000,9999));

        $params = "appid={$appid}&mch_id={$mch_id}&nonce_str={$nonce_str}&product_id={$product_id}&time_stamp={$time_stamp}";
        $tmp = $params."&key={$key}";
        $sign = strtoupper(md5($tmp));
        $payUrl = $payUrl.$params."&sign={$sign}";
        
        return $payUrl;
    }
}