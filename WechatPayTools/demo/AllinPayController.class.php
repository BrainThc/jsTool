<?php
defined('PTS80')||exit('PTS80 Defined');

class AllinPayController extends Controller{
    //微信订单支付
    public function OrderwPay(){
        $user = session::get('user');
        //支付单号
        $payment_number = Request::post('payment_number');
        $payment_number = trim($payment_number);
        $payment_type = Request::post('payType');
        $return['status'] = 'error';
        //核对订单金额
        $dao = Dao::instance();
        $sql = 'select pay.trade_money,sum(o.order_amount) as order_money from '.$dao->table('order_payment').' as pay';
        $sql .= ' left join '.$dao->table('order_payment_item').' as i on pay.order_payment_id = i.order_payment_id';
        $sql .= ' left join '.$dao->table('order_info').' as o on o.order_number = i.order_number';
        $sql .= ' where pay.payment_number = '.$payment_number;
        $payment_info = $dao->queryOne($sql);
        if(empty($payment_info)){
            OrderMasterModel::debug('balance payment','会员:'.$user['user_id'].'支付单号'.$payment_number.'支付类型:通联'.' 订单金额核对失败');
            $return['errorMsg'] = '订单核对异常，请稍后再试';
        }else if($payment_info['trade_money'] != $payment_info['order_money']){
            OrderMasterModel::debug('balance payment','会员:'.$user['user_id'].'支付单号'.$payment_number.'支付类型:通联'.' 订单金额核对异常，请稍后再试');
            $return['errorMsg'] = '订单核对异常，请稍后再试';
        }else{
            if( !($payment_info['trade_money'] > 0) || $payment_info['trade_money'] == '0.00' ){
                $return['errorMsg'] = '下单成功';
            }else{
                $Fmoney = $payment_info['trade_money']*100;
                $money = $payment_info['trade_money'];
                $return['status'] = 'ok';
            }
        }
        if($return['status'] == 'error'){
            exit(json_encode($return));
        }else{
            $return['status'] = 'error';
        }
        //检查是否团购订单
        $dao = Dao::instance();
        $sql = 'SELECT g.gid,g.group_limit,g.parent_id FROM '.$dao->table('order_payment').' AS opay';
        $sql .= ' INNER JOIN '.$dao->table('order_payment_item').' AS opayi ON opayi.order_payment_id = opay.order_payment_id';
        $sql .= ' INNER JOIN '.$dao->table('order_info').' AS o ON o.order_number = opayi.order_number';
        $sql .= ' INNER JOIN '.$dao->table('group_order').' AS g ON g.order_id = o.order_id';
        $sql .= ' WHERE o.group_id > 0 AND opay.payment_number = '.$payment_number;
        $group_info = $dao->queryOne($sql);
        if( !empty($group_info) ){
            $group_ids = empty($group_info['parent_id']) ? $group_info['gid'] : $group_info['parent_id'];
            //检查当前成团人数
            $sql = 'SELECT g.* FROM '.$dao->table('group_order').' AS g';
            $sql .= ' INNER JOIN '.$dao->table('order_info').' AS o ON o.order_id = g.order_id';
            $sql .= ' WHERE o.order_status = 1 AND o.pay_status = 1 AND g.parent_id = '.$group_ids;
            $group_partner = $dao->queryAll($sql);
            if( count($group_partner)+1 >= $group_info['group_limit'] ){
                $return['status'] = 'error';
                $return['errorMsg'] = '该团队已成团，订单将自动取消';
                exit(json_encode($return));
            }
            //检查是否已参团
            if( !empty($group_partner) ){
                foreach( $group_partner as $v){
                    if( $v['user_id'] == $user['user_id'] ){
                        $return['status'] = 'error';
                        $return['errorMsg'] = '您已参加该团，订单将自动取消';
                        exit(json_encode($return));
                    }
                }
            }
        }
        switch($payment_type){
            case 'allinpay' :// 通联银联
                $allin = $this->OrderAllinpay($user['user_id'],$payment_number,$Fmoney);
                if($allin['returns'] != 'ok' ){
                    $return['errorMsg'] = '支付出错，请稍后再试';
                }else{
                    $return['status'] = 'ok';
                    $return['h'] = $allin['h'];
                }
                break;
            case 'wxPay' : //通联微信支付
                //获取会员openid
                $user_model = new UsersInfoModel();
                $userInfo = $user_model->getRow(array(
                        'field' => 'wx_openid,wx_openid1',
                        'where' => 'user_id = '.$user['user_id']
                    ));
                if( empty($userInfo) ){
                    $return['errorMsg'] = '获取用户身份失败';
                }else{
                    $openid = $userInfo['wx_openid1'];
                    if( empty($openid) && !empty($_COOKIE['openid']) ){
                        $openid = $_COOKIE['openid'];
                    }
					//通惠商户
                   $allin =  $this->weixinPay($payment_number,$openid);
				   //通联-微信支付
//                    $allin = $this->OrderWxAliPayDo($user['user_id'],$openid,$payment_number,$money,'W02');//临时转换为原通惠商户支付
                    if($allin['returns'] != 'ok'){
                        $return['errorMsg'] = $allin['msg'];
                    }else{
                        $return['status'] = 'ok';
                        $return['s'] = $allin['s'];
                    }
                }
                break;
            case 'alPay' : //支付宝支付
                $return['errorMsg'] = '暂未开通该支付';
                break;
            default :
                $return['errorMsg'] = '请选择支付类型';
                break;
        }
        exit(json_encode($return));
    }

