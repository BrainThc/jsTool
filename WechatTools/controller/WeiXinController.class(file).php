<?php
/**
 * User: thc
 */
class WeiXinController extends Controller
{   
    protected static $appid = 'wx634a21407ee85ff5';
    protected static $appsecret = '58680e5c79a75df5b3785ebac05b762e';
    function __construct()
    {
        parent::__construct();
    }

    //金融中心微信静默授权服务器返回方法
    function FcredirectUri(){
        $fc_user_model = new UsersInfoModel();
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
            // $model_login = new UsersLoginModel();
            // $model_login->record_login($_SESSION['user']['user_id'], FcUsersLoginModel::MODILE);
            $url = $back_url;
        }else{
            //绑定微信号
            $url = __HOME__.'?g=WapSite&c=FinancialL&a=bindMobile';
        }
        //不能用header 不能用header
        echo "<script>location='".$url."'</script>";
    }


    //微信配置config
    public function getConfig(){
        //微信配置
        $timestamp = time();
        $nonceStr = $this -> getNonceStr();
        // $config['appId'] = self::$appid;
        $config['appId'] = self::$appid;
        $config['timestamp'] = $timestamp;
        $config['nonceStr'] = $nonceStr;
        $config['signature'] = $this -> getJsSignature($timestamp,$nonceStr);
        //微信分享配置
        $shareMessage = $this->get_shareMessage('con');
        $config_info = array(
                'configData' => json_encode($config),
                'shareMessage' => json_encode($shareMessage)
            );
        exit($config_info);
    }

    public function getConfigParam($url,$share){
        //微信配置
        $timestamp = time();
        $nonceStr = $this -> getNonceStr();
        // $config['appId'] = self::$appid;
        $config['appId'] = self::$appid;
        $config['timestamp'] = $timestamp;
        $config['nonceStr'] = $nonceStr;
        $config['signature'] = $this -> getJsSignature($timestamp,$nonceStr);
        //微信分享配置
        $shareMessage = $this->get_shareMessage($url,$share);
        $config_info = array(
                'configData' => json_encode($config),
                'shareMessage' => json_encode($shareMessage)
            );

        return $config_info;
    }

    /**
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }

    /**
     *获取js签名
     */
    public function getJsSignature($timestamp,$nonceStr){
        $file_path = ROOT.'cache/';
        $name = 'jsapiTicket.txt';
        $filename = $file_path.$name;
        //查看缓存
        $data=file_get_contents($filename);
        if ($data) {//存在缓存
            $data=unserialize($data);
            $now_time=time();
            if ($now_time-7000<$data['time']) {//ticket没过期
                $jsapiTicket=$data['ticket'];
            }else{//ticket过期,从新获取
                $accessTokenArr = $this->get_access_token();
                $accessToken = $accessTokenArr['access_token'];//1获取accesstoken;
                $jsapiTicketArr = $this->get_ticket($accessToken);
                $jsapiTicket = $jsapiTicketArr['ticket'];//2获取jsapiTicket
                $data=array('ticket'=>$jsapiTicketArr['ticket'],'time'=>time());
                $data=serialize($data);
                file_put_contents($filename, $data);
            }
        }else{//缓存不存在 重新获取
            $accessTokenArr = $this->get_access_token();
            $accessToken = $accessTokenArr['access_token'];//1获取accesstoken;
            $jsapiTicketArr = $this->get_ticket($accessToken);
            $jsapiTicket = $jsapiTicketArr['ticket'];//2获取jsapiTicket
            $data=array('ticket'=>$jsapiTicketArr['ticket'],'time'=>time());
            $data=serialize($data);
            file_put_contents($filename, $data);
        }
        $url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        // $url = SHAREURL;
        // return $url;
        //3.签名算法
        $param = array(
            'noncestr' =>$nonceStr,
            'jsapi_ticket' => $jsapiTicket,
            'timestamp' => $timestamp,
            'url' => $url
        );//形成数组，让后按字典序排序
        //3.1步骤1. 对所有待签名参数按照字段名的ASCII 码从小到大排序（字典序）
        ksort($param);

        //3.2使用URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串string1：
        $string ='';
        foreach($param as $key => $value){
            $string .= $key.'='.$value.'&';
        }

        $string = rtrim($string,"&");

        $str = sha1($string);//3.3 对string1进行sha1签名

        return $str;
    }

    //获取access_token
    function get_access_token(){
        $wxUrl = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.self::$appid.'&secret='.self::$appsecret;
        $info = $this->httpGet($wxUrl);
        return $info;
    }

    //获取ticket
    function get_ticket($accessToken){
        $ticketUrl = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$accessToken.'&type=jsapi';
        $ticket = $this->httpGet($ticketUrl);
        return $ticket;
    }

    //配置分享内容
    function get_shareMessage($url,$share){
        $shareMessage = array(
            'title' => $share['title'],
            'digest' => $share['digest'],
            'link'  => $url,
            'img'   => $share['img']
        );
        return $shareMessage;

    }

    /**
     * 分享链接进入 登录返回Url处理方法
     * @return [type] [description]
     */
    function backUrl($type){
        $url = '';
        $share['title'] = '通惠购';
        $share['digest'] = '全民创业从通惠购开始，惠分享 · 惠生活，不止是购物的商城！';
        $share['img'] = WapImg.'home/home_logo.png';
        if(isset($_GET['c']) && !empty($_GET['c']) && isset($_GET['a']) && !empty($_GET['a'])){
            $c = $_GET['c'];
            $a = $_GET['a'];
            $ids = empty($_GET['id']) ? '' : $_GET['id'];
            $pid = empty($_GET['pid']) ? '' : $_GET['pid'];
            $out_id = empty($_GET['outlet_id']) ? '' : $_GET['outlet_id'];
            $agent_id = empty($_GET['agentId']) ? '' : $_GET['agentId'];
            $keyword = empty($_GET['keyword']) ? '' : $_GET['keyword'];
            $url = __HOME__.'wap/';
            switch($c){
                case 'Order' :
                    $url .= 'Index-index';
                    break;
                case 'User' :
                    $url .= 'Index-index';
                    break;
                case 'Cart' :
                    $url .= 'Index-index';
                    break;
                case 'Search' :
                    if( !empty($keyword) ){
                        $url .= $c.'-'.$a.'-'.$keyword;
                    }else{
                        $url .= 'Index-index';
                    }
                    break;
                case 'Article' :
                    if(isset($ids) && !empty($ids)){
                        $url .= $c.'-'.$a.'-'.$ids;
                    }else{
                        $url .= $c.'-'.$a;
                    }
                    break;
                case 'Category' :
                    if( $a == 'productlist' || $a == 'goods' || $a == 'thgoods'){
                        $url .= $c.'-'.$a;
                        if( $a == 'productlist' && !empty($pid) ){
                            $url .= '-'.$pid;
                        }
                        if( $a == 'goods' && !empty($ids) ){
                            $url .= '-'.$ids;
                            if( !empty($out_id) && empty($agent_id) ){
                                $url .= '-'.$out_id;
                            }else if( empty($out_id) && !empty($agent_id)){
                                $url .= '-0-'.$agent_id;
                            }
                            $goodsInfo = $this->goods_info($ids);
                        }
                        if( $a == 'thgoods' && !empty($ids) ){
                            $url .= '-'.$ids;
                            if( !empty($out_id) ){
                                $url .= '-'.$out_id;
                            }
                            //goods_id 获取 item_id
                            $goods_model = new GoodsItemModel();
                            $item_ids = $goods_model -> getRow(array('field'=>'item_id','where'=>'deleted = 0 and goods_id = '.$ids,'order'=>'item_price asc','limit'=>1));
                            if(!empty($item_ids)){
                                $goodsInfo = $this->goods_info($item_ids['item_id']);
                            }
                        }
                    }else if($a = 'index' ){
                        $url .= $c.'-'.$a;
                    }else{
                        $url .= 'Index-index';
                    }
                    break;
                case 'Outlets' :
                    $url .= $c.'-'.$a;
                    if( $a == 'home' && !empty($ids) ){
                        $url .= '-'.$ids;
                    }
                    if( $a == 'goods' && !empty($ids) ){
                        $url .= '-'.$ids;
                        $goodsInfo = $this->goods_info($ids);
                    }
                    if( $a == 'shop' && !empty($ids)){
                        $url .= '-'.$ids;
                    }
                    break;
                case 'Store' :
                    if( !empty($ids) ){
                        $url .= $c.'-'.$a.'-'.$ids;
                        $store_info = $this->get_stroe($ids);
                        if(!empty($store_info)){
                            $share['title'] = '通惠商家购';
                            $share['digest'] = '欢迎光临'.$store_info['store_name'];
                            $share['img'] = $store_info['store_label'];
                        }
                    }else{
                        $url .= $c.'-'.$a;
                    }
                    break;
                case 'Special' :
                        $url .= $c.'-'.$a.'-'.$ids;
                        $special_info = $this->get_special($ids);
                        if(!empty($special_info)){
                            $share['title'] = $special_info;
                            $share['digest'] = $special_info;
                        }
                    break;
                case 'SpecialActivity' :
                        $url .= $c.'-'.$a.'-'.$ids;
                        $act_info = $this->get_special_act($ids);
                        if(!empty($get_special_act)){
                            $share['title'] = $act_info['ac_name'];
                            $share['digest'] = $act_info['ac_title'];
                        }
                    break;
                case 'Brand' :
                    $url .= $c.'-'.$a;
                    if( $a == 'view' && !empty($ids) ){
                        $url .= '-'.$ids;
                        $b_info = $this->get_brand($ids);
                        if(!empty($b_info)){
                            $share['title'] = $b_info['brand_name'];
                            $share['digest'] = $b_info['brand_name'].' - 品牌详情 - 通惠购微网站';
                            if( !empty($b_info['brand_pic']) ){
                                $share['img'] = $b_info['brand_pic'];
                            }
                        }
                    }
                    break;
                case 'Agent' :
                    if( isset($_GET['agent_id']) && !empty($_GET['agent_id']) ){
                        $url .= 'Agent-agentGoods-'.$_GET['agent_id'];
                    }
                    $share['title'] = '代理中心';
                    $share['digest'] = '代理中心 - 商品列表 - 通惠购微网站';
                    break;
                case 'Exchange' :
                    $url .= $c.'-'.$a;
                    if( $a == 'goods' && !empty($ids) ){
                        $url .= $c.'-'.$a.'-'.$ids;
                    }
                    break;
                default:
                    $url .= $c.'-'.$a;
                    break;
            }   
            $url .= '.html';
        }else{
            $url .= 'Index-index.html';
        }
        if($type == 'back'){
            return $url;
        }else{
            if(!empty($goodsInfo)){
                $share['title'] = '商品详情';
                $share['digest'] = $goodsInfo['goods_name'];
                $share['img'] = $goodsInfo['list_image'];
            }
            return $share;
        }
    }

    //提示关注页
    public function guanzhu(){
        $this->display();
    }

    //获取分享商品页的信息配置
    public function goods_info($ids){
        $goods_model = new GoodsModel();
        $sql = 'select g.goods_name,im.list_image from rmth_goods as g left join rmth_goods_item as i on i.goods_id = g.goods_id left join rmth_goods_images as im on im.goods_id = g.goods_id where i.item_id = '.$ids;
        $goods_info = $goods_model->getRow($sql);
        return $goods_info;
    }

    //主题页获取分享信息
    public function get_special($ids){
        $special_model = new SpecialModel();
        $special_info = $special_model->getOne(array('field'=>'special_name','where'=>' special_id = '.$ids));
        return $special_info;
    }

    public function get_stroe($ids){
        $store_model = new StoreModel();
        $store_info = $store_model -> getRow(array('field'=>'store_name,store_label','where'=>' store_id = '.$ids));
        return $store_info;
    }
    //获取主题活动页面
    public function get_special_act($ids){
        $ac_model = new SpecialActivityModel();
        $ac_info = $ac_model->getRow(array('field'=>'ac_name,ac_title','where'=>' ac_id = '.$ids));
        return $ac_info;
    }

    //获取品牌页面的详情title
    public function get_brand($ids){
        $b_model = new BrandModel();
        $b_info = $b_model->getRow(array('field'=>'brand_name,brand_pic','where'=>'brand_id = '.$ids));
        return $b_info;
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

    /**
     * 用户获取微信身份信息页面
     */
    public function WeChatId(){
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        //发送短信
        // $code = time().rand(1000,9999).$_SESSION['user']['user_id'];
        // $code = 
        $mobile = '15013004683';
        // $content = '【通惠购】您本次的服务号为：'.$code.'（通惠客服绝不会索取此服务号，切勿告知他人）。服务号有效使用2次。超过两次后请联系客服，注明情况后重新获取服务号。如有问题请致电400-090-1333';
        // $info = file_get_contents('http://api.smsbao.com/sms?u=rmth&p='.md5('rmthyfj852').'&m='.$mobile.'&c='.urlencode($content));
        // if($info){
        //     $return['status'] = 0;
        //     $return['reason'] = '短信发送失败,请稍后再试！';
        // }
        $this->display();
    }
  
}