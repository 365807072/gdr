<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2019 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Powerless < wzxaini9@gmail.com>
// +----------------------------------------------------------------------

namespace app\space\controller;

use think\Request;
use AliPay\AopClient;
use app\third\WeChatPay;
use app\space\model\OrderModel;
use cmf\controller\HomeBaseController;

class NotifyController extends HomeBaseController
{
    public function _initialize()
    {
        parent::_initialize();
    }

    public function spaceAliPayNotify(Request $request)
    {
        $params = $request->post();
        $orderModel = new OrderModel();
        // 验签
//        $aliPay = new AopClient();
//        $aliConfig = config('pay')['ali_pay'];
//        $rsaCheck = $aliPay->rsaCheckV1($params, $aliConfig->ali_payRsaPublicKey,$params['sign_type']);
//        writeDebug(date('Y-m-d H:i:s', time()) . ' ali_pay result = ' . json_encode($params). ' check1 = '. json_encode($rsaCheck), 'ali_pay_test');

//        if($rsaCheck === true) {
            if ($params['trade_status'] == 'TRADE_SUCCESS' && ($params['total_amount'] > 0)) {
                $order = $orderModel->get_order([
                    'order_sn' => ['eq', $params['out_trade_no']],
                    'pay_status' => 0
                ]);
                writeDebug(date('Y-m-d H:i:s', time()) . ' ali_pay order_result = ' . json_encode($params). ' order = '. json_encode($order), 'ali_pay');

                if(!empty($order) && ($order['user_money'] == $params['total_amount'])){
                    $data = [
                        'order' => $order,
                        'pay' => [
                            'pay_code' => 'ali',
                            'pay_name' => '支付宝',
                            'trade_no' => $params['trade_no'],
                            'order_sn' => $params['out_trade_no'],
                            'total_amount' => $params['total_amount'],
                        ]
                    ];
                    $result = $orderModel->add_space($data);

                    if ($result !== true) exit('SUCCESS');
                    exit('SUCCESS');
                }else{
                    exit('SUCCESS');
                }
            }
//        }
        exit('SUCCESS');
    }

    public function spaceWxPayNotify(Request $request)
    {
        $params = $request->post();
        $wxPay = new WeChatPay();
        $app = $wxPay->getApp();

        $response = $app->handlePaidNotify(function($message, $fail){
            $orderModel = new OrderModel();
            // 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
            $order = $orderModel->get_order([
                'order_sn' => ['eq', $message['out_trade_no']],
                'pay_status' => 0
            ]);
            writeDebug(date('Y-m-d H:i:s', time()) . ' wx_pay result = ' . json_encode($message). ' order = '. json_encode($order), 'wx_pay');
            if(empty($order) || ($order['pay_status'] == 1)) return true; // 告诉微信，我已经处理完了，订单没找到，别再通知我了
            // 建议在这里调用微信的【订单查询】接口查一下该笔订单的情况，确认是已经支付

            if ($message['return_code'] === 'SUCCESS') { // return_code 表示通信状态，不代表支付状态

                if($message['result_code'] === 'SUCCESS'){  // 用户支付成功
                    $data = [
                        'order' => $order,
                        'pay' => [
                            'pay_code' => 'wx',
                            'pay_name' => '微信',
                            'trade_no' => isset($message['transaction_id'])?$message['transaction_id']:$message['trade_no'],
                            'order_sn' => $message['out_trade_no'],
                            'total_amount' => isset($message['total_fee'])?($message['total_fee'] * 100 ):$message['total_amount']
                        ]
                    ];
                    $result = $orderModel->add_space($data);
                    writeDebug(date('Y-m-d H:i:s', time()) . ' wx_pay db_result = ' . json_encode($result). ' order = '. json_encode($order), 'wx_pay');
//                    if($result !== true) exit('SUCCESS');
//                    exit('SUCCESS');
                    return true;

                } elseif ($message['result_code'] === 'FAIL') {  // 用户支付失败
                    $result = $orderModel->edit_order([
                        'order_sn'=>$message['out_trade_no'],
                        'pay_status'=>0
                    ],['pay_status' => 2]);

//                    if($result === false) exit('SUCCESS');
//                    exit('SUCCESS');
                    return true;
                }

            } else {
                return $fail('通信失败，请稍后再通知我');
            }

            return true; // 返回处理完成
        });

        $response->send(); // return $response;
    }
}