    private function OrderAllinpay($user_id,$payment_number,$money){
        //签名部分
        $datas1 = array(
                    'signType' => 0,
                    'merchantId' => '009440648160043',
//                    'partnerUserId' => $user_id//正式使用
                    'partnerUserId' => '94360'//会员id 正式测试会员 id：94360
                    );
        $key = 'a678400d568e2c189914bb79881ac1d9';
        $query1 = '&';
        foreach($datas1 as $v=>$e){
            $query1 .= $v.'='.$e.'&';
        }
        $datas1['signMsg'] = strtoupper(md5($query1.'key='.$key.'&'));
        $json = json_decode($this->http_post('https://service.allinpay.com/usercenter/merchant/UserInfo/reg.do',$datas1),true);
        if(!in_array($json['resultCode'],array('0000','0006'))){
            exit;
        }
        $datas2 = array(
            'inputCharset'  => 1,
            'pickupUrl'     => __HOME__.'wap/Order-payOrderSuccess-'.$payment_number.'.html',
            'receiveUrl'    => Url::base() . '/librarys/allinpay/notify.php',
            'version'       => 'v1.0',
            'language'      => 1,
            'signType'      => 1,
            'merchantId'    => '009440648160043',
            'orderNo'       => $payment_number,
            'orderAmount'   => $money,
            'orderCurrency' => 156,
            'orderDatetime' => date('YmdHis'),
            'ext1'          => '<USER>'.$json['userId'].'</USER>',
            'ext2'          => $payment_number,
            'payType'       => 33
            );
        $query2 = '';
        foreach($datas2 as $k=>$d){
            $query2 .= $k.'='.$d.'&';
        }
        $datas2['signMsg'] = strtoupper(md5($query2.'key='.$key));
        $html = '<form action="https://cashier.allinpay.com/mobilepayment/mobile/SaveMchtOrderServlet.action" id="TFrom" method="POST">';
        foreach($datas2 as $k=>$d){
            $html .= '<input type="hidden" name="'.$k.'" value="'.$d.'" />';
        }
$html .= <<<EOF
    </form>
<script type="text/javascript">
    document.getElementById('TFrom').submit();
</script>
EOF;
        $return['returns'] = 'ok';
        $return['h'] = $html;
        return $return;
    }
    //  支付宝，微信 订单支付
    private function OrderWxAliPayDo($user_id,$openid,$payment_number,$orderAccount,$payType){
        include ROOT . '/librarys/allinpay/AllinPay.php';

        $allin = new AllinPay();
        $params = [];

        $params['orderNo'] = $payment_number;
        $params['money'] = $orderAccount;
        $params['notify_url'] = Url::base().'/librarys/allinpay/allinqrcode_notify.php';
        $params['paytype'] = $payType;
        if($payType == 'W02'){
            // $params['acct'] = $openid;
            $params['acct'] = 'oYhPtw1E3WtlWcBpKHKnCo2T9cJI';
        }else if($payType == 'A02'){
            return array('returns'=>'error');
        }else{
            return array('returns'=>'error','msg'=>'没有选择类型');
        }
        $weixin_config = $allin->weixinPay($params);
        if($weixin_config === false){
            OrderMasterModel::debug('wepOrderWeiXinpay',$user_id.'用户配置出错'.$weixin_config.',参数：'.json_encode($params));
            return array('returns'=>'error','msg'=>'配置出错');
        }else{
            return array('returns'=>'ok','s'=>json_decode($weixin_config));
        }
    }

