<?php
defined('PTS80')||exit('PTS80 Defined');
/**
 * 	微信功能调用model
 * @author thc 
 * 	
 * @date 2016-10-17 15:19:12
 */
class WeiXinModel extends Model{

    protected $table = 'wx_config';

    protected static $appid = 'wx634a21407ee85ff5';
    protected static $appsecret = '58680e5c79a75df5b3785ebac05b762e';

    /**
	 * [客服文本信息推送]
	 * @param  [type] $content [推送内容]
	 * @param  [type] $openid      [会员openid]
     * @param  [type] $type     [内容模板]
     * @param  string $sendtype    [推送类型]
     * @param  string $token        [防止线程占用外传access_token]
	 * @return [type]          [description]
	 */
    public function sendMessTo($content,$openid,$type,$sendtype='text',$token=''){
        // 检查分享配置
        if( empty($content) || empty($openid) ){
            return false;
        }
        // 内容配置
        $data = array(
            'touser' => $openid,
            'msgtype' => $sendtype,
        );
        $url = '';
        switch($sendtype){
            case 'text' ://文本推送
                $Aces = $this->get_wx_config();//不能放在公共部分调用
                $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$Aces['access_token'];
                $cont = $this->getTextSendCont($content,$type);
                if( empty($cont) ){
                    return false;
                }
                $data['text'] = array(
                    'content' => $cont
                );
                break;
            case 'temp' :
                $Aces = $this->get_wx_config();//不能放在公共部分调用
                //重新内容配置
                $data = array(
                    'touser' => $openid,
                );
                $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$Aces['access_token'];
                $data = $this->getTempSendCont($content,$type,$data);
                break;
            case 'news' ://图文推送
                if($type!=''){
                    $content[0]['title'] = '@'.$type.' '.$content[0]['title'];
                }
                //防止线程增加
                $data["news"] = array(
                    "articles" => $content
                );
                if($token == ''){
                    return false;
                }
                $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$token;
                break;
        }
        if($url == '' ){
            return false;
        }
        $jsonArr = $this->httpPost($url,json_encode($data,JSON_UNESCAPED_UNICODE));
        $json = json_decode($jsonArr, true);
        return true;
    }
    
    //文本推送 现用消息模板推送
    public function getTextSendCont($content,$type){
        $t = date('Y-m-d H:i:s',time());
        $server_mobile = '0757-82901333';
        $dao = Dao::instance();
        $cont = '';
        switch($type){
            //注册
            case 'reg' :
                $cont = "注册成功通知：\n（".$t."）\n恭喜 ".$content."，您已成功注册我们的会员";
                break;
            //上下级绑定
            case 'p_bind' :
                $cont = '账号：'.$content.' 已成为您的的下级';
                break;
            //购买
            case 'buy' :
                //支付单号获取 order_number_array
                $sql = "select it.order_number from ".$dao->table('order_payment_item')." as it left join ".$dao->table('order_payment')." as pay on pay.order_payment_id = it.order_payment_id where pay.payment_number = ".$content;
                $order_number_list = $dao->queryAll($sql);
                $order_number = '';
                foreach( $order_number_list as $list ){
                    $order_number .= $list['order_number']."\n";
                }
                $cont = "下单成功通知：\n（".$t."）\n恭喜您已成功下单！我们会及时为您发货，感谢您购买通惠购的商品！\n支付编号：\n".$content."\n订单编号： \n".$order_number."\n如有需要，请拨打客服电话: ".$server_mobile;
                break;
            //发货
            case 'ship' :
                //获取运单编号
                $sql = "select waybill,express_id from ".$dao->table('order_delivery')." as d left join ".$dao->table('order_info')." as o on o.order_id = d.order_id where order_number = '{$content}'";
                $delivery = $dao->queryOne($sql);
                //获取快递公司名
                if( !empty($delivery) && !empty($delivery['express_id']) ){
                    $sql = "select express_name from ".$dao->table('express')." where express_id = ".$delivery['express_id'];
                    $delivery_name = $dao->queryOne($sql);
                    $cont = "发货通知：\n（".$t."）\n您购买的宝贝已发货，快递小哥正在快马扬鞭的为您送货，请耐心等待！\n订单编号：".$content."\n".$delivery_name['express_name']."\n运单号：".$delivery['waybill']."\n如有需要，请拨打客服电话：".$server_mobile;
                }
                break;
            //退货成功
            case 'refund' :
                $cont = "退货审核成功通知：\n您的退货申请已通过审核。\n订单编号：".$content."\n1、请保持宝贝包装齐全，不影响二次销售，否则不予退货。\n2、退货运费自理。\n(注：不接受任何快递到付！)\n感谢您的配合与谅解！\n如有需要，请拨打客服电话：".$server_mobile;
                break;
            //换货
            case 'change' :
                $cont = "换货审核成功通知：\n您的换货申请已通过审核。\n订单编号：".$content."\n（".$t."）1、请保持宝贝包装齐全，不影响二次销售，否则不予换货。\n2、换货运费自理。\n(注：不接受任何快递到付！)\n感谢您的配合与谅解！\n如有需要，请拨打客服电话：".$server_mobile;
                break;
            //完成订单
            case 'finish' :
                $cont = "订单完成通知：\n（".$t."）\n尊敬的客户！\n您已完成订单：".$content."\n如果满意我们的服务，欢迎留言好评！感谢您购买通惠购的商品！\n如有需要，请拨打客服电话：".$server_mobile;
                break;
            //充值
            case 'charge' :
                $cont = "充值通知：\n（".$t."）\n尊敬的客户！\n您已完成充值：".$content."\n如果满意我们的服务，欢迎留言好评！\n如有疑问，请拨打客服电话：".$server_mobile;
                break;
            case 'group_success' :
                $cont = "";
                break;
            case 'group_fail' :
                $cont = "" ;
                break;
        }
        return $cont;
    }

