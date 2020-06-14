<?php
/**
 * Created by PhpStorm.
 * User: Zero
 * Date: 2019/6/3
 * Time: 14:27
 */
namespace app\third;

use think\Db;
use EasyWeChat\Factory;

//微信支付
class WeChatPay
{
    private $config;
    private $app;

    public function __construct()
    {
        $this->config = config('pay')['wx_pay'];
        $this->app = Factory::payment($this->config);
    }

    public function getApp(){ return $this->app; }

    public function pay($order, $notify_url)
    {
        $result = $this->app->order->unify([
            'body'              => $order['body'],
            'out_trade_no'      => $order['order_sn'],
            'total_fee'         => $order['user_money']*100,
            'spbill_create_ip'  => get_ip(), // 可选，如不传该参数，SDK 将会自动获取相应 IP 地址
            'notify_url'        => $notify_url, // 支付结果通知网址，如果不设置则会使用配置里的默认地址
            'trade_type'        => 'APP',   // 请对应换成你的支付方式对应的值类型
//            'openid'            => $user['id'],
        ]);

        if (!isset($result['prepay_id'])) {//判断返回参数中是否有prepay_id
//            return $result['err_code_des'];
            return $result;
        }
        $jssdk = $this->app->jssdk;
        $config = $jssdk->appConfig($result['prepay_id']);

        return $config;
    }

    //退款
    public function refund($order, $notify_url)
    {
        $dateTime = date('Y-m-d H:i:s', time());

        // 根据商户订单号退款 参数分别为：商户订单号、商户退款单号、订单金额、退款金额、其他参数
        $result = $this->app->refund->byOutTradeNumber($order['order_sn'], $order['refund_sn'], $order['total_price']*100, $order['refund_price']*100, [
            // 可在此处传入其他参数，详细参数见微信支付文档
            'refund_desc' => $order['refund_desc'],
            'notify_url' =>  $notify_url
        ]);

        if(($result['return_code'] == 'SUCCESS') && ($result['return_msg'] == 'OK') && ($result['result_code'] == 'SUCCESS')){
            writeDebug($dateTime . ' refund success: order_sn=' . $order['order_sn'], 'wx_refund', 5, true);
            return true;
        } else {
            writeDebug($dateTime . 'refund fail, msg:' . json_encode($result) . ' order_sn=' . $order['order_sn'], 'wx_refund', 5, true);
            return false;
        }
    }

    public function transfer($order)
    {
        $dateTime = date('Y-m-d H:i:s', time());

        $result = $this->app->transfer->toBalance([
            'partner_trade_no' => $order['order_sn'], // 商户订单号，需保持唯一性(只能是字母或者数字，不能包含有符号)
            'openid' => $order['openid'],
            'check_name' => 'NO_CHECK', // NO_CHECK：不校验真实姓名, FORCE_CHECK：强校验真实姓名
            //'re_user_name' => $order['username'], // 如果 check_name 设置为FORCE_CHECK，则必填用户真实姓名
            'amount' => $order['trans_amount']*100, // 企业付款金额，单位为分
            'desc' => $order['order_title'], // 企业付款操作说明信息。必填
        ]);

        if($result['return_code'] == 'SUCCESS' && $result['return_msg'] == 'OK'){
            // 直接处理
            if($result['result_code'] == 'SUCCESS'){

                $user = Db::name('member')->where(['id' => $order['user_id']])->find();

                $user_money = bcsub($user['balance'], $order['trans_amount'], 2);
                //更新转账日志
                $add = [
                    'user_id'       =>  $order['user_id'],
                    'order_id'      =>  $result['payment_no'],
                    'order_sn'      =>  $order['order_sn'],
                    'pay_no'        =>  $order['user'],
                    'amount'        =>  $order['trans_amount'],
                    'explain'       =>  $order['order_title'],
                    'status'        =>  1,
                    'create_time'   =>  time(),
                    'update_time'   =>  time(),
                ];
                Db::name('member_transfer_log')->insert($add);
                //更新余额
                Db::name('member')->where(['id' => $order['user_id']])->update(['balance'=>$user_money]);
                //记录余额变动
                $add = [
                    'user_id'       =>  $order['user_id'],
                    'value'         =>  $order['trans_amount'],
                    'type'          =>  0,
                    'remark'        =>  $order['order_title'],
                    'create_time'   =>  time(),
                ];
                Db::name('member_account_log')->insert($add);

                writeDebug($dateTime . ' transfer success: order_sn=' . $order['order_sn'], 'wx_pay', 5, true);
                return true;
            } elseif ($result['result_code'] == 'FAIL') {
                writeDebug($dateTime . 'transfer fail, msg:' . $result['err_code'].'->'.$result['err_code_des'] . ' order_sn=' . $order['order_sn'], 'wx_pay', 5, true);
            }

            writeDebug($dateTime . ' transfer success: order_sn=' . $order['order_sn'], 'wx_pay', 5, true);
            return true;
        } else {
            writeDebug($dateTime . 'transfer fail, msg:' . $result['err_code'].'->'.$result['err_code_des'] . ' order_sn=' . $order['order_sn'], 'wx_pay', 5, true);
            return false;
        }
    }
    
}