    public function weixinPay($orderId,$openId)
    {
        $allin['returns'] = 'error';
        //include(ROOT.DIRECTORY_SEPARATOR."librarys".DIRECTORY_SEPARATOR."weixin.php");
        include(ROOT . 'librarys' . DIRECTORY_SEPARATOR . 'weixin' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'WxPay.Api.php');
        include(ROOT . 'librarys' . DIRECTORY_SEPARATOR . 'weixin' . DIRECTORY_SEPARATOR . 'WxPay.JsApiPay.php');

        //get order payment info
//        $orderId = Request::get('payment_number');
//        $orderId = '9818241518244271290348';

        if (empty($orderId)) {
            $allin['msg'] = '支付单号异常';
//            exit('param error');
            return $allin;
        }
        if(!preg_match('/^\d+$/', $orderId)){
            $allin['msg'] = '支付单号异常';
//            exit('param error');
            return $allin;
        }
        //$user = Session::get('user');

        $payment = new OrderPaymentModel();
        $order = $payment->getPaymentInfoByNumber($orderId);
        if (empty($order)) {
            $allin['msg'] = '支付单号异常';
//            exit('订单没有找到');
            return $allin;
        }

        $goodsList = $payment->getPaymentOrderGoodsList($orderId);
        if (empty($goodsList)) {
            $allin['msg'] = '订单信息异常';
//            exit('找不到支付订单商品信息');
            return $allin;
        }

        $goodsName = '';
        foreach ($goodsList AS $row) {
            $goodsName .= $row['goods_name'] . ', ';
        }
        $goodsName = substr($goodsName, 0, 120);
        $orderTotal = $order['trade_money'];
        $tradeMoney = $orderTotal * 100;
        //print_r($order);exit;
        $tools = new JsApiPay();
//        $openId = $tools->GetOpenid();
        //order
        $input = new WxPayUnifiedOrder();
        $input->SetBody(mb_convert_encoding($goodsName, 'utf-8', 'utf-8,gbk'));
        //$input->SetAttach('test');
        $input->SetOut_trade_no($orderId);
        $input->SetTotal_fee($tradeMoney);
        $input->SetTime_start(date('YmdHis'));
        $input->SetTime_expire(date('YmdHis', time()+600));
        $input->SetGoods_tag('test');
        // $input->SetNotify_url('https://www.thgo8.com/librarys/weixin/notify.php');
        //$input->SetNotify_url(__HOME__.'/librarys/allinpay/allinqrcode_notify.php');
        $input->SetNotify_url(__HOME__.'/librarys/weixin/notify.php');
        $input->SetTrade_type('JSAPI');
        $input->SetOpenid($openId);
//        $input->SetOpenid($_COOKIE['openid']);
        $order = WxPayApi::unifiedOrder($input);
        //print_r($order);exit;
        $jsApiParameters = $tools->GetJsApiParameters($order);
        //echo $jsApiParameters;
//        $this->assign('jsParameters', $jsApiParameters);
//        $this->assign('orderTotal', $orderTotal);
        $allin['returns'] = 'ok';
        $allin['s'] = json_decode($jsApiParameters);
        return $allin;
//        $this->display('WapSite/Order/weixinPay');
    }

    //充值创建订单
    public function createRecharge(){
        //检查登录
        // $user = $this->checkLogin();
        $user = session::get('user');
        if(empty($user)){
            $return['errorMsg'] = '登录信息检查失败';
        }
        $t = time();
        $dataInfo = Request::post();
        $rechargeCsrf = session::get('recharge_number');
        $user_model = new UsersInfoModel();
        $userInfo = $user_model->getRow(array('field'=>'mobile,wx_openid,wx_openid1','where'=>'user_id = '.$user['user_id']));
        //端口判断
        $openid = $userInfo['wx_openid1'];
        if(stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') === true){
            if(empty($openid) && !empty($_COOKIE['openid']) ){
                $openid = $_COOKIE['openid'];
            }else{
                $return['errorMsg'] = '微信身份异常<br />请重新登录微信App';
                exit(json_encode($return));
            }
        }
        //检查重复提交
        if(!empty($rechargeCsrf) && $rechargeCsrf['csrf'] == $dataInfo['csrf']){
            Session::delete('recharge_number');
        }else{
            $return['errorMsg'] = '不要重复提交';
            exit(json_encode($return));
        }
        //充值金额
        $money = $dataInfo['money'];
        //验证码
        $code = $dataInfo['code'];
        //支付类型
        $payType = $dataInfo['payType'];
        //检查最低金额
        if($money <= 0 || $money < 0.01){
            $return['errorMsg'] = '最低 ¥ 0.01';
            exit(json_encode($return));
        }
        // 转换为 分单位
        $Fmoney = $money*100;

        $return['status'] = 'error';
        try {
            if(empty($userInfo['mobile'])){
                throw new Exception("手机号异常");
            }
            //检查验证码
            $sms = new SMS();
            if($sms->validate_code($userInfo['mobile'], $code) === false){
                throw new Exception("验证码错误");
            }

            //生成充值流水号
            $rechargeOrder = new RechargeOrderModel();
            $charge_number = $rechargeOrder -> createPaymentNumber();
            if(empty($charge_number)){
                throw new Exception("充值单号生成失败!<br />请稍后再试！");
            }
            if($payType == 'wxPay'){
                $pay_type = 'allin_weixinpay';
            }else{
                $pay_type = 'allinpay';
                
            }
            $user_model->begin();
            //生成单号数据
            $add = array(
                'recharge_number'   => $charge_number,
                'user_id'           => $user['user_id'],
                'recharge_money'    => $money,
                'user_ip'           => Request::ip(),
                'created'           => $t,
                'pay_type'          => $pay_type,
                'paid'              => 0,
                'pay_time'          => 0,
                'trade_no'          => '',
                'origin'            => 3,
            );
            if( $rechargeOrder->add($add)==false){
                throw new Exception("充值单号生成失败!<br />请稍后再试！");
            }
            $return['status'] = 'ok';
            $user_model->commit();
        } catch (Exception $e) {
            $user_model->rollback();
            $return['errorMsg'] = $e->getMessage();
        }
        if($return['status'] == 'error'){
            exit(json_encode($return));
        }else{
            $return['status'] = 'error';
        }
        switch($payType){
            case 'wxPay' ://微信
                //通联微信支付 （停用）
//                $payinfo = $this->rechargeWxAliPayDo($user['user_id'],$openid,$charge_number,$money,'W02');
                //普通微信支付
                $payinfo = $this->rechargeWeiXinPay($openid,$charge_number,$money);
                if($payinfo['returns'] != 'ok'){
                    $return['errorMsg'] = '支付出错，请稍后再试';
                    exit(json_encode($return));
                }
                $return['status'] = 'ok';
                $return['s'] = $payinfo['s'];
                break;
            case 'alipay' ://支付宝
                // $payinfo = $this->rechargeWxAliPayDo($user['user_id'],'',$charge_number,$Fmoney,'A02');
                // if($payinfo['returns'] != 'ok'){
                    $return['errorMsg'] = '支付出错，请稍后再试';
                    exit(json_encode($return));
                // }
                // $return['status'] = 'ok';
                // $return['s'] = $payinfo['s'];
                break;
            case 'cupPay' ://银联
                $html = $this->rechargeAllinpay($user['user_id'],$charge_number,$Fmoney);
                if(!empty($html)){
                    $return['status'] = 'ok';
                    $return['h'] = $html;
                }else{
                    $return['errorMsg'] = '银联支付错误，请稍后再试';
                }
                break;
            default :
                $return['errorMsg'] = '未选择支付类型';
                break;
        }
        exit(json_encode($return));
    }

