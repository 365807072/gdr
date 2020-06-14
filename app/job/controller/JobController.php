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

namespace app\job\controller;

use app\portal\model\PortalTagModel;
use cmf\controller\AdminBaseController;
use think\Db;
use think\db\Query;

class JobController extends AdminBaseController{


    //兼职列表
    public function index(){

        $data = $this->request->param();

        $list = Db::name('job')
            ->alias('j')
            ->join('cmf_member m' ,'j.user_id = m.id','LEFT')

            ->where(function (Query $query) use ($data) {
                if (!empty($data['keyword'])) {
                    $keyword = $data['keyword'];
                    $query->where('j.title|m.user_nickname', 'like', "%$keyword%");
                }
            })

            ->field([
                'j.id',
                'j.title',
                'j.address',
                'j.money',
                'j.type',
                'j.phone',
                'm.user_nickname',
                'j.status',
                'j.create_time'
            ])
//            ->order("u.create_time DESC")
//            ->order("time DESC")
            ->paginate(10);



        $this->assign('page', $list->render());
        $this->assign('list', $list);


        return $this->fetch();
    }

    /**
     * 兼职详情
     */
    public function detail(){

        $id = $this->request->param('id');

        $info = Db::table('cmf_job')
            ->alias('j')
            ->join('cmf_member m' ,'j.user_id = m.id','LEFT')
            ->where(['j.id'=>$id])
            ->field([
                'm.id',
                'm.user_nickname',
                'm.avatar',
                'j.title',
                'j.create_time',
                'j.person',
                'j.money',
                'j.type',
                'j.hope',
                'j.content',
                'j.picture',
                'j.address',
                'j.phone',
                'j.status',
                'j.total'
            ])
            ->find();

        $this->assign('info',$info);


        return $this->fetch();
    }

    /**
     * 兼职删除
     */
    public function delete()
    {
        $intId = $this->request->param("id", 0, 'intval');

        if (empty($intId)) {
            $this->error(lang("没有此条记录"));
        }
        Db::name('job')->where('id', $intId)->delete();


        $this->success(lang("删除成功"));
    }


    public function detailPost(){


        $data = $this->request->param('status');
        $id = $this->request->param('id', 0, 'intval');

        $insert_data = Db::table('cmf_job')->where('user_id' ,$id)->update(['status'=>$data]);

        if ($insert_data > 0) {
            $this->success('成功', url('job/index'));
        } else {
            $this->error('失败');
        }
    }


    //兼职列表
    public function report(){

        $data = $this->request->param();

        $list = Db::name('report')
            ->alias('r')
            ->join('cmf_job j' ,'r.job_id = j.id','LEFT')
            ->join('cmf_member m','r.boss_id = m.id','LEFT')
            ->where(function (Query $query) use ($data) {
                if (!empty($data['keyword'])) {
                    $keyword = $data['keyword'];
                    $query->where('j.title|m.user_nickname', 'like', "%$keyword%");
                }
            })
            ->field([
                'r.id',//举报id
                'j.id as job_id', //兼职id
                'j.title', //兼职
                'r.report_type',//兼职举报类型
                'm.user_nickname', //雇主昵称
                'm.report_more',//此用户被举报次数
                'm.user_status',//此用户状态
                'r.create_time', //举报时间
            ])
//            ->order("u.create_time DESC")
            ->order("r.id ASC")
            ->paginate(10);
        $this->assign('page', $list->render());
        $this->assign('list', $list);
        return $this->fetch();
    }


    public function report1(){
        $data = $this->request->param();
        $list = Db::name('report')
            ->alias('r')
            ->join('cmf_member m' ,'r.people_id = m.id','LEFT')
            ->join('cmf_job j' ,'j.user_id = m.id','LEFT')
            ->where(function (Query $query) use ($data) {
                if (!empty($data['keyword'])) {
                    $keyword = $data['keyword'];
                    $query->where('j.title', 'like', "%$keyword%");
                }
            })
            ->field([
                'r.id',
                'r.people_id',
                'j.title',
                'm.user_nickname as report_user',
                'r.report_type',
                'm.report_more',
                'r.create_time',
                'r.boss_id',
                'm.user_status',
            ])
//            ->order("u.create_time DESC")
            ->order("create_time DESC")
            ->group('r.id')
            ->paginate(10);
//            ->toArray();
        $this->assign('page', $list->render());
        $list = $list->toArray()['data'];
        $a = array_column($list,'boss_id');
        $b = array_column($list,'people_id');
        $c = array_values(array_merge($a,$b));
        $user_list = db('member')->whereIn('id',$c)->select()->toArray();
        $user_list = array_column($user_list,null,'id');
        foreach ($list as $k => $v){

            $list[$k]['report_user'] = $user_list[$v['people_id']]['user_nickname'];
            $list[$k]['report_uid'] = $user_list[$v['boss_id']]['user_nickname'];

        }
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 客服删除兼职按钮
     */
    public function reportDelete()
    {
        $intId = $this->request->param("id", 0, 'intval');//举报信息id
        //需要通过举报信息删除对应兼职内容
        if (empty($intId)) {
            $this->error(lang("没有此条记录"));
        }
        Db::name('job')->where('id', $intId)->delete();
        $this->success(lang("删除成功"));
    }

    /*
     * 拉黑被举报人
     */
    public function ban()
    {
        $intId = $this->request->param("id", 0, 'intval');//举报信息id
        //获取被举报人id
        $boss_id = Db::name('report')->where('id',$intId)->field('boss_id')->find();
        if (empty($boss_id)) {
            $this->error(lang("没有此条记录"));
        }
        $user = Db::name('member')->where('id',$boss_id['boss_id'])->update(['user_status'=>0]);
        if (!$user) {
            $this->error(lang("修改失败"));
        }
        $this->success(lang("拉黑成功"));
    }

    /*
     * 开启已拉黑被举报人
     */
    public function cancelban()
    {
        $intId = $this->request->param("id", 0, 'intval');//举报信息id
        //获取被举报人id
        $boss_id = Db::name('report')->where('id',$intId)->field('boss_id')->find();
        if (empty($boss_id)) {
            $this->error(lang("没有此条记录"));
        }
        $user = Db::name('member')->where('id',$boss_id['boss_id'])->update(['user_status'=>1]);
        if (!$user) {
            $this->error(lang("修改失败"));
        }
        $this->success(lang("开启成功"));
    }
}