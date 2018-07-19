<?php
defined('PTS80')||exit('PTS80 Defined');

/**
 * Class UserController
 * 会员登录、注册控制器
 * thc
 * 2017-6-22
 */
class LoginController extends BaseController{
    use OpenSSLTrait;

    protected static $appid = 'wx634a21407ee85ff5';

    function __construct() {
        parent::__construct();
        //微信进入标识
        $is_weixin = 0;
        if(stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $is_weixin = 1;
        }
        $this->assign('is_wechat',$is_weixin);
    }

    // 生成图形验证码
    public function create_validate_code(){
        $verify = new Verify;
        $verify->entry();
    }

    //登录选择页
    public function index(){
        $this->loginState(1);
        if(stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            if ( isset($_SESSION['unionid']) && !empty($_SESSION['unionid']) && ( !isset($_SESSION['user']) || empty($_SESSION['user']['user_id']) ) ) {
                //若没有登录 自动登录
                $user_model = new UsersInfoModel();
                $userinfo = $user_model->getRow(array('field' => '*', 'where' => "wx_openid = '" . $_SESSION['unionid'] . "'"));
                if (!empty($userinfo) && $userinfo['allow_login'] == 1) {
                    if (empty($userinfo['user_name']) || empty($userinfo['mobile'])) {
                        $_SESSION['info_error'] = 1;
                        $url = __HOME__ . 'wap/Login-bindMobile.html';
                        header('location:' . $url);
                        exit;
                    }
                    $login_mess = array(
                        'user_id' => $userinfo['user_id'],
                        'nickname' => $userinfo['nickname'],
                        'user_name' => $userinfo['user_name']
                    );
                    //登录并配置登录信息
                    $user_model->registerSession($login_mess);
                    /**  登陆成功记录登陆信息 **/
                    $model_login = new UsersLoginModel();
                    $model_login->record_login($_SESSION['user']['user_id'], UsersLoginModel::MODILE);
                    $url = __HOME__ . 'wap/User-home.html';
                    header('location:' . $url);
                } else if (empty($userinfo)) {
                    header('location:'. __HOME__ . 'wap/Login-bindMobile.html');
                    exit;
                }
            }else if( !isset($_SESSION['unionid']) || empty($_SESSION['unionid']) ){//不存在微信身份信息 用户授权检查
                $wx_url = __HOME__ . 'index.php?g=WapSite&c=WeiXin&a=redirectUri';
                $redi_url = urlencode($wx_url);
                header('location:https://open.weixin.qq.com/connect/oauth2/authorize?appid='.self::$appid.'&redirect_uri=' . $redi_url . '&response_type=code&scope=snsapi_userinfo&state=qwe#wechat_redirect');
                exit;
            }
        }
        $this->display();
    }

    //会员登录页
    public function login(){
        $this->loginState(1);
        $type = isset($_GET['type']) ? 1 : 0;
        if(stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            if ( isset($_SESSION['unionid']) && !empty($_SESSION['unionid']) && ( !isset($_SESSION['user']) || empty($_SESSION['user']['user_id']) ) ) {
                //若没有登录 自动登录
                $user_model = new UsersInfoModel();
                $userinfo = $user_model->getRow(array('field' => '*', 'where' => "wx_openid = '" . $_SESSION['unionid'] . "'"));
                if (!empty($userinfo) && $userinfo['allow_login'] == 1) {
                    if (empty($userinfo['user_name']) || empty($userinfo['mobile'])) {
                        $_SESSION['info_error'] = 1;
                        $url = __HOME__ . 'wap/Login-bindMobile.html';
                        header('location:' . $url);
                        exit;
                    }
                    $login_mess = array(
                        'user_id' => $userinfo['user_id'],
                        'nickname' => $userinfo['nickname'],
                        'user_name' => $userinfo['user_name']
                    );
                    //登录并配置登录信息
                    $user_model->registerSession($login_mess);
                    /**  登陆成功记录登陆信息 **/
                    $model_login = new UsersLoginModel();
                    $model_login->record_login($_SESSION['user']['user_id'], UsersLoginModel::MODILE);
                    $url = __HOME__ . 'wap/User-home.html';
                    header('location:' . $url);
                } else if (empty($userinfo)) {
                    header('location:'. __HOME__ . 'wap/Login-bindMobile.html');
                    exit;
                }
            }else if( !isset($_SESSION['unionid']) || empty($_SESSION['unionid']) ){//不存在微信身份信息 用户授权检查
                $wx_url = __HOME__ . 'index.php?g=WapSite&c=WeiXin&a=redirectUri';
                $redi_url = urlencode($wx_url);
                header('location:https://open.weixin.qq.com/connect/oauth2/authorize?appid='.self::$appid.'&redirect_uri=' . $redi_url . '&response_type=code&scope=snsapi_userinfo&state=qwe#wechat_redirect');
                exit;
            }
        }
        $secure = $this->openssl_generate_keys();
        $this->assign("secure", $secure);
        $this->assign('type',$type);
        $this->display();
    }

