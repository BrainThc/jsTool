<?php
defined('PTS80')||exit('PTS80 Defined');

/**
 * thc
 * 2017-6-22
 */
class LoginController extends BaseController{
    use OpenSSLTrait;

    function __construct() {
        parent::__construct();
    }

    //登录选择页
    public function index(){
        $secure = $this->openssl_generate_keys();
    }

    /**
     * 移动端登录
     */
    function checkin(){
        $tel = $this->openssl_decode($_POST['md5_pubKey'], trim($_POST['username']));
        $password = $this->openssl_decode($_POST['md5_pubKey'], trim($_POST['pwd']));
        //销毁秘钥
        $this->openssl_finished($_POST['md5_pubKey']);
    }
}

?>