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

namespace app\notify\controller;

use app\user\model\PersonalModel;
use think\Exception;
use think\Request;
use AliPay\AopClient;
use app\third\WeChatPay;
use app\space\model\OrderModel;
use cmf\controller\HomeBaseController;
use think\Db;

class NotifyController extends HomeBaseController
{
    public function _initialize()
    {
        parent::_initialize();
    }

    //保证金支付宝回调
    public function marginAliPayNotify(Request $request)
    {
        $params = $request->post();
        $orderModel = new OrderModel();
        $personalModel = new PersonalModel();
        // 验签
//        $aliPay = new AopClient();
//        $aliConfig = config('pay')['ali_pay'];
//        $rsaCheck = $aliPay->rsaCheckV1($params, $aliConfig->ali_payRsaPublicKey,$params['sign_type']);

//        if($rsaCheck === true) {
            if ($params['trade_status'] == 'TRADE_SUCCESS' && ($params['total_amount'] > 0)) {
                $order = $orderModel->get_order([
                    'order_sn' => ['eq', $params['out_trade_no']],
                    'pay_status' => 0
                ]);

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
                    $result = $personalModel->add_margin($data);

                    if ($result !== true) exit('OK');
                    exit('SUCCESS');
                }else{
                    exit('OK');
                }
            }
//        }
        exit('OK');
    }

    //保证金微信回调
    public function marginWxPayNotify(Request $request)
    {
        $params = $request->post();
        $wxPay = new WeChatPay();
        $app = $wxPay->getApp();

        $response = $app->handlePaidNotify(function($message, $fail){
            $orderModel = new OrderModel();
            $personalModel = new PersonalModel();
            // 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
            $order = $orderModel->get_order([
                'order_sn' => ['eq', $message['out_trade_no']],
                'pay_status' => 0
            ]);
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
                        ],
                    ];
                    $result = $personalModel->add_margin($data);

                    return true;
//                    if($result !== true) exit('OK');
//                    exit('SUCCESS');

                } elseif ($message['result_code'] === 'FAIL') {  // 用户支付失败
                    $result = $orderModel->edit_order([
                        'order_sn'=>$message['out_trade_no'],
                        'pay_status'=>0
                    ],['pay_status' => 2]);

                    return true;
//                    if($result === false) exit('OK');
//                    exit('SUCCESS');
                }

            } else {
                return $fail('通信失败，请稍后再通知我');
            }

            return true; // 返回处理完成
        });

        $response->send(); // return $response;
