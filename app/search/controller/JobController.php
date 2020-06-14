<?php

//命名空间
namespace app\search\controller;

use app\validate\SearchValidate;
use think\Db;
use think\db\Query;
use think\facade\Validate;
use cmf\controller\HomeBaseController;
use app\user\model\UserModel;
use app\user\model\JobModel;

class JobController extends HomeBaseController
{
    public function _initialize()
    {
        parent::_initialize();
        parent::_isLogin();
        if(!in_array(request()->action(), ['search','lsg_personal','lsg_profession','personal_data','evaluate'])){
            parent::_initUser();
        };
    }

    /*
   * 临时工 -  开启或者关闭接单
   */

    public function SwitchButton()
    {
        $button = Db::table('cmf_member')->where('id', $this->user['user_id'])->field('works_status')->find();

        if ($button['works_status'] == 1) $SwitchButton = Db::table('cmf_member')->where('id', $this->user['user_id'])->update(['works_status' => 0]);
        else $SwitchButton = Db::table('cmf_member')->where('id', $this->user['user_id'])->update(['works_status' => 1]);


        if ($SwitchButton !== false)  $this->ajaxResult(1, '修改成功', ['status'=>$button['works_status']==1?0:1]);
        else $this->ajaxResult(-1, '修改失败');
    }

    /*
     * 检查是否开启接单
     */
    public function SwitchButtonCheck()
    {
        $user = $this->user;
        $this->ajaxResult('1', 'success', ['status'=>$user['works_status']]);
    }

    /*
    * 临时工 -  个人资料(1)
    */
    public function PersonalData()
    {

        $info = Db::table('cmf_member')
            ->where(['id' => $this->user['user_id']])
            ->field([
                    'id',//用户id
                    'user_nickname', //昵称
                    'avatar', //头像
                    'label_name', //标签名
                    'disabled_time', //不接单时间
                    'content',
                    'show_picture'
                ]
            )->find();

        if ($info && $info['show_picture']) $info['show_picture'] = json_decode($info['show_picture']);

        $info['service_rank'] = Db::table('cmf_job_comment')
            ->where('reply_id', $this->user['user_id'])->field([
                'CONVERT(IFNULL(AVG(service_rank),5),SIGNED) as service_rank'
            ])->find();

        $bond = Db::table('cmf_personal_check')->where('uid', $this->user['user_id'])->field([
            "sum(personal_deposit)+sum(business_deposit) as x",
            "IFNULL( SUM( CASE WHEN personal_status = 1 AND personal_pay = 1 THEN 1 ELSE 0 END ), 0) as personal_status",
            "IFNULL( SUM( CASE WHEN business_status = 1 AND business_pay = 1 THEN 1 ELSE 0 END ), 0) as business_status",
            ])->find();

        $info['bond'] = $bond['x'];
        $info['personal_status'] = $bond['personal_status'];
        $info['business_status'] = $bond['business_status'];

//        $info['status'] = Db::table('cmf_fans')->where('follow_id',$info['id'])->field('status')->find()['status'];

        if (!empty($info['label_name'])) {
            $info['label_name'] = json_decode($info['label_name']);
        } else {
            $info['label_name'] = [];
        }
        if (!empty($info['disabled_time'])) {
            $info['disabled_time'] = json_decode($info['disabled_time']);
        }

        // 被预约时间
        $info['job_time'] = Db::name('parttime')->where(['worker_id'=>$this->user['user_id']])->column('starttime');

        $this->ajaxResult(1, '成功', $info);
    }


