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

namespace app\member\controller;

use cmf\controller\AdminBaseController;
use think\Console;
use think\Db;
use think\db\Query;

class MemberController extends AdminBaseController{

    /**
     * 用户列表
     */
    public function index(){

        $data = $this->request->param();
        $list = Db::name('member')


            ->where(function (Query $query) use ($data) {
                if (!empty($data['keyword'])) {
                    $keyword = $data['keyword'];
                    $query->where('user_nickname', 'like', "%$keyword%");
                }
            })
            ->field([
                'id',//用户id
                'user_nickname', //用户名
                'sex',//性别
                'mobile', //联系电话
                'user_email', //邮箱
                'create_time', //创建时间
                'user_status',//用户状态
            ])
//            ->order("u.create_time DESC")
            ->order("id DESC")
            ->paginate(10);

            $this->assign('page', $list->render());
            $this->assign('list', $list);

        //输入模板
        return $this->fetch();
    }


    /**
     * 用户详情页
     */
    public function detail(){

        $id = $this->request->param('id');

        $info = Db::table('cmf_member')
                ->alias('m')
                ->join('cmf_address a' ,'m.id = a.user_id','LEFT')
                ->join('cmf_personal_check c' ,'m.id = c.uid','LEFT')
                ->where(['m.id'=>$id])
                ->field([
                    'm.id', //用户id
                    'm.user_nickname', //用户名称
                    'm.avatar',//用户头像
                    'm.job',//主要职业
                    'm.balance',//账户金额
                    'm.enterprise',//在职企业
                    'm.telephone',//联系电话
                    'm.user_status',//用户状态
                    'm.show_picture',//展示图片
                    'a.my_address',//我的地址
                    'm.address',//商户店址
                    'c.personal_status', //个人认证
                    'c.personal_deposit', //个人认证
                    'c.business_status',//工商认证
                    'c.business_deposit',//工商认证
                    'm.degree', //学历
                    'm.age',//年龄
                ])
                ->find();

        $this->assign('info',$info);

        return $this->fetch();
    }


    /**
     * 用户删除
     */
    public function delete(){

        $id = $this->request->param('id', 0, 'intval');

        if ($id == 1) {
            $this->error("最高管理员不能删除！");
        }

        if (Db::name('user')->delete($id) !== false) {
            Db::name("user")->where("id", $id)->delete();
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }

    /**
     * 用户认证页
     */
    public function check(){

        $data = $this->request->param();
        $personal_status= isset($data['personal_status']) ?$data['personal_status'] : '';//状态 0默认
        if($personal_status!=='') {
            $where['personal_status'] = $personal_status;
        }else{
            $where = '';
        }

        $list = Db::name('member')

            ->alias('m')
            ->join('cmf_personal_check c','m.id = c.uid','LEFT')
            ->where(function (Query $query) use ($data) {
                if (!empty($data['keyword'])) {
                    $keyword = $data['keyword'];
                    $query->where('m.user_nickname', 'like', "%$keyword%");
                }
            })
            ->where($where)
            ->field([
                'm.id',//用户id
                'm.user_nickname', //用户名
                'c.image',//用户自拍头像
                'c.faceid_image', // 身份证正面
                'c.backid_image',// 身份证反面
                'c.handid_image',// 手持身份证
                'c.personal_status',//个人审核状态
                'c.personal_deposit',//个人保证金
                'c.personal_upload_date',//个人认证上传时间
                'c.personal_licence', //营业执照
                'c.business_status', // 营业执照审核状态
                'c.business_deposit', //商户保证金
                'c.personal_upload_date', //提交时间
            ])
//            ->order("u.create_time DESC")
            ->order("c.personal_upload_date DESC")
            ->paginate(10);
        $this->assign('personal_status',isset($data['personal_status']) ? $data['personal_status'] : '');
        $this->assign('page', $list->render());
        $this->assign('list', $list);


        //输出页面
        return $this->fetch();
    }

    //个人审核
    public function personal_check(){

        $id = $this->request->param('id');

        $info = Db::name('member')
            ->alias('m')
            ->join('cmf_personal_check c','m.id = c.uid','LEFT')
            ->where(['m.id'=>$id])
            ->field([
                'm.id', // 用户id
                'm.user_nickname',//用户已名字
                'c.image', //自拍照
                'c.faceid_image',
                'c.backid_image',
                'c.handid_image',
                'c.personal_status', //个人审核状态
                'c.personal_deposit', //个人认证保证金
                'c.personal_upload_date', //个人认证上传时间

            ])
            ->find();
            ;


        $this->assign('info',$info);

        //输出页面
        return $this->fetch();
    }

    //工商认证
    public function business_check(){

        $id = $this->request->param('id');

        $info = Db::name('member')
            ->alias('m')
            ->join('cmf_personal_check c','m.id = c.uid','LEFT')
            ->where(['m.id'=>$id])
            ->field([
                'm.id', // 用户id
                'm.user_nickname',//用户已名字
                'c.business_licence', //工商执照
                'c.business_status', //个人审核状态
                'c.business_deposit', //个人认证保证金
                'c.business_upload_date', //个人认证上传时间

            ])
            ->find();
        ;
        $this->assign('info',$info);


        //输出页面
        return $this->fetch();
    }

    //个人審核狀態
    public function detailPost(){

        //获取所有数据
        $data = $this->request->param('personal_status');
        $id = $this->request->param('id', 0, 'intval');
//        $status = input('personal_status');
//        $this->success($data);
        $insert_data = Db::table('cmf_personal_check')->where('uid' ,$id)->update(['personal_status'=>$data]);



        if ($insert_data > 0) {
            $this->success('认证成功!', url('member/check'));
        } else {
            $this->error('更新失败');
        }
    }


    //工商认证
    public function businessPost(){
        //获取所有数据
        $data = $this->request->param('business_status');
        $id = $this->request->param('id', 0, 'intval');
//        $status = input('personal_status');
//        $this->success($data);
        $insert_data = Db::table('cmf_personal_check')->where('uid' ,$id)->update(['business_status'=>$data]);



        if ($insert_data > 0) {
            $this->success('认证成功!', url('member/check'));
        } else {
            $this->error('更新失败');
        }
    }

    //认证详情页
    public function check_detail(){

        //接受传过来的id

        $id = $this->request->param('id');

        $info = Db::name('member')
            ->alias('m')
            ->join('cmf_personal_check c','m.id = c.uid','LEFT')
            ->where(['m.id'=>$id])
            ->field([
                'm.id',
                'm.user_nickname',//用户昵称
                'm.avatar',//用户头像
                'c.personal_status', //个人审核状态
                'c.personal_deposit', //个人认证保证金
                'c.personal_upload_date', //个人认证上传时间
                'c.business_status', //工商审核状态
                'c.business_deposit', //工商认证保证金
                'c.business_upload_date', //工商认证上传时间

            ])
            ->find();
        ;
        $this->assign('info',$info);

        return $this->fetch();
    }





    public function ban()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            $result = Db::name("member")->where(["id" => $id, "user_type" => 2])->setField('user_status', 0);
            if ($result) {
                $this->success("会员拉黑成功！", "member/index");
            } else {
                $this->error('会员拉黑失败,会员不存在,或者是管理员！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    /**
     * 本站用户启用
     * @adminMenu(
     *     'name'   => '本站用户启用',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '本站用户启用',
     *     'param'  => ''
     * )
     */
    public function cancelBan()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            Db::name("member")->where(["id" => $id, "user_type" => 2])->setField('user_status', 1);
            $this->success("会员启用成功！", '');
        } else {
            $this->error('数据传入失败！');
        }
    }
}