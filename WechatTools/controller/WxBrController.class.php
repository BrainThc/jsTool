<?php
defined('PTS80')||exit('PTS80 Defined');
 
/**
** 街控制器
**/
class WxBrController extends Controller{
	protected static $appid = 'wx634a21407ee85ff5';
    protected static $appsecret = '58680e5c79a75df5b3785ebac05b762e';

	public function message_show(){
        $code = $_GET['code'];
        //获取信息
        $userInfoUrl ='https://api.weixin.qq.com/sns/oauth2/access_token?appid='.self::$appid.'&secret='.self::$appsecret.'&code='.$code.'&grant_type=authorization_code';
        $wx_userInfo = $this -> httpGet($userInfoUrl);
        //检查是否关注
        if( (isset($wx_userInfo['errcode']) && $wx_userInfo['errcode'] == '40003') || empty($wx_userInfo['unionid']) ){//可能是appid错误导致，或则是code失效导致
            header('location:'.__HOME__.'?g=WapSite&c=WxBr&a=index');//重新获取
            exit;
        }
        if( !empty($wx_userInfo['unionid']) ) {
            $_SESSION['unionid'] = $wx_userInfo['unionid'];
            setcookie('unionid', $wx_userInfo['unionid'], time() + 86400 * 3);
        }
        if( !empty($wx_userInfo['openid']) ){
            $_SESSION['openid'] = $wx_userInfo['openid'];
            setcookie('openid',$wx_userInfo['openid'],time()+86400*3);
        }
        $user_model = new UsersInfoModel();
        $user_table = $user_model->getTable();
        $sql = "SELECT user_id,user_name,password,mobile FROM ${user_table} WHERE wx_openid = :wx_openid or wx_openid1 = :wx_openid1";
        $where['wx_openid'] = $_SESSION['unionid'];
        $where['wx_openid1'] = $_SESSION['openid'];
        $user_info = $user_model->getAllSafely($sql,$where);
        $this->assign('userInfo',$user_info);
        $this->assign('unionid',$_SESSION['unionid']);
        $this->assign('openid',$_SESSION['openid']);
		$this->display();
	}

	public function index(){
	    if( empty($_COOKIE['unionid']) || empty($_COOKIE['openid']) ){
            $backUrl = __HOME__.'?g=WapSite&c=WxBr&a=message_show';
            $redi_url = urlencode($backUrl);
            header('location:https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . self::$appid . '&redirect_uri=' . $redi_url . '&response_type=code&scope=snsapi_userinfo&state=qwe#wechat_redirect');
            exit;
        }else{
            $user_model = new UsersInfoModel();
            $user_table = $user_model->getTable();
            $sql = "SELECT user_id,user_name,password,mobile FROM ${user_table} WHERE wx_openid = :wx_openid or wx_openid1 = :wx_openid1";
            $where['wx_openid'] = $_COOKIE['unionid'];
            $where['wx_openid1'] = $_COOKIE['openid'];
            $user_info = $user_model->getAllSafely($sql,$where);
            $this->assign('userInfo',$user_info);
            $this->assign('unionid',$_COOKIE['unionid']);
            $this->assign('openid',$_COOKIE['openid']);
            $this->display('WapSite/WxBr/message_show');
        }
    }

    /*
    **通过curl发送get请求
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
	
}
?>