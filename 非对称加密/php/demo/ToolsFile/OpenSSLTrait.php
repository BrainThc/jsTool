<?php

/**
 * 此方法用于平台前端安全数据加密  对应前端Js文件[jsencrypt.js] 前端encrypt后的数据需要encodeURIComponent处理一下
 * User: Feng
 * Date: 2018/6/4
 * Time: 16:33
 */
trait OpenSSLTrait
{
    // 创建PKCS#8私钥公钥
    public function openssl_generate_keys(){
        $config = array(
            "private_key_bits" => 1024,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );

// Create the private and public key
        $res = openssl_pkey_new($config);

// Extract the private key from $res to $privKey
        openssl_pkey_export($res, $privKey);

// Extract the public key from $res to $pubKey
        $pubKey = openssl_pkey_get_details($res);
        $pubKey = $pubKey["key"];

        $md5_pubKey = md5($pubKey);

        Session::set($md5_pubKey, $privKey);

        return [
            'md5_pubKey' => $md5_pubKey,
            'pubKey' => $pubKey,
        ];
    }


    // 使用私钥解码加密数据
    public function  openssl_decode($md5_pubKey, $data){
        $crypttext   = base64_decode($data);
        if (openssl_private_decrypt($crypttext, $sourcestr, Session::get($md5_pubKey), OPENSSL_PKCS1_PADDING))
        {
            return "".$sourcestr;
        }
        return "";
    }

    // 加密解密流程结束
    public function openssl_finished($md5_pubKey){
        Session::set($md5_pubKey, null);
    }


}