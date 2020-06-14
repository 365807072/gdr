<?php
/**
 * Created by PhpStorm.
 * User: Crazypeak
 * Date: 2019/4/17
 * Time: 18:28
 */

namespace app\third;

use OSS\OssClient;
use think\Request;

/**
 * 阿里云SDK对象封装
 * Class AliModel
 * @package app\common\model
 */
class AliModel{
    /**
     * @var string 测试Id/Key,权限受制-外包测试专用
     */
    private $secretId  = 'LTAI792k3c9dKPVd';
    private $secretKey = 'DlJdOIP2mV8kJRwQZQb0lFWWsMah3Y';
    private $bucket = 'wky-project';
    private $endpoint = 'wky-project.oss-cn-shenzhen.aliyuncs.com';

    /**
     * V1.1
     * 阿里OSS文件上传封装，针对所有文件
     * OSS安装：composer require aliyuncs/oss-sdk-php
     * @param string $file_item post提交的键值 文件路径
     * @param array $validate 自定义验证规则
     * @param bool $type 模式true上传控件、false现有文件修改
     * @return array
     * @throws \OSS\Core\OssException
     */
    public function OssUploadFile($file_item = 'image.jpg', $validate = [], $type = false) {
        if(empty($file_item)) {
            return ['code' => -1, 'msg' => '路径错误', 'result' => []];
        }

        //专属文件夹、存储区与对应域名，外包测试专用
        $dir      = 'linshigong';

        if($type && $requestObj = Request::file($file_item)) {
            //文件对象

            //默认添加对应文件大小限制
            $validate['size'] = isset($validate['size']) ? $validate['size'] : config()['config']['image_upload_limit_size'];

            //文件服务端本地存放目录
            $dir_path = CMF_ROOT . 'public' . DS . 'uploads';
            $fileObj  = $requestObj->validate($validate)->move($dir_path);

            if(!$fileObj)
                return ['code' => -1, 'msg' => $requestObj->getError(), 'result' => []];

            //文件对应名称（加密后）
            $file_name = $fileObj->getFilename();
            //文件绝对路径
            $file_path = $dir_path . DS . $fileObj->getSaveName();
        } else if(is_file($file_item)) {
            $file_name = basename($file_item);
            $file_path = $file_item;
        } else return ['code' => -1, 'msg' => '文件错误', 'result' => []];

        try {
            $cosClient = new OssClient(
                $this->secretId,
                $this->secretKey,
                $this->endpoint,
                //域名与存储区进行绑定
                true
            );

            $result = $cosClient->putObject(
                $this->bucket,
                //文件对应名称（加密后）
                $dir . DS . $file_name,
                //文件内容，因是本地存储，可直接读取
                file_get_contents($file_path)
            );
            return ['code' => 1, 'msg' => '通用上传', 'result' => ['file_path' => $result['oss-request-url']]];//下载地址
//            return ['code' => 1, 'msg' => '通用上传', 'result' => $result];
        } catch(\Exception $e) {
            writeDebug("[" . date('Y-m-d H:i:s') . "] (AliOss_Error)：". json_encode($e), 'AliOss_Error', 5, true);
            return ['code' => -1, 'msg' => 'OSS Error', 'result' => []];
        }
    }

    public function OssCopyFile($file_item = 'image.jpg', $to_item_name = '')
    {
        if(empty($file_item)) {
            return ['code' => -1, 'msg' => '路径错误', 'result' => []];
        }

        $to_item_name = isset($to_item_name) ? $to_item_name : $to_item_name.'.copy';
//        $file_item = trim(ltrim($file_item, $this->endpoint), '/');
//        $to_item_name = trim(ltrim($to_item_name, $this->endpoint), '/');

        $tempArr = explode('/', trim($file_item, '/')); unset($tempArr[0]);
        $file_item = implode('/', array_values($tempArr)); unset($tempArr);

        $tempArr = explode('/', trim($to_item_name, '/')); unset($tempArr[0]);
        $to_item_name = implode('/', array_values($tempArr)); unset($tempArr);

        $out_item_name = 'http://'.$this->endpoint.'/'.$to_item_name;

        try {
            $cosClient = new OssClient(
                $this->secretId,
                $this->secretKey,
                $this->endpoint,
                //域名与存储区进行绑定
                true
            );

            $result = $cosClient->copyObject(
                $this->bucket,
                $file_item,
                $this->bucket,
                $to_item_name
            );
            $result['out_item_name'] = $out_item_name;
            return ['code' => 1, 'msg' => '通用复制', 'result' => $result];
        } catch(\Exception $e) {
            writeDebug("[" . date('Y-m-d H:i:s') . "] (AliOss_Error)：". json_encode($e), 'AliOss_Error', 5, true);
            return ['code' => -1, 'msg' => 'OSS Error', 'result' => []];
        }
    }

    public function OssGetFile($file_item = 'image.jpg')
    {
        if(empty($file_item)) {
            return ['code' => -1, 'msg' => '路径错误', 'result' => []];
        }

        $tempArr = explode('/', trim($file_item, '/'));
        unset($tempArr[0]);
        $file_item = implode('/', array_values($tempArr));

        try {
            $cosClient = new OssClient(
                $this->secretId,
                $this->secretKey,
                $this->endpoint,
                //域名与存储区进行绑定
                true
            );

            $result = $cosClient->getObject(
                $this->bucket,
                $file_item
            );
            return ['code' => 1, 'msg' => '通用获取', 'result' => $result];
        } catch(\Exception $e) {
            writeDebug("[" . date('Y-m-d H:i:s') . "] (AliOss_Error)：". json_encode($e), 'AliOss_Error', 5, true);
            return ['code' => -1, 'msg' => 'OSS Error', 'result' => []];
        }
    }

    public function OssDelFile($file_item)
    {
        if(empty($file_item)) {
            return ['code' => -1, 'msg' => '路径错误', 'result' => []];
        }

        if(is_array($file_item)){
            foreach ($file_item as $key => $value){
                $tempArr = explode('/', trim($value, '/'));
                unset($tempArr[0]);
                $file_item[$key] = implode('/', array_values($tempArr));
                unset($tempArr);
            }
        }else{
            $tempArr = explode('/', trim($file_item, '/'));
            unset($tempArr[0]);
            $file_item = implode('/', array_values($tempArr));
        }

        try {
            $cosClient = new OssClient(
                $this->secretId,
                $this->secretKey,
                $this->endpoint,
                //域名与存储区进行绑定
                true
            );

            $result = $cosClient->deleteObjects($this->bucket, $file_item);
            return ['code' => 1, 'msg' => '通用删除', 'result' => $result];
        } catch (\Exception $e) {
            writeDebug("[" . date('Y-m-d H:i:s') . "] (AliOss_Error)：". json_encode($e), 'AliOss_Error', 5, true);
            return ['code' => -1, 'msg' => 'OSS Error', 'result' => []];
        }
    }

}
