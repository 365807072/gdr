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

use app\third\AliSms;
use think\Db;
use think\facade\Validate;
use cmf\controller\HomeBaseController;
use app\member\model\MemberModel;
use app\validate\MemberValidate;
use think\Request;

class LoginController extends HomeBaseController
{
    public function _initialize()
    {
        parent::_initialize();
        parent::_isLogin();
        if(in_array(request()->action(), ['passwordreset'])){
            parent::_initUser();
        };
    }

    /**
     * 登录
     */
    public function index()
    {
        $redirect = $this->request->param("redirect");
        if (empty($redirect)) {
            $redirect = $this->request->server('HTTP_REFERER');
        } else {
            if (strpos($redirect, '/') === 0 || strpos($redirect, 'http') === 0) {
            } else {
                $redirect = base64_decode($redirect);
            }
        }
        if(!empty($redirect)){
            session('login_http_referer', $redirect);
        }
        if (cmf_is_user_login()) { //已经登录时直接跳到首页
            return redirect($this->request->root() . '/');
        } else {
            return $this->fetch(":login");
        }
    }

    /**
     * 登录验证提交
     */
    public function doLogin(Request $request)
    {

        if ($this->request->isPost()) {
            $validate = new \think\Validate([
//                'captcha'  => 'require',
                'username' => 'require',
                'password' => 'require|min:6|max:32',
            ]);
            $validate->message([
                'username.require' => '用户名不能为空',
                'password.require' => '密码不能为空',
                'password.max'     => '密码不能超过32个字符',
                'password.min'     => '密码不能小于6个字符',
//                'captcha.require'  => '验证码不能为空',
            ]);

            $data = $request->param();
//            $data = $this->request->post();

            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

//            if (!cmf_captcha_check($data['captcha'])) {
//                $this->error(lang('CAPTCHA_NOT_RIGHT'));
//            }
            $userModel         = new MemberModel();
            $user['user_pass'] = $data['password'];
            if (Validate::is($data['username'], 'email')) {
                $user['user_email'] = $data['username'];
                $log                = $userModel->doEmail($user);
            } else if (cmf_check_mobile($data['username'])) {
                $user['mobile'] = $data['username'];
                $log            = $userModel->doMobile($user);
            } else {
                $user['user_login'] = $data['username'];
                $log                = $userModel->doName($user);
            }
            $session_login_http_referer = session('login_http_referer');
            define('USER_ID',$session_login_http_referer['user_id']);

            $redirect = empty($session_login_http_referer) ? $this->request->root() : $session_login_http_referer;
            switch ($log) {
                case 0:
                    cmf_user_action('login');
                    $this->success(lang('LOGIN_SUCCESS'), $redirect);
                    break;
                case 1:
                    $this->error(lang('PASSWORD_NOT_RIGHT'));
                    break;
                case 2:
                    $this->error('账户不存在');
                    break;
                case 3:
                    $this->error('账号被禁止访问系统');
                    break;
                default :
                    $this->error('未受理的请求');
            }
        } else {
            $this->error("请求错误");
        }
    }

    /**
     * 找回密码
     */
    public function findPassword()
    {
        return $this->fetch('/find_password');
    }

    /**
     * 用户密码重置
     */
    public function passwordReset()
    {

        if ($this->request->isPost()) {
            $validate = new \think\Validate([
                'captcha'           => 'require',
                'verification_code' => 'require',
                'password'          => 'require|min:6|max:32',
            ]);
            $validate->message([
                'verification_code.require' => '验证码不能为空',
                'password.require'          => '密码不能为空',
                'password.max'              => '密码不能超过32个字符',
                'password.min'              => '密码不能小于6个字符',
                'captcha.require'           => '验证码不能为空',
            ]);

            $data = $this->request->post();
            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

            $captchaId = empty($data['_captcha_id']) ? '' : $data['_captcha_id'];
            if (!cmf_captcha_check($data['captcha'], $captchaId)) {
                $this->error('验证码错误');
            }

            $errMsg = cmf_check_verification_code($data['username'], $data['verification_code']);
            if (!empty($errMsg)) {
                $this->error($errMsg);
            }

            $userModel = new MemberModel();
            if ($validate::is($data['username'], 'email')) {

                $log = $userModel->emailPasswordReset($data['username'], $data['password']);

            } else if (cmf_check_mobile($data['username'])) {
                $user['mobile'] = $data['username'];
                $log            = $userModel->mobilePasswordReset($data['username'], $data['password']);
            } else {
                $log = 2;
            }
            switch ($log) {
                case 0:
                    $this->success('密码重置成功', cmf_url('user/Profile/center'));
                    break;
                case 1:
                    $this->error("您的账户尚未注册");
                    break;
                case 2:
                    $this->error("您输入的账号格式错误");
                    break;
                default :
                    $this->error('未受理的请求');
            }

        } else {
            $this->error("请求错误");
        }
    }