    /**
     * 移动端登录
     */
    function checkin(){
        $json['returns'] = 0;
        if( empty($_POST['username']) || empty($_POST['pwd']) || empty($_POST['md5_pubKey']) ){
            $json['msg'] = '网络连接错误，请稍后再试';
            exit(json_encode($json));
        }
        $tel = $this->openssl_decode($_POST['md5_pubKey'], trim($_POST['username']));
        $password = $this->openssl_decode($_POST['md5_pubKey'], trim($_POST['pwd']));
//        $tel = trim($_POST['username']);
//        $password = trim($_POST['pwd']);
        $data['tel'] = $tel;
        $data['password'] = $password;

        $user_model = new UsersInfoModel();
        $user_table = $user_model->getTable();
        $sql_where = array();
        $user_sql = 'SELECT user_id, nickname, user_name, mobile,password,real_name,dynamic_code, allow_login, old_password,salt FROM '.$user_table;
        $valid_arr = array(
            array('tel',1,'请输入登陆账号','require'),
            array('password',1,'请输入登陆密码','require')
        );
        $user_model -> set_valid($valid_arr);
        if(!$user_model -> _autoCheck($data)){	//验证数据合法性
            echo $user_model -> getError();
            exit;	//提交错误信息
        }

        // 验证是否是邮箱登陆
        $valid_arr = array(
            array('tel',1,'','email')
        );
        $user_model -> set_valid($valid_arr);
        if(!$user_model -> _autoCheck($data)){	//验证数据合法性
            // 验证是否是手机登录
            $valid_arr = array(
                array('tel',1,'','mobile')
            );
            $user_model -> set_valid($valid_arr);
            if(!$user_model -> _autoCheck($data)){
                $where = "user_name = :user_name";
                $sql_where['user_name'] = $tel;
            }
            else{
                $where = "(user_name = :user_name or mobile = :mobile)";
                $sql_where['user_name'] = $tel;
                $sql_where['mobile'] = $tel;
            }
        }
        else{
            $where = "email = :email";
            $sql_where['email'] = $tel;
        }
        $user_sql = $user_sql.' WHERE '.$where;
        $user = $user_model->getRowSafely($user_sql,$sql_where);
        //检查账号
        if(empty($user)){
            $json['msg'] = Lang::get('account').'不存在';
            exit(json_encode($json));
        }else if($user['password'] != UsersInfoModel::passwd($password,$user['dynamic_code']) ){
            if(empty(trim($user['old_password'])))
            {
                $json['msg'] = '密码不正确!';
                exit(json_encode($json));
            }
            else if(md5(md5($password).$user['salt']) != $user['old_password']){
                $json['msg'] = '新系统上线,旧系统会员登录如遇到异常,请联系管理员修改密码!';
                exit(json_encode($json));
            }

        }
        else if($user['allow_login'] == 0)
        {
            $json['msg'] = Lang::get('account').'已被停用,如有疑问,请联系客服!';
            exit(json_encode($json));
        }

        // 检查若是旧系统账号初次登录,设置成新系统密码,清除旧系统账号密码.
        if(!empty($user['old_password']) )
        {
            $model_user_info = new UsersInfoModel;
            $dynamic_code = UsersInfoModel::getDynamicCode();
            $passwd = UsersInfoModel::passwd($password, $dynamic_code);
            $new_data['password'] = $passwd;
            $new_data['dynamic_code'] = $dynamic_code;
            $new_data['old_password'] = '';
            $new_data['salt'] = '';
            $model_user_info->update($new_data, "user_id={$user['user_id']}");
        }


        $user_session['user_name'] = $user['user_name'];
        $user_session['nickname'] = $user['nickname'];
        $user_session['user_id'] = $user['user_id'];

        $user_model->registerSession($user_session);
        /**  登陆成功记录登陆信息 **/
        $model_login = new UsersLoginModel();
        $model_login->record_login($user['user_id'], UsersLoginModel::MODILE);
        $json['returns'] = 1;
        $json['msg'] = '登录成功!';
//        $referer = Session::get('referer');
//        if(empty($referer))
//        {
//            if(empty($user['mobile']))
//            {
//                // 旧账号没有手机号码的要求完善用户资料（绑定手机）
//                $json['no_mobile'] = 1;
//                $json['referer'] = Url::to(['Index/User/perfect_information_page']);
//            }
//            else{
//                $json['referer'] = Url::to(['Index/User/index']);
//            }
//
//        }
//        else{
//            $json['referer'] = $referer;
//        }
        //销毁秘钥
        $this->openssl_finished($_POST['md5_pubKey']);
        exit(json_encode($json));
    }

