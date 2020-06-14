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
namespace app\user\controller;

use app\third\AliSms;
use cmf\controller\HomeBaseController;
use think\facade\Validate;
use app\user\model\UserModel;
use think\Db;

class RegisterController extends HomeBaseController
{

    /**
     * 前台用户注册
     */
    public function index()
    {
        $redirect = $this->request->post("redirect");
        if (empty($redirect)) {
            $redirect = $this->request->server('HTTP_REFERER');
        } else {
            $redirect = base64_decode($redirect);
        }
        session('login_http_referer', $redirect);

        if (cmf_is_user_login()) {
            return redirect($this->request->root() . '/');
        } else {
            return $this->fetch(":register");
        }
    }

    /**
     * 前台用户注册提交
     */
    public function doRegister()
    {
        if ($this->request->isPost()) {
            $rules = [
                'captcha'  => 'require',
                'code'     => 'require',
                'password' => 'require|min:6|max:32',

            ];

            $isOpenRegistration = cmf_is_open_registration();

            if ($isOpenRegistration) {
                unset($rules['code']);
            }

            $validate = new \think\Validate($rules);
            $validate->message([
                // 'code.require'     => '验证码不能为空',
                'password.require' => '密码不能为空',
                'password.max'     => '密码不能超过32个字符',
                'password.min'     => '密码不能小于6个字符',
                'captcha.require'  => '验证码不能为空',
            ]);

            $data = $this->request->post();
            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

            $captchaId = empty($data['_captcha_id']) ? '' : $data['_captcha_id'];
            if (!cmf_captcha_check($data['captcha'], $captchaId)) {
                $this->error('验证码错误');
            }

            // if (!$isOpenRegistration) {
            //     $errMsg = cmf_check_verification_code($data['username'], $data['code']);
            //     if (!empty($errMsg)) {
            //         $this->error($errMsg);
            //     }
            // }

            $register          = new UserModel();
            $user['user_pass'] = $data['password'];
            if (Validate::is($data['username'], 'email')) {
                $user['user_email'] = $data['username'];
                $log                = $register->register($user, 3);
            } else if (cmf_check_mobile($data['username'])) {
                $user['mobile'] = $data['username'];
                $log            = $register->register($user, 2);
            } else {
                $log = 2;
            }
            $sessionLoginHttpReferer = session('login_http_referer');
            $redirect                = empty($sessionLoginHttpReferer) ? cmf_get_root() . '/' : $sessionLoginHttpReferer;
            switch ($log) {
                case 0:
                    $this->success('注册成功', $redirect);
                    break;
                case 1:
                    $this->error("您的账户已注册过");
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
        -临时工注册方法-
        username  用户手机
        password  用戶密碼
        code 手機驗證碼
    */
    public function lsg_doRegister(){

        //接受全部数据
        $data = $this->request->param();
//        用户输入验证码
        if ($data['code']){
            //错误提示信息
            $errMsg = cmf_check_verification_code($data['username'],$data['code']);
            //如果有这个变量
            if (!empty($errMsg)){
                $this->ajaxReturn(['msg'=>$errMsg,'status'=>-1]);
            }
        }else{
        //用户没有输入验证码
            $this->ajaxReturn(['msg'=>'请输入验证码','status'=>-1]);
        }
        $register = new UserModel();
        //用户密码
            $user['user_pass']  = $data['password'];
        //判断手机格式
        if (cmf_check_mobile($data['username'])){
            $user['mobile'] = $data['username'];
            $log = $register->register($user,2);

            if ($log['status'] == 0){
                $this->ajaxReturn(['msg'=>'注册成功!','token'=>$log['token'],'status'=>1]);
            }else if ($log['status'] == 1){
                $this->ajaxReturn(['msg'=>'此账号已经注册','status'=>-1]);
            }else if ($log['status'] == 2){
                $this->ajaxReturn(['msg'=>'您输入的账号格式错误','status'=>-1]);
            }else{
                $this->ajaxReturn(['msg'=>'未受理的请求','status'=>-1]);
            }
        //手机格式错误
        }else{
            $this->ajaxReturn(['msg'=>'请输入正确的手机格式','status'=>-1]);
        }
    }


    //臨時工驗證碼方法
    /**
     * 注册code(验证码)
     * mobile(用户手机号码)
     */
    public function SmMobiles(){
        $data = $this->request->param();
        if (cmf_check_mobile($data['mobile'])) { //检查手机格式
            $userModel = new UserModel();
            $find_user = db('user')->where('mobile', $data['mobile'])->count();
            if ($find_user > 0) {//发送短信
                $this->ajaxReturn(['msg' => '该用户已经存在', 'status' => -1]);
            } else {
                $send_time = time();//发送时间
                $expire_time = $send_time + 300;//先默认5分钟限期
                $code = rand(1000, 9999);
                cmf_verification_code_log($data['mobile'], $code, $expire_time);
//                $message = '【临时工】您的验证码是' . $code . ',此短信5分钟内有效';
//                $data = array(
//                    'Id' => 2675,
//                    'Name' => '聚慧货搬搬',
//                    'Psw' => 123456,
//                    'Message' => $message,
//                    'Phone' => $data['mobile'],
//                    'Timestamp' => $send_time,
//                    'Ext' => ''
//                );
//                $curl_string = '';
//                foreach ($data as $key => $value) {
//                    $curl_string .= $key . '=' . $value . '&';
//                }
//                $ch = curl_init();
//                curl_setopt($ch, CURLOPT_URL, 'http://124.172.234.157:8180/service.asmx/SendMessage' . 'Str?' . substr($curl_string, 0, -1));
//                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//                curl_setopt($ch, CURLOPT_HEADER, 0);
//                //curl_setopt($ch, CURLOPT_POST, 1);
//                //curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
//
//                //执行命令
//                $data = curl_exec($ch);
//                //关闭URL请求
//                curl_close($ch);
//
//                $re = explode(':', explode(',', $data)[0])[1];
//                $message = explode(':', explode(',', $data)[1])[1];

                $aliSms = new AliSms();
                $res = $aliSms->sendSms($data['mobile'], $code);

                if(true === $res) $this->ajaxReturn(['code' => $code, 'status' => 1]);
                else $this->ajaxReturn(['msg' => '发送失败', 'status' => -1]);
            }
        } else {
            $this->ajaxReturn(['msg' => '手机格式错误!', 'status' => -1]);
        }
    }
}