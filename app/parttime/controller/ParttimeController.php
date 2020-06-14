<?php

//命名空间
namespace app\parttime\controller;

use app\third\AliPay;
use app\third\WeChatPay;
use app\validate\ParttimeValidate;
use app\validate\SearchValidate;
use think\Db;
use think\db\Query;
use think\Exception;
use think\facade\Validate;
use cmf\controller\HomeBaseController;

class ParttimeController extends HomeBaseController
{
    public function _initialize()
    {
        parent::_initialize();
        parent::_isLogin();
        if(!in_array(request()->action(), ['search'])){
            parent::_initUser();
        };
    }

    /*
     * 临时工 -  发布兼职
     */
    public function lsg_put_job()
    {
        $data = $this->request->param();
        $time = $this->request->param('time/a');
        $errorInfo = (new ParttimeValidate())->goCheck('lsg_put_job');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        //查出认证状态
        $info = Db::table('cmf_personal_check')
            ->where('uid', $this->user['user_id'])
            ->field([
                'personal_status',
                'business_status',
            ])
            ->find();
//        if(!$info) $this->ajaxResult(-1, '只允许通过个人认证或商户认证才能使用该兼职发布功能');

        if (true || (($info['personal_status'] == 1) && ($info['personal_pay'] == 1)) || (($info['business_status'] == 1)  && ($info['business_pay'] == 1))) {
            //发布兼职用户id
            $up['user_id'] = $this->user['user_id'];

            if(empty($data['title'])) $this->ajaxResult(-1, '请填写兼职标题');
            if(empty($data['endtime']) || empty($data['starttime'])) $this->ajaxResult(-1, '请选择日期');
            $up['endtime'] = strtotime($data['endtime']);
            $up['starttime'] = strtotime($data['starttime']);

            //标题
            $up['title'] = $data['title'];
            //开始时间
//            $time = getCurrentNextTime($data['endtime']);
//            $up['starttime'] = $time['todayStart'];
//            $up['endtime'] = $time['todayEnd'];

            //备注
            if (!empty($data['remarks'])) {
                $up['remarks'] = $data['remarks'];
            }

            //工作结算
            if (!empty($data['type']) || !empty($data['pieces'])) {
                $up['type'] = $data['type'];//类型
                $up['person'] = $data['person']; //人数
                $up['money'] = $data['money']; //金额
                if ($data['type'] == 3) {
                    $up['type'] = $data['type'];//类型
                    $up['person'] = $data['person']; //人数
                    $up['money'] = $data['money']; //金额
                    $up['pieces'] = $data['pieces']; //件数
                }
            } else {
                $this->ajaxResult(-1, '请填写工资结算');
            }

            //工作要求
            if (!empty($data['hope'])) {
                $up['hope'] = $data['hope'];
            }

            //工作要求图片
            if (!empty($data['picture'])) {
                $up['picture'] = $data['picture'];
            }

            //工作内容
            if (!empty($data['content'])) {
                $up['content'] = $data['content'];

            }

            if(empty($data['address']) || empty($data['province']) || empty($data['city']) || empty($data['district'])) $this->ajaxResult(-1, '请填写详细地址');

            //工作地址
            if (!empty($data['address'])) {
                $address = $data['province'] . $data['city'] . $data['district'] . $data['address'];
                $url = 'http://restapi.amap.com/v3/geocode/geo?address=' . $address . '&key=b413542c6a53d085cc5b3466520d7e79';
                if ($result = file_get_contents($url)) {
                    $result = json_decode($result, true);
                    //判断是否成功
                    if (!empty($result['count'])) {
                        $LongitudeLatitude = $result['geocodes']['0']['location'];
                        $longitudes = explode(',', $LongitudeLatitude);
                        $up['longitude'] = $longitudes['0'];
                        $up['latitude'] = $longitudes['1'];
                    } else {
                        $this->ajaxResult(-1, '请填写正确的地址');
                    }
                }
                $up['province'] = $data['province'];
                $up['city'] = $data['city'];
                $up['district'] = $data['district'];
                $up['address'] = $address;
            }

            //联系方式
            if (!empty($data['phone'])) {
                $up['phone'] = $data['phone'];
            }

            //总额
            $up['total'] = $data['total'];

            //创建时间
            $up['create_time'] = time();
            $up['join_id'] = '';

            //兼职状态
            $up['status'] = 0;  // 支付回调改状态
            $up['order_sn'] = '1' . time('YmdHis') . rand(100, 999);

            //插入数据库
            $db_feed = Db::table('cmf_job')->insertGetId($up);

            if ($db_feed > 0) {
                $this->ajaxResult(1, '发布成功', ['job_id' => $db_feed]);
            } else {
                $this->ajaxResult(-1, '保存失败请重新提交');
            }
        } else {
            $this->ajaxResult(-1, '只允许通过个人认证或商户认证才能使用该兼职发布功能');
        }
    }

