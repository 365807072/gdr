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
namespace app\user\controller; //命名空间

use cmf\controller\AdminBaseController; //引入父类控制器

use think\Db; //引用db构造器
use think\db\Query;


class FeedbackController extends AdminBaseController{ // 继承父类控制器

        public function index(){

            $content = hook_one('user_feedback_index_view');

            if (!empty($content)){
                return $content;
            }

            $list = Db::name('feedback')
                ->alias('f')
                ->where(function (Query $query){
                   $data = $this->request->param();

                    if (!empty($data['keyword'])) {
                        $keyword = $data['keyword'];
                        $query->where('u.user_nickname', 'like', "%$keyword%");
                    }
                })
                ->join('user u','f.user_id = u.id')
                ->order("f.create_time DESC")
                ->paginate(10);
            // 获取分页显示
            $page = $list->render();
            $this->assign('list', $list);
            $this->assign('page', $page);

            // 渲染模板输出
            return $this->fetch();
        }


}