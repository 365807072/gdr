<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2019 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +---------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace cmf\controller;


use app\user\model\OssModel;
use think\Db;
use app\admin\model\ThemeModel;
use think\Response;
use think\View;

class HomeBaseController extends BaseController
{
    protected $user = [];

    protected $token = [];

    protected static $dbConfig = [];

    protected function initialize()
    {
        // 监听home_init
        hook('home_init');
        parent::initialize();
        $siteInfo = cmf_get_site_info();
        View::share('site_info', $siteInfo);
        self::$dbConfig = self::getDbConfig();
        $this->checkUserSpace();
    }

    protected function _initUser()
    {
        //判断token的传值
//        if (isset($_SERVER["HTTP_TOKEN"])){
//            $this->user = $this->check_user_login($_SERVER["HTTP_TOKEN"]);//验证用户
//        }else{
            $this->token = trim(request()->header('token'));
            if(empty($this->token)) $this->ajaxResult(-1,'请登录后再试!');

            $this->user = $this->check_user_login($this->token); //验证用户
//        }
        if (!$this->user){
            $this->ajaxResult(-1,'请登录后再试!');
        }
        $this->checkUserSpace();
    }

    protected function _isLogin()
    {
        $this->token = trim(request()->header('token'));
        if(!empty($this->token)) $this->user = $this->check_user_login($this->token);
        $this->checkUserSpace();
    }

    static public function getDbConfig($name = null)
    {
        if(!empty($name)){
            $config = Db::name('config')->where(['status' => 1, 'name' => $name])->find();
        }else{
            if(!empty(self::$dbConfig)) return self::$dbConfig;
            $config = Db::name('config')->where(['status' => 1])->select()->toArray();
        }
        return $config;
    }

    protected function getUser(){
        if(!empty($this->user)) return $this->user;
        if(!empty($this->token)){
            // 获取用户信息主表信息
            $this->user = $this->check_user_login($this->token); //验证用户
            return $this->user;
        }
        return [];
    }

    /**
     * 检查失效空间
     */
    protected function checkUserSpace()
    {
        $user = $this->getUser();
        if (!empty($user)) {
            $space = Db::name('member_space')->where(['user_id' => $user['id']])->find();
            if (empty($space)) {
                Db::name('member_space')->insert([
                    'user_id' => $user['id'],
                    'create_time' => time(),
                    'update_time' => time()
                ]);
                $space = Db::name('member_space')->where(['user_id' => $user['id']])->find();
            }
            $space_relation = Db::name('space_relation')->where([
                'user_id' => $user['id'],
                'expired_time' => ['elt', time()]
            ])->select();
            if (!empty($space_relation)) {
                $space_relation = @$space_relation->toArray();
                $space_relation_ids = array_column($space_relation, 'id');
                $space_relation_space = array_column($space_relation, 'space');
                $expired_space = array_sum($space_relation_space);
                if ($expired_space > 0) {
                    try {
                        Db::startTrans();

                        Db::name('member_space')->where(['user_id' => $user['id']])->update([
                            'space' => (($space['space'] - $expired_space) < 0 ? 0 : ($space['space'] - $expired_space))
                        ]);

                        Db::name('space_relation')->where(['id' => ['in', $space_relation_ids]])->delete();

                        Db::commit();
                    } catch (\Exception $e) {
                        Db::rollback();
                    }
                }
            }
        }
    }

    protected function _initializeView()
    {
        $cmfThemePath = config('template.cmf_theme_path');
        $cmfDefaultTheme = cmf_get_current_theme();

        $themePath = "{$cmfThemePath}{$cmfDefaultTheme}";

        $root = cmf_get_root();
        //使cdn设置生效
        $cdnSettings = cmf_get_option('cdn_settings');
        if (empty($cdnSettings['cdn_static_root'])) {
            $domain = cmf_get_domain();
            $viewReplaceStr = [
                '__ROOT__' => $domain . $root,
                '__TMPL__' => $domain . "{$root}/{$themePath}",
                '__STATIC__' => $domain . "{$root}/static",
                '__WEB_ROOT__' => $domain . $root
            ];
        } else {
            $cdnStaticRoot = rtrim($cdnSettings['cdn_static_root'], '/');
            $viewReplaceStr = [
                '__ROOT__' => $root,
                '__TMPL__' => "{$cdnStaticRoot}/{$themePath}",
                '__STATIC__' => "{$cdnStaticRoot}/static",
                '__WEB_ROOT__' => $cdnStaticRoot
            ];
        }

        $viewReplaceStr = array_merge(config('view_replace_str'), $viewReplaceStr);
        config('template.view_base', WEB_ROOT . "{$themePath}/");
        config('view_replace_str', $viewReplaceStr);

        $themeErrorTmpl = "{$themePath}/error.html";
        if (file_exists_case($themeErrorTmpl)) {
            config('dispatch_error_tmpl', $themeErrorTmpl);
        }

        $themeSuccessTmpl = "{$themePath}/success.html";
        if (file_exists_case($themeSuccessTmpl)) {
            config('dispatch_success_tmpl', $themeSuccessTmpl);
        }


    }