    //忘记密码
    public function forgetpwd(){
        $this->loginState(1);
        $this->display();
    }

    //移动端用绑定注册 第一步
    public function register(){
        $this->loginState(1);
        $csrf = md5(time().rand(1000,9999));
        $_SESSION['register']['_c'] = $csrf;
        $this->assign('_c',$csrf);
//        if( !empty($_POST['mobile']) && !empty($_POST['code']) ){
//            require_once(Func.'validate.php');
//            $json['returns'] = 0;
//            $mobile = trim($_POST['mobile']);
//            $code = trim($_POST['code']);
//            if(!validate_mobile($mobile)) {
//                $json['msg'] = '手机号码格式不正确';
//                exit(json_encode($json));
//            }
//            //检查手机号是否注册
//            $user_model = new UsersInfoModel();
//            $sql = 'SELECT user_id FROM '.$user_model->getTable().' WHERE mobile = :mobile';
//            $sql_where['mobile'] = $mobile;
//            $user_info = $user_model->getRowSafely($sql,$sql_where);
//            if(!empty($user_info)){
//                $json['msg'] = '手机号已注册';
//                exit(json_encode($json));
//            }
//            $sms = new SMS();
//            if ( $sms->validate_code($mobile, $code) === false ) {
//                $json['msg'] = '验证码错误';
//                exit(json_encode($json));
//            }
//            $_SESSION['register'] = array();
//            $_SESSION['register']['mobile'] = trim($_POST['mobile']);
//            $json['returns'] = 1;
//            exit(json_encode($json));
//        }
        $this->display();
    }

    //移动端用绑定注册 第二步 改内容取消
//    public function regMsg(){
//        $this->loginState(1);
//        if( !isset($_SESSION['register']) || empty($_SESSION['register']['mobile'])){
//            header('location:'.__HOME__.'wap/Login-register.html');
//            exit;
//        }
//        if( !empty($_POST['username']) && !empty($_POST['nickname']) ){
//            require_once(Func.'validate.php');
//            $json['returns'] = 0;
//            $user_name = trim($_POST['username']);
//            $nickname = trim($_POST['nickname']);
//            //检查账号是否注册
//            $user_model = new UsersInfoModel();
//            $sql = 'SELECT user_id FROM '.$user_model->getTable().' WHERE user_name = :user_name';
//            $sql_where['user_name'] = $user_name;
//            $user_info = $user_model->getRowSafely($sql,$sql_where);
//            if(!empty($user_info)){
//                $json['msg'] = '用户名已注册';
//                exit(json_encode($json));
//            }
//            //检查用户名
//            if(!validate_user_name($user_name)){
//                $json['msg'] = '用户名格式错误';
//                exit(json_encode($json));
//            }
//            $_SESSION['register']['user_name'] = $user_name;
//            $_SESSION['register']['nickname'] = $nickname;
//            $json['returns'] = 1;
//            exit(json_encode($json));
//        }
//        $this->assign('mobile',$_SESSION['register']['mobile']);
//        $this->display();
//    }