    //发布兼职支付
    function createJobPay()
    {
        $user = $this->user;
        $param = request()->param();
        if (!isset($param['id']) || !$param['id']) $this->ajaxResult(-1, '兼职信息错误');
        if (!isset($param['type'])) $this->ajaxResult(-1, '支付类型错误');

        $rs = Db::name('job')->where(['id' => $param['id'], 'user_id' => $user['user_id']])->find();
        if (!$rs || $rs['status'] != 0) $this->ajaxResult(-1, '兼职信息错误');

        if ($rs['type'] == 3) $money = $rs['person'] * $rs['money'] * $rs['pieces'];  //件数
        else $money = $rs['person'] * $rs['money'];

        switch ($param['type']) {
            case 'wx':
                $user_money = round($money * 100, 2);
                $data = [
                    'order_sn' => $rs['order_sn'],
                    'user_money' => $user_money,
                    'body' => '兼职发布' . $rs['order_sn'],
                ];
                $result = (new WeChatPay())->pay($data, $this->request->domain() . 'notify/Notify/jobWxPayNotify');
                break;
            case 'ali':
                $user_money = round($money, 2);
                $data = [
                    'order_sn' => $rs['order_sn'],
                    'user_money' => $user_money,
                    'body' => '兼职发布' . $rs['order_sn'],
                ];
                $result = (new AliPay())->pay($data, $this->request->domain() . 'notify/Notify/jobAliPayNotify');
                break;
            case 'balance':  //余额
                if (empty($this->user['user_pass'])) $this->ajaxResult(-1, '请先设置密码');
                if (!isset($param['pass']) || empty($param['pass'])) $this->ajaxResult(-1, '请输入支付密码');
                if (cmf_password($param['pass']) != $this->user['user_pass']) $this->ajaxResult(-1, '支付密码不对，请重新输入');
                if ($user['balance'] < $money) $this->ajaxResult(-1, '余额不足');
                $user_money = bcsub($user['balance'], $money, 2);
                Db::startTrans();
                try {
                    //更新余额
                    $res = Db::name('member')->where(['id' => $user['user_id']])->update(['balance' => $user_money]);
                    if (!$res) throw new Exception('fail');
                    //记录余额变动
                    $add = [
                        'user_id' => $user['user_id'],
                        'value' => $rs['person'] * $rs['money'],
                        'value_type' => 0,
                        'type' => 2,
                        'other_id' => $rs['id'],
                        'remark' => '兼职发布余额支付' . $rs['order_sn'],
                        'create_time' => time(),
                    ];
                    $res = Db::name('member_account_log')->insert($add);
                    if (!$res) throw new Exception('fail');

                    $res = Db::name('job')->where(['id' => $rs['id']])->update(['status' => 1, 'pay_type' => 'balance']);
                    if (!$res) throw new Exception('fail');

                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->ajaxResult(-1, '余额支付失败');
                }
                $result = 'success';  //余额操作成功直接success
                break;
            default:
                $this->ajaxResult(-1, '支付类型错误');
                break;
        }
        $this->ajaxResult(1, 'success', $result);
    }