    /**
     * 加载模板输出
     * @access protected
     * @param string $template 模板文件名
     * @param array $vars 模板输出变量
     * @param array $replace 模板替换
     * @param array $config 模板参数
     * @return mixed
     */
    protected function fetch($template = '', $vars = [], $replace = [], $config = [])
    {
        $template = $this->parseTemplate($template);
        $more = $this->getThemeFileMore($template);
        $this->assign('theme_vars', $more['vars']);
        $this->assign('theme_widgets', $more['widgets']);
        $content = parent::fetch($template, $vars, $replace, $config);

        $designingTheme = cookie('cmf_design_theme');

        if ($designingTheme) {
            $app = $this->request->module();
            $controller = $this->request->controller();
            $action = $this->request->action();

            $output = <<<hello
<script>
var _themeDesign=true;
var _themeTest="test";
var _app='{$app}';
var _controller='{$controller}';
var _action='{$action}';
var _themeFile='{$more['file']}';
if(parent && parent.simulatorRefresh){
  parent.simulatorRefresh();  
}
</script>
hello;

            $pos = strripos($content, '</body>');
            if (false !== $pos) {
                $content = substr($content, 0, $pos) . $output . substr($content, $pos);
            } else {
                $content = $content . $output;
            }
        }


        return $content;
    }

    /**
     * 自动定位模板文件
     * @access private
     * @param string $template 模板文件规则
     * @return string
     */
    private function parseTemplate($template)
    {
        // 分析模板文件规则
        $request = $this->request;
        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨模块调用
            list($module, $template) = explode('@', $template);
        }

        $viewBase = config('template.view_base');

        if ($viewBase) {
            // 基础视图目录
            $module = isset($module) ? $module : $request->module();
            $path = $viewBase . ($module ? $module . DIRECTORY_SEPARATOR : '');
        } else {
            $path = isset($module) ? APP_PATH . $module . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR : config('template.view_path');
        }