    // 银联支付
    private function rechargeAllinpay($user_id,$charge_number,$money){
        //签名部分
        $datas1 = array(
                    'signType' => 0,
                    'merchantId' => '009440648160043',
                    // 'partnerUserId' => $user_id//正式使用
                    'partnerUserId' => '94360'//会员id 正式测试会员 id：94360
                    );
        $key = 'a678400d568e2c189914bb79881ac1d9';
        $query1 = '&';
        foreach($datas1 as $v=>$e){
            $query1 .= $v.'='.$e.'&';
        }
        $datas1['signMsg'] = strtoupper(md5($query1.'key='.$key.'&'));
        $json = json_decode($this->http_post('https://service.allinpay.com/usercenter/merchant/UserInfo/reg.do',$datas1),true);
        if(!in_array($json['resultCode'],array('0000','0006'))){
            exit;
        }
        $datas2 = array(
            'inputCharset'  => 1,
            'pickupUrl'     => __HOME__.'wap/User-money.html',
            'receiveUrl'    => __HOME__.'?g=WapSite&c=AllinPay&a=rechargeDoIn',
            'version'       => 'v1.0',
            'language'      => 1,
            'signType'      => 1,
            'merchantId'    => '009440648160043',
            'orderNo'       => $charge_number,
            'orderAmount'   => $money,
            'orderCurrency' => 156,
            'orderDatetime' => date('YmdHis'),
            'ext1'          => '<USER>'.$json['userId'].'</USER>',
            'ext2'          => $charge_number,
            'payType' => 33
            );
        $query2 = '';
        foreach($datas2 as $k=>$d){
            $query2 .= $k.'='.$d.'&';
        }
        $datas2['signMsg'] = strtoupper(md5($query2.'key='.$key));
        $html = '<form action="https://cashier.allinpay.com/mobilepayment/mobile/SaveMchtOrderServlet.action" id="TFrom" method="POST">';
        foreach($datas2 as $k=>$d){
            $html .= '<input type="hidden" name="'.$k.'" value="'.$d.'" />';
        }
$html .= <<<EOF
    </form>
<script type="text/javascript">
    document.getElementById('TFrom').submit();
</script>
EOF;
        return $html;
    }

    //  支付宝 微信充值    通联支付停用转用rechargeWeiXinPay()
    private function rechargeWxAliPayDo($user_id,$openid,$charge_number,$money,$payType){
        include ROOT . '/librarys/allinpay/AllinPay.php';

        $allin = new AllinPay();
        $params = [];

        $params['orderNo'] = $charge_number;
        $params['money'] = $money;
        $params['notify_url'] = __HOME__.'librarys/allinpay/allinqrcode_recharge_notify.php';
        $params['paytype'] = $payType;
        if($payType == 'W02'){
            // $params['acct'] = $openid;
            $params['acct'] = 'oYhPtw1E3WtlWcBpKHKnCo2T9cJI';
        }else if($payType == 'A02'){
            return array('returns'=>'error');
        }
        $weixin_config = $allin->weixinPay($params);
        if($weixin_config === false){
            OrderMasterModel::debug('recharge',$user_id.'用户配置出错'.$weixin_config.',参数：'.json_encode($params));
            return array('returns'=>'error');
        }else{
            return array('returns'=>'ok','s'=>json_decode($weixin_config));
        }
    }

