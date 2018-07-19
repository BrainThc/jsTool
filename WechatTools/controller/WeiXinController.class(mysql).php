<?php
/**
 * User: thc
 */
class WeiXinController extends CheckLoginController
{   
    protected static $appid = 'wx634a21407ee85ff5';
    protected static $appsecret = '58680e5c79a75df5b3785ebac05b762e';
    function __construct()
    {
        parent::__construct();
    }

    //微信静默授权服务器返回方法
    function redirectUri(){
        $user_model = new UsersInfoModel();
        $code = $_GET['code'];
        $back_url = empty($_GET['url']) ? '' : $_GET['url'];
        if(empty($back_url)){
            $back_url = __HOME__.'wap/Index-index.html';
        }
        //获取信息
        $userInfoUrl ='https://api.weixin.qq.com/sns/oauth2/access_token?appid='.self::$appid.'&secret='.self::$appsecret.'&code='.$code.'&grant_type=authorization_code';
        $wx_userInfo = $this -> httpGet($userInfoUrl);
        //拉去微信用户信息
        // $scopInfoUrl = "https://api.weixin.qq.com/sns/userinfo?access_token={$wx_userInfo['access_token']}&openid={$wx_userInfo['openid']}&lang=zh_CN";
        // $scop_info  = $this -> httpGet($scopInfoUrl);
        // print_r($scop_info);
        // exit;
        if( ( isset($wx_userInfo['errcode']) && $wx_userInfo['errcode'] == '40003' ) || empty($wx_userInfo['unionid']) ){//可能是appid错误导致，或则是code失效导致
            Url::redirect('WapSite/WeiXin/followwx');
            exit;
        }else{
            $_SESSION['unionid'] = $wx_userInfo['unionid'];
            setcookie('unionid',$wx_userInfo['unionid'],time()+86400*3);
        }
        if( !empty($wx_userInfo['openid']) ){
            $_SESSION['openid'] = $wx_userInfo['openid'];
            setcookie('openid',$wx_userInfo['openid'],time()+86400*3);
        }
        //不能用header 不能用header
        echo "<script>location='".$back_url."'</script>";
    }

    //金融中心微信静默授权服务器返回方法
    function FcredirectUri(){
        $fc_user_model = new FcUsersInfoModel();
        $code = $_GET['code'];
        $back_url = empty($_GET['url']) ? '' : $_GET['url'];
        if(empty($back_url)){
            $back_url = __HOME__.'?g=WapSite&c=Financial&a=index';
        }
        //获取信息
        $userInfoUrl ='https://api.weixin.qq.com/sns/oauth2/access_token?appid='.self::$appid.'&secret='.self::$appsecret.'&code='.$code.'&grant_type=authorization_code';
        $wx_userInfo = $this -> httpGet($userInfoUrl);
        if((isset($wx_userInfo['errcode']) && $wx_userInfo['errcode'] == '40003') || empty($wx_userInfo['unionid'])){//可能是appid错误导致，或则是code失效导致
            Url::redirect('WapSite/WeiXin/followwx');
            throw new Exception('发生错误');
        }else{
            $_SESSION['unionid'] = $wx_userInfo['unionid'];
            setcookie('unionid',$wx_userInfo['unionid'],time()+86400*3);
        }
        if( !empty($wx_userInfo['openid']) ){
            $_SESSION['openid'] = $wx_userInfo['openid'];
            setcookie('openid',$wx_userInfo['openid'],time()+86400*3);
        }
        //用unionid检查账户 若查到 openid 需要更新openid
        $userinfo = $fc_user_model->getRow(array('field'=>'user_id,user_name,wx_openid,wx_unionid,mobile','where'=>"wx_openid = '{$wx_userInfo['openid']}'"));
        if( !empty($userinfo) ){
            $login_sess = array(
                'user_id'   => $userinfo['user_id'],
                // 'nickname'  => $userinfo['nickname'],
                'user_name' => $userinfo['user_name']
            );
            //登录并配置登录信息
            $fc_user_model->registerSession($login_sess);
            /**  登陆成功记录登陆信息 **/
            // $model_login = new FcUsersLoginModel();
            // $model_login->record_login($_SESSION['user']['user_id'], FcUsersLoginModel::MODILE);
            $url = $back_url;
        }else{
            //绑定微信号
            $url = __HOME__.'?g=WapSite&c=FinancialL&a=bindMobile';
        }
        //不能用header 不能用header
        echo "<script>location='".$url."'</script>";
    }

    //提示关注页
    public function guanzhu(){
        $this->display();
    }

    /**通过curl发送get请求
     * @param string $userInfoUrl
     * @return array
     */
    public function httpGet($userInfoUrl){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$userInfoUrl);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);

        // https头
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);

        $userInfo =json_decode( curl_exec($ch) , true );
        if(curl_error($ch)){
            var_dump(curl_error($ch));
        }
        curl_close($ch);
        return $userInfo;
    }

    /**
     *  发送post请求
     * @param string $url
     * @param string $data
     * @return mixed
     */
    public function httpPost($url,$data=''){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,false);
        curl_setopt($ch, CURLOPT_POST,true);
        //非空发送数据
        if(!empty($data)){
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
        }

        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);

        $res = curl_exec($ch);

        if(curl_errno($ch)){
            var_dump(curl_error($ch));
        }

        curl_close($ch);
        return $res;
    }

    /**
     *  跳转公众号二维码
     */
    public function followwx()
    {
        $this->display('WapSite/WeiXin/guanzhu');
    }

    function orderClassNo(){
        if( empty($_GET['no']) ){
            $training = [];
        }else {
            $tid = Intval($_GET['no']);
            if( empty($tid) ){
                $training = [];
            }else{
                $dao = Dao::instance();
                $sql = "select * from " . $dao->table('training_log') . " where tid='{$tid}'";
                $training = $dao->queryOne($sql);
                if( empty($training) ) {
                    $training = [];
                }else{
                    $training['no'] = sprintf("%'.08d", $training['tid']);
                }
            }
        }
        $this->assign('class',$training);
        $this->display();
    }
  
}