    /*
     * 临时工 -  搜索
     * 筛选功能尚未完成!!!!
     * 搜索词 keyword
     */
    public function search()
    {
        $data = $this->request->param();
        $errorInfo = (new SearchValidate())->goCheck('search');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        $keyword = isset($data['keyword']) ? htmlspecialchars($data['keyword']) : '';
        $sort = empty($data['sort']) ? 0 : $data['sort'];// 0默认 1距离 2评分高 3评分低 4价格高 5价格低

        $field = '0 AS disance';
        if (isset($data['lat']) && !empty($data['lat']) && isset($data['lng']) && !empty($data['lng'])) {
            $field = "round(IFNULL((
                        6371392.89 * acos (
                         cos ( radians({$data['lat']}) )
                         * cos( radians(a.latitude ) )
                         * cos( radians( a.longitude ) - radians({$data['lng']}) )
                         + sin ( radians({$data['lat']}) )
                         * sin( radians( a.latitude ) )
                       )
                    ),999999999999)/1000, 0) AS disance";
        }

        //排序 默认0
        switch ($sort) {
            case 1:
                $order = "disance"; //最近
                break;
            case 2:
                $order = "service_rank desc"; //评分高
                break;
            case 3:
                $order = "service_rank"; //评分低
                break;
            case 4:
                $order = "p.profession_price desc"; //价格高
                break;
            case 5:
                $order = "p.profession_price";  //价格低
                break;
            default:
                $order = "";
        }
        $info = Db::table('cmf_user_profession')
            ->alias('p')
            ->where(function (Query $query) use ($data, $keyword) {
                $province = empty($data['province']) ? '' : $data['province'];
                $city = empty($data['city']) ? '' : $data['city'];
                $district = empty($data['district']) ? '' : $data['district'];
                $sex = empty($data['sex']) ? '' : $data['sex']; //0:不限 1:男 2:女
                $ageGt = empty($data['agegt']) ? '' : $data['agegt'];
                $ageLte = empty($data['agelte']) ? '' : $data['agelte'];
                $is_person_check = empty($data['check']) ? '' : $data['check'];
                $type = empty($data['type']) ? 0 : $data['type'];

                if($province == '全国'){
//                    $query->where(['a.country'=>'中国']);
                }else{
                    if (!empty($province)) { //省
                        $query->where(['a.province' => $province]);
                    }
                    if($city == '全省'){
                        $city_pid = Db::name('region')->where(['level'=>1,'pid'=>0,'name'=>$province])->value('id');
                        $city_name = Db::name('region')->where(['level'=>2,'pid'=>$city_pid])->column('name');
                        $query->where(['a.city' => ['in', $city_name]]);
                    }else{
                        if (!empty($city)) { //市
                            $query->where(['a.city' => $city]);
                        }
                        if($district == '全市'){
                            $district_pid = Db::name('region')->where(['level'=>2,'name'=>$city])->value('id');
                            $district_name = Db::name('region')->where(['level'=>3,'pid'=>$district_pid])->column('name');
                            $query->where(['a.district' => ['in', $district_name]]);
                        }else{
                            if (!empty($district)) { //区
                                $query->where(['a.district' => $district]);
                            }
                        }
                    }
                }

                if (!empty($keyword)) { //关键词
                    $query->where('p.profession_name', 'like', "%$keyword%");
                }
                if (!empty($sex)) { //关键词
                    $query->where(['m.sex' => $sex]);
                }
                if (!empty($ageGt)) { //大于这个年龄
                    $query->where('m.age', '>=', $ageGt);
                }
                if (!empty($ageLte)) { //小于这个年龄
                    $query->where('m.age', '<=', $ageLte);
                }
                if (!empty($is_person_check)) {
                    $query->where(['m.is_person_check' => $is_person_check]);
                }
                switch ($type) {
                    case 0:
                        $query->where([
                            'k.personal_status' => 1,
                            'k.personal_pay' => 1,
                        ]);
                        break;
                    case 1:
                        $query->where([
                            'k.business_status' => 1,
                            'k.business_pay' => 1,
                        ]);
                        break;
                    default:
                        $this->ajaxResult(-1, '类型错误');
                        break;
                }
            })
            ->join('cmf_member m', 'p.user_id = m.id', 'LEFT')
            ->join('cmf_address a', "a.user_id = m.id and a.status = 1", 'LEFT')
            ->join('cmf_job_comment c', "p.user_id = c.reply_id ", 'LEFT')
            ->join('cmf_personal_check k', "p.user_id = k.uid ", 'LEFT')
            ->field([
                'p.profession_name', //工种名称
                'p.profession_price', //工种价格
                'a.country', //工种价格
                'a.province', //工种价格
                'a.city', //工种价格
                'a.district', //工种价格
                'm.user_nickname', //用户昵称
                'm.avatar', //用户头像
                'm.sex',//用户性别
                'm.id',//用户id
                'k.personal_status',//认证状态
                'CONVERT(IFNULL(AVG(c.service_rank),5),SIGNED) as service_rank',//用户id
            ])
            ->field($field)
            ->group('p.user_id')
            ->where('m.works_status', 1)
            ->order($order)
            ->paginate($this->pageSize, false, ['page' => $this->currentPage])
            ->toArray();

        foreach ($info['data'] as $k => $v) {
            if ($v['id'] == $this->user['user_id']) {
                unset($info[$k]);
            }
        }
        $this->ajaxResult(1, '成功', $info);
    }

    /*
     *  临时工 - 个人资料
     *
     */
    public function lsg_personal()
    {
        $id = $this->request->param('id');
        $errorInfo = (new SearchValidate())->goCheck('lsg_personal');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        $info = Db::table('cmf_member')
            ->alias('m')
            ->join('cmf_user_profession p', 'p.user_id = m.id', 'LEFT')
            ->join('cmf_personal_check k', "p.user_id = k.uid ", 'LEFT')
            ->where(['m.id' => $id])
            ->field([
                    'm.id',//用户id
                    'm.user_nickname', //昵称
                    'm.avatar', //头像
                    'm.label_name', //标签名
                    'p.id as profession_id', //工种id
                    'p.profession_price', //工种价格
                    'p.profession_name', //工种价格
                    'm.disabled_time', //不接单时间
                    'm.content', //个人说明
                    'm.show_picture', //展示图片
                    'k.personal_status',//认证状态
                ]
            )->find();
        $status = Db::table('cmf_fans')->where('follow_id', $info['id'])->field('status')->find();

        $bond = Db::table('cmf_personal_check')->where('uid', $this->user['user_id'])->field([
            "sum(personal_deposit)+sum(business_deposit) as x",
            "IFNULL( SUM( CASE WHEN personal_status = 1 AND personal_pay = 1 THEN 1 ELSE 0 END ), 0) as personal_status",
            "IFNULL( SUM( CASE WHEN business_status = 1 AND business_pay = 1 THEN 1 ELSE 0 END ), 0) as business_status",
        ])->find();

        $comment = Db::table('cmf_job_comment')->where('reply_id', $info['id'])->field(['avatar', 'user_nickname', 'content'])->order('add_time')->find();

        $CommentNum = Db::table('cmf_job_comment')->where(['reply_id' => $info['id'], 'type' => 2])->count();

//        $info['service_rank'] = Db::table('cmf_job_comment')
//            ->where('reply_id', $id)
//            ->avg('service_rank');
        $info['service_rank'] = Db::table('cmf_job_comment')
            ->where('reply_id', $id)->field([
                'CONVERT(IFNULL(AVG(service_rank),5),SIGNED) as service_rank'
            ])->find();

        $info['status'] = $status['status'];

        $info['bond'] = $bond['x'];
        $info['personal_status'] = $bond['personal_status'];
        $info['business_status'] = $bond['business_status'];

        $info['comment'] = $comment;

        $info['CommentNum'] = $CommentNum;

        //        $info['status'] = Db::table('cmf_fans')->where('follow_id',$info['id'])->field('status')->find()['status'];

        if (!empty($info['label_name'])) {
            $info['label_name'] = json_decode($info['label_name']);
        } else {
            $info['label_name'] = [];
        }
        if (!empty($info['disabled_time'])) {
            $info['disabled_time'] = json_decode($info['disabled_time']);
        }
        if (!empty($info['show_picture'])) {
            $info['show_picture'] = json_decode($info['show_picture']);
        } else {
            $info['show_picture'] = [];
        }
        $info['profession_price'] = Db::table('cmf_user_profession')
            ->where('profession_price> 0 ')
            ->min('profession_price');

        // 被预约时间
        $info['job_time'] = Db::name('parttime')->where(['worker_id'=>$id])->column('starttime');

        $this->ajaxResult(1, '成功', $info);
    }


    /*
*  临时工 - 工种选择
*
*/

    public function lsg_profession()
    {

        //获取用户id
        $id = $this->request->param('id');
        $errorInfo = (new SearchValidate())->goCheck('id_check');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);
        //根据用户id去查询对应的工种
        $data = Db::table('cmf_user_profession')
            ->where(['user_id' => $id])
            ->select();

        !empty($data) ? $this->ajaxResult(1, '查询成功', $data) : $this->ajaxResult(-1, '请重试!');

    }


    /*
    *  临时工 - 预约
    *
    */
    public function job_book()
    {
        $data = $this->request->param();
        $errorInfo = (new SearchValidate())->goCheck('job_book');//验证id ,profession_id
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);
        $work_id = $this->request->param('id'); //临时工id

        $re = Db::table('cmf_user_profession')->where('id', $data['profession_id'])->field('profession_name,profession_price,id as profession_id')->find();


        $work = Db::table('cmf_member')->where('id', $work_id)->field('disabled_time,id,modify_q,work_status,modify_pay,work_status,shipping_q')->find();


        /*
                $address=Db::table('cmf_address')
                    ->alias('a')
                    ->join('cmf_member m','a.user_id = m.id','LEFT')
                    ->where('user_id',$this->user['user_id'])
                    ->field('a.user_id,a.my_address,a.mobile,a.nickname,m.avatar')->find();*/

        $info = array_merge($re, $work);

