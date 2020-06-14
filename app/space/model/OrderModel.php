<?php
namespace app\space\model;

use app\third\AliPay;
use app\third\WeChatPay;
use think\Db;
use think\Model;

class OrderModel extends Model
{
    public function get_product($map, $field = [])
    {
        $data = Db::name('space_product')->where($map)->field($field)->find();
        return $data;
    }

    public function get_products($map, $field = [], $page='1', $limit='10', $order = ['create_time'=>'desc'])
    {
        $data = Db::name('space_product')->where($map)->field($field)->order($order)->paginate($limit, false, ['page' => $page])->toArray();
        return $data;
    }

    public function add_order($param)
    {
        try{
            Db::startTrans();

            $order_sn = getOrderNo();
            $order_id = Db::name('order')->insertGetId([
                'user_id' => $param['user']['id'],
                'product_id' => $param['product']['id'],
                'order_sn' => $order_sn,
                'space' => $param['product']['space'],
                'money' => $param['product']['money'],
                'user_money' => $param['product']['money'],
                'pay_type' => $param['pay_type'],
                'type' => 0,
                'create_time' => time(),
                'update_time' => time()
            ]);
            if(!$order_id) throw new \Exception('order table insert fail', 100201);

            switch ($param['pay_type']){
                case 0:
                    $user = Db::name('member')->where([
                        'id' => $param['user']['id']
                    ])->find();
                    if(empty($user)) throw new \Exception('请登录再试', 100901);
                    if($param['product']['money'] > $user['balance']) throw new \Exception('余额不足', 100901);

                    $res = Db::name('member')->where([
                        'id' => $param['user']['id']
                    ])->setDec('balance', $param['product']['money']);
                    if(!$res) throw new \Exception('member table update fail', 100601);

                    $r = Db::name('member_account_log')->insert([
                        'user_id' => $param['user']['id'],
                        'value' => $param['product']['money'],
                        'value_type' => 0,
                        'type' => 0,
                        'remark' => '用户购买空间',
                        'create_time' => time()
                    ]);
                    if(!$r) throw new \Exception('member_account_log table insert fail', 100602);

                    // add_space
                    $r1 = Db::name('space_relation')->insert([
                        'user_id' => $param['user']['id'],
                        'space' => $param['product']['space'],
                        'create_time' => time(),
                        'expired_time' => strtotime(" + ".(int) $param['product']['effect']." day"),
                    ]);
                    if(!$r1) throw new \Exception('space_relation table insert fail', 100602);

                    $r2 = Db::name('member_space')->where([
                        'user_id' => $param['user']['id']
                    ])->setInc('space', $param['product']['space']);
                    if(!$r2) throw new \Exception('member_space table update fail', 100612);

                    $r3 = Db::name('order')->where([
                        'id' => $order_id
                    ])->update(['pay_status' => 1]);
                    if(!$r3) throw new \Exception('order table update fail', 100613);

                    $result = [];
                    break;
                case 1:
                    $pay = new AliPay();
                    $result = $pay->pay([
                        'order_sn' => $order_sn,
                        'user_money' => $param['product']['money'],
                        'body' => '购买临时工空间',
                    ], rtrim($param['base_url'], '/').'/space/Notify/spaceAliPayNotify');
                    break;
                case 2:
                    $pay = new WeChatPay();
                    $result = $pay->pay([
                        'order_sn' => $order_sn,
                        'user_money' => $param['product']['money'],
                        'body' => '购买临时工空间',
                    ], rtrim($param['base_url'], '/').'/space/Notify/spaceWxPayNotify');
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

    public function add_space($param)
    {
        try{
            Db::startTrans();

            $param['user'] = Db::name('member')->where([
                'id' => $param['order']['user_id']
            ])->find();
            if(empty($param['user'])) throw new \Exception('user empty', 100611);

            $param['product'] = Db::name('space_product')->where([
                'id' => $param['order']['product_id']
            ])->find();
            if(empty($param['product'])) throw new \Exception('product empty', 100612);

            $result = Db::name('space_relation')->insert([
                'user_id' => $param['user']['id'],
                'space' => $param['product']['space'],
                'create_time' => time(),
                'expired_time' => strtotime(" + ".(int) $param['product']['effect']." day"),
            ]);
            if(!$result) throw new \Exception('space_relation table insert fail', 100602);

            $res = Db::name('member_space')->where([
                'user_id' => $param['user']['id']
            ])->setInc('space', $param['product']['space']);
            if(!$res) throw new \Exception('member_space table update fail', 100612);

            $r = Db::name('order')->where([
                'id' => $param['order']['id']
            ])->update([
                'pay_status' => 1,
                'update_time' => time(),
                'trade_no' => $param['pay']['trade_no']
            ]);
            if(false === $r) throw new \Exception('order table update fail', 100613);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            writeDebug(date('Y-m-d H:i:s', time()) . ' wx_pay error = ' . $e->getMessage(), 'add_space_error');
            return $e->getMessage();
        }
    }

    public function get_order($map, $field = [])
    {
        $data = Db::name('order')->where($map)->field($field)->find();
        return $data;
    }

    public function edit_order($map, $data = [])
    {
        $res = Db::name('order')->where($map)->update($data);
        return $res;
    }
}