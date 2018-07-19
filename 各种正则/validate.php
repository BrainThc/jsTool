<?php
defined('PTS80')||exit('PTS80 Defined');
// 验证邮件格式是否正确
function validate_email($email)
{
	return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
// 验证手机格式是否正确
function validate_mobile($mobile)
{
	return preg_match('/^1[3|4|5|6|7|8|9]\d{9}$/', $mobile);
}
// 验证IP地址是否正确
function validate_ip($ip)
{
	return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}
// 验证是否是URL
function validate_url($url)
{
	return filter_var($url, FILTER_VALIDATE_URL) !== false;
}
/* 由6~20位字母和数字组合,且以字母开头 */
function validate_login_password($password){
	return preg_match('/^[a-zA-Z]\w{5,19}$/', $password);
}
/* 6位数字支付密码 */
function validate_pay_word($payword){
	return preg_match('/^\d{6,6}$/', $payword);
}
function validate_user_name($user_name){
	return preg_match('/^[a-zA-Z]\w{5,19}$/', $user_name);
}