    public function getTempSendCont($content,$type,$data){
        $t = date('Y-m-d H:i:s',time());
        $server_mobile = '0757-82901333';
        $dao = Dao::instance();
        if( empty($data) ){
            return false;
        }
        switch($type){
            //注册
            case 'reg' :
                $data['template_id'] = 'DQZeb5CRutRup6_qaxqZxnlZjJ6TtuwAxJAYs6Rd_M8';//正式模板id
                $data['url'] = __HOME__.'wap/Index-index.html';
                $data['topcolor'] = '#FF0000';
                $data['data'] = array(
                    'first'=>array(
                        'value' => '恭喜您注册成为会员！',
                        'color' => '#FF0000'
                    ),
                    'keyword1'=>array(
                        'value' => $content
                    ),
                    'keyword2'=>array(
                        'value' => $t
                    ),
                    'remark'=>array(
                        'value' => '恭喜您注册成为会员，您将享受到会员所有权利！如有疑问请致电：'.$server_mobile
                    )
                );
                break;
            //上下级绑定
            case 'p_bind' :
                $cont = '账号：'.$content.' 已成为您的的下级';
                break;
            //购买支付下单成功
            case 'buy' :
                $order_cont = '';
                $order_pay_money = 0;
                //支付单号获取 order_number_array
                $sql = "select it.order_number,it.order_amount from ".$dao->table('order_payment_item')." as it left join ".$dao->table('order_payment')." as pay on pay.order_payment_id = it.order_payment_id where pay.payment_number = ".$content;
                $order_number_list = $dao->queryAll($sql);
                $order_number = '';
                foreach( $order_number_list as $list ){
                    $order_number .= $list['order_number']."\n";
                    $order_pay_money = bcadd($order_pay_money,$list['order_amount'],2);
                }
                $order_cont = "订单编号： \n".$order_number."\n我们会及时为您发货，感谢您购买通惠购的商品！ 如有需要，请拨打客服电话: ".$server_mobile;

                $data['template_id'] = 'JrSgHStDreGWP1P02S_Df2-R58orQkUBbwNyQVgwTpA';//正式模板id
                $data['url'] = __HOME__.'wap/Order-orderList.html';
                $data['topcolor'] = '#FF0000';
                $data['data'] = array(
                    'first'=>array(
                        'value' => "【 订单支付成功通知 】\n".$t,
                        'color' => '#FF0000'
                    ),
                    'keyword1'=>array(
                        'value' => $order_pay_money.' 元'
                    ),
                    'keyword2'=>array(
                        'value' => $content
                    ),
                    'remark'=>array(
                        'value' => $order_cont
                    )
                );
                break;
            //发货
            case 'ship' :
                //获取运单编号
                $sql = "select waybill,express_id,oc.address,prov.name as prov_name,city.name as  city_name,area.name as area_name from ".$dao->table('order_delivery')." as d";
                $sql .= " inner join ".$dao->table('order_info')." as o on o.order_id = d.order_id";
                $sql .= " inner join ".$dao->table('order_consignee')." as oc on oc.order_id = o.order_id";
                $sql .= " inner join ".$dao->table('city')." as prov on prov.id = oc.province";
                $sql .= " inner join ".$dao->table('city')." as city on city.id = oc.city";
                $sql .= " inner join ".$dao->table('city')." as area on area.id = oc.area";
                $sql .= " where o.order_number = '{$content}'";
                $delivery = $dao->queryOne($sql);
                //获取快递公司名
                if( !empty($delivery) && !empty($delivery['express_id']) ){
                    $sql = "select express_name from ".$dao->table('express')." where express_id = ".$delivery['express_id'];
                    $delivery_name = $dao->queryOne($sql);
                    $data['template_id'] = 'iIao3lczdeNhl2GD4sGNaYLQ9Zk4skBUh5ETAxT0E7I';//正式模板id
                    $data['url'] = __HOME__.'wap/Order-tranList-'.$content.'.html';
                    $data['topcolor'] = '#FF0000';
                    $data['data'] = array(
                        'first'=>array(
                            'value' => "您购买的宝贝已发货，快递小哥正在快马扬鞭的为您送货，请耐心等待！",
                            'color' => '#FF0000'
                        ),
                        'keyword1'=>array(
                            'value' => $content
                        ),
                        'keyword2'=>array(
                            'value' => $delivery_name['express_name']."\n运单号：".$delivery['waybill']
                        ),
                        'keyword3'=>array(
                            'value' => $t
                        ),
                        'keyword4'=>array(
                            'value' => $delivery['prov_name'].' '.$delivery['city_name'].' '.$delivery['area_name']."\n".$delivery['address']."\n"
                        ),
                        'remark'=>array(
                            'value' => "如有需要，请拨打客服电话：".$server_mobile
                        )
                    );
                }else{
                    return false;
                }
                break;
            //退货申请成功
            case 'refund' :
                //获取订单退换单信息
                $sql = 'SELECT orf.refund_id,og.goods_name,orf.order_number,status,setems_status,seller_remark FROM '.$dao->table('order_refund')." AS orf";
                $sql .= ' INNER JOIN '.$dao->table('order_goods')." AS og ON og.order_goods_id = orf.order_goods_id";
                $sql .= " WHERE refund_id = ".$content;
                $refund_info = $dao->queryOne($sql);
                if( empty($refund_info) || $refund_info['status'] == 3 ){
                    return false;
                }
                //配置检查进度内容
                $cont_info = $this->refund_order_cont($refund_info,'退货',$server_mobile);
                if( $cont_info == false ){
                    return false;
                }
                $data['template_id'] = 'GAoPAnCPz7uSrbWNsTsbDDP_Xc9IYdQHfxPJxDbJuic';//正式模板id
                $data['url'] = __HOME__.'wap/Order-refundPrompt-'.$content.'.html';
                $data['topcolor'] = '#FF0000';
                $data['data'] = array(
                    'first'=>array(
                        'value' => $cont_info['first'],
                        'color' => '#FF0000'
                    ),
                    'keyword1'=>array(
                        'value' => $t
                    ),
                    'keyword2'=>array(
                        'value' => $refund_info['order_number']
                    ),
                    'keyword3'=>array(
                        'value' => $refund_info['goods_name']
                    ),
                    'remark'=>array(
                        'value' => $cont_info['remark'],
                        'color' => '#FF0000'
                    )
                );
                break;
            //换货
            case 'change' :
                //获取订单退换单信息
                $sql = 'SELECT orf.refund_id,og.goods_name,orf.order_number,status,setems_status,seller_remark  FROM '.$dao->table('order_refund')." AS orf";
                $sql .= ' INNER JOIN '.$dao->table('order_goods')." AS og ON og.order_goods_id = orf.order_goods_id";
                $sql .= " WHERE refund_id = ".$content;
                $refund_info = $dao->queryOne($sql);
                if(empty($refund_info)){
                    return false;
                }
                //配置检查进度内容
                $cont_info = $this->refund_order_cont($refund_info,'换货',$server_mobile);
                if( $cont_info == false ){
                    return false;
                }
                $data['template_id'] = 'GAoPAnCPz7uSrbWNsTsbDDP_Xc9IYdQHfxPJxDbJuic';//正式模板id
                $data['url'] = __HOME__.'wap/Order-refundPrompt-'.$content.'.html';
                $data['topcolor'] = '#FF0000';
                $data['data'] = array(
                    'first'=>array(
                        'value' => $cont_info['first'],
                        'color' => '#FF0000'
                    ),
                    'keyword1'=>array(
                        'value' => $t
                    ),
                    'keyword2'=>array(
                        'value' => $refund_info['order_number']
                    ),
                    'keyword3'=>array(
                        'value' => $refund_info['goods_name']
                    ),
                    'remark'=>array(
                        'value' => $cont_info['remark'],
                        'color' => '#FF0000'
                    )
                );
                break;
            //完成订单
            case 'finish' :
                //获取订单退换单信息
                $sql = 'SELECT oc.consignee FROM '.$dao->table('order_info')." AS o";
                $sql .= ' INNER JOIN '.$dao->table('order_consignee')." AS oc ON oc.order_id = o.order_id";
                $sql .= " WHERE o.order_number = ".$content;
                $order_info = $dao->queryOne($sql);
                if(empty($order_info)){
                    return false;
                }
                $data['template_id'] = '6eFU08AGo2vL37Uh8Ll7LM0DJaQo_Nom7s3YqQnHtI8';//正式模板id
                $data['url'] = __HOME__.'wap/Order-orderPrompt-'.$content.'.html';
                $data['topcolor'] = '#FF0000';
                $data['data'] = array(
                    'first'=>array(
                        'value' => "【 订单完成通知 】\n".$t,
                        'color' => '#FF0000'
                    ),
                    'keyword1'=>array(
                        'value' => $content
                    ),
                    'keyword2'=>array(
                        'value' => $order_info['consignee']
                    ),
                    'keyword3'=>array(
                        'value' => '已完成'
                    ),
                    'remark'=>array(
                        'value' => "记得来评价，留下您宝贵的意见哦！\n如有需要，请拨打客服电话：".$server_mobile,
                        'color' => '#FF0000'
                    )
                );
                break;
            //充值
            case 'charge' :
                $data['template_id'] = 'aPnsLXBBwUu1dc3KanKGkFfoEBQ78ltxHhASZkv2TAU';//正式模板id
                $data['url'] = __HOME__.'wap/User-money.html';
                $data['topcolor'] = '#FF0000';
                $data['data'] = array(
                    'first'=>array(
                        'value' => "充值完成通知",
                        'color' => '#FF0000'
                    ),
                    'accountType'=>array(
                        'value' => '充值类型'
                    ),
                    'account'=>array(
                        'value' => '惠积分',
                        'color' => '#FF0000'
                    ),
                    'amount'=>array(
                        'value' => $content
                    ),
                    'result'=>array(
                        'value' => '充值成功'
                    ),
                    'remark'=>array(
                        'value' => "如果满意我们的服务，欢迎留言好评！\n如有疑问，请拨打客服电话：".$server_mobile,
                        'color' => '#FF0000'
                    )
                );
                break;
            case 'Transfer' ://惠积分转账
                $data['template_id'] = 'RLRj_JolYutHOiiRHQxQQtqoiWcp0opTI3_0h7xhPyk';//正式模板id
                $data['url'] = __HOME__.'wap/User-money.html';
                $data['topcolor'] = '#FF0000';
                $data['data'] = array(
                    'first'=>array(
                        'value' => "",
                        'color' => '#FF0000'
                    ),
                    'remark'=>array(
                        'value' => "拼团更实惠，点击查看更多拼团活动！\n如有疑问，请拨打客服电话：".$server_mobile,
                        'color' => '#FF0000'
                    )
                );
                break;
            case 'group_success' ://拼团成功
                //获取拼团信息
                $sql = "SELECT og.start_time,og.group_limit,g.goods_name FROM ".$dao->table('group_order')." AS og";
                $sql .= " INNER JOIN ".$dao->table('group_buying_product')." AS gb ON gb.product_id = og.group_id";
                $sql .= " INNER JOIN ".$dao->table('goods').' AS g ON g.goods_id = gb.goods_id';
                $sql .= " WHERE og.gid = ".$content;
                $group_info = $dao->queryOne($sql);
                if( empty($group_info) ){
                    return false;
                }
                $data['template_id'] = 'wVPjPHYmVYkRvMH2M9VlOBQzp33kZy7Hk1gXdDmDlYk';//正式模板id
                $data['url'] = __HOME__.'wap/GroupBuying-index.html';
                $data['topcolor'] = '#FF0000';
                $data['data'] = array(
                    'first'=>array(
                        'value' => "【 拼团成功通知 】",
                        'color' => '#FF0000'
                    ),
                    'keyword1'=>array(
                        'value' => "【 ${group_info['group_limit']}人团 】 ${group_info['goods_name']}"
                    ),
                    'keyword2'=>array(
                        'value' => date('Y年m月d日',$group_info['start_time'])
                    ),
                    'remark'=>array(
                        'value' => "拼团更实惠，点击查看更多拼团活动！\n如有疑问，请拨打客服电话：".$server_mobile,
                        'color' => '#FF0000'
                    )
                );
                break;
            case 'group_fail' ://拼团失败
                //获取拼团信息
                $sql = "SELECT og.start_time,og.end_time,og.group_limit,g.goods_name FROM ".$dao->table('group_order')." AS og";
                $sql .= " INNER JOIN ".$dao->table('group_buying_product')." AS gb ON gb.product_id = og.group_id";
                $sql .= " INNER JOIN ".$dao->table('goods').' AS g ON g.goods_id = gb.goods_id';
                $sql .= " WHERE og.gid = ".$content;
                $group_info = $dao->queryOne($sql);
                if( empty($group_info) ){
                    return false;
                }
                //获取参与人数
                $sql = "SELECT count(gid) as nums FROM ".$dao->table('group_order')." WHERE parent_id = ".$content;
                $group_count = $dao->queryOne($sql);
                if( empty($group_count['nums']) ){
                    $group_count = 1;
                }else{
                    $group_count = $group_count['nums'] + 1;
                }
                $data['template_id'] = 'ZE5nXBq1FIu2odA4ZV-aVKB_RX5uP70WU1Y8nFmNHAo';//正式模板id
                $data['url'] = __HOME__.'wap/GroupBuying-index.html';
                $data['topcolor'] = '#FF0000';
                $data['data'] = array(
                    'first'=>array(
                        'value' => "【 拼团失败通知 】",
                        'color' => '#FF0000'
                    ),
                    'keyword1'=>array(
                        'value' => "【 ${group_info['group_limit']}人团 】 ${group_info['goods_name']}"
                    ),
                    'keyword2'=>array(
                        'value' => $group_count
                    ),
                    'keyword3'=>array(
                        'value' => date('Y年m月d日',$group_info['end_time'])
                    ),
                    'remark'=>array(
                        'value' => "拼团更实惠，点击试试其他团吧！\n如有疑问，请拨打客服电话：".$server_mobile,
                        'color' => '#FF0000'
                    )
                );
                break;
        }
        return $data;
    }