    //兼职结算
    function jobAccountPay()
    {
        $user = $this->user;

        $param = request()->param();
        //验证支付密码
//        if (!isset($param['user_paypass'])) $this->ajaxResult(-1,'支付密码必须');
        if (!$user['user_pass']) $this->ajaxResult(-1, '请先设置登录密码');
        $flag = cmf_compare_password($param['user_paypass'], $user['user_pass']);
        if (!$flag) $this->ajaxResult(-1, '支付密码错误');
        //是否提供有兼职id
        if (!isset($param['id']) || !$param['id']) $this->ajaxResult(-1, '兼职信息错误');
        //
        if (!isset($param['type']) || !in_array($param['type'], [0, 1])) $this->ajaxResult(-1, '兼职信息错误');
        if ($param['type'] == 0 && !isset($param['worker_id'])) $this->ajaxResult(-1, '人员必须');

        $rs = Db::name('job')->where(['id' => $param['id']])->find();
        if ($rs['status'] != 2) $this->ajaxResult(-1, '兼职信息错误');
        $where = ['settlement_status' => 0, 'job_id' => $param['id']];
        if ($param['type'] == 0) $where['worker_id'] = $param['worker_id'];
        $member_job = Db::name('job_people')->where($where)->select();
        if (!$member_job) $this->ajaxResult(-1, '暂无人员需要结算');

        Db::startTrans();
        try {
            $value = $rs['money'];
            if ($rs['type'] == 3) $value = $rs['money'] * $rs['pieces'];
            foreach ($member_job as $member) {
                //更新用户余额
                $res = Db::name('member')->where(['id' => $member['worker_id']])->setInc('balance', $value);
                if (!$res) throw new Exception('更新用户余额失败');
                //记录数值变动
                $add = [
                    'user_id' => $rs['user_id'],
                    'value' => $value,
                    'value_type' => 1,
                    'type' => 3,
                    'other_id' => $rs['id'],
                    'remark' => '兼职结算',
                    'create_time' => time(),
                ];
                $res = Db::name('member_account_log')->insert($add);
                if (!$res) throw new Exception('记录用户余额失败');

                //更新状态为结束
                $where = ['id' => $member['id']];
                Db::name('job_people')->where($where)->update(['settlement_status' => 1]);
            }
            $where = ['settlement_status' => 0, 'job_id' => $param['id']];
            $member_job = collection(Db::name('job_people')->where($where)->select())->toArray();
            if (!$member_job) {
                $res = Db::name('job')->where(['id' => $param['id']])->update(['status' => 3]);
                if (!$res) throw new Exception('更新兼职状态失败');
                //判断是否有未使用金额，直接退款
                $totalMoney = Db::name('member_account_log')->where(['other_id' => $rs['id'], 'type' => 2])->value('value');
                $useMoney = Db::name('member_account_log')->where(['other_id' => $rs['id'], 'type' => 3])->sum('value');
                $user_money = bcsub($totalMoney, $useMoney, 2);
                if ($user_money > 0) {
                    switch ($rs['pay_type']) {
                        case 'wx':
                            $data = [
                                'order_sn' => $rs['order_sn'],
                                'refund_sn' => '3' . date('YmdHis') . rand(100, 999),
                                'total_price' => $totalMoney,
                                'refund_price' => $user_money,
                                'refund_desc' => '结算剩余退款',
                            ];
                            (new WeChatPay())->refund($data, 1);
                            break;
                        case 'ali':
                            $data = [
                                'out_trade_no' => $rs['order_sn'],
                                'out_request_no' => '3' . date('YmdHis') . rand(100, 999),
                                'refund_amount' => $user_money,
                            ];
                            (new AliPay())->refund($data, 1);
                            break;
                        case 'balance':
                            //更新余额
                            $res = Db::name('member')->where(['id' => $user['user_id']])->setInc('balance', $user_money);
                            if (!$res) throw new Exception('更新余额失败');
                            //记录余额变动
                            $add = [
                                'user_id' => $user['user_id'],
                                'value' => $user_money,
                                'type' => 0,
                                'remark' => '兼职结算剩余退回余额',
                                'create_time' => time(),
                            ];
                            $res = Db::name('member_account_log')->insert($add);
                            if (!$res) throw new Exception('fail');
                            break;
                    }
                }

            }
            Db::commit();
            $this->ajaxResult(1, '结算成功');
        } catch (Exception $e) {
            Db::rollback();
            $this->ajaxResult(-1, $e->getMessage());
        }
    }