//        $info['boss_name'] = Db::table('cmf_member')->where('id',$this->user['user_id'])->field('user_nickname')->find()['user_nickname'];

        $this->ajaxResult(1, '成功', $info);
    }

    /*
     * 工作预约日期
     */
    public function job_book_date()
    {
        $params = $this->request->param();
        $errorInfo = (new SearchValidate())->goCheck('job_book_date');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);//验证id,profession_id,date

        $work = Db::table('cmf_member')->where('id', $params['id'])->field('disabled_time,id,modify_q,work_status,modify_pay,work_status,shipping_q')->find();

        // 限制日接单
        $date = strtotime($params['date']);
        $dateTime = getCurrentNextTime($date);
        $count = Db::table('cmf_parttime')->where([
            'worker_id'=>$work['id'],
            'create_time'=>['between', [$dateTime['startDay'], $dateTime['endDay']]]
        ])->field([
            'from_unixtime(`create_time`, "%Y-%m-%d") as create_date',
            'count(id) as count',
            '0 as beyond'
        ])->group('create_date')->select()->toArray();
        if(!empty($count)){
            foreach ($count as $key => $item){
                if($item['count'] >= $work['shipping_q']) $count[$key]['beyond'] = 1;
            }
        }

        $this->ajaxResult(1, '成功', $count);
    }

    /*
    *  临时工 - 判断是否开启工作
    *
    */
    public function CheckWorks()
    {

        $data = $this->request->param();
        $workings = $data['workings'];

        $id = $data['id'];
        if ($workings == 1) {
            $r = Db::name('member')->where("concat('|',work_status,'|') REGEXP '({$workings})'")->where(['id' => $id])->value('id');
            $r && $this->ajaxResult(1, '成功');
            $this->ajaxResult(-1, '对方未开启线下工作');
        }

    }

    /*
    *  临时工 - 预约提交
    */
    public function book_submit()
    {
        $data = $this->request->param();
        $user = $this->user;

        //年龄
        if (!empty($data['nickname'])) {
            $nickname = $data['nickname'];
        } else {
            $nickname = $user['user_nickname'];
        }
        //电话号码
        if (!empty($data['mobile'])) {
            $mobile = $data['mobile'];
        } else {
            $mobile = $user['mobile'];
        }

        //地址
        if (!empty($data['my_address'])) {
            $my_address = $data['my_address'];
        } else {
            $my_address = '';
        }


        $errorInfo = (new SearchValidate())->goCheck('book_submit');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

//        $boss =Db::table('cmf_address')->where('user_id',$this->user['user_id'])->field('nickname,my_address,mobile')->find();

        $re = Db::table('cmf_user_profession')
            ->alias('p')
            ->join('cmf_member m ', 'p.user_id = m.id', 'LEFT')
            ->where('p.id', $data['profession_id'])
            ->field('p.profession_name,p.profession_price,p.user_id,p.id as profession_id,m.user_nickname,m.shipping_q')
            ->find();

        // 限制日接单
        $dateTime = getCurrentNextTime();
        $count = Db::table('cmf_parttime')->where(['worker_id'=>$re['user_id'],'create_time'=>['between', [$dateTime['todayStart'], $dateTime['todayEnd']]]])->count();
        if($count >= $re['shipping_q']) $this->ajaxResult(-1, '已达到日接单上限');

        $insert_data = array(
            'boss_id' => $this->user['user_id'], // 雇主id
            'boss_name' => $nickname,// 雇主名字
            'phone' => $mobile,//雇主电话电话号码
            'address' => $my_address, //雇主地址
            'starttime' => $data['job_time'],//工作时间
            'modify_time' => $data['modify_time'],//可修改次数
            'workings' => $data['workings'],//工作方式
            'modify_pay' => $data['modify_pay'],//是否付费修改
            'title' => $re['profession_name'],//工种名字
            'money' => $re['profession_price'],//工种价格
            'worker_id' => $re['user_id'],//临时工id
            'worker_name' => $re['user_nickname'],//临时工名字
            'working_status' => 1,//兼职状态
            'order_sn' => '2' . date('YmdHis') . rand(100, 999),//订单号
            'create_time' => time() //创建时间

        );

        if (!empty($data['hope'])) {
            $insert_data['hope'] = $data['hope'];
        }
//        $updata_user_personalId = Db::table('cmf_parttime')->insertGetId($insert_data);

//        $insert_job = array(
//            'job_id' => $updata_user_personalId, // 雇主id
//            'boss_id' => $this->user['user_id'],
//            'worker_id' => $re['user_id'],
//            'working_status'=>1,
//            'create_time' => time() //创建时间
//        );


        $insert = Db::table('cmf_parttime')->insert($insert_data);

        $insert > 0 ? $this->ajaxResult(1, '提交成功') : $this->ajaxResult(-1, '提交失败请重新提交');

    }


    /*
   *  临时工 - 工种选择
   *
   */

    public function job_select()
    {

        //获取用户id
        $id = $this->request->param('id');
//        $errorInfo = (new SearchValidate())->goCheck('id_check');
//        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);
        //根据用户id去查询对应的工种
        $data = Db::table('cmf_user_profession')
            ->where(['user_id' => $id])
            ->select();
        $this->ajaxResult(1, '成功', $data);
    }

    /*
    *  临时工 - 基础资料
    *
    */

    public function personal_data()
    {

        //获取用户id
        $id = $this->request->param('id');
        $errorInfo = (new SearchValidate())->goCheck('id_check');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);
        //根据用户id去查对应的个人资料
        $data = Db::table('cmf_member')
            ->alias('m')
            ->where(['m.id' => $id])
            ->field([
                'm.user_nickname', //名称
                'm.sex', //性别
                'm.degree', //学历
                'm.age', //年龄
                'm.job', //主要职业
                'm.enterprise',//在职企业
                'm.telephone',//联系电话
                'm.user_email',//邮箱
                's.business_status',//认证状态
//                "(if(s.business_status=0,'验证','未验证')) as ty ",//方法二
                //手机
                //工商认证
                'm.address',//商户地址
            ])
            ->join('cmf_address a', 'm.id = a.user_id', 'LEFT')
            ->join('cmf_personal_check s', 'm.id = s.uid', 'LEFT')
            ->find();
        if ($data['business_status'] == 1) {
            $data['type'] = '验证';
        } else {
            $data['type'] = '未验证';
        }
        $this->ajaxResult(1, '成功', $data);
    }


    public function evaluate()
    {

        $data = $this->request->param();

        $re = Db::table('cmf_job_comment')
            ->where(['reply_id' => $data['id'], 'type' => 2])
            ->field('avatar,user_nickname,add_time,content,profession,job_id,user_id,img')
            ->select()
            ->toArray();


        $this->ajaxResult(1, '成功', $re);
    }

    public function MyEvaluate()
    {

        $data = $this->request->param();

        $re = Db::table('cmf_job_comment')
            ->where(['job_id' => $data['job_id'], 'user_id' => $this->user['user_id'], 'type' => 1])
            ->field('avatar,user_nickname,content,add_time')
            ->find();

        $this->ajaxResult(1, '成功', $re);
    }

}