        $depr = config('template.view_depr');
        if (0 !== strpos($template, '/')) {
            $template = str_replace(['/', ':'], $depr, $template);
            $controller = cmf_parse_name($request->controller());
            if ($controller) {
                if ('' == $template) {
                    // 如果模板文件名为空 按照默认规则定位
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . cmf_parse_name($request->action(true));
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }
        return $path . ltrim($template, '/') . '.' . ltrim(config('template.view_suffix'), '.');
    }

    /**
     * 获取模板文件变量
     * @param string $file
     * @param string $theme
     * @return array
     */
    private function getThemeFileMore($file, $theme = "")
    {

        //TODO 增加缓存
        $theme = empty($theme) ? cmf_get_current_theme() : $theme;

        // 调试模式下自动更新模板
        if (APP_DEBUG) {
            $themeModel = new ThemeModel();
            $themeModel->updateTheme($theme);
        }

        $themePath = config('template.cmf_theme_path');
        $file = str_replace('\\', '/', $file);
        $file = str_replace('//', '/', $file);
        $webRoot = str_replace('\\', '/', WEB_ROOT);
        $themeFile = str_replace(['.html', '.php', $themePath . $theme . "/", $webRoot], '', $file);

        $files = Db::name('theme_file')->field('more')->where('theme', $theme)
            ->where(function ($query) use ($themeFile) {
                $query->where('is_public', 1)->whereOr('file', $themeFile);
            })->select();

        $vars = [];
        $widgets = [];
        foreach ($files as $file) {
            $oldMore = json_decode($file['more'], true);
            if (!empty($oldMore['vars'])) {
                foreach ($oldMore['vars'] as $varName => $var) {
                    $vars[$varName] = $var['value'];
                }
            }

            if (!empty($oldMore['widgets'])) {
                foreach ($oldMore['widgets'] as $widgetName => $widget) {

                    $widgetVars = [];
                    if (!empty($widget['vars'])) {
                        foreach ($widget['vars'] as $varName => $var) {
                            $widgetVars[$varName] = $var['value'];
                        }
                    }

                    $widget['vars'] = $widgetVars;
                    $widgets[$widgetName] = $widget;
                }
            }
        }

        return ['vars' => $vars, 'widgets' => $widgets, 'file' => $themeFile];
    }

    public function checkUserLogin()
    {
        $userId = cmf_get_current_user_id();
        if (empty($userId)) {
            if ($this->request->isAjax()) {
                $this->error("您尚未登录", cmf_url("user/Login/index"));
            } else {
                $this->redirect(cmf_url("user/Login/index"));
            }
        }
    }

    /*
     新增各类方法
    */

    public function arrayGetImgUrl($data)
    {
        if (is_string($data)) return $data;

        //        is_object($data) && $data = @$data->toArray();
        if (is_array($data) || is_object($data)) foreach ($data as &$item) {
            //            if (is_null($item)){
            //                $item = '';
            //                continue;
            //            }

            if (is_array($item) || is_object($item)) {
                $item = $this->arrayGetImgUrl($item);
                continue;
            }

            if (is_string($item) && in_array(substr($item, -4), ['.png', '.jpg', 'jpeg'])) {
                $item = cmf_get_image_url($item);
                continue;
            };
        }
        return $data;
    }

    /*
        新增ajax返回
    */
    public function  ajaxResult($code = 0, $msg = '', $data = [])
    {
        $result = [
            'status' => $code,
            'msg' => $msg,
            'data' => $this->arrayGetImgUrl($data),
        ];

        $this->ajaxReturn($result);
    }

    public function ajaxReturn($result)
    {
        $header = [
            'Content-Type' => 'application/json; charset=utf-8',
            //            'Access-Control-Allow-Origin'      => 'http://localhost:8081',
            'Access-Control-Allow-Credentials' => 'true',
        ];

        Response::create($result, 'json', 200, $header)->send();
        die;
    }

    //判断登录状态
    public function check_user_login($token = '')
    {
        //如果没有token
        if (empty($token)) {
            $this->ajaxResult(-1, '未登录');
        } else {
            $is_user = Db::table('cmf_user_token')//主表
            ->alias('a')//主表别名
            ->join('cmf_member m', 'a.user_id = m.id')
                ->where(['a.token' => $token])
                ->field(['a.user_id', 'a.token','m.*'])//显示字段
                ->find();
            //查询不出数据
            if (empty($is_user)) {
                $this->ajaxResult(-1, '登录异常');
                //把数据返回出去
            } else {
                return $is_user;
            }
        }
    }


    //图片上传
    public function uploadImg()
    {
//        $this->check_user_login($this->request->header('token'));

//        $file     = $this->request->file('imgFile');
        $validate = ['size' => config('image_upload_limit_size'), 'ext' => 'jpg,png,gif,jpeg'];
//        $dir      = UPLOAD_PATH . $this->request->controller() . DS . (defined('USER_ID') ? USER_ID : 'default') . DS;
//        if(!($_exists = file_exists($dir))) {
//            $isMk = mkdir($dir);
//        }
//        $info = $file->validate($validate)->move($dir, true);

        $ali = new OssModel();
        $info = $ali->OssUploadFile('imgFile', $validate);
        if ($info['code'] == 1) {
//            $img_url = $post['head_pic'] = DS . $dir . date('Ymd') . DS . $info->getFilename();
            $this->ajaxResult(1, '通用图片上传', ['img_url' => $info['result']['file_path']]);
        } else {
            $this->ajaxResult(0, $info['msg']);//上传错误提示错误信息
        }
    }

    public function ossUpload()
    {
        $files = request()->file('file');
        if (!is_array($files)) $arr = [$files];
        else $arr = $files;
        $fileArr = [];
        foreach ($arr as $file) {
            // 移动到框架应用根目录/public/uploads/ 目录下
            $info = $file->move(ROOT_PATH . 'public' . DS . 'upload');
            if ($info) {
                $filePath = $info->getPathName();
                $rs = (new OssModel())->OssUploadFile($filePath, [], false);
                if ($rs['code'] == 1) $fileArr[] = $rs['result']['file_path'];
                unset($info); //必须删除这个info，不然文件被锁定
                unlink($filePath);
            } else {
                // 上传失败获取错误信息
                return $file->getError();
            }
        }
        $this->ajaxResult(1, '图片上传成功', ['img_url' => $fileArr]);
    }

}