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
namespace app\user\model;

use app\third\AliPay;
use app\third\WeChatPay;
use think\Db;
use think\Model;

class PersonalModel extends Model
{
    //个人审核状态
    public function personal_reviews($user_id)
    {
        $personal_status = Db::table('cmf_personal_check')
            ->field('personal_status')
            ->where('uid', $user_id)
            ->find();

        return $personal_status;
    }

    //发布兼职扣款
    function createJobCost($data)
    {

    }


    public function add_order($param)
    {
        try {
            Db::startTrans();

            $nowTime = time();
            $order_sn = getOrderNo();
            $order_id = Db::name('order')->insertGetId([
                'user_id' => $param['user_id'],
                'order_sn' => $order_sn,
                'money' => $param['money'],
                'user_money' => $param['money'],
                'pay_type' => $param['pay_type'],
                'type' => ($param['type'] == 1 ? 2 : 1),
                'create_time' => $nowTime,
                'update_time' => $nowTime
            ]);
            if (!$order_id) throw new \Exception('order table insert fail', 100201);

            switch ($param['pay_type']) {
                case 0:
                    $user = Db::name('member')->where([
                        'id' => $param['user_id']
                    ])->find();
                    if (empty($user)) throw new \Exception('请登录再试', 100901);
                    if ($param['money'] > $user['balance']) throw new \Exception('余额不足', 100901);

                    $personal_check = Db::name('personal_check')->where([
                        'uid' => $param['order']['user_id'],
                    ])->find();
                    $is_person_check = 0;
                    $personal_check['business_pay'] = isset($personal_check['business_pay'])?$personal_check['business_pay']:0;
                    $personal_check['personal_pay'] = isset($personal_check['personal_pay'])?$personal_check['personal_pay']:0;

                    $res = Db::name('member')->where([
                        'id' => $param['user_id']
                    ])->setDec('balance', $param['money']);
                    if (!$res) throw new \Exception('member table update fail', 100601);

                    $r = Db::name('member_account_log')->insert([
                        'user_id' => $param['user_id'],
                        'value' => $param['money'],
                        'value_type' => 1,
                        'type' => ($param['type'] == 1 ? 3 : 2),
                        'remark' => ($param['type'] == 1 ? '用户添加企业认证保证金' : '用户添加个人认证保证金'),
                        'create_time' => $nowTime
                    ]);
                    if (!$r) throw new \Exception('member_account_log table insert fail', 100602);

                    // add_money
                    if ($param['type'] == 0) {
                        $money = round(($personal_check['personal_pay'] + $param['money']), 2);
                        $update = ['personal_deposit' => $money];
                        if($money > 9.9){
                            $update['personal_pay'] = 1;
                            $is_person_check = 1;
                        }else{
                            $update['personal_pay'] = 0;
                        }
                        $r1 = Db::name('personal_check')->where([
                            'uid' => $param['user_id'],
                        ])->update($update);
                        if (false === $r1) throw new \Exception('personal_check table insert fail', 100602);
                    } elseif ($param['type'] == 1) {
                        $money = round(($personal_check['business_pay'] + $param['money']), 2);
                        $update = ['business_deposit' => $money];
                        if($money > 199){
                            $update['business_pay'] = 1;
                            $is_person_check = 1;
                        }else{
                            $update['business_pay'] = 0;
                        }
                        $r1 = Db::name('personal_check')->where([
                            'uid' => $param['user_id'],
                        ])->update($update);
                        if (false === $r1) throw new \Exception('personal_check table insert fail', 100602);
                    }

                    $r2 = Db::name('order')->where([
                        'id' => $order_id
                    ])->update(['pay_status' => 1]);
                    if (false === $r2) throw new \Exception('order table update fail', 100613);

                    $r3 = Db::name('member')->where(['id'=>$param['user_id']])->update(['is_person_check'=>$is_person_check]);
                    if (false === $r3) throw new \Exception('member table update fail', 100613);

                    $result = 'success';
                    break;
                case 1:
                    $pay = new AliPay();
                    $result = $pay->pay([
                        'order_sn' => $order_sn,
                        'user_money' => $param['money'],
                        'body' => ($param['type'] == 1 ? '用户添加企业认证保证金' : '用户添加个人认证保证金'),
                    ], rtrim($param['base_url'], '/') . '/notify/Notify/marginAliPayNotify');
                    break;
                case 2:
                    $pay = new WeChatPay();
                    $result = $pay->pay([
                        'order_sn' => $order_sn,
                        'user_money' => $param['money'],
                        'body' => ($param['type'] == 1 ? '用户添加企业认证保证金' : '用户添加个人认证保证金'),
                    ], rtrim($param['base_url'], '/') . '/notify/Notify/marginWxPayNotify');
                    break;
                default:
                    throw new \Exception('支付方式错误', 100211);
                    break;
            }

            Db::commit();
            return ['code' => 200, 'result' => $result];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => 500, 'result' => $e->getMessage()];
        }
    }

    public function add_margin($param)
    {
        try {
            Db::startTrans();

            $personal_check = Db::name('personal_check')->where([
                'uid' => $param['order']['user_id'],
            ])->find();
            $is_person_check = 0;
            $personal_check['business_pay'] = isset($personal_check['business_pay'])?$personal_check['business_pay']:0;
            $personal_check['personal_pay'] = isset($personal_check['personal_pay'])?$personal_check['personal_pay']:0;

            $r = Db::name('member_account_log')->insert([
                'user_id' => $param['order']['user_id'],
                'value' => $param['order']['money'],
                'value_type' => 1,
                'type' => ($param['order']['type'] == 2 ? 3 : 2),
                'remark' => ($param['order']['type'] == 2 ? '用户添加企业认证保证金' : '用户添加个人认证保证金'),
                'create_time' => time()
            ]);
            if (!$r) throw new \Exception('member_account_log table insert fail', 100602);

            // add_money
            if ($param['order']['type'] == 2) {
                $money = round(($personal_check['business_pay'] + $param['order']['money']), 2);
                $update = ['business_deposit' => $money];
                if($money > 199){
                    $update['business_pay'] = 1;
                    $is_person_check = 1;
                }else{
                    $update['business_pay'] = 0;
                }
                $r1 = Db::name('personal_check')->where([
                    'uid' => $param['order']['user_id'],
                ])->update($update);
                if (false === $r1) throw new \Exception('personal_check table update fail', 100602);
            } elseif ($param['order']['type'] == 1) {
                $money = round(($personal_check['personal_pay'] + $param['order']['money']), 2);
                $update = ['personal_deposit' => $money];
                if($money > 9.9){
                    $update['personal_pay'] = 1;
                    $is_person_check = 1;
                }else{
                    $update['personal_pay'] = 0;
                }
                $r1 = Db::name('personal_check')->where([
                    'uid' => $param['order']['user_id'],
                ])->update($update);
                if (false === $r1) throw new \Exception('personal_check table update fail', 100602);
            }

            $r3 = Db::name('member')->where(['id'=>$param['user_id']])->update(['is_person_check'=>$is_person_check]);
            if (false === $r3) throw new \Exception('member table update fail', 100613);

            $r2 = Db::name('order')->where([
                'id' => $param['order']['id']
            ])->update(['pay_status' => 1]);
            if (false === $r2) throw new \Exception('order table update fail', 100613);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => 500, 'result' => $e->getMessage()];
        }
    }
}