<?php
defined('PTS80')||exit('PTS80 Defined');
//综合控制器
class BaseController extends Controller{
    public function __construct(){
        parent::__construct();
        $_GET     && $this->xss_clean($_GET);
        $_POST    && $this->xss_clean($_POST);
        $_COOKIE  && $this->xss_clean($_COOKIE);
        //记录分享人id
        if( isset($_GET['share_id']) && !empty($_GET['share_id']) ){
            //保留的分享人id 用于分享获得红包功能
            $_SESSION['share_id'] = intval($_GET['share_id']);
        }
        //微信登录
        if(stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')){
            $weixin = new WeiXinModel();
            $user_model = new UsersInfoModel();
            //微信登录返回地址 处理
            $backUrl = '';
            $backUrl = $weixin->backUrl('back');
            //登录后执行的配置
            //分享配置内容
            $share = $weixin->backUrl('share');
            if( isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']) ){
                //拼接分享地址
                $pid = '&p_id='.$_SESSION['user']['user_id'];
                $url = __HOME__.'wap/?'.$_SERVER['QUERY_STRING'].$pid;
                if($_GET['c'] == 'Agent'){
                    $url = __HOME__.'wap/?g=WapSite&c=Agent&a=agentGoods&agent_id='.$_SESSION['user']['user_id'];
                }else if($_GET['c'] == 'User' || $_GET['c'] == 'Order' || $_GET['c'] == 'Cart' ){
                    $url = __HOME__.'wap/?g=WapSite&c=Index&a=index'.$pid;
                }
                if( $_GET['c'] != 'Exchange' && $_GET['c'] != 'GroupBuying' && $_GET['a'] != 'kindex' && $_GET['a'] != 'seckill' ){
                    $url .= '&share_id='.$_SESSION['user']['user_id'];
                }
                if( $_GET['c'] == 'NewUser' ){
                    $url .= '&goShare=1';
                }
                $param = $weixin ->getConfigParam($url,$share);
            }else{
                $url = __HOME__.'wap/?'.$_SERVER['QUERY_STRING'];
                $param = $weixin ->getConfigParam($url,$share);
            }
            $fx_weixin = "var configData ='".$param['configData']."'; var shareMessage ='".$param['shareMessage']."'; ";
            $wx_url = __HOME__.'index.php?g=WapSite&c=WeiXin&a=redirectUri';
            //配置上级关系内容
            if( isset($_GET['p_id']) && !empty($_GET['p_id']) ){
                $wx_url .= '&p_id='.$_GET['p_id'];
                //保留的上级id 登录后绑定上级关系
                $_SESSION['parent_id'] = intval($_GET['p_id']);
            }

            //配置区域代理内容
            if( isset($_GET['agent_id']) && !empty($_GET['agent_id']) && $_GET['g'] != 'agentGoods'){
                //保留的区域上级id 登录后绑定上级关系
                $_SESSION['agent_shop']['user_id'] = $_GET['agent_id'];
                header('location:'.__HOME__.'wap/Agent-agentGoods-'.$_GET['agent_id'].'.html');
            }
            //查看是否有登录缓存，有的话自动登录，并更新缓存时间
            if( isset($_COOKIE['unionid']) &&  !empty($_COOKIE['unionid']) && !isset($_SESSION['unionid']) ){
                $_SESSION['unionid'] = $_COOKIE['unionid'];
                setcookie('unionid',$_SESSION['unionid'],time()+86400*3);
                if(isset($_COOKIE['openid']) && !empty($_COOKIE['openid'])){
                    $_SESSION['openid'] = $_COOKIE['openid'];
                    setcookie('openid',$_SESSION['openid'],time()+86400*3);
                }
            }
            //判断身份是否存在用户授权
            if( !isset($_SESSION['unionid']) || empty($_SESSION['unionid']) ){//不存在微信身份
                $redi_url = urlencode($wx_url.'&url='.$backUrl);
        		header('location:https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx634a21407ee85ff5&redirect_uri='.$redi_url.'&url='.$backUrl.'&response_type=code&scope=snsapi_userinfo&state=qwe#wechat_redirect');
                exit;
            }else{//身份存在，自动登录
                //判断用户状态
                if( empty($_SESSION['info_error']) && ( !isset($_SESSION['user']) || empty($_SESSION['user']['user_id']) ) ){
                    //自动登录
                    $userinfo = $user_model->getRow(array('field'=>'user_id,nickname,user_name,mobile,allow_login','where'=>"wx_openid = '".$_SESSION['unionid']."'"));
                    // 旧微信端用户迁移至新系统, 没有用户名和手机号码的情况下,需要补全用户信息
                    if( !empty($userinfo) && ( empty($userinfo['user_name']) || empty($userinfo['mobile']) ) ){
                        $_SESSION['info_error'] = 1;
                        $url = __HOME__.'wap/Login-bindMobile.html';
                        header('location:'.$url);
                        exit;
                    }
                    //检查用户是否存在
                    if(!empty($userinfo) && $userinfo['allow_login'] == 1){
                        $login_mess = array(
                            'user_id'   => $userinfo['user_id'],
                            'nickname'  => $userinfo['nickname'],
                            'user_name' => $userinfo['user_name']
                        );
                        //登录并配置登录信息
                        $user_model->registerSession($login_mess);
                        /**  登陆成功记录登陆信息 **/
                        $model_login = new UsersLoginModel();
                        $model_login->record_login($_SESSION['user']['user_id'],UsersLoginModel::MODILE);
                    }
                }
            }
        }else{
            $fx_weixin="var configData =''; var shareMessage =''; ";
        }
        $this->assign('fx_weixin',$fx_weixin);
        if( !empty($_GET['jumpType']) && $_GET['jumpType'] == 'exchange' ){
            //新版兑换商城配置
            $exchange_url = __HOME__.'wap/Exchange-index.html';
            if( !empty($_GET['section']) && ( $_GET['section'] == 'search' || $_GET['section'] == 'list' || $_GET['section'] == 'product' ) ){
                $exchange_url .= '/'.$_GET['section'];
                if( !empty($_GET['ids']) ){
                    $exchange_url .= '/'.$_GET['ids'];
                }
            }
            header('location:'.$exchange_url);
        }
        if( isset($_GET['goShare']) && $_GET['goShare'] == '1' ){
            header('location:'.__HOME__.'wap/NewUser-goodsShare.html');
        }
    }

    /**
     * 页面登录状态
     * @param  [type] $state [0 非登录窗口、注册窗口]
     * @return [type]        [1 登录窗口、注册窗口]
     */
    function loginState($state){
        switch($state){
            case 0 :
                if(!isset($_SESSION['user'])){
                    return header('location:'.__HOME__.'wap/Login-login-1314.html');
                }
                break;
            case 1 :
                if(isset($_SESSION['user'])){
                    return header('location:'.__HOME__.'wap/User-home.html');
                }
                break;
        }
    }

    //登录检查
    function checkLogin(){
        if(isset($_SESSION['user']) || !empty($_SESSION['user'])){
            return $_SESSION['user'];
        }else{
            return exit('非法操作');
        }
    }
}
?>