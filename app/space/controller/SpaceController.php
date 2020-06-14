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

namespace app\space\controller;

use app\third\AliPay;
use app\third\WeChatPay;
use think\Db;
use think\Request;
use app\space\model\OrderModel;
use app\space\model\SpaceModel;
use app\validate\SpaceValidate;
use cmf\controller\HomeBaseController;

class SpaceController extends HomeBaseController
{
    public function _initialize()
    {
        parent::_initialize();
        parent::_initUser();
    }

    /**
     *  我的空间
     */
    public function index()
    {
        $spaceModel = new SpaceModel();
        $data = $spaceModel->get_user_space([
            'user_id' => $this->user['id']
        ], ['user_id,space,used_space']);
        $data['space'] = round($data['space'] / 1024 / 1024, 2); // b->mb
        $data['used_space'] = round($data['used_space'] / 1024 / 1024, 2);

        $this->ajaxResult(1, 'success', $data);
    }

    /**
     *  打开文件夹
     */
    public function folderList()
    {
        $param = $this->request->param();
        $errorInfo = (new SpaceValidate())->goCheck('folderList');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        $map = [
            's.user_id' => $this->user['id'],
            's.type' => $param['type']
        ];
        if (isset($param['keyword']) && !empty($param['keyword'])) $map['s.boss_name'] = ['like', decorateSearch_pre($param['keyword'])];

        $spaceModel = new SpaceModel();
        $data = $spaceModel->get_space_list($map, ['s.*,from_unixtime(s.create_time) as create_time,from_unixtime(s.update_time) as update_time'], $this->currentPage, $this->pageSize);

        $this->ajaxResult(1, 'success', $data);
    }

    /**
     *  清除提示状态
     */
    public function removeSpaceRemind()
    {
        $param = $this->request->param();
        $errorInfo = (new SpaceValidate())->goCheck('folderList');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        $spaceModel = new SpaceModel();
        $result = $spaceModel->update_space([
            'user_id' => $this->user['id'],
            'type' => $param['type']
        ], [
            'is_remind' => 0
        ]);
        if ($result === false) $this->ajaxResult(-1, 'error');

        $this->ajaxResult(1, 'success');
    }

    /**
     *  文件列表
     */
    public function fileList()
    {
        $param = $this->request->param();
        $errorInfo = (new SpaceValidate())->goCheck('fileList');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        $spaceModel = new SpaceModel();
        $data = $spaceModel->get_space_detail_list([
            's.user_id' => $this->user['id'],
            's.id' => $param['space_id']
        ], ['sd.*,from_unixtime(sd.create_time) as create_time,from_unixtime(sd.update_time) as update_time'], $this->currentPage, $this->pageSize);
        if (!empty($data['data'])) foreach ($data['data'] as &$item) {
            $item['used_space'] = round(($item['used_space'] / 1024 / 1024), 2); // k->m
        }
        $this->ajaxResult(1, 'success', $data);
    }

    /**
     *  加入历史文件夹
     */
    public function fileAddHistory()
    {
        $param = $this->request->param();
        $errorInfo = (new SpaceValidate())->goCheck('fileAddHistory');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        $spaceModel = new SpaceModel();
        $data = $spaceModel->get_space_detail([
            'id' => $param['file_id']
        ]);
        if (empty($data)) $this->ajaxResult(-1, '文件不存在');
        if ($data['is_addhistory'] == 1) {
            $history = $spaceModel->get_space_detail([
                'space_id' => $data['space_id'],
                'link' => $data['link'] . '.historyCopy'
            ]);
            if (!empty($history)) $this->ajaxResult(-1, '改文件已添加到历史文件夹，请勿重复添加');
        }
        $data['user_id'] = $this->user['id'];

        $space = $spaceModel->get_space([
            'id' => $data['space_id']
        ], ['id,user_id,job_id,boss_id,boss_name,type,used_space,is_remind']);
        if ($space['type'] != 1) $this->ajaxResult(-1, '文件无法加入历史或已添加');
        $data['space'] = $space;

        $data = $spaceModel->file_add_history($data);
        if ($data !== true) $this->ajaxResult(-1, $data);
        $this->ajaxResult(1, 'success');
    }

    /**
     *  删除文件夹
     */
    public function folderDel()
    {
        $param = $this->request->param();
        $errorInfo = (new SpaceValidate())->goCheck('folderDel');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);
        if (!is_array($param['folder_id'])) $param['folder_id'] = [$param['folder_id']];

        $spaceModel = new SpaceModel();
        $space = $spaceModel->get_spaces([
            'id' => ['in', $param['folder_id']]
        ]);
        if (empty($space)) $this->ajaxResult(-1, '文件夹不存在');
        if (count($space) != count($param['folder_id'])) $this->ajaxResult(-1, '文件夹不存在');

        foreach ($space as $k => $val) {
            if ($val['type'] == 0) $this->ajaxResult(-1, '进行中的文件不能编辑');
        }