    //移动端用绑定注册 第三步
    public function setPwd(){
        $this->loginState(1);
        if( !isset($_SESSION['register']) || empty($_SESSION['register']['mobile']) ){
            header('location:'.__HOME__.'wap/Login-register.html');
            exit;
        }
        $csrf = md5(time().rand(1000,9999));
        $_SESSION['register']['_c'] = $csrf;
        $this->assign('_c',$csrf);
        $this->display();
    }

    //微信绑定手机号
    public function bindMobile(){
        $this->loginState(1);
        if( empty($_SESSION['unionid']) || empty($_SESSION['openid'])){
            header('location:'.__HOME__.'wap/Login-login.html');
            exit;
        }
        $_SESSION['wxbind_code'] = md5(time().rand(1000,9999));
        $this->assign('_c',$_SESSION['wxbind_code']);
        $this->display();
    }

    //绑定操作
    public function bind(){
        $json['status'] = 0;
        $user_info = new UsersInfoModel();
        $user_table = $user_info->getTable();
        //微信状态检查
        if( empty($_SESSION['unionid']) || empty($_SESSION['openid']) ) {
            $json['msg'] = '微信身份失效请重新进入';
            exit(json_encode($json));
        }
        require_once(Func.'validate.php');
        if( empty($_POST['code']) || empty($_POST['mobile']) ){
            $json['msg'] = '网络连接失败，请稍后再试';
            exit(json_encode($json));
        }
        $mobile = trim($_POST['mobile']);
        if( !validate_mobile($mobile) ) {
            $json['msg'] = '请输入正确的手机号码';
            exit(json_encode($json));
        }
        $order_info = new OrderInfoModel();
        //检查验证码
        $sms = new SMS();
        if($sms->validate_code($mobile, $_POST['code']) === true){
            //检查手机号是否存在
            $where = 'mobile = '.$_POST['mobile'];
            $check_state = $user_info->getAll(array('field'=>'user_id,nickname,user_name,wx_openid,p_id,wx_openid1','where'=>$where));
            //有-绑定用户 没-创建用户
            if(!empty($check_state)){
                // 已存在的账号
                if(count($check_state)>1){
                    $json['msg'] = '手机号存在绑定多个账号<br />请联系客服处理';
                    exit(json_encode($json));
                }
                //检查openid 是否存在
                $where = ' wx_openid1 = "'.$_SESSION['openid'].'" and wid > 0 and user_name is null';
                $openid_user = $user_info->getAll(array('field'=>'user_id,p_id','where'=>$where));
                if( !empty($openid_user) ){
                    //检查是否多个
                    if( count($openid_user) > 1){
                        $json = array(
                            'status' => 0,
                            'msg'	 => '您的微信号已绑定过多次<br />请联系客服处理'
                        );
                        exit(json_encode($json));
                    }
                    //检查是否同一id
                    if( $openid_user[0]['user_id'] != $check_state[0]['user_id'] ){
                        //检查是否存在上下级
                        if($check_state[0]['p_id'] != 0 && $openid_user[0]['p_id'] != 0 ){
                            $json = array(
                                'status' =>	0,
                                'msg'	=> '存在上级绑定无法同步数据<br />请联系客服处理'
                            );
                            exit(json_encode($json));
                        }
                        //检查旧账号是否下过订单
                        $order_num = $order_info->getOne(array('field'=>'count(*)','where'=>'user_id = '.$openid_user[0]['user_id']));
                        if( $order_num != 0 ){
                            $json = array(
                                'status' =>	0,
                                'msg'	=> '旧商城账号下过订单无法同步<br />请联系客服处理'
                            );
                            exit(json_encode($json));
                        }
                        //同步信息
                        if(!Tongbu::oldWxToPc($openid_user[0]['user_id'],$check_state[0]['user_id'])){
                            $json = array(
                                'status' =>	0,
                                'msg'	=> '旧商城新商城账号信息同步失败<br />请联系客服处理'
                            );
                            exit(json_encode($json));
                        }else{
                            //同步成功配置登录
                            $login_info['user_id'] = $check_state[0]['user_id'];
                            $login_info['nickname'] = $check_state[0]['nickname'];
                            $login_info['user_name'] = $check_state[0]['user_name'];
                            $user_info->registerSession($login_info);
                            /**  登陆成功记录登陆信息 **/
                            $model_login = new UsersLoginModel();
                            $model_login->record_login($_SESSION['user']['user_id'], UsersLoginModel::MODILE);
                            if( isset($_SESSION['info_error']) && !empty($_SESSION['info_error']) ){
                                $_SESSION['info_error'] = 0;
                            }
                            $json = array(
                                'status' => 1,
                                'msg'	 => '绑定成功'
                            );
                            exit(json_encode($json));

                        }
                    }
                }
                //检查unionid 是否存在
                $where = ' wx_openid = "'.$_SESSION['unionid'].'" and wid > 0 and user_name is null';
                $unionid_user = $user_info->getAll(array('field'=>'user_id,p_id','where'=>$where));

                if( !empty($unionid_user) ){
                    //检查是否多个
                    if( count($unionid_user) > 1 ){
                        $json = array(
                            'status'	=> 0,
                            'msg'		=> '您的微信号已绑定过多次<br />请联系客服处理'
                        );
                        exit(json_encode($json));
                    }
                    //检查是否同一id
                    if($unionid_user[0]['user_id'] != $check_state[0]['user_id']){
                        //检查是否存在上下级
                        if($check_state[0]['p_id'] != 0 && $unionid_user[0]['p_id'] != 0 ){
                            $json = array(
                                'status' =>	0,
                                'msg'	=> '存在上级绑定无法同步数据<br />请联系客服处理'
                            );
                            exit(json_encode($json));
                        }
                        //检查旧账号是否下过订单
                        $order_num = $order_info->getOne(array('field'=>'count(*)','where'=>'user_id = '.$unionid_user[0]['user_id']));
                        if( $order_num != 0 ){
                            $json = array(
                                'status' =>	0,
                                'msg'	=> '旧商城账号下过订单无法同步<br />请联系客服处理'
                            );
                            exit(json_encode($json));
                        }
                        //同步信息
                        if(!Tongbu::oldWxToPc($unionid_user[0]['user_id'],$check_state[0]['user_id'])){
                            $json = array(
                                'status' =>	0,
                                'msg'	=> '旧商城新商城账号信息同步失败<br />请联系客服处理'
                            );
                            exit(json_encode($json));
                        }else{
                            //同步成功配置登录
                            $login_info['user_id'] = $check_state[0]['user_id'];
                            $login_info['nickname'] = $check_state[0]['nickname'];
                            $login_info['user_name'] = $check_state[0]['user_name'];
                            $user_info->registerSession($login_info);
                            /**  登陆成功记录登陆信息 **/
                            $model_login = new UsersLoginModel();
                            $model_login->record_login($_SESSION['user']['user_id'], UsersLoginModel::MODILE);
                            $json = array(
                                'status' => 1,
                                'msg'	 => '绑定成功'
                            );
                            exit(json_encode($json));
                        }
                    }
                }

                //检查 账号是否绑定过 微信账号
                $where = 'mobile = '.$_POST['mobile'];
                $state = false;
                if( !empty($check_state[0]['wx_openid']) || !empty($check_state[0]['wx_openid1']) ){
                    if( $_SESSION['unionid'] == $check_state[0]['wx_openid'] ){
                        if( isset($_SESSION['openid']) && !empty($_SESSION['openid']) ){
                            $set['wx_openid1'] = $_SESSION['openid'];
                            $state = $user_info->update($set,$where);
                        }else{
                            $state = true;
                        }
                    }else if( $_SESSION['openid'] == $check_state[0]['wx_openid1']){
                        if( isset($_SESSION['unionid']) && !empty($_SESSION['unionid']) ){
                            $set['wx_openid'] = $_SESSION['unionid'];
                            $state = $user_info->update($set,$where);
                        }else{
                            $state = true;
                        }
                    }else{
                        $json = array(
                            'status' => 0,
                            'msg'	 => '该手机已绑定过微信'
                        );
                        exit(json_encode($json));
                    }
                }else{
                    //微信 unionid 绑定
                    if( isset($_SESSION['openid']) && !empty($_SESSION['openid']) ){
                        $set['wx_openid1'] = $_SESSION['openid'];
                    }
                    $set['wx_openid'] = $_SESSION['unionid'];
                    $state = $user_info->update($set,$where);
                }
                if($state){
                    $login_info['user_id'] = $check_state[0]['user_id'];
                    $login_info['nickname'] = $check_state[0]['nickname'];
                    $login_info['user_name'] = $check_state[0]['user_name'];
                    $user_info->registerSession($login_info);
                    /**  登陆成功记录登陆信息 **/
                    $model_login = new UsersLoginModel();
                    $model_login->record_login($_SESSION['user']['user_id'], UsersLoginModel::MODILE);
                    $json = array(
                        'status' => 1,
                        'msg'	 => '绑定成功'
                    );
                }else{
                    $json = array(
                        'status' => 2,
                        'msg' 	=> '网络错误，请稍后再试！',
                        'url'	=> 'Index-index.html'
                    );
                }
            }else{
                $_SESSION['register']['user_tel'] = $_POST['mobile'];
                $json = array(
                    'status' => 2,
                    'msg'	 => '手机号未注册',
                    'url'	 => 'Login-wxSetPwd.html'
                );
            }
        }else{
            $json = array(
                'status' => 0,
                'msg' 	 => '验证码错误'
            );
        }
        exit(json_encode($json));
    }