    //订单支付
    function orderPay()
    {
        $user = $this->user;
        $param = request()->param();
        if (!isset($param['id']) || !$param['id']) $this->ajaxResult(-1, '订单信息错误');
        if (!isset($param['type'])) $this->ajaxResult(-1, '支付类型错误');

        $rs = Db::name('parttime')->where(['id' => $param['id'], 'boss_id' => $user['user_id']])->find();
        if (!$rs || $rs['working_status'] != 2) $this->ajaxResult(-1, '订单信息错误');

        $money = $rs['money'];

        switch ($param['type']) {
            case 'wx':
                $data = [
                    'order_sn' => $rs['order_sn'],
                    'user_money' => $money,
                    'body' => '临时工支付' . $rs['order_sn'],
                ];
                $rs = (new WeChatPay())->pay($data, $this->request->domain() . 'notify/Notify/parttimeWxPayNotify');
                break;
            case 'ali':
                $data = [
                    'order_sn' => $rs['order_sn'],
                    'user_money' => $money,
                    'body' => '临时工支付' . $rs['order_sn'],
                ];
                $rs = (new AliPay())->pay($data, $this->request->domain() . 'notify/Notify/parttimeAliPayNotify');
                break;
            case 'balance':
                if ($user['balance'] < $money) $this->ajaxResult(-1, '余额不足');
                $user_money = bcsub($user['balance'], $money, 2);

                Db::startTrans();
                try {
                    //更新余额
                    $res = Db::name('member')->where(['id' => $user['user_id']])->update(['balance' => $user_money]);
                    if (!$res) throw new Exception('fail');
                    //记录余额变动
                    $add = [
                        'user_id' => $user['user_id'],
                        'value' => $money,
                        'value_type' => 0,
                        'type' => 4,
                        'remark' => '雇主支付' . $rs['order_sn'],
                        'other_id' => $rs['id'],
                        'create_time' => time(),
                    ];
                    $res = Db::name('member_account_log')->insert($add);
                    if (!$res) throw new Exception('fail');

                    $res = Db::name('parttime')->where(['id' => $rs['id']])->update(['working_status' => 3, 'pay_type' => 'balance']);
                    if (!$res) throw new Exception('fail');

                    //临时工增加余额  单结束才+
                    //$worker_id = $rs['worker_id'];
                    //$res = Db::name('member')->where(['id' => $worker_id])->setInc('balance', $money);
                    //if (!$res) throw new Exception('fail');

                    //$add = [
                    //    'user_id'       =>  $worker_id,
                    //    'value'         =>  $money,
                    //    'value_type'    =>  1,
                    //    'type'          =>  4,
                    //    'other_id'      =>  $rs['id'],
                    //    'remark'        =>  '临时工余额增加',
                    //    'create_time'   =>  time(),
                    //];
                    //$res = Db::name('member_account_log')->insert($add);
                    //if (!$res) throw new Exception('fail');

                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->ajaxResult(-1, '余额支付失败');
                }
                $rs = 'success';  //余额操作成功直接success
                break;
            default:
                $this->ajaxResult(-1, '支付类型错误');
        }
        $this->ajaxResult(1, 'success', $rs);
    }