        $res = $spaceModel->delete_space_folder($space);
        if ($res !== true) $this->ajaxResult(-1, $res);
        $this->ajaxResult(1, 'success');
    }

    /**
     * 删除文件
     */
    public function fileDel()
    {
        $param = $this->request->param();
        $errorInfo = (new SpaceValidate())->goCheck('fileAddHistory');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);
        if (!is_array($param['file_id'])) $param['file_id'] = [$param['file_id']];

        $spaceModel = new SpaceModel();
        $data = $spaceModel->get_space_details([
            'id' => ['in', $param['file_id']]
        ]);
        if (empty($data)) $this->ajaxResult(-1, '文件不存在');
        if (count($data) != count($param['file_id'])) $this->ajaxResult(-1, '文件不存在');

        $space_id = array_unique(array_column($data, 'space_id'));
        if (count($space_id) > 1) $this->ajaxResult(-1, '只能同时删除同一个文件夹的文件');

        $space = $spaceModel->get_space([
            'id' => $space_id[0]
        ], ['id,user_id,job_id,boss_id,boss_name,type,used_space,is_remind']);
        if ($space['type'] == 0) $this->ajaxResult(-1, '进行中的文件不能编辑');
        $data['space'] = $space;

        $res = $spaceModel->delete_space_details($data);
        if ($res !== true) $this->ajaxResult(-1, $res);
        $this->ajaxResult(1, 'success');
    }

    /**
     *  历史文件列表
     */
    public function fileHistoryList()
    {
        $spaceModel = new SpaceModel();
        $data = $spaceModel->history_file_list($this->user['id'], [], $this->currentPage, $this->pageSize);
        $this->ajaxResult(1, 'success', $data);
    }

    /**
     *  空间产品列表
     */
    public function spaceProduct()
    {
        $spaceModel = new OrderModel();
        $data = $spaceModel->get_products([
            'is_delete' => 0
        ], [], $this->currentPage, $this->pageSize);
        $this->ajaxResult(1, 'success', $data);
    }

    /**
     *  发起支付
     */
    public function initPay()
    {
        $param = $this->request->param();
        $errorInfo = (new SpaceValidate())->goCheck('initPay');
        if ($errorInfo !== true) $this->ajaxResult(-1, $errorInfo['msg'], $errorInfo['data']);

        $spaceModel = new OrderModel();
        $product = $spaceModel->get_product(['id' => $param['product_id'], 'is_delete' => 0]);
        if (empty($product)) $this->ajaxResult(-1, '产品不存在，请重新选择');
        if ($param['pay_type'] == 0) {
            if (empty($this->user['user_paypass'])) $this->user['user_paypass'] = $this->user['user_pass'];
            if (!isset($param['pass']) || empty($param['pass'])) $this->ajaxResult(-1, '请输入支付密码');
            if (cmf_password($param['pass']) != $this->user['user_paypass']) $this->ajaxResult(-1, '支付密码不对，请重新输入');
            if ($this->user['balance'] < $product['money']) $this->ajaxResult(-1, '余额不足');
        }

        $data = [
            'product' => $product,
            'user' => $this->user,
            'pay_type' => $param['pay_type'],
            'base_url' => $this->request->domain()
        ];
        $result = $spaceModel->add_order($data);
        if ($result['code'] != 200) $this->ajaxResult(-1, $result['result']);

        $this->ajaxResult(1, 'success', $result['result']);
    }

    public function refund()
    {
        $param = $this->request->param();
        $data = [
            'order_sn' => $param['order_sn'],
            'refund_sn' => '3' . date('YmdHis') . rand(100, 999),
//            'total_price'   => $param['total_money'],
//            'refund_price'  => $param['user_money'],
            'user_money' => $param['total_money'],
            'refund_desc' => '结算剩余退款',
        ];
        $res = (new AliPay())->refund($data, $this->request->domain() . 'notify/Notify/orderRefundAliNotify');
//        $res = (new WeChatPay())->refund($data, rtrim($this->request->domain(), '/').'/space/Notify/spaceAliPayNotifyxx');
        $this->ajaxResult(1, 'success', $res);
    }

    public function transfer()
    {
        $param = $this->request->param();
//        $data = [
////            'order_sn'      => $param['order_sn'],
//            'order_sn'     => '3'.date('YmdHis').rand(100, 999),
//            'trans_amount'   => $param['total_money'],
//            'order_title'  => '测试提现',
//            'openid'   => 'oqFDJ1Muzm5oO0uCSmKxmxYQCKsk',
//        ];

        $data = [
            'order_sn' => date('YmdHis') . rand(10000, 99999),
            'user' => 18219116599,
            'order_title' => '企业转账',
            'trans_amount' => 0.1,
            'user_id' => 32
        ];
        $res = (new AliPay())->transfer($data);
//        $res = (new WeChatPay())->transfer($data);
        $this->ajaxResult(1, 'success', $res);
    }
}