    //微站用 设置密码
    public function wxSetPwd(){
        $this->loginState(1);
        if( !stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') || empty($_SESSION['openid']) || empty($_SESSION['unionid']) || empty($_SESSION['register']['user_tel']) ){
            header('location:'.__HOME__.'wap/Login-index.html');
            exit;
        }
        $user_model = new UsersInfoModel();
        $pid_state = 0;
        //openid 查询是否 旧商城用户
        $where = 'wx_openid1 = "'.$_SESSION['openid'].'"';
        $openid_user_pid = $user_model->getRow(array('field'=>'user_id','where'=>$where));
        if(!empty($openid_user_pid)){
            $pid_state = 1;
        }
        //unionid 查询是否 旧商城用户
        $where = 'wx_openid = "'.$_SESSION['unionid'].'"';
        $unionid_user_pid = $user_model->getRow(array('field'=>'user_id','where'=>$where));
        if(!empty($openid_user_pid)){
            $pid_state = 1;
        }
        $this->assign('pid_state',$pid_state);
        $csrf = md5(time().rand(1000,9999));
        $_SESSION['register']['_wxc'] = $csrf;
        $this->assign('_wxc',$csrf);
        $this->display();
    }

    //app去积分商城入口
    public function goExchange(){
        $reg ="/rmthmessenger/";
        if(preg_match_all($reg, $_SERVER['HTTP_USER_AGENT'], $matches)){
            $url = urlencode(__HOME__.'?g=WapSite&c=Login&a=exchange');
            header('location:'.__HOME__.'connect/oauth2/authorize.php?appid=rmth_sc&redirect_uri='.$url.'&response_type=code&scope=snsapi_base&state=zvcd');
            exit;
        }else{
            header('location:'.__HOME__.'wap/Exchange-index.html');
            exit;
        }
    }