    public function rechargeWeiXinPay($openId,$charge_number,$money)
    {
        $allin['returns'] = 'error';
        //include(ROOT.DIRECTORY_SEPARATOR."librarys".DIRECTORY_SEPARATOR."weixin.php");
        include(ROOT . 'librarys' . DIRECTORY_SEPARATOR . 'weixin' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'WxPay.Api.php');
        include(ROOT . 'librarys' . DIRECTORY_SEPARATOR . 'weixin' . DIRECTORY_SEPARATOR . 'WxPay.JsApiPay.php');

        if (empty($charge_number)) {
            $allin['msg'] = '充值单号异常';
            return $allin;
        }

        $tradeMoney = $money * 100;
        //print_r($order);exit;
        $tools = new JsApiPay();
//        $openId = $tools->GetOpenid();
        //order
        $input = new WxPayUnifiedOrder();
        $input->SetBody(mb_convert_encoding('充值业务', 'utf-8', 'utf-8,gbk'));
        //$input->SetAttach('test');
        $input->SetOut_trade_no($charge_number);
        $input->SetTotal_fee($tradeMoney);
        $input->SetTime_start(date('YmdHis'));
        $input->SetTime_expire(date('YmdHis', time()+600));
        $input->SetGoods_tag('test');
        $input->SetNotify_url(__HOME__.'librarys/weixin/recharge_notify.php');
        $input->SetTrade_type('JSAPI');
        $input->SetOpenid($openId);
//        $input->SetOpenid($_COOKIE['openid']);
        $order = WxPayApi::unifiedOrder($input);
        //print_r($order);exit;
        $jsApiParameters = $tools->GetJsApiParameters($order);
        //echo $jsApiParameters;
//        $this->assign('jsParameters', $jsApiParameters);
//        $this->assign('orderTotal', $orderTotal);
        $allin['returns'] = 'ok';
        $allin['s'] = json_decode($jsApiParameters);
        return $allin;
//        $this->display('WapSite/Order/weixinPay');
    }
    
    //银联充值回调执行
    public function rechargeDoIn(){

        include ROOT . DIRECTORY_SEPARATOR .'librarys/allinpay/AllinPay.php';

        $allin = new AllinPay();
        $result = $allin->verifySign();
        $payResult = $allin->getRequest('payResult');

        if($result && $payResult == 1){
            $money = $allin->getRequest('payAmount');
            $paymentNumber = $allin->getRequest('orderNo');
            $tradeNo = $allin->getRequest('paymentOrderId');
            $type = "allinpay";
            $model_recharge_order = new RechargeOrderModel();
            $model_recharge_order->updateOrderStatus($paymentNumber, $money, $tradeNo, $type);
        } else {
            OrderMasterModel::debug('allinpay', "fuck fail");
        }

        $payNumber = $_POST['orderNo'];
        $recharge_money = $_POST['orderAmount'];
        $queryId = $_POST['paymentOrderId'];
        $rechargeOrder = new RechargeOrderModel();
        $rechargeOrder->updateOrderStatus($payNumber,$recharge_money,$queryId,'allinpay');
    }
    

    //金融充值创建订单
    public function createRechargeFc(){
        //检查登录
        // $user = $this->checkLogin();
        $user = session::get('fc_user');
        if(empty($user)){
            $return['errorMsg'] = '登录信息检查失败';
        }
        $t = time();
        $dataInfo = Request::post();
        $rechargeCsrf = session::get('recharge_number');
        $bank_card = new FcUsersBankCardModel();
        if( empty( $bank_card->getAll(array('field'=>'*','where'=>'user_id = '.$user['user_id'])) ) ){
            $return['errorMsg'] = '未绑定提现银行卡，请先绑定';
            exit(json_encode($return));
        }
        // $user = session::get('user');
        $user_model = new FcUsersInfoModel();
        $userInfo = $user_model->getRow(array('field'=>'mobile,wx_openid,wx_unionid','where'=>'user_id = '.$user['user_id']));
        //端口判断
        $openid = $userInfo['wx_openid'];
        if(stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') === true){
            if(empty($openid) && !empty($_COOKIE['openid']) ){
                $openid = $_COOKIE['openid'];
            }else{
                $return['errorMsg'] = '微信身份异常<br />请重新登录微信App';
                exit(json_encode($return));
            }
        }
        //检查重复提交
        if(!empty($rechargeCsrf) && $rechargeCsrf['csrf'] == $dataInfo['csrf']){
            Session::delete('recharge_number');
        }else{
            $return['errorMsg'] = '不要重复提交';
            exit(json_encode($return));
        }
        //充值金额
        $money = $dataInfo['money'];
        //验证码
        $code = $dataInfo['code'];
        //支付类型
        $payType = $dataInfo['payType'];
        //检查最低金额
        if($money <= 0 || $money < 0.01){
            $return['errorMsg'] = '最低 ¥ 0.01';
            exit(json_encode($return));
        }
        // 转换为 分单位
        $Fmoney = $money*100;

        $return['status'] = 'error';
        try {
            if(empty($userInfo['mobile'])){
                throw new Exception("手机号异常");
            }
            //检查验证码
            $sms = new SMS();
            if($sms->validate_code($userInfo['mobile'], $code) === false){
                throw new Exception("验证码错误");
            }

            //生成充值流水号
            $rechargeOrder = new FcRechargeOrderModel();
            $charge_number = $rechargeOrder -> createPaymentNumber();
            if(empty($charge_number)){
                throw new Exception("充值单号生成失败!<br />请稍后再试！");
            }
            if($payType == 'wxPay'){
                $pay_type = 'allin_weixinpay';
            }else{
                $pay_type = 'allinpay';
                
            }
            $user_model->begin();
            //生成单号数据
            $add = array(
                'recharge_number'   => $charge_number,
                'user_id'           => $user['user_id'],
                'recharge_money'    => $money,
                'user_ip'           => Request::ip(),
                'created'           => $t,
                'pay_type'          => $pay_type,
                'paid'              => 0,
                'pay_time'          => 0,
                'trade_no'          => '',
                'origin'            => 3,
            );
            if( $rechargeOrder->add($add)==false){
                throw new Exception("充值单号生成失败!<br />请稍后再试！");
            }
            $return['status'] = 'ok';
            $user_model->commit();
        } catch (Exception $e) {
            $user_model->rollback();
            $return['errorMsg'] = $e->getMessage();
        }
        if($return['status'] == 'error'){
            exit(json_encode($return));
        }else{
            $return['status'] = 'error';
        }
        switch($payType){
            case 'wxPay' ://微信
                $payinfo = $this->Fc_rechargeWxAliPayDo($user['user_id'],$openid,$charge_number,$money,'W02');
                if($payinfo['returns'] != 'ok'){
                    $return['errorMsg'] = '支付出错，请稍后再试';
                    exit(json_encode($return));
                }
                $return['status'] = 'ok';
                $return['s'] = $payinfo['s'];
                break;
            case 'alipay' ://支付宝
                // $payinfo = $this->rechargeWxAliPayDo($user['user_id'],'',$charge_number,$Fmoney,'A02');
                // if($payinfo['returns'] != 'ok'){
                    $return['errorMsg'] = '支付出错，请稍后再试';
                    exit(json_encode($return));
                // }
                // $return['status'] = 'ok';
                // $return['s'] = $payinfo['s'];
                break;
            case 'cupPay' ://银联
                $html = $this->Fc_rechargeAllinpay('fc'.$user['user_id'],$charge_number,$Fmoney);
                if(!empty($html)){
                    $return['status'] = 'ok';
                    $return['h'] = $html;
                }else{
                    $return['errorMsg'] = '银联支付错误，请稍后再试';
                }
                break;
            default :
                $return['errorMsg'] = '未选择支付类型';
                break;
        }
        exit(json_encode($return));
    }