    //获取退换货推送信息
    private function refund_order_cont($refund_info,$type,$server_mobile){
        $first = '';
        $remark = '';
        switch($refund_info['setems_status']){
            case '-1' ://商家第一次拒绝申请
                $first = "商家拒绝${type}通知";
                $remark = "商家拒绝${type}，点击查看详情！\n如有疑问，请拨打客服电话：".$server_mobile;
                break;
            case '0' ://商家第一次未审核
                $first = "${type}申请成功通知";
                $remark = "请保持宝贝包装齐全，不影响二次销售，否则不予${type}。\n注：（不接受任何快递到付！）\n感谢您的配合与谅解！\n如有需要，请拨打客服电话：".$server_mobile;
                break;
            case '1' ://同意买家发货
                $first = "商家同意${type}通知";
                $remark = "请将宝贝寄回商家指定的地址（注：不接受快递到付）。\n如有疑问，请拨打客服电话：".$server_mobile;
                break;
            case '2' ://买家已发货 等待商家收货
                switch($refund_info['seller_remark']){
                    case '0' ://等待商家收货 （用户发货）
                        return false;
                        break;
                    case '1' ://商家通过收货
                        //交给财务
                        switch($refund_info['status']){
                            case '0' ://等待审核
                                $first = '商家确认收货通知';
                                $remark = "请耐心等待平台处理，感谢您的支持！\n如有疑问，请拨打客服电话：".$server_mobile;
                                if( $type == '换货' ){
                                    $remark = "商家已确认收货，并尽快为您发货，感谢您的支持！\n如有疑问，请拨打客服电话：".$server_mobile;
                                }
                                break;
                            case '1' ://审核通过
                                $first = "${type}成功通知";
                                $remark = "请耐心等待平台处理，感谢您的支持！\n如有疑问，请拨打客服电话：".$server_mobile;
                                break;
                            case '2' :
                                $first = "${type}失败通知";
                                $remark = "商家已拒绝${type}，点击查看详情！\n如有疑问，请拨打客服电话：".$server_mobile;
                                break;
                            default :
                                return false;
                                break;
                        }
                        break;
                    case '2' ://商家拒绝收货
                        $first = '商家拒绝收货通知';
                        $remark = "商家拒绝收货，商品将退回，点击查看详情！\n如有疑问，请拨打客服电话：".$server_mobile;
                        break;
                    default :
                        return false;
                        break;
                }
                break;
            default :
                return false;
                break;
        }
        return array('first'=>$first,'remark'=>$remark);
    }

