<?php
/**
 * Created by PhpStorm.
 * User: Crazypeak
 * Date: 2019/4/17
 * Time: 18:28
 */

namespace app\user\model;

use OSS\OssClient;
use think\Request;

/**
 * 阿里云SDK对象封装
 * Class AliModel
 * @package app\common\model
 */
class OssModel {
    /**
     * @var string 测试Id/Key,权限受制-外包测试专用
     */
    private $secretId  = 'LTAI792k3c9dKPVd';
    private $secretKey = 'DlJdOIP2mV8kJRwQZQb0lFWWsMah3Y';

    /**
     * V1.1
     * 阿里OSS文件上传封装，针对所有文件
     * OSS安装：composer require aliyuncs/oss-sdk-php
     * @param string $file_item post提交的键值
     * @param array $validate 自定义验证规则
     * @param bool $type 模式true上传控件、false现有文件修改
     * @return array
     * @throws \OSS\Core\OssException
     */
    function OssUploadFile($file_item = 'image.jpg', $validate = [], $type = TRUE) {
        if (empty($file_item)) {
            return ['code' => -1, 'msg' => '路径错误', 'result' => []];
        }
        vendor('aliyuncs.oss-sdk-php.autoload');
        //专属文件夹、存储区与对应域名，外包测试专用
        $dir      = 'lsg';//项目文件夹
        $bucket   = 'wky-project';
        $endpoint = 'wky-project.oss-cn-shenzhen.aliyuncs.com';

        if ($type && $requestObj = Request::instance()->file($file_item)) {
            //文件对象
            //默认添加对应文件大小限制
            $validate['size'] = $validate['size'] ?? config('image_upload_limit_size');

            //文件服务端本地存放目录
            $dir_path = ROOT_PATH . 'public' . DS . 'upload';
            $fileObj  = $requestObj->validate($validate)->move($dir_path);

            if (!$fileObj)
                return ['code' => -1, 'msg' => $requestObj->getError(), 'result' => []];

            //文件对应名称（加密后）
            $file_name = $fileObj->getFilename();
            //文件绝对路径
            $file_path = $dir_path . DS . $fileObj->getSaveName();
        } elseif (is_file($file_item)) {
            $file_name = basename($file_item);
            $file_path = $file_item;
        } else return ['code' => -1, 'msg' => '文件错误', 'result' => []];

        try {
            $cosClient = new OssClient(
                $this->secretId,
                $this->secretKey,
                $endpoint,
                //域名与存储区进行绑定
                TRUE
            );

            $result = $cosClient->putObject(
                $bucket,
                //文件对应名称（加密后）
                $dir . $file_name,
                //文件内容，因是本地存储，可直接读取
                file_get_contents($file_path)
            );
            return ['code' => 1, 'msg' => '通用图片上传', 'result' => ['file_path' => $result['oss-request-url']]];//下载地址
        }
        catch (\Exception $e) {
            return ['code' => -1, 'msg' => 'OSS Error', 'result' => []];
        }
    }
}