     //  支付宝 微信充值
    private function Fc_rechargeWxAliPayDo($user_id,$openid,$charge_number,$money,$payType){
        include ROOT . '/librarys/allinpay/AllinPay.php';

        $allin = new AllinPay();
        $params = [];

        $params['orderNo'] = $charge_number;
        $params['money'] = $money;
        $params['notify_url'] = __HOME__.'librarys/allinpay/fc_allinqrcode_recharge_notify.php';
        $params['paytype'] = $payType;
        if($payType == 'W02'){
            // $params['acct'] = $openid;
            $params['acct'] = 'oYhPtw1E3WtlWcBpKHKnCo2T9cJI';
        }else if($payType == 'A02'){
            return array('returns'=>'error');
        }
        $weixin_config = $allin->weixinPay($params);
        if($weixin_config === false){
            OrderMasterModel::debug('recharge',$user_id.'用户配置出错'.$weixin_config.',参数：'.json_encode($params));
            return array('returns'=>'error');
        }else{
            return array('returns'=>'ok','s'=>json_decode($weixin_config));
        }
    }

    //银联充值回调执行
    public function FcrechargeDoIn(){

        include ROOT . DIRECTORY_SEPARATOR .'librarys/allinpay/AllinPay.php';

        $allin = new AllinPay();
        $result = $allin->verifySign();
        $payResult = $allin->getRequest('payResult');

        if($result && $payResult == 1){
            $money = $allin->getRequest('payAmount');
            $paymentNumber = $allin->getRequest('orderNo');
            $tradeNo = $allin->getRequest('paymentOrderId');
            $type = "allinpay";
            $model_recharge_order = new FcRechargeOrderModel();
            $model_recharge_order->updateOrderStatus($paymentNumber, $money, $tradeNo, $type);
        } else {
            OrderMasterModel::debug('allinpay', "fuck fail");
        }

        $payNumber = $_POST['orderNo'];
        $recharge_money = $_POST['orderAmount'];
        $queryId = $_POST['paymentOrderId'];
        $rechargeOrder = new FcRechargeOrderModel();
        $rechargeOrder->updateOrderStatus($payNumber,$recharge_money,$queryId,'allinpay');
    }