    /*
      临时工-忘记密码验证码
      mobile 用户名
  */

    public function lsgpasswordSend(){

        //接受全部数据
        $data = $this->request->param();

        $errorinfo = (new MemberValidate())->goCheck('lsg_passwordSend', $data);
        if($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);

        //判断手机号是否有输入
        if (!empty($data['mobile'])){
            //判断手机号码格式
            if (cmf_check_mobile($data['mobile'])){
                //查询用户
                $find_user = db('member')->where('mobile',$data['mobile'])->count();


                //判断用户是否存在
                if ($find_user > 0){
                    $send_time = time(); //发送时间;
                    $out_time =  $send_time + 300; // 过期时间
                    $code = rand(1000,9999); //随机四位数验证码
                    cmf_verification_code_log($data['mobile'],$code,$out_time);//把验证码存起来
//                    $message = '【临时工】您的验证码是' . $code . ',此短信5分钟内有效'; //短信内容
//                    $data = array(
//                        'Id' => 2675,
//                        'Name' => '聚品测试',
//                        'Psw' => 123456,
//                        'Message' => $message,
//                        'Phone' => $data['mobile'],
//                        'Timestamp' => $send_time,
//                        'Ext' => ''
//                    );
//                    $curl_string = '';
//                    foreach ($data as $key => $value) {
//                        $curl_string .= $key . '=' . $value . '&';
//                    }
//                    $ch = curl_init();
//                    curl_setopt($ch, CURLOPT_URL, 'http://124.172.234.157:8180/service.asmx/SendMessage' . 'Str?' . substr($curl_string, 0, -1));
//                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//                    curl_setopt($ch, CURLOPT_HEADER, 0);
//                    //curl_setopt($ch, CURLOPT_POST, 1);
//                    //curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
//
//                    //执行命令
//                    $data = curl_exec($ch);
//                    //关闭URL请求
//                    curl_close($ch);
//
//                    $re = explode(':', explode(',', $data)[0])[1];
//                    $message = explode(':', explode(',', $data)[1])[1];
//
//                    //返回的验证码
//                    $this->ajaxReturn(['code' => $code, 'status' => 1,]);

                    $aliSms = new AliSms();
                    $res = $aliSms->sendSms($data['mobile'], $code);

                    if(true === $res) $this->ajaxReturn(['code' => $code, 'status' => 1]);
                    else $this->ajaxReturn(['msg' => '发送失败', 'status' => -1]);
                }else{
                    //用户不存在
                    $this->ajaxResult(-1,'此用户不存在,请注册!');
                }
                //用户格式不正确
            }else{
                $this->ajaxResult(-1,'手机格式不正确!');
            }
            //没有输入手机号
        }else{
            $this->ajaxResult(-1,'没有输入手机号!');
        }
    }

    /*
    临时工-忘记密码找回
    mobile 用户名
    password 新密码
    code 验证码
    */

    public function lsg_passwordReset(){

        //获取全部录入数据
        $data = $this->request->param();

        $errorinfo = (new MemberValidate())->goCheck('lsg_passwordReset', $data);
        if($errorinfo !== true) $this->ajaxResult(-1, $errorinfo['msg'], $errorinfo['data']);

        // 验证手机与验证码
        $errMsg =cmf_check_verification_code($data['mobile'],$data['code']);
        //验证验证码存在错误返回提示信息
        if (!empty($errMsg)){
            $this->ajaxResult(-1,$errMsg);
        }else{
            //正常情况下
            $userModel = new MemberModel();
            $log = $userModel->mobilePasswordReset($data['mobile'],$data['password']);
            //各种返回状态码
            switch ($log){
                case 0:
                    $this->ajaxResult(1,'密码重置成功');
                    break;
                case 1:
                    $this->ajaxResult(-1,'此账号尚未注册');
                    break;
                default :
                    $this->ajaxReturn(['msg'=>'未受理的请求','status'=>-1]);
            }
        }
    }

    /**
     * 退出登录
     */

    public function logout()
    {
        $data = $this->request->param();

        $where = ['user_id' => $this->user['user_id'] ];

        $logout = Db::table('cmf_user_token')->where($where)->delete();

        if ($logout >0 ){

            $this->ajaxResult(1,'退出成功');
        }
            $this->ajaxResult(1,'数据有误请重试');
    }
}