    //订单确认完成
    function orderFinish()
    {
        $user = $this->user;
        $param = request()->param();
        if (!isset($param['id']) || !$param['id']) $this->ajaxResult(-1, '订单信息错误');
        $rs = Db::name('parttime')->where(['id' => $param['id'], 'boss_id' => $user['user_id']])->find();
        if (!$rs || $rs['working_status'] != 4) $this->ajaxResult(-1, '订单信息错误');
        try {
            //被雇者增加余额  单结束才+
            $worker_id = $rs['worker_id'];
            $res = Db::name('member')->where(['id' => $worker_id])->setInc('balance', $rs['money']);
            if (!$res) throw new Exception('临时工余额更新失败');

            $add = [
                'user_id' => $worker_id,
                'value' => $rs['money'],
                'value_type' => 1,
                'type' => 4,
                'other_id' => $rs['id'],
                'remark' => '临时工支付',
                'create_time' => time(),
            ];
            $res = Db::name('member_account_log')->insert($add);
            if (!$res) throw new Exception('临时工余额变动记录失败');
        } catch (Exception $e) {
            $this->ajaxResult(-1, $e->getMessage());
        }

        Db::table('cmf_parttime')->where('id', $rs['id'])->update(array('working_status' => 5));

        $this->ajaxResult(1, '操作成功');


    }

    //订单退款
    function orderRefund()
    {
        $user = $this->user;
        $param = request()->param();
        if (!isset($param['id']) || !$param['id']) $this->ajaxResult(-1, '订单信息错误');

        $parttime_refund = Db::name('parttime_refund')->where(['id' => $param['id']])->find();
        if (!$parttime_refund) $this->ajaxResult(-1, '退款信息错误');
        $parttime = Db::name('parttime')->where(['order_sn' => $parttime_refund['order_sn'], 'boss_id' => $user['user_id']])->find();
        if (!$parttime || ($parttime['working_status'] != 3 && $parttime['working_status'] != 4)) $this->ajaxResult(-1, '订单信息错误');

        $money = $parttime['money'];
        switch ($parttime['pay_type']) {
            case 'wx':
                $data = [
                    'order_sn' => $parttime['order_sn'],
                    'refund_sn' => $parttime_refund['refund_sn'],
                    'total_price' => $money,
                    'refund_price' => $money,
                    'refund_desc' => '结算剩余退款',
                ];
                $rs = (new WeChatPay())->refund($data, $this->request->domain() . 'notify/Notify/orderRefundWxNotify');
                break;
            case 'ali':
                $data = [
                    'out_trade_no' => $parttime['order_sn'],
                    'out_request_no' => $parttime_refund['refund_sn'],
                    'refund_amount' => $money,
                ];
                $rs = (new AliPay())->refund($data, $this->request->domain() . 'notify/Notify/orderRefundAliNotify');
                break;
            case 'balance':
                //更新余额
                Db::startTrans();
                try {
                    $res = Db::name('member')->where(['id' => $parttime['boss_id']])->setInc('balance', $money);
                    if (!$res) throw new Exception('更新余额失败');
                    //记录余额变动
                    $add = [
                        'user_id' => $parttime['boss_id'],
                        'value' => $money,
                        'type' => 0,
                        'remark' => '雇主余额退回' . $parttime['order_sn'],
                        'other_id' => $parttime['id'],
                        'create_time' => time(),
                    ];
                    $res = Db::name('member_account_log')->insert($add);
                    if (!$res) throw new Exception('更新余额记录失败');
                    Db::commit();
                    $rs = true;
                } catch (Exception $e) {
                    Db::rollback();
                    $this->ajaxResult(-1, $e->getMessage());
                }
                break;
            default:
                $this->ajaxResult(-1, '退款类型错误');
                break;
        }
        if ($rs) $this->ajaxResult(1, '退款成功');
        $this->ajaxResult(1, '退款失败');
    }