    // 金融银联支付
    private function Fc_rechargeAllinpay($user_id,$charge_number,$money){
        //签名部分
        $datas1 = array(
                    'signType' => 0,
                    'merchantId' => '009440648160043',
                    // 'partnerUserId' => $user_id//正式使用
                    'partnerUserId' => '94360'//会员id 正式测试会员 id：94360
                    );
        $key = 'a678400d568e2c189914bb79881ac1d9';
        $query1 = '&';
        foreach($datas1 as $v=>$e){
            $query1 .= $v.'='.$e.'&';
        }
        $datas1['signMsg'] = strtoupper(md5($query1.'key='.$key.'&'));
        $json = json_decode($this->http_post('https://service.allinpay.com/usercenter/merchant/UserInfo/reg.do',$datas1),true);
        if(!in_array($json['resultCode'],array('0000','0006'))){
            exit;
        }
        $datas2 = array(
            'inputCharset'  => 1,
            'pickupUrl'     => __HOME__.'?g=WapSite&c=FinancialUser&a=home',
            'receiveUrl'    => __HOME__.'?g=WapSite&c=AllinPay&a=FcrechargeDoIn',
            'version'       => 'v1.0',
            'language'      => 1,
            'signType'      => 1,
            'merchantId'    => '009440648160043',
            'orderNo'       => $charge_number,
            'orderAmount'   => $money,
            'orderCurrency' => 156,
            'orderDatetime' => date('YmdHis'),
            'ext1'          => '<USER>'.$json['userId'].'</USER>',
            'ext2'          => $charge_number,
            'payType' => 33
            );
        $query2 = '';
        foreach($datas2 as $k=>$d){
            $query2 .= $k.'='.$d.'&';
        }
        $datas2['signMsg'] = strtoupper(md5($query2.'key='.$key));
        $html = '<form action="https://cashier.allinpay.com/mobilepayment/mobile/SaveMchtOrderServlet.action" id="TFrom" method="POST">';
        foreach($datas2 as $k=>$d){
            $html .= '<input type="hidden" name="'.$k.'" value="'.$d.'" />';
        }
$html .= <<<EOF
    </form>
<script type="text/javascript">
    document.getElementById('TFrom').submit();
</script>
EOF;
        return $html;
    }

    //生活余额充值创建订单
    //充值创建订单
    public function createLifeRecharge(){
        //检查登录
        // $user = $this->checkLogin();
        $user = session::get('user');
        if(empty($user)){
            $return['errorMsg'] = '登录信息检查失败';
        }
        $t = time();
        $dataInfo = Request::post();
        $rechargeCsrf = session::get('life_recharge');
        // $user = session::get('user');
        $user_model = new UsersInfoModel();
        $userInfo = $user_model->getRow(array('field'=>'mobile,wx_openid,wx_openid1','where'=>'user_id = '.$user['user_id']));
        //端口判断
        $openid = $userInfo['wx_openid1'];
        if(stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') === true){
            if(empty($openid) && !empty($_COOKIE['openid']) ){
                $openid = $_COOKIE['openid'];
            }else{
                $return['errorMsg'] = '微信身份异常<br />请重新登录微信App';
                exit(json_encode($return));
            }
        }
        //检查重复提交
        if(!empty($rechargeCsrf) && $rechargeCsrf['csrf'] == $dataInfo['csrf']){
            Session::delete('life_recharge');
        }else{
            $return['errorMsg'] = '不要重复提交';
            exit(json_encode($return));
        }
        //充值金额
        $money = $dataInfo['money'];
        //验证码
        $code = $dataInfo['code'];
        //支付类型
        $payType = $dataInfo['payType'];
        //检查最低金额
        if($money <= 0 || $money < 0.01){
            $return['errorMsg'] = '最低 ¥ 0.01';
            exit(json_encode($return));
        }
        // 转换为 分单位
        $Fmoney = $money*100;

        $return['status'] = 'error';
        try {
            if(empty($userInfo['mobile'])){
                throw new Exception("手机号异常");
            }
            //检查验证码
            $sms = new SMS();
            if($sms->validate_code($userInfo['mobile'], $code) === false){
                throw new Exception("验证码错误");
            }

            //生成充值流水号
            $rechargeOrder = new RechargeLifeOrderModel();
            $charge_number = $rechargeOrder -> createPaymentNumber();
            if(empty($charge_number)){
                throw new Exception("充值单号生成失败!<br />请稍后再试！");
            }
            if($payType == 'wxPay'){
                $pay_type = 'allin_weixinpay';
            }else{
                $pay_type = 'allinpay';

            }
            $user_model->begin();
            //生成单号数据
            $add = array(
                'recharge_number'   => $charge_number,
                'user_id'           => $user['user_id'],
                'recharge_money'    => $money,
                'user_ip'           => Request::ip(),
                'created'           => $t,
                'pay_type'          => $pay_type,
                'paid'              => 0,
                'pay_time'          => 0,
                'trade_no'          => '',
                'origin'            => 3,
            );
            if( $rechargeOrder->add($add)==false){
                throw new Exception("充值单号生成失败!<br />请稍后再试！");
            }
            $return['status'] = 'ok';
            $user_model->commit();
        } catch (Exception $e) {
            $user_model->rollback();
            $return['errorMsg'] = $e->getMessage();
        }
        if($return['status'] == 'error'){
            exit(json_encode($return));
        }else{
            $return['status'] = 'error';
        }
        switch($payType){
            case 'wxPay' ://微信
                $payinfo = $this->lifeRechargeWxAliPayDo($user['user_id'],$openid,$charge_number,$money,'W02');
                if($payinfo['returns'] != 'ok'){
                    $return['errorMsg'] = '支付出错，请稍后再试';
                    exit(json_encode($return));
                }
                $return['status'] = 'ok';
                $return['s'] = $payinfo['s'];
                break;
            case 'alipay' ://支付宝
                // $payinfo = $this->rechargeWxAliPayDo($user['user_id'],'',$charge_number,$Fmoney,'A02');
                // if($payinfo['returns'] != 'ok'){
                $return['errorMsg'] = '支付出错，请稍后再试';
                exit(json_encode($return));
                // }
                // $return['status'] = 'ok';
                // $return['s'] = $payinfo['s'];
                break;
            case 'cupPay' ://银联
                $html = $this->lifeRechargeAllinpay($user['user_id'],$charge_number,$Fmoney);
                if(!empty($html)){
                    $return['status'] = 'ok';
                    $return['h'] = $html;
                }else{
                    $return['errorMsg'] = '银联支付错误，请稍后再试';
                }
                break;
            default :
                $return['errorMsg'] = '未选择支付类型';
                break;
        }
        exit(json_encode($return));
    }

