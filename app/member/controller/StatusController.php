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

//                       .::::.
//                     .::::::::.
//                    :::::::::::
//                 ..:::::::::::'
//              '::::::::::::'
//                .::::::::::
//           '::::::::::::::..
//                ..::::::::::::.
//              ``::::::::::::::::
//               ::::``:::::::::'        .:::.
//              ::::'   ':::::'       .::::::::.
//            .::::'      ::::     .:::::::'::::.
//           .:::'       :::::  .:::::::::' ':::::.
//          .::'        :::::.:::::::::'      ':::::.
//         .::'         ::::::::::::::'         ``::::.
//     ...:::           ::::::::::::'              ``::.
//    ```` ':.          ':::::::::'                  ::::..
//                       '.:::::'                    ':'````..
namespace app\member\controller;

use app\member\model\BaseModel;
use app\third\AliPay;
use app\third\WeChatPay;
use cmf\controller\HomeBaseController;
use think\Exception;
use think\facade\Validate;
use think\Db;
use app\member\model\MemberModel;
use app\validate\MemberValidate;

class StatusController extends HomeBaseController
{


    //优先执行判断登录状态

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub

        $this->token = $this->request->patch('token');

        //判断token的传值
        if (isset($_SERVER["HTTP_TOKEN"])) {
            $this->user = $this->check_user_login($_SERVER["HTTP_TOKEN"]);//验证用户
        } else {
            $this->user = $this->check_user_login($this->token); //验证用户
        }
        if (!$this->user) {
            $this->ajaxResult(-1, '请登录后再试!');
        }


    }

    /*
   * 临时工 - 数量()
   */
    public function job_num()
    {
        $re['job_num'] = Db::table('cmf_job')->where(['user_id' => $this->user['user_id'], 'status' => ['in', [1,2]]])->count();

        $job_read = Db::table('cmf_job')->where(['user_id' => $this->user['user_id'], 'read_status' => 0])->count();
        $job_read > 0 ? $re['job_read'] = 1 : $re['job_read'] = 0;

        $re['join_num'] = Db::table('cmf_job_people')->where(['worker_id' => $this->user['user_id'], 'working_status' => ['in', [3, 4]]])->count();
        $join_read = Db::table('cmf_job_people')->where(['worker_id' => $this->user['user_id'], 'working_status' => 3, 'read_status' => 0])->count();
        $join_read > 0 ? $re['join_read'] = 1 : $re['join_read'] = 0;

        $this->ajaxResult(1, '请求成功', $re);
    }


    /*
    * 临时工 - 用户发布的兼职()
    */
    public function send_job()
    {
        $this->check_status($this->user['user_id']);

        $data = $this->request->param();
        $errorinfo = (new MemberValidate())->goCheck('send_job', $data);
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);

        $type = $this->request->param('type');

        switch ($type) {
            case 1:
                $status = ['in', [1, 2]];
                break;
            case 2:
                $status = 3;
                break;
            default :
                $this->ajaxResult(1, '类型错误');
                break;
        }

        $where = ['user_id' => $this->user['user_id'], 'status' => $status];

        $info = Db::table('cmf_job')
            ->field([
                'title', //标题
                'money',//金额
                'type', //类型
                'create_time', //发布时间
                'id',//发布兼职id
                'status'
            ])
            ->where($where)
            ->order('id', 'desc')
            ->paginate($this->pageSize, false, ['page' => $this->currentPage])->toArray();

        $info['count'] = Db::name('job')->where(['user_id' => $this->user['user_id']])->field([
            'IFNULL( SUM( CASE WHEN status = 1 OR status = 2 OR status = 3 THEN 1 ELSE 0 END ), 0) as count',
            'IFNULL( SUM( CASE WHEN status = 1 OR status = 2 THEN 1 ELSE 0 END ), 0) as processing_count',
            'IFNULL( SUM( CASE WHEN status = 3 THEN 1 ELSE 0 END ), 0) as over_count',
        ])->find();

        $this->ajaxResult(1, '请求成功', $info);
    }


    /*
    * 临时工 - 用户发布的兼职(自动跳转)
    */
    public function check_status($user_id)
    {
        $where = ['starttime' => ['<=', time()], 'endtime' => ['>=', time()], 'user_id' => $user_id, 'status' => 1];
        $info = Db::table('cmf_job')->where($where)->select()->toArray();
        $ids = array_column($info, 'id');

        //启动事务
        Db::startTrans();

        $upJob = Db::table('cmf_job')->whereIn('id', $ids)->update(['status' => 2]);
        $upPeople = Db::table('cmf_job_people')->whereIn('job_id', $ids)->update(['working_status' => 4]);

        //根据返回值判断是否均执行成功
        if ($upJob && $upPeople) {
            //执行成功，提交事务
            Db::commit();
        } else {
            //任一执行失败，执行回滚操作，相当于均不执行
            Db::rollback();
        }
    }

    // 兼职人员列表
    public function partTimeWorkers()
    {
        $errorinfo = (new MemberValidate())->goCheck('partTimeWorkers');
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);
        $params = $this->request->param();
        $refund_type = [0 => '', 1 => '未完成', 2 => '人员未到', 3 => '未上传凭证', 4 => '不符合要求', 5 => '其他原因'];
        switch ($params['type']) { // 1报名 2确认 3进行中
            case 1:
                $working_status = 1;
                break;
            case 2:
                $working_status = 3;
                break;
            case 3:
                $working_status = 4;
                break;
            case 4:
                $working_status = ['<>', 0];
                break;
            default:
                $this->ajaxResult(-1, '类型错误');
                break;
        }

        $job = Db::table('cmf_job')->where('id', $params['job_id'])->field(['id', 'money'])->find();
        if (!$job) $this->ajaxResult(-1, '兼职不存在');

        $info = Db::name('job_people j')
            ->where(['j.job_id' => $params['job_id'], 'working_status' => $working_status])
            ->join('cmf_member m', 'j.worker_id = m.id')
            ->field([
                'm.user_nickname',
                'm.avatar',
                'm.id',
                'j.working_status',
                'j.settlement_status',
                'j.refundorder',
                $job['money'] . ' as money'
            ])
            ->order('j.id', 'asc')
            ->paginate($this->pageSize, false, ['page' => $this->currentPage])->toArray();

        if(!empty($info['data'])){
            foreach ($info['data'] as &$item){
                $item['refundorder'] = isset($refund_type[$item['refundorder']])?$refund_type[$item['refundorder']]:'';
            }
        }

        $this->ajaxResult(1, '请求成功', $info);
    }

    /*
    * 临时工 - 发布中 - 兼职详情 - (全部报名人员)
    */
    public function recruit()
    {
        $errorinfo = (new MemberValidate())->goCheck('recruit');
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);

        $id = $this->request->param('job_id');  //兼职id
        $info = Db::table('cmf_job_people')
            ->where(['j.job_id' => $id, 'working_status' => 1])
            ->alias('j')
            ->join('cmf_member m', 'j.worker_id = m.id', 'LEFT')
            ->field([
                'm.user_nickname',
                'm.avatar',
                'm.id',
            ])
            ->select();
        $this->ajaxResult(1, '请求成功', $info);
    }

    /*
    * 临时工 - 发布中 - 兼职详情 - (兼职内容)
    */

    public function details()
    {
        $errorinfo = (new MemberValidate())->goCheck('details');
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);
        $id = $this->request->param('job_id'); //兼职id

        $re = Db::table('cmf_job')->where('id', $id)->setInc('views');

        $info = Db::table('cmf_job')
            ->alias('j')
            ->join('cmf_member m', 'j.user_id = m.id', 'LEFT')
            ->where('j.id', $id)
            ->field([
                'm.user_nickname',//用户名
                'j.title',//兼职标题
                'j.starttime',// 兼职开始时间
                'j.endtime',//兼职结束时间
                'j.money',//金额
                'j.type',//类型
                'j.hope', //工作要求
                'j.content', //工作内容
                'j.address',//工作地址
                'j.phone',//联系电话
                'j.views', //浏览次数
                'j.total', //总额
                'j.views',//浏览次数
            ])
            ->find();

        $this->ajaxResult(1, '请求成功', $info);
    }

    /*
    * 临时工 - 发布中 - 兼职详情 - (人员确定)
    */

    public function confirm()
    {
        $data = $this->request->param();
        $errorinfo = (new MemberValidate())->goCheck('confirm', $data);
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);

        $ids = $this->request->param('ids/a', ''); //用户id数组[1,2,3]
        $re = Db::table('cmf_job')->where('id', $data['job_id'])->find();
        if ($re) $join = count(explode(',', $re['join_id'])); //总数

        if (($re['person'] - $join) >= count($ids)) {
            foreach ($ids as $v) {
                $people = Db::table('cmf_job_people')
                    ->where(['job_id' => $re['id'], 'worker_id' => $v])
                    ->update(['working_status' => 3, 'confirm_time' => time()]);
            }

            if ($re['join_id']) {
                $re['join_id'] = implode(',', array_merge(explode(',', $re['join_id']), $ids));
            } else {
                $re['join_id'] = implode(',', $ids);
            }

            Db::name('job')->where(['id' => $data['job_id']])->update(['join_id' => $re['join_id']]);
            $this->ajaxResult(1, '确认成功');
        } else {
            $this->ajaxResult(-1, '已经超出需要人数,请重新确认!!');
        }
    }

    /*
    * 临时工 - 发布中 - 兼职详情 - (已确定人员)
    */
    public function confirm_people()
    {
        $data = $this->request->param();
        $errorinfo = (new MemberValidate())->goCheck('confirm_people', $data);
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);

        $id = $this->request->param('job_id');  //兼职id

        $info = Db::table('cmf_job_people')
            ->where(['j.job_id' => $id, 'working_status' => 3])
            ->alias('j')
            ->join('cmf_member m', 'j.worker_id = m.id', 'LEFT')
            ->field([
                'm.user_nickname',
                'm.avatar'
            ])
            ->select();

        $this->ajaxResult(1, '请求成功', $info);
    }

    public function check_del_job()
    {
        $user = $this->user;
        $params = $this->request->param();
        $errorInfo = (new MemberValidate())->goCheck('del_job', $params);
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        $job = Db::table('cmf_job')->where(['id'=>$params['job_id'], 'user_id'=>$user['user_id']])->field('status')->find();
        if (!$job) $this->ajaxResult(-1, '兼职不存在');

        $people = Db::table('cmf_job_people')->where(['job_id' => $params['job_id'], 'working_status' => ['in', [3, 4]]])->select();

        if ($people) {
            foreach ($people as $key => $value) {
                if (in_array($value['settlement_status'], [0])) {
                    $this->ajaxResult(-1, '仍有可结算人员，不可关闭该兼职');
                    break;
                }
            }
        }

        $this->ajaxResult(1, 'success', ['bool' => true]);
    }

    /*
    * 临时工 -  兼职删除
    * //还要退款没做
    *
    */
    public function del_job()
    {
        $user = $this->user;
        $data = $this->request->param();
        $errorinfo = (new MemberValidate())->goCheck('del_job', $data);
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);
        //获取此条兼职id
        $id = $this->request->param('job_id');
        $job = Db::table('cmf_job')->where(['id'=>$id, 'user_id'=>$user['user_id']])->find();
        if (!$job) $this->ajaxResult(-1, '兼职不存在');
        if ($job['status'] == 3) $this->ajaxResult(-1, '该兼职已关闭');

        try {
            Db::startTrans();

            $p_id = [];
            $people = Db::table('cmf_job_people')->where(['job_id' => $id, 'working_status' => ['in', [3, 4]]])->select()->toArray();
            if ($people) {
                foreach ($people as $key => $value) {
                    if (in_array($value['settlement_status'], [0])) {
                        throw new Exception('仍有可结算人员，不可关闭该兼职', 130602);
                        break;
                    }
                    $p_id[] = $value['id'];
                }
                if (!empty($p_id)) {
                    $res = Db::table('cmf_job_people')->where(['id' => ['in', $p_id]])->update(['working_status' => 7]);
                    if (false === $res) throw new Exception('cmf_job del fail', 100612);
                }
                // 删除未确认的
                $res1 = Db::table('cmf_job_people')->where(['job_id' => $id, 'working_status' => 0])->delete();
                if (false === $res1) throw new Exception('cmf_job del fail', 100612);
            }

            $result = Db::table('cmf_job')->where('id', $id)->update(['status' => 3]);
            if (false === $result) throw new Exception('cmf_job del fail', 100612);

            //判断是否有未使用金额，直接退款
//            $value = $job['money'];
//            if ($job['type'] == 3) $value = $job['money'] * $job['pieces'];
            $totalMoney = Db::name('member_account_log')->where(['other_id' => $job['id'], 'type' => 2])->value('value');
            $useMoney = Db::name('member_account_log')->where(['other_id' => $job['id'], 'type' => 3])->sum('value');
            $user_money = bcsub($totalMoney, $useMoney, 2);
            if ($user_money > 0) {
//                switch ($job['pay_type']) {
//                    case 'wx':
//                        $data = [
//                            'order_sn' => $job['order_sn'],
//                            'refund_sn' => '3' . date('YmdHis') . rand(100, 999),
//                            'total_price' => $totalMoney,
//                            'refund_price' => $user_money,
//                            'refund_desc' => '结算剩余退款',
//                        ];
//                        (new WeChatPay())->refund($data, 1);
//                        break;
//                    case 'ali':
//                        $data = [
//                            'out_trade_no' => $job['order_sn'],
//                            'out_request_no' => '3' . date('YmdHis') . rand(100, 999),
//                            'refund_amount' => $user_money,
//                        ];
//                        (new AliPay())->refund($data, 1);
//                        break;
//                    case 'balance':
//                        //更新余额
//                        $res = Db::name('member')->where(['id' => $user['user_id']])->update(['balance' => $user_money]);
//                        if (!$res) throw new Exception('更新余额失败');
//                        //记录余额变动
//                        $add = [
//                            'user_id' => $user['user_id'],
//                            'value' => $user_money,
//                            'type' => 0,
//                            'remark' => '兼职结算剩余退回余额',
//                            'create_time' => time(),
//                        ];
//                        $res = Db::name('member_account_log')->insert($add);
//                        if (!$res) throw new Exception('fail');
//                        break;
//                }

                $res = Db::name('member')->where(['id' => $user['user_id']])->setInc('balance', $user_money);
                if (false === $res) throw new Exception('更新余额失败');
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
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->ajaxResult(-1, $e);
        }

        $this->ajaxResult(1, '关闭成功');
    }

    /*
   * 临时工 -  进行中(全部人员)
   */
    public function ongoing_people()
    {
        $data = $this->request->param();
        $errorinfo = (new MemberValidate())->goCheck('ongoing_people', $data);
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);
        $id = $this->request->param('job_id');  //兼职id

        $info = Db::table('cmf_job_people')
            ->where(['j.job_id' => $id, 'working_status' => 4])
            ->alias('j')
            ->join('cmf_member m', 'j.worker_id = m.id', 'LEFT')
            ->field([
                'm.user_nickname',
                'm.avatar'
            ])
            ->select();

        $this->ajaxResult(1, '请求成功', $info);
    }


    /*
    * 临时工 - 进行中(兼职内容)
    */

    public function ongoing_details()
    {

        $data = $this->request->param();
        $errorinfo = (new MemberValidate())->goCheck('ongoing_details', $data);
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);

        $id = $this->request->param('job_id'); //兼职id

        $re = Db::table('cmf_job')->where('id', $id)->setInc('views');

        $info = Db::table('cmf_job')
            ->alias('j')
            ->join('cmf_member m', 'j.user_id = m.id', 'LEFT')
            ->where('j.id', $id)
            ->field([
                'm.user_nickname',//用户名
                'j.title',//兼职标题
                'j.starttime',// 兼职开始时间
                'j.endtime',//兼职结束时间
                'j.money',//金额
                'j.type',//类型
                'j.hope', //工作要求
                'j.content', //工作内容
                'j.address',//工作地址
                'j.phone',//联系电话
                'j.total', //总额
                'j.views',//浏览次数
            ])
            ->find();

        $this->ajaxResult(1, '请求成功', $info);
    }


    /*
    * 临时工 - 进行中(已确定人员)
    */
    public function working_status()
    {
        $data = $this->request->param();
        $errorinfo = (new MemberValidate())->goCheck('working_status', $data);
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);

        $id = $this->request->param('job_id');  //兼职id

        $info = Db::table('cmf_job_people')
            ->alias('j')
            ->where(['j.job_id' => $id, 'j.working_status' => 4])
            ->join('cmf_member m', 'j.worker_id = m.id', 'LEFT')
            ->field([
                'j.settlement_status',
                'm.user_nickname',
                'm.avatar',
                'm.id',
                'j.job_id'
            ])
            ->select()
            ->toArray();

        foreach ($info as $k => $v) {
            $info[$k]['money'] = Db::table('cmf_job')->where('id', $id)->field('money')->find()['money'];
        }
        $this->ajaxResult(1, '请求成功', $info);
    }


    /*
    * 临时工 - 进行中(已确定人员人数)
    */

    public function AssignNumber()
    {

        $id = $this->request->param('job_id');  //兼职id

        $type = $this->request->param('type');//1发布中 2进行中

        if ($type == 1) {
            $re['TotalNumber'] = Db::table('cmf_job')->where('id', $id)->find()['person'];

            $re['AssignNumber'] = Db::table('cmf_job_people')->where(['job_id' => $id, 'working_status' => 3])->count();
        } else {
            $re['TotalNumber'] = Db::table('cmf_job')->where('id', $id)->find()['person'];

            $re['AssignNumber'] = Db::table('cmf_job_people')->where(['job_id' => $id, 'working_status' => 4])->count();
        }


        $this->ajaxResult(1, '请求成功', $re);
    }


    /*
    * 临时工 - 进行中 - 人员工作状态 - 不结算类型
    */

    public function getRefundConfig()
    {

        $data = [];
        $refund_type = [1 => '未完成', 2 => '人员未到', 3 => '未上传凭证', 4 => '不符合要求', 5 => '其他原因'];

        foreach ($refund_type as $k => $item) {

            $data[] = ['type_id' => $k, 'type' => $item];
        }

        $this->ajaxResult(1, '成功', ['refund_type' => $data]);

    }

    /*
    * 临时工 - 进行中 - 人员工作状态 - 不结算
    */

    public function RefundOrder()
    {

        $data = $this->request->param();
        $errorinfo = (new MemberValidate())->goCheck('RefundOrder', $data);
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);

        $data['type'] = $this->request->param('type_id'); //不结算原因
        $data['worker_id'] = $this->request->param('worker_id'); //工人id
        $data['job_id'] = $this->request->param('job_id'); //兼职id

        $where = ['worker_id' => $data['worker_id'], 'job_id' => $data['job_id']];

        $up = array(
            'settlement_status' => 2,
            'refundorder' => $data['type'],
        );

        $info = Db::table('cmf_job_people')->where($where)->setField($up);

        if ($info > 0) {
            $this->ajaxResult(1, '完成');
        } else {
            $this->ajaxResult(-1, '数据错误');
        }


    }

    //查看凭证
    public function lookVoucher()
    {
        $data = $this->request->param();
        $errorinfo = (new MemberValidate())->goCheck('lookVoucher', $data);
        if ($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);

        $data['job_id'] = $this->request->param('job_id');
        $data['people_id'] = $this->request->param('people_id');

        $where = ['job_id' => $data['job_id'], 'people_id' => $data['people_id']];

        $info = Db::table('cmf_job_voucher')->where($where)->field(['show_picture', 'remarks'])->select();

        if ($info) {
            $this->ajaxResult(1, '请求成功', $info);
        }
        $this->ajaxResult(-1, '没有数据');
    }


    /*
  * 临时工 - 三个状态的数量
  */

    public function StatusNumber()
    {

        $re['ReleaseNumber'] = Db::table('cmf_job')->where(['user_id' => $this->user['user_id'], 'status' => 1])->count();
        $re['ConductNumber'] = Db::table('cmf_job')->where(['user_id' => $this->user['user_id'], 'status' => 2])->count();
        $re['EndNumber'] = Db::table('cmf_job')->where(['user_id' => $this->user['user_id'], 'status' => 3])->count();


        $this->ajaxResult(1, '请求成功', $re);
    }


}