//        exit('OK');
    }

    //兼职发布微信回调
    function jobWxPayNotify(Request $request) {
        $params = $request->post();
        $wxPay = new WeChatPay();
        $app = $wxPay->getApp();

        $response = $app->handlePaidNotify(function($message, $fail){
            $order_sn = $message['out_trade_no'];
            $jobDb = Db::name('job');
            // 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
            $job = $jobDb->where(['order_sn' => $order_sn])->find();
            if ($job['status'] > 1) return true;// 告诉微信，我已经处理完了，订单没找到，别再通知我了
            // 建议在这里调用微信的【订单查询】接口查一下该笔订单的情况，确认是已经支付

            if ($message['return_code'] === 'SUCCESS') { // return_code 表示通信状态，不代表支付状态

                if($message['result_code'] === 'SUCCESS'){  // 用户支付成功

                    $jobDb->where(['id' => $job['id']])->update(['status'=>1, 'pay_type'=>'wx']);
                    //记录支付数据
                    $value = $job['person']*$job['money'];
                    if ($job['type'] == 3) $value = $job['person']*$job['money']*$job['pieces'];
                    $add = [
                        'user_id'       =>  $job['user_id'],
                        'value'         =>  $value,
                        'value_type'    =>  1,
                        'type'          =>  2,
                        'other_id'      =>  $job['id'],
                        'remark'        =>  '兼职发布支付',
                        'create_time'   =>  time(),
                    ];
                    Db::name('member_account_log')->insert($add);
                } elseif ($message['result_code'] === 'FAIL') {  // 用户支付失败

                }

            } else {
                return $fail('通信失败，请稍后再通知我');
            }

            return true; // 返回处理完成
        });

        $response->send(); // return $response;
    }

    //兼职发布支付宝回调
    function jobAliPayNotify(Request $request) {
        $params = $request->post();
        $jobDb = Db::name('job');
        // 验签
        $aliPay = new AopClient();
        $aliConfig = config('pay')['ali_pay'];
        $rsaCheck = $aliPay->rsaCheckV1($params, $aliConfig->ali_payRsaPublicKey,$params['sign_type']);

        if($rsaCheck === true) {
            if ($params['trade_status'] == 'TRADE_SUCCESS' && ($params['total_amount'] > 0)) {
                $order_sn = $params['out_trade_no'];
                $job = $jobDb->where(['order_sn' => $order_sn])->find();
                if ($job['status'] > 1) exit('SUCCESS');
                if(!empty($job) && ($job['money']*$job['person'] == $params['total_amount'])){
                    $result = $jobDb->where(['id' => $job['id']])->update(['status' => 1, 'pay_type'=>'ali']);
                    //记录支付数据
                    $value = $job['person']*$job['money'];
                    if ($job['type'] == 3) $value = $job['person']*$job['money']*$job['pieces'];
                    $add = [
                        'user_id'       =>  $job['user_id'],
                        'value'         =>  $value,
                        'value_type'    =>  1,
                        'type'          =>  2,
                        'other_id'      =>  $job['id'],
                        'remark'        =>  '兼职发布支付',
                        'create_time'   =>  time(),
                    ];
                    Db::name('member_account_log')->insert($add);

                    if ($result !== true) exit('SUCCESS');
                    exit('SUCCESS');
                }else{
                    exit('SUCCESS');
                }
            }
        }
        exit('SUCCESS');
    }

    //临时工支付微信回调
    function parttimeWxPayNotify() {
        $wxPay = new WeChatPay();
        $app = $wxPay->getApp();

        $response = $app->handlePaidNotify(function($message, $fail){
            $order_sn = $message['out_trade_no'];
            $parttimeDb = Db::name('parttime');
            // 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
            $parttime = $parttimeDb->where(['order_sn' => $order_sn])->find();
            if ($parttime['status'] > 2) return true;// 告诉微信，我已经处理完了，订单没找到，别再通知我了
            // 建议在这里调用微信的【订单查询】接口查一下该笔订单的情况，确认是已经支付

            if ($message['return_code'] === 'SUCCESS') { // return_code 表示通信状态，不代表支付状态

                if($message['result_code'] === 'SUCCESS'){  // 用户支付成功

                    try {
                        $res = $parttimeDb->where(['id' => $parttime['id']])->update(['status'=>3, 'pay_type'=>'wx']);
                        if (!$res) throw new Exception('更新支付状态失败');
                        //记录支付数据
                        $value = $parttime['money'];
                        $add = [
                            'user_id'       =>  $parttime['boss_id'],
                            'value'         =>  $value,
                            'value_type'    =>  1,
                            'type'          =>  4,
                            'other_id'      =>  $parttime['id'],
                            'remark'        =>  '雇主支付',
                            'create_time'   =>  time(),
                        ];
                        $res = Db::name('member_account_log')->insert($add);
                        if (!$res) throw new Exception('雇主支付记录失败');

                        //被雇者增加余额  单结束才+
                        //$worker_id = $parttime['worker_id'];
                        //$res =  Db::name('member')->where(['id' => $worker_id])->setInc('balance', $value);
                        //if (!$res) throw new Exception('临时工余额更新失败');

                        //$add = [
                        //    'user_id'       =>  $worker_id,
                        //    'value'         =>  $value,
                        //    'value_type'    =>  1,
                        //    'type'          =>  4,
                        //    'other_id'      =>  $parttime['id'],
                        //    'remark'        =>  '临时工支付',
                        //    'create_time'   =>  time(),
                        //];
                        //$res = Db::name('member_account_log')->insert($add);
                        //if (!$res) throw new Exception('临时工余额变动记录失败');

                    } catch (Exception $e) {
                        writeDebug("雇主支付回调{$parttime['order_sn']}:".$e->getMessage(), 'notify');
                    }

                } elseif ($message['result_code'] === 'FAIL') {  // 用户支付失败

                }

            } else {
                return $fail('通信失败，请稍后再通知我');
            }

            return true; // 返回处理完成
        });

        $response->send(); // return $response;
    }

    //临时工支付微信回调
    function parttimeAliPayNotify(Request $request) {
        $params = $request->post();
        $parttimeDb = Db::name('parttime');
        // 验签
        $aliPay = new AopClient();
        $aliConfig = config('pay')['ali_pay'];
        $rsaCheck = $aliPay->rsaCheckV1($params, $aliConfig->ali_payRsaPublicKey,$params['sign_type']);

        if($rsaCheck === true) {
            if ($params['trade_status'] == 'TRADE_SUCCESS' && ($params['total_amount'] > 0)) {
                $order_sn = $params['out_trade_no'];
                $parttime = $parttimeDb->where(['order_sn' => $order_sn])->find();
                if ($parttime['status'] > 2) exit('SUCCESS');
                if(!empty($parttime) && ($parttime['money'] == $params['total_amount'])){

                    try {
                        $res = $parttimeDb->where(['id' => $parttime['id']])->update(['status'=>3, 'pay_type'=>'ali']);
                        if (!$res) throw new Exception('更新支付状态失败');
                        //记录支付数据
                        $value = $parttime['money'];
                        $add = [
                            'user_id'       =>  $parttime['boss_id'],
                            'value'         =>  $value,
                            'value_type'    =>  1,
                            'type'          =>  4,
                            'other_id'      =>  $parttime['id'],
                            'remark'        =>  '雇主支付',
                            'create_time'   =>  time(),
                        ];
                        $res = Db::name('member_account_log')->insert($add);
                        if (!$res) throw new Exception('雇主支付记录失败');

                        //被雇者增加余额  单结束才+
                        //$worker_id = $parttime['worker_id'];
                        //$res = Db::name('member')->where(['id' => $worker_id])->setInc('balance', $value);
                        //if (!$res) throw new Exception('临时工余额更新失败');

                        //$add = [
                        //    'user_id'       =>  $worker_id,
                        //    'value'         =>  $value,
                        //    'value_type'    =>  1,
                        //    'type'          =>  4,
                        //    'other_id'      =>  $parttime['id'],
                        //    'remark'        =>  '临时工支付',
                        //    'create_time'   =>  time(),
                        //];
                        //$res = Db::name('member_account_log')->insert($add);
                        //if (!$res) throw new Exception('临时工余额变动记录失败');

                        exit('SUCCESS');
                    } catch (Exception $e) {
                        writeDebug("雇主支付回调{$parttime['order_sn']}:".$e->getMessage(), 'notify');
                    }
                    exit('SUCCESS');
                }else{
                    exit('SUCCESS');
                }
            }
        }
        exit('SUCCESS');
    }

    //临时工退款微信回调
    function orderRefundWxNotify() {
        $wxPay = new WeChatPay();
        $app = $wxPay->getApp();
        $response = $app->handleRefundedNotify(function ($message, $reqInfo, $fail) {
            $time = date('Y-m-d H:i:s');
            $message = array_merge($message, $reqInfo);
            $out_refund_no = $message['out_refund_no'];
            $refund = Db::name('parttime_refund')->where(['refund_sn' => $out_refund_no])->find();

            if ( (isset($message['return_code']) && $message['return_code']==='SUCCESS')) {
                if ( (isset($message['refund_status'])&&$message['refund_status']==='SUCCESS') ) {
                    $refundUpArr = array('type' => '2');
                    $parttimeUpArr = array('refund_type' => '2');
                    Db::startTrans();
                    try {
                        $rs = Db::name('parttime_refund')->where(['refund_sn' => $out_refund_no])->update($refundUpArr);
                        if (!$rs) throw new Exception('退款表更新失败');
                        $rs = Db::name('parttime')->where(['order_sn' => $refund['order_sn']])->update($parttimeUpArr);
                        if (!$rs) throw new Exception('临时工表更新');
                        Db::commit();
                        writeDebug($time.'临时工退款回调成功 '.$out_refund_no."\n", 'refund');
                    } catch (Exception $e) {
                        Db::rollback();
                        writeDebug($time.'临时工退款回调失败 '.$out_refund_no.':'.$e->getMessage()."\n", 'refund');
                    }
                }
            }
            return true; // 返回 true 告诉微信“我已处理完成”
            // 或返回错误原因 $fail('参数格式校验错误');
        });

        $response->send();
    }
}