    // 兼职
    public function search()
    {
        $data = $this->request->param();
        $keyword = isset($data['keyword']) ? htmlspecialchars($data['keyword']) : '';

//        if (!empty($data['keyword'])) {
//            $errorInfo = (new SearchValidate())->goCheck('search');
//            if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);
//        }
        $sort = empty($data['sort']) ? 0 : $data['sort'];// 0默认 1距离 2价格高 3价格低
        $field = '0 AS disance';
        if (isset($data['lat']) && !empty($data['lat']) && isset($data['lng']) && !empty($data['lng'])) {
            $field = "round(IFNULL((
                                6371392.89 * acos (
                                 cos ( radians({$data['lat']}) )
                                 * cos( radians(latitude ) )
                                 * cos( radians( longitude ) - radians({$data['lng']}) )
                                 + sin ( radians({$data['lat']}) )
                                 * sin( radians( latitude ) )
                               )
                            ),999999999999)/1000, 0) AS disance";
        }
        //排序 默认0
        switch ($sort) {
            case 1:
                $order = ['disance'=>'asc']; //最近
                break;
            case 2:
                $order = ['money'=>'desc']; //价格高
                break;
            case 3:
                $order = ['money'=>'asc'];  //价格低
                break;
            default:
                $order = ['id'=>'desc'];
        }
        $info = Db::table('cmf_job')
            ->where(function (Query $query) use ($data, $keyword) {
                $province = empty($data['province']) ? '' : $data['province'];
                $city = empty($data['city']) ? '' : $data['city'];
                $district = empty($data['district']) ? '' : $data['district'];
//                if (!empty($province)) { //省
//                    $query->where(['province' => $province]);
//                }
//                if (!empty($city)) { //市
//                    $query->where(['city' => $city]);
//                }
//                if (!empty($district)) { //区
//                    $query->where(['district' => $district]);
//                }

                if($province == '全国'){
                    $query->where(['country'=>'中国']);
                }else{
                    if (!empty($province)) { //省
                        $query->where(['province' => $province]);
                    }
                    if($city == '全省'){
                        $city_pid = Db::name('region')->where(['level'=>1,'pid'=>0,'name'=>$province])->value('id');
                        $city_name = Db::name('region')->where(['level'=>2,'pid'=>$city_pid])->column('name');
                        $query->where(['city' => ['in', $city_name]]);
                    }else{
                        if (!empty($city)) { //市
                            $query->where(['city' => $city]);
                        }
                        if($district == '全市'){
                            $district_pid = Db::name('region')->where(['level'=>2,'name'=>$city])->value('id');
                            $district_name = Db::name('region')->where(['level'=>3,'pid'=>$district_pid])->column('name');
                            $query->where(['district' => ['in', $district_name]]);
                        }else{
                            if (!empty($district)) { //区
                                $query->where(['district' => $district]);
                            }
                        }
                    }
                }

                if (!empty($keyword)) { //关键词
                    $query->where('title', 'like', "%$keyword%");
                }
            })
            ->where(['endtime'=>['gt', time()], 'status'=>1])
            ->field([
                'title', //工种名称
                'money', //工种价格
                'type', //结算类型
                'create_time',//时间
                'endtime',//时间
                'id',//兼职id
                'user_id',//老板id
                'province',//老板id
                'city',//老板id
                'district',//老板id
            ])
            ->field($field)
            ->group('id')
            ->order($order)
            ->paginate($this->pageSize, false, ['page' => $this->currentPage])
            ->toArray();
        if(!empty($info['data'])){
            foreach ($info['data'] as $k => $v) {
                if ($v['user_id'] == $this->user['user_id']) {
                    unset($info[$k]);
                }
            }
        }
        empty($info) ? $this->ajaxResult(-1, '没有数据') : $this->ajaxResult(1, '成功', $info);
    }


    //列表
    public function parttime_list()
    {

        $data = $this->request->param();

        $info = Db::table('cmf_job')->field(['create_time', 'title', 'type', 'money', 'id'])->select()->toArray();

        empty($info) ? $this->ajaxResult(-1, '没有数据') : $this->ajaxResult(1, '成功', $info);

    }

    /*
   * 临时工 -  兼职详情页
   */
    public function lsg_details()
    {
        $errorInfo = (new ParttimeValidate())->goCheck('lsg_details');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);
        $id = $this->request->param('id');
        $type = $this->request->param('type');

        if (Db::table('cmf_job'))
            $re = Db::table('cmf_job')->where('id', $id)->setInc('views');

        $work = Db::table('cmf_job')
            ->alias('j')
            ->where(['j.id' => $id])
            ->field([
                'm.id',//雇主id
                'm.user_nickname', //昵称
                'm.avatar', //头像
                'j.id as job_id', //兼职id
                'j.title', //兼职标题
                'j.starttime',//工作开始时间
                'j.endtime',//工作结束时间
                'j.money',//单价
                'j.type',//单价类型
                'j.hope',//工作要求
                'j.content',//工作内容
                'j.address',//工作地址
                'j.phone',//联系电话
                'j.join_id',// 报名者id
                'j.person',// 总报名人数
                'j.views',//浏览次数
                'j.total', //总额
                'j.picture', //总额
            ])
            ->join('cmf_member m', 'j.user_id = m.id', 'LEFT')
            ->find();

        $followNum = Db::table('cmf_fans')->where('follow_id', $work['id'])->count();
        $fansNum = Db::table('cmf_fans')->where('user_id', $work['id'])->count();
        $status = Db::table('cmf_fans')->where('follow_id', $work['id'])->find()['status'];
        $bond = Db::table('cmf_personal_check')->where('uid', $work['id'])->field(["sum(personal_deposit)+sum(business_deposit) as x"])->find()['x'];

        $work['status'] = $status;
        $work['followNum'] = $followNum;
        $work['fansNum'] = $fansNum;
        $work['bond'] = $bond;

        $join_count = 0;
        if ($work['join_id']) {
            $work['join_cout'] = count(json_decode($work['join_id']));
        }
        $infos = Db::table('cmf_job_people')->where(['worker_id' => $this->user['user_id'], 'job_id' => $id])->field('working_status')->find();

        if (empty($infos)) {
            $work['working_status'] = 0;
            $this->ajaxResult(1, '请求成功', $work);
        } else {
            $info = array_merge($work, $infos);
            $this->ajaxResult(1, '请求成功', $info);
        }
    }

    /*
    * 临时工 -  兼职报名
    */
    public function do_job()
    {
        $data = $this->request->param();
        $errorInfo = (new ParttimeValidate())->goCheck('do_job');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        if (!empty($data['job_id'])) {
            $re = Db::table('cmf_job')->where('id', $data['job_id'])->find();
            if ($re['user_id'] == $this->user['user_id']) {
                $this->ajaxResult('-1', '不能报名自己发布的兼职');
            } else if ($re) {
                $people = Db::table('cmf_job_people')->insert(['boss_id' => $re['user_id'], 'working_status' => 1, 'worker_id' => $this->user['user_id'], 'create_time' => time(), 'job_id' => $re['id']]);
                if ($people > 0) {
                    $this->ajaxResult('1', '已报名!报名后需等待雇主确定收取,有可能不会被收取');
                } else {
                    $this->ajaxResult('-1', '数据错误');
                }
            }
        } else {
            $this->ajaxResult(-1, '数据错误,请重试!');
        }
    }

    /*
* 临时工 - 进行中 - 人员工作状态 - 不结算类型
*/

    public function getReport()
    {

        $data = [];
        $refund_type = [1 => '色情内容', 2 => '诈骗内容', 3 => '垃圾发布', 4 => '其他违法信息'];

        foreach ($refund_type as $k => $item) {

            $data[] = ['type_id' => $k, 'type' => $item];
        }

        $this->ajaxResult(1, '成功', ['refund_type' => $data]);

    }


    /*
    * 临时工 -  兼职举报
    *
    *
    */
    public function lsg_report()
    {

        $data = $this->request->param();
        $errorInfo = (new ParttimeValidate())->goCheck('lsg_report');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        $report['boss_id'] = $data['boss_id'];
        $report['people_id'] = $this->user['user_id'];
        $report['job_id'] = $data['job_id'];
        $report['report_type'] = $data['type_id'];
        $report['create_time'] = time();

        $info = Db::table('cmf_report')->insert($report);


        if ($info > 0) {
            $this->ajaxResult(1, '完成');
        } else {
            $this->ajaxResult(-1, '数据错误');
        }


    }

    /**
     * 兼职添加留言
     */
    public function add_job_comment()
    {
        $data = $this->request->param();
        $errorInfo = (new ParttimeValidate())->goCheck('add_job_comment');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);
        $r = Db::table('cmf_member')->where('id', $this->user['user_id'])->field('user_nickname,avatar')->find();
        if (empty($data['content'])) {
            $this->ajaxResult(1, '评论内容不能为空');
        }
        $insert_data = array(
            'job_id' => $data['job_id'],//订单id
            'from_user_id' => $this->user['user_id'],//发表评论的用户id
            'create_time' => time(),//发布评论时间
            'avatar' => $r['avatar'],//用户头像
            'user_nickname' => $r['user_nickname'],//用户昵称
            'content' => $data['content'],//评论内容
        );


        $do_insert = Db::table('cmf_consult')->insert($insert_data);

        if ($do_insert > 0) {
            $this->ajaxResult(1, '评论成功');
        } else {
            $this->ajaxResult(-1, '操作有误');
        }
    }

    /**
     * 兼职回复留言
     */
    public function add_job_reply()
    {

        $data = $this->request->param();
        $errorInfo = (new ParttimeValidate())->goCheck('do_job');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);


        if ((Db::table('cmf_job')->where('id', $data['job_id'])->value('user_id')) == $this->user['user_id']) {

            if (empty($data['content'])) {
                $this->ajaxResult(1, '回复内容不能为空');
            }
            $insert_data = array(
                'reply_id' => $data['comment_id'],//回复目标id
                'from_user_id' => $this->user['user_id'],//发表评论的用户id
                'create_time' => time(),//发布评论时间
                'content' => $data['content'],//回复内容
                'job_id' => $data['job_id'],
                'consult_id' => $data['consult_id'],
            );

            $do_insert = Db::table('cmf_job_reply')->insert($insert_data);

            if ($do_insert > 0) {
                $this->ajaxResult(1, '回复成功');
            } else {
                $this->ajaxResult(-1, '操作有误');
            }

        } else {
            $this->ajaxResult(-1, '不是发布兼职的用户,不能进行此操作');
        }

    }

    /**
     * 留言详情
     */
    public function job_comment()
    {
        $data = $this->request->param();
        if (empty($data['job_id']) || !$data['job_id']) {
            $this->ajaxResult(1, '兼职id不能为空');
        }
        $comment = Db::table('cmf_consult')
            ->where('job_id', $data['job_id'])
            ->field([
                'job_id',
                'content',
                'user_nickname',
                'avatar',
                'from_user_id',
                'create_time',
                'id',
            ])
            ->select()
            ->toArray();


        foreach ($comment as $k => $v) {
            $comment[$k]['reply'] = Db::table('cmf_job_reply')->where(['reply_id' => $v['from_user_id'], 'consult_id' => $v['id']])->field('content')->select()->toArray();

        }
        if (!empty($comment)) {
            $this->ajaxResult(1, '成功', $comment);
        } else {
            $this->ajaxResult(-1, '没有数据');
        }
    }

}