    //图文信息测试
    public function sand_test($openid,$sendtype,$cont){
        $Aces = $this->get_wx_config();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$Aces['access_token'];
        // 内容配置
        $data = array(
            'touser' => $openid,
            'msgtype' => $sendtype,
            'text' => array(
                'content' => $cont
            )
        );
        $jsonArr = $this->httpPost($url,json_encode($data,JSON_UNESCAPED_UNICODE));
        $json = json_decode($jsonArr, true);
    }

    //微信配置config
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

    //兑换商城微信配置config
    public function getExchangeConfigParam($url,$share){
        //微信配置
        $timestamp = time();
        $nonceStr = $this -> getNonceStr();
        // $config['appId'] = self::$appid;
        $config['appId'] = self::$appid;
        $config['timestamp'] = $timestamp;
        $config['nonceStr'] = $nonceStr;
        $config['signature'] = $this -> getJsExchangeSignature($url,$timestamp,$nonceStr);
        //微信分享配置
        $shareMessage = $this->get_shareMessage($share['link'],$share);
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

    //获取access_token
    function get_access_token(){
        $wxUrl = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.self::$appid.'&secret='.self::$appsecret;
        $info = $this->httpGet($wxUrl);
        return $info;
    }

    //获取ticket
    //需要先获取access_token
    function get_ticket($accessToken){
        $ticketUrl = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$accessToken.'&type=jsapi';
        $ticket = $this->httpGet($ticketUrl);
        return $ticket;
    }

    /**
     * 获取 签名的ticket 或 access_token
     * @param $get_type      all [全拿] acc [只拿access_token]
     */
    public function get_wx_config(){
        //获取配置
        $table = $this->getTable();
        $get_sql_where = 'config_name = :access_token or config_name = :jsapi_ticket';
        $config_name['jsapi_ticket'] = 'jsapi_ticket';
        $config_name['access_token'] = 'access_token';
        $get_sql = 'select * from '.$table.' where '.$get_sql_where;
        $config_all = $this->getAllSafely($get_sql,$config_name);
        $config = [];
        foreach( $config_all as $con ){
            $config[$con['config_name']] = $con;
        }
        //检查时间是否过期
        $time_up = 7000;//秒
        $t = time();
        $exp_t = bcsub($t,$time_up,0);
        //检查是否存在 且若 access_token 的值为空 或 更新时间过期 重新获取 access_token 和 ticket
        if( empty($config['access_token']) || empty($config['access_token']['value']) || empty($config['access_token']['update_time']) || $config['access_token']['update_time'] <= $exp_t  ){
            $accessTokenArr = $this->get_access_token();
            $accessToken = $accessTokenArr['access_token'];//1获取accesstoken;
            $jsapiTicketArr = $this->get_ticket($accessToken);
            $jsapiTicket = $jsapiTicketArr['ticket'];//2获取jsapiTicket
            try{
                $this->begin();
                //更新access_token
                if( $this->updateSafely(['value'=>$accessToken,'update_time'=>$t,'last_time'=>$config['access_token']['update_time']],['config_name'=>'access_token']) === false){
                    throw new Exception('网络错误更新失败');
                }
                //更新ticket
                if( $this->updateSafely(['value'=>$jsapiTicket,'update_time'=>$t,'last_time'=>$config['jsapi_ticket']['update_time']],['config_name'=>'jsapi_ticket']) === false){
                    throw new Exception('网络错误更新失败');
                }
                $this->commit();
            }catch( Exception $e ){
                $this->rollback();
                $error_msg = $e->getMessage();
            }
        }else{
            $accessToken = $config['access_token']['value'];
            $jsapiTicket = $config['jsapi_ticket']['value'];
        }

        return array('access_token'=>$accessToken,'jsapi_ticket'=>$jsapiTicket);
    }

    /**
     *获取js签名
     */
    public function getJsSignature($timestamp,$nonceStr){
        $wx_config = $this->get_wx_config();
        $url = __HOME__.trim($_SERVER['REQUEST_URI'],'/');
        // $url = SHAREURL;
        // return $url;
        //3.签名算法
        $param = array(
            'noncestr' =>$nonceStr,
            'jsapi_ticket' => $wx_config['jsapi_ticket'],
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

    /**
     *兑换商城获取js签名
     */
    public function getJsExchangeSignature($url,$timestamp,$nonceStr){
        $wx_config = $this->get_wx_config();
        // $url = SHAREURL;
        // return $url;
        //3.签名算法
        $param = array(
            'noncestr' =>$nonceStr,
            'jsapi_ticket' => $wx_config['jsapi_ticket'],
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
            $cid = empty($_GET['cid']) ? '' : $_GET['cid'];
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
                        $url .= '?g=WapSite&c=Search&a=index&keyword='.$keyword;
                        $share['title'] = '通惠商品列表';
                        $share['digest'] = '搜索商品-'.$keyword;
                    }else{
                        $url .= $c.'-'.$a.'.html';
                    }
                    break;
                case 'Article' :
                    if(isset($ids) && !empty($ids)){
                        $url .= $c.'-'.$a.'-'.$ids;
                    }else{
                        $url .= $c.'-'.$a;
                    }
                    break;
                case 'Goods' :
                    if( $a == 'index' || $a == 'gindex' || $a == 'kindex' || $a == 'goodsList' ){
                        $url .= $c.'-'.$a;
                        if( $a == 'goodsList' && !empty($cid) ){
                            $url .= '-'.$cid;
                        }
                        if( ( $a == 'index' || $a == 'kindex' ) && !empty($ids) ){
                            $url .= '-'.$ids;
                            if( !empty($out_id) && empty($agent_id) ){
                                $url .= '-'.$out_id;
                            }else if( empty($out_id) && !empty($agent_id)){
                                $url .= '-0-'.$agent_id;
                            }
                            $goodsInfo = $this->goods_info($ids);
                            if(!empty($goodsInfo)){
                                if( $a == 'kindex' ){
                                    $t = time();
                                    $sec_kill = new SecKillModel();
                                    $sec_kill_table = $sec_kill->getTable();
                                    $sec_kill_goods_table = $sec_kill->getTable('sec_kill_goods');
                                    $sql = "SELECT g.kill_price as price FROM ${sec_kill_table} AS s INNER JOIN ${sec_kill_goods_table} AS g ON g.sec_kill_id = s.sec_kill_id WHERE s.wap = 1 AND s.start_time < ${t} AND s.end_time > ${t} AND g.goods_id = ${goodsInfo['goods_id']}";
                                    $pro_info = $sec_kill->getRowSafely($sql);
                                }else{
                                    $pro_model = new GoodsModel();
                                    $pro_info = $pro_model -> getRow(array('field'=>'goods_price as price','where'=>'goods_id = '.$goodsInfo['goods_id']));
                                }
                                $share['title'] = $pro_info['price'].'元 '.$goodsInfo['goods_name'];
                                $share['digest'] = $goodsInfo['goods_name'];
                                $share['img'] = $goodsInfo['list_image'];
                            }
                        }
                        if( $a == 'gindex' && !empty($ids) ){
                            $url .= '-'.$ids;
                            if( !empty($out_id) ){
                                $url .= '-'.$out_id;
                            }
                            //goods_id 获取 item_id
                            $goods_model = new GoodsItemModel();
                            $item_ids = $goods_model -> getRow(array('field'=>'item_id','where'=>'deleted = 0 and goods_id = '.$ids,'order'=>'item_price asc','limit'=>1));
                            if(!empty($item_ids)){
                                $goodsInfo = $this->goods_info($item_ids['item_id']);
                                if(!empty($goodsInfo)){
                                    $pro_model = new GoodsModel();
                                    $pro_info = $pro_model -> getRow(array('field'=>'goods_price','where'=>'goods_id = '.$goodsInfo['goods_id']));
                                    $share['title'] = $pro_info['goods_price'].'元 '.$goodsInfo['goods_name'];
                                    $share['digest'] = $goodsInfo['goods_name'];
                                    $share['img'] = $goodsInfo['list_image'];
                                }
                            }
                        }
                    }else if( $a = 'seckill' ){
                        $url .= $c.'-'.$a;
                        $share['title'] = '通惠秒杀，惠选好货，全网特惠马上抢购！';
                        $share['digest'] = '通惠秒杀，惠选好货，全网特惠马上抢购！';
                        $share['img'] = 'https://img.thgo8.com/assets/users/09/14/87c7e0420520b87ac1d6b4da691001e775b70169.png';
                    }else{
                        $url .= 'Index-index';
                    }
                    break;
                case 'GroupBuying' ://团购部分
                    $share['title'] = '新人拼团1.1元包邮';
                    $share['digest'] = '你的好友喊你来拼团啦！新人拼团低至1.1元包邮，赶紧来抢购！';
                    $share['img'] = 'https://img.thgo8.com/assets/images/11/02/0d4b289be5da224138a2552f111305f92cc10dfb.jpg';
                    if( $a == 'goods' && !empty($ids)){
                        $url .= $c.'-'.$a.'-'.$ids;
                        //goods_id 获取 item_id
                        $goods_model = new GoodsItemModel();
                        $item_ids = $goods_model -> getRow(array('field'=>'item_id','where'=>'deleted = 0 and goods_id = '.$ids,'order'=>'item_price asc','limit'=>1));
                        $pro_model = new GroupBuyingProductModel();
                        $pro_info = $pro_model -> getRow(array('field'=>'price','where'=>'goods_id = '.$ids));
                        if(!empty($item_ids) && !empty($pro_info) ){
                            $goodsInfo = $this->goods_info($item_ids['item_id']);
                            if(!empty($goodsInfo)){
                                $share['title'] = $pro_info['price'].'元 '.$goodsInfo['goods_name'];
                                $share['digest'] = $goodsInfo['goods_name'];
                                $share['img'] = $goodsInfo['list_image'];
                            }
                        }
                    }else if( $a == 'groupTheme' && !empty($ids) ){//团购主题
                        $url .= $c.'-'.$a.'-'.$ids;
                        //获取主题标题
                        $theme_model = new GroupBuyingThemeModel();
                        $theme_info = $theme_model->getRowSafely(
                            "SELECT title FROM ".$theme_model->getTable().' WHERE theme_id = :theme_id',
                            ['theme_id'=>$ids]
                        );
                        if( !empty($theme_info) ) {
                            $share['title'] = '通惠团购主题-' . $theme_info['title'];
                        }
                    }else if( $a == 'groupCate' && !empty($ids) ){//分类标题
                        $url .= $c.'-'.$a.'-'.$ids;
                        //获取分类标题
                        $type_model = new GroupBuyingTypeModel();
                        $type_info = $type_model->getRowSafely(
                            "SELECT title FROM ".$type_model->getTable().' WHERE type_id = :type_id',
                            ['type_id'=>$ids]
                        );
                        if( !empty($type_info) ){
                            $share['title'] = '通惠团购分类-'.$type_info['title'];
                        }
                    }else if( $a == 'groupCreate' ){//完成团购订单
                        //用order_id获取商品信息
                        $dao = Dao::instance();
                        $sql = 'SELECT p.goods_id,o.gid FROM '.$dao->table('group_order')." AS o INNER JOIN ".$dao->table('group_buying_product')." AS p ON p.product_id = o.group_id WHERE o.order_id = ${ids}";
                        $order_info = $dao->queryOne($sql);
                        if(!empty($order_info)){
                            //goods_id 获取 item_id
                            $goods_model = new GoodsItemModel();
                            $item_ids = $goods_model -> getRow(array('field'=>'item_id','where'=>'deleted = 0 and goods_id = '.$order_info['goods_id'],'order'=>'item_price asc','limit'=>1));
                            if( !empty($item_ids) ){
                                $goodsInfo = $this->goods_info($item_ids['item_id']);
                                $pro_model = new GroupBuyingProductModel();
                                $pro_info = $pro_model -> getRow(array('field'=>'price','where'=>'goods_id = '.$goodsInfo['goods_id']));
                                if(!empty($goodsInfo)){
                                    $share['title'] = $pro_info['price'].'元 '.$goodsInfo['goods_name'];
                                    $share['digest'] = '你的好友喊你来拼团啦！'.$goodsInfo['goods_name'];
                                    $share['img'] = $goodsInfo['list_image'];
                                }
                            }
                            $url .= 'GroupBuying-groupTeam-'.$order_info['gid'];
                        }
                    }else if( $a == 'groupTeam' ){
                        //用order_id获取商品信息
                        $dao = Dao::instance();
                        $sql = 'SELECT p.goods_id,o.gid FROM '.$dao->table('group_order')." AS o INNER JOIN ".$dao->table('group_buying_product')." AS p ON p.product_id = o.group_id WHERE o.gid = ${ids}";
                        $order_info = $dao->queryOne($sql);
                        if(!empty($order_info)){
                            //goods_id 获取 item_id
                            $goods_model = new GoodsItemModel();
                            $item_ids = $goods_model -> getRow(array('field'=>'item_id','where'=>'deleted = 0 and goods_id = '.$order_info['goods_id'],'order'=>'item_price asc','limit'=>1));
                            if( !empty($item_ids) ){
                                $goodsInfo = $this->goods_info($item_ids['item_id']);
                                $pro_model = new GroupBuyingProductModel();
                                $pro_info = $pro_model -> getRow(array('field'=>'price','where'=>'goods_id = '.$goodsInfo['goods_id']));
                                if(!empty($goodsInfo)){
                                    $share['title'] = $pro_info['price'].'元 '.$goodsInfo['goods_name'];
                                    $share['digest'] = '你的好友喊你来拼团啦！'.$goodsInfo['goods_name'];
                                    $share['img'] = $goodsInfo['list_image'];
                                }
                            }
                            $url .= 'GroupBuying-groupTeam-'.$order_info['gid'];
                        }
                    }else{
                        $url .= 'GroupBuying-index';
                    }
                    break;
                case 'Outlet' :
                    $url .= $c.'-'.$a;
                    if( $a == 'home' && !empty($ids) ){
                        $url .= '-'.$ids;
                        $outlet_info = $this->get_outlet($ids);
                        if(!empty($outlet_info)){
                            $share['title'] = '通惠门店';
                            $share['digest'] = '欢迎光临'.$outlet_info['outlet_name'];
                            $share['img'] = $outlet_info['outlet_pic'];
                        }
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
                case 'Wantonly' :
                    $share['title'] = '通惠任意购';
                    $share['digest'] = '通惠任意购';
                    break;
                case 'Special' :
                    if($a == 'special') {
                        $url .= $c . '-' . $a . '-' . $ids;
                        $special_info = $this->get_special($ids);
                        if (!empty($special_info)) {
                            $share['title'] = $special_info;
                            $share['digest'] = $special_info;
                        }
                    }
                    break;
                case 'SpecialActivity' :
                    $url .= $c.'-'.$a.'-'.$ids;
                    $act_info = $this->get_special_act($ids);
                    if(!empty($act_info)){
                        $share['title'] = $act_info['ac_name'];
                        $share['digest'] = $act_info['ac_title'];
                    }
                    break;
                case 'BrandActivity' ://品牌主题
                    $url .= $c.'-'.$a.'-'.$ids;
                    $act_info = $this->get_brand_special_act($ids);
                    if(!empty($act_info)){
                        $share['title'] = $act_info['activity_title'];
                        $share['digest'] = $act_info['recommend'];
                    }
                    break;
                case 'SecondActivity' :
                    $url .= $c.'-'.$a.'-'.$ids;
                    $act_info = $this->get_second_special_act($ids);
                    if(!empty($act_info)){
                        $share['title'] = $act_info['activity_title'];
                        $share['digest'] = $act_info['activity_title'];
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
                    $share['digest'] = '代理店 - 商品列表 - 通惠购微网站';
                    break;
                case 'Exchange' :
                    $url .= 'Exchange-index.html';
                    if( !empty($_GET['section']) && ( $_GET['section'] == 'search' || $_GET['section'] == 'list' || $_GET['section'] == 'product' ) ){
                        $url .= '/'.$_GET['section'];
                        if( !empty($_GET['ids']) ){
                            $url .= '/'.$_GET['ids'];
                        }
                    }
                    break;
                case 'NewUser' :
                    $url .= 'NewUser-goodsShare';
                    $share['title'] = '好货齐分享';
                    $share['digest'] = '好货齐分享，来看看你的好友都在买什么吧！';
                    break;
                case 'Noshery' :
                    $url .= 'Noshery-home-1';
                    $share['title'] = '猴急外卖';
                    $share['digest'] = '猴急外卖';
                    break;
                default:
                    $url .= $c.'-'.$a;
                    break;
            }
            if( $c != 'Search' && $c != 'Exchange' ){
                $url .= '.html';
            }
        }else{
            $url .= 'Index-index.html';
        }
        if($type == 'back'){
            return $url;
        }else{
            return $share;
        }
    }

    //获取分享商品页的信息配置
    public function goods_info($ids){
        $goods_model = new GoodsModel();
        $sql = 'select g.goods_id,g.goods_name,im.list_image from rmth_goods as g left join rmth_goods_item as i on i.goods_id = g.goods_id left join rmth_goods_images as im on im.goods_id = g.goods_id where i.item_id = '.$ids;
        $goods_info = $goods_model->getRow($sql);
        return $goods_info;
    }

    //主题页获取分享信息
    public function get_special($ids){
        $special_model = new SpecialModel();
        $special_info = $special_model->getOne(array('field'=>'special_name','where'=>' special_id = '.$ids));
        return $special_info;
    }

    //获取门店信息
    public function get_outlet($ids){
        $outlet_model = new OutletInfoModel();
        $outlet_info = $outlet_model -> getRow(array('field'=>'outlet_name,outlet_pic','where'=>' outlet_id = '.$ids));
        return $outlet_info;
    }

    //获取商家信息
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

    //获取品牌主题活动页面信息
    public function get_brand_special_act($ids){
        $ac_model = new BrandActivityModel();
        $ac_info = $ac_model->getRowSafely(
            "SELECT activity_title,recommend FROM ".$ac_model->getTable()." WHERE activity_id = :ac_id",
            ['ac_id'=>$ids]
        );
        return $ac_info;
    }
    //获取品牌主题活动页面信息
    public function get_second_special_act($ids){
        $ac_model = new SecondActivityModel();
        $ac_info = $ac_model->getRowSafely(
            "SELECT activity_title FROM ".$ac_model->getTable()." WHERE activity_id = :ac_id",
            ['ac_id'=>$ids]
        );
        return $ac_info;
    }

    //获取品牌页面的详情title
    public function get_brand($ids){
        $b_model = new BrandModel();
        $b_info = $b_model->getRow(array('field'=>'brand_name,brand_pic','where'=>'brand_id = '.$ids));
        return $b_info;
    }

    //秒杀商品消息推送
    public function sec_kill_push($sec_kill_id=0){
        //参数检查
        if(empty($sec_kill_id)){
            return false;
        }
        $sql = 'SELECT sec_kill_id,start_time FROM '.$this->getTable('sec_kill').' WHERE sec_kill_id = '.$sec_kill_id;
        $sec_kill_info = $this->getRowSafely($sql);
        if( empty($sec_kill_info) ){
            return false;
        }
        //获取秒杀场次商品信息
        $sql = ' SELECT goods_id FROM '.$this->getTable('sec_kill_goods').' WHERE sec_kill_id = '.$sec_kill_id;
        $goods_all = $this->getAllSafely($sql);
        if(!empty($goods_all)){
            $goods_list = '';
            foreach( $goods_all as $goods ){
                $goods_list .= $goods['goods_id'].',';
            }
            $goods_list = trim($goods_list,',');
            //获取需要推送的列表
            $sql = 'SELECT * FROM '.$this->getTable('sec_kill_goods_push').' WHERE sec_kill_id = '.$sec_kill_id.' AND goods_id in ('.$goods_list.') AND push_type = 2';
            $push_list = $this->getAllSafely($sql);
            if( empty($push_list)){
                return true;
            }
            $wx_config = $this->get_wx_config();
            $access_token = $wx_config['access_token'];
            foreach( $push_list as $list){
                if( !empty($list['wx_openid']) ){
                    $data = array(
                        'touser' => $list['wx_openid'],
                        'template_id' => 'P9hS5OBYrTBftvUGzW6jhAOHgXP7naCjn8fPPIVH76A',//测试
//                        'template_id' => 'wVPjPHYmVYkRvMH2M9VlOBQzp33kZy7Hk1gXdDmDlYk',//正式
                        'url' => __HOME__.'wap/Goods-gindex-'.$list['goods_id'].'.html',
                        'topcolor' => '#E066FF',//顶部颜色
                        'data' => array(
                            'first' => array(
                                'value' => '通惠团购活动提醒 群发测试',
                                'color' => '#FF0000'
                            ),
                            'keyword1' => array(
                                'value' => $list['goods_name'],
                                'color' => '#173177'
                            ),
                            'keyword2' => array(
                                'value' => date('Y-m-d H:i:s',$sec_kill_info['start_time']),
                                'color' => '#173177'
                            ),
                            'remark' => array(
                                'value' => '小主，万福金安！您心爱的宝贝即将开抢！点击赶紧入场吧！',
                                'color' => '#173177'
                            )
                        )
                    );
                    $data = json_encode($data);
                    //只请求 不判断成功与否
                    $this->httpPost('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token,$data);
                }
            }
            return true;
        }else{
            return false;
        }
    }

    //获取兑换商城分享配置
    public function excgabgeShareConfig($viewType,$ids,$user_id = 0){
        if($user_id != 0){
            $pid = '&p_id='.$user_id;
        }
        $config = array(
            'title' => '通惠购-兑换商城',
            'digest' => '兑换商城钜惠产品持续上新，更多优惠，请点击查看详情！',
            'link' => __HOME__.'wap/?g=WapSite&c=Exchange&a=index',
            'linkSignature' => __HOME__.'wap/Exchange-index.html',
            'img' => WapImg.'home/home_logo.png'
        );
        if( empty($viewType) ){
            return $config;
        }

        switch($viewType){
            case 'product' ://兑换商品
                $id = intval($ids);
                if( empty($id) ){
                    return $config;
                }
                //获取兑换商品详情
                $exchange_goods = new ExchangeGoodsModel();
                $goods_table = $exchange_goods->getTable();
                $goodsItem_table = $exchange_goods->getTable('exchange_goods_item');
                $goodsImg_table = $exchange_goods->getTable('exchange_goods_images');
                $sql = "SELECT g.goods_name,img.list_image FROM ${goods_table} AS g";
                $sql .= " INNER JOIN ${goodsItem_table} AS i ON i.goods_id = g.goods_id";
                $sql .= " INNER JOIN ${goodsImg_table} AS img ON img.goods_id = g.goods_id WHERE i.item_id = ${id}";
                $goods_info = $exchange_goods->getRowSafely($sql);
                if(empty($goods_info)){
                    return $config;
                }
                $config['title'] = '商品详情';
                $config['digest'] = $goods_info['goods_name'];
//                $config['link'] = __HOME__.'wap/Exchange-index.html/product/'.$id;
                $config['img'] = $goods_info['list_image'];
                break;
            default :
                return $config;
                break;
        }
        return $config;
    }

    /*
     *通过curl发送get请求
     * @param string $userInfoUrl
     * @return array
     */
    protected function httpGet($userInfoUrl){
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
    protected function httpPost($url,$data=''){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
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

}



?>