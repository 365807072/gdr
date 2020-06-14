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

namespace app\myspace\controller;

use cmf\controller\HomeBaseController;

class ConfigController extends HomeBaseController
{
    public function _initialize()
    {
        parent::_initialize();
    }

    /*
     * 用户协议及隐私协议
     */
    public function agreement()
    {
        $post = $this->request->param('type');
        if ($post == 1) {
            $userAgreement  = cmf_get_option('user_agreement');
            $agreement['agreement'] = $userAgreement['user_agreement'];
            $this->ajaxResult(1,'成功',$agreement);
        }
        if ($post == 2) {
            $privacyAgreement  = cmf_get_option('privacy_agreement');
            $agreement['agreement'] = $privacyAgreement['privacy_agreement'];
            $this->ajaxResult(1,'成功',$agreement);
        }
        $this->ajaxResult(2,'失败请输入请求参数');
    }
}