    public function exchange(){
        $data['appid'] = 'rmth_sc';
        $data['secret'] = '7uje83s6ps16gsqoaypei6g5xt40ge5xqh1xppown4vf5f33f13a2myi4jd7hus7i3vjgz9e8bs4hnqa';
        $data['code'] = $_GET['code'];
        $data['grant_type'] = 'authorization_code';
        $returns = json_decode($this->http_post(__HOME__.'?g=Sns&c=OAuth2&a=access_token',$data),true);
        if( !empty($returns['openid']) ){//授权登录
            $user_model = new UsersInfoModel();
            $sql = 'SELECT user_id,nickname,user_name FROM '.$user_model->getTable().' WHERE user_id = :user_id';
            $userinfo = $user_model->getRowSafely($sql,['user_id'=>$returns['openid']]);
            if( !empty($userinfo) ){
                $login_mess = array(
                    'user_id' => $userinfo['user_id'],
                    'nickname' => $userinfo['nickname'],
                    'user_name' => $userinfo['user_name']
                );
                //登录并配置登录信息
                $user_model->registerSession($login_mess);
                /**  登陆成功记录登陆信息 **/
                $model_login = new UsersLoginModel();
                $model_login->record_login($_SESSION['user']['user_id'], UsersLoginModel::MODILE);
            }
            header('location:'.__HOME__.'wap/Exchange-index.html');
        }
    }

