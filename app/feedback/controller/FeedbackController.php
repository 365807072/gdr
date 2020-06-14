<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2019 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// +----------------------------------------------------------------------
namespace app\feedback\controller;

use cmf\controller\AdminBaseController;
use app\portal\model\PortalPostModel;
use app\portal\service\PostService;
use app\portal\model\PortalCategoryModel;
use think\Db;
use app\admin\model\ThemeModel;
use think\db\Query;

class FeedbackController extends AdminBaseController
{
    /**
     * 反馈问题列表
     */

    public function index(){

        $data = $this->request->param();



        $problem_list = Db::table('cmf_feedback')
            ->alias('f')
            ->join('cmf_user u','f.user_id = u.id')
            ->where(function(Query $query) use($data){
                $keyword = empty($data['keyword']) ? '' : $data['keyword'];

                if(!empty($keyword)){
                    $query->where('a.title', 'like', "%$keyword%");
                }
            })
            ->field([
                'f.id',
                'f.comment',
                'f.picture',
                'f.create_time',
                'f.mobile',
                'u.user_nickname',
            ])
            ->paginate(10);
        $problem_list->appends($data);//添加URL参数
        $this->assign('keyword', isset($data['keyword']) ? $data['keyword'] : ''); //关键字
        $this->assign('problem_list', $problem_list); //常见问题内容
        $this->assign('page', $problem_list->render()); //分页

        return $this->fetch();
    }
}