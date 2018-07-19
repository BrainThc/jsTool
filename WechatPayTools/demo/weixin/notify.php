<?php
define("PTS", true);
ini_set('date.timezone', 'Asia/Shanghai');
//require "../../include/loader.php";
//spl_autoload_register(['AutoLoader','defaultLoader']);
require "../../include/include.inc.php";
include('../order.php');

require_once "lib/WxPay.Api.php";
require_once 'lib/WxPay.Notify.php';
require_once 'log.php';

//初始化日志
$logHandler = new CLogFileHandler("logs/" . date('Y-m-d') . '.log');
$log = Log::Init($logHandler, 15);

class PayNotifyCallBack extends WxPayNotify {

	//查询订单
	public function Queryorder($transaction_id) {
		$input = new WxPayOrderQuery();
		$input->SetTransaction_id($transaction_id);
		$result = WxPayApi::orderQuery($input);
		Log::DEBUG("query:" . json_encode($result));
		if (array_key_exists("return_code", $result) && array_key_exists("result_code", $result) && $result["return_code"] == "SUCCESS" && $result["result_code"] == "SUCCESS") {
			return true;
		}
		return false;
	}

	/**
	 * @author Youyo <hailang111@126.com>
	 * @param int $tradeNo 
	 * @desc 查询订单(商户订单号查询)
	 *  */
	public function Queryorder_TradeNo($tradeNo) {
		header("Content-type: text/html; charset=utf-8");
		$input = new WxPayOrderQuery();
		$input->SetOut_trade_no($tradeNo);
		$result = WxPayApi::orderQuery($input);
		//支付状态描述转义
		$trade_state_desc = urldecode($result['trade_state_desc']);
		//赋值
		$result['trade_state_desc'] = $trade_state_desc;
		//添加订单号
		$result['order_id'] = $_SESSION['order_info']['order_sn'];
		//改造查询信息
		$result_str = '{';
		foreach ($result as $key => $value) {
			$result_str .= '"' . $key . '":"' . $value . '",';
		}
		$result_str = rtrim($result_str, ",");
		$result_str .= '}';

		if (array_key_exists("return_code", $result) && array_key_exists("result_code", $result) && $result["return_code"] == "SUCCESS" && $result["result_code"] == "SUCCESS") {
			if (array_key_exists("trade_state", $result) && $result["trade_state"] == "SUCCESS") {
				/* 成功状态 */
				//日志记录
				//Log::INFO("Queryorder_TradeNo:" . $result['order_id'] . ' ' . $result['trade_state'] . "\r\n" . $result_str);
				return "SUCCESS";
			} else {
				/* 失败状态 */
				//日志记录
				//Log::INFO("Queryorder_TradeNo_WARN:" . $result['order_id'] . ' ' . $result['trade_state'] . "\r\n" . $result_str);
				return "FAIL";
			}
		}
	}

	//重写回调处理函数
	public function NotifyProcess($data, &$msg) {
		Log::DEBUG("call back:" . json_encode($data));
		$notfiyOutput = array();

		if (!array_key_exists("transaction_id", $data)) {
			$msg = "输入参数不正确";
			return false;
		}
		//查询订单，判断订单真实性
		if (!$this->Queryorder($data["transaction_id"])) {
			$msg = "订单查询失败";
			return false;
		}

        OrderMasterModel::debug('weixinpay', "tradeNO:{$data['transaction_id']}, orderNO:{$data['out_trade_no']}, money:{$data['total_fee']}");
		$order = new Order();
		$order->updateOrderStatus($data['out_trade_no'], $data['transaction_id'], 'weixinpay', $data['total_fee']);
		return true;
	}

}

Log::DEBUG("begin notify");
$notify = new PayNotifyCallBack();
$notify->Handle(false);