    //  生活余额支付宝 微信充值
    private function lifeRechargeWxAliPayDo($user_id,$openid,$charge_number,$money,$payType){
        include ROOT . '/librarys/allinpay/AllinPay.php';

        $allin = new AllinPay();
        $params = [];

        $params['orderNo'] = $charge_number;
        $params['money'] = $money;
        $params['notify_url'] = __HOME__.'librarys/allinpay/allinqrcode_recharge_life_notify.php';
        $params['paytype'] = $payType;
        if($payType == 'W02'){
//            $params['acct'] = $openid;
            $params['acct'] = 'oYhPtw1E3WtlWcBpKHKnCo2T9cJI';
        }else if($payType == 'A02'){
            return array('returns'=>'error');
        }
        $weixin_config = $allin->weixinPay($params);
        if($weixin_config === false){
            OrderMasterModel::debug('life_recharge',$user_id.'用户配置出错'.$weixin_config.',参数：'.json_encode($params));
            return array('returns'=>'error');
        }else{
            return array('returns'=>'ok','s'=>json_decode($weixin_config));
        }
    }

    // 生活余额 充值银联支付
    private function lifeRechargeAllinpay($user_id,$charge_number,$money){
        //签名部分
        $datas1 = array(
            'signType' => 0,
            'merchantId' => '009440648160043',
            // 'partnerUserId' => $user_id//正式使用
            'partnerUserId' => '94360'//会员id 正式测试会员 id：94360
        );
        $key = 'a678400d568e2c189914bb79881ac1d9';
        $query1 = '&';
        foreach($datas1 as $v=>$e){
            $query1 .= $v.'='.$e.'&';
        }
        $datas1['signMsg'] = strtoupper(md5($query1.'key='.$key.'&'));
        $json = json_decode($this->http_post('https://service.allinpay.com/usercenter/merchant/UserInfo/reg.do',$datas1),true);
        if(!in_array($json['resultCode'],array('0000','0006'))){
            exit;
        }
        $datas2 = array(
            'inputCharset'  => 1,
            'pickupUrl'     => 'http://trip.thgo8.com/member/ordermain.aspx',
            'receiveUrl'    => __HOME__.'?g=WapSite&c=AllinPay&a=lifeRechargeDoIn',
            'version'       => 'v1.0',
            'language'      => 1,
            'signType'      => 1,
            'merchantId'    => '009440648160043',
            'orderNo'       => $charge_number,
            'orderAmount'   => $money,
            'orderCurrency' => 156,
            'orderDatetime' => date('YmdHis'),
            'ext1'          => '<USER>'.$json['userId'].'</USER>',
            'ext2'          => $charge_number,
            'payType' => 33
        );
        $query2 = '';
        foreach($datas2 as $k=>$d){
            $query2 .= $k.'='.$d.'&';
        }
        $datas2['signMsg'] = strtoupper(md5($query2.'key='.$key));
        $html = '<form action="https://cashier.allinpay.com/mobilepayment/mobile/SaveMchtOrderServlet.action" id="TFrom" method="POST">';
        foreach($datas2 as $k=>$d){
            $html .= '<input type="hidden" name="'.$k.'" value="'.$d.'" />';
        }
        $html .= <<<EOF
    </form>
<script type="text/javascript">
    document.getElementById('TFrom').submit();
</script>
EOF;
        return $html;
    }

    //银联充值回调执行
    public function lifeRechargeDoIn(){

        include ROOT . DIRECTORY_SEPARATOR .'librarys/allinpay/AllinPay.php';

        $allin = new AllinPay();
        $result = $allin->verifySign();
        $payResult = $allin->getRequest('payResult');

        if($result && $payResult == 1){
            $money = $allin->getRequest('payAmount');
            $paymentNumber = $allin->getRequest('orderNo');
            $tradeNo = $allin->getRequest('paymentOrderId');
            $type = "allinpay";
            $model_recharge_order = new RechargeLifeOrderModel();
            $model_recharge_order->updateOrderStatus($paymentNumber, $money, $tradeNo, $type);
        } else {
            OrderMasterModel::debug('allinpay', "fuck fail");
        }

        $payNumber = $_POST['orderNo'];
        $recharge_money = $_POST['orderAmount'];
        $queryId = $_POST['paymentOrderId'];
        $rechargeOrder = new RechargeLifeOrderModel();
        $rechargeOrder->updateOrderStatus($payNumber,$recharge_money,$queryId,'allinpay');
    }

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