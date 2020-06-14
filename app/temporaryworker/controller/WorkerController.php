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

namespace app\TemporaryWorker\controller;

use app\TemporaryWorker\model\WorkerModel;
use cmf\controller\AdminBaseController;
use think\Db;
use think\db\Query;

class WorkerController extends AdminBaseController{

    //列表页
    public function index(){

        $data = $this->request->param();

        $list = Db::name('parttime')
            ->where(function (Query $query) use ($data) {
                if (!empty($data['keyword'])) {
                    $keyword = $data['keyword'];
                    $query->where('title|boss_name', 'like', "%$keyword%");
                }
            })
            ->field([
                'id',
                'title',
                'worker_name',
                'create_time',
                'boss_name',
                'money',
                'starttime',
                'address',
            ])
            ->paginate(10);
        $this->assign('page', $list->render());
        $this->assign('list', $list);
        return $this->fetch();
    }

}