    //app去抢红包入口
    public function goPlayRed(){
        $reg ="/rmthmessenger/";
        if(preg_match_all($reg, $_SERVER['HTTP_USER_AGENT'], $matches)){
            $url = urlencode(__HOME__.'?g=WapSite&c=Login&a=playRed');
            header('location:'.__HOME__.'connect/oauth2/authorize.php?appid=rmth_sc&redirect_uri='.$url.'&response_type=code&scope=snsapi_base&state=zvcd');
            exit;
        }else{
            header('location:'.__HOME__.'wap/User-redPacketAP.html');
            exit;
        }
    }

    public function playRed(){
        $data['appid'] = 'rmth_sc';
        $data['secret'] = '7uje83s6ps16gsqoaypei6g5xt40ge5xqh1xppown4vf5f33f13a2myi4jd7hus7i3vjgz9e8bs4hnqa';
        $data['code'] = $_GET['code'];
        $data['grant_type'] = 'authorization_code';
        $returns = json_decode($this->http_post(__HOME__.'?g=Sns&c=OAuth2&a=access_token',$data),true);
        if( !empty($returns['openid']) ){//授权登录
            $user_model = new UsersInfoModel();
            $sql = 'SELECT user_id,nickname,user_name FROM '.$user_model->getTable().' WHERE user_id = :user_id';
            $userinfo = $user_model->getRowSafely($sql,['user_id'=>$returns['openid']]);
            if( !empty($userinfo) ){
                $login_mess = array(
                    'user_id' => $userinfo['user_id'],
                    'nickname' => $userinfo['nickname'],
                    'user_name' => $userinfo['user_name']
                );
                //登录并配置登录信息
                $user_model->registerSession($login_mess);
                /**  登陆成功记录登陆信息 **/
                $model_login = new UsersLoginModel();
                $model_login->record_login($_SESSION['user']['user_id'], UsersLoginModel::MODILE);
            }
            header('location:'.__HOME__.'wap/User-redPacketAP.html');
        }
    }

    /**
     *  发送post请求
     * @param string $url
     * @param string $data
     * @return mixed
     */
    public function http_post($url,$post_data){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        if(stripos($url,'https') !== false){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $output = curl_exec($ch);
        if($output){
            curl_close($ch);
            return $output;
        }else{
            $error = curl_error($ch);
            curl_close($ch);
            return $error;
        }
    }


}
?>