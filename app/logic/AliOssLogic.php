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

namespace app\logic;

use think\Db;
use app\third\AliModel;

class AliOssLogic
{
    public function uploadFileToAli($files = [], $validate = [], $type = false)
    {
        if(empty($files)) return false;
        if (!is_array($files)) $arr = [$files];
        else $arr = $files;

        $fileArr = [];
        $aliModel = new AliModel();
        //默认添加对应文件大小限制
        $validate['size'] = isset($validate['size']) ? $validate['size'] : config('aliOss')['upload']['upload_limit_size'];

        foreach($arr as $file){
            // 移动到框架应用根目录/public/uploads/ 目录下
            $info = $file->validate($validate)->move(ROOT_PATH . 'public' . DS . 'upload');
            if($info){
                $filePath = $info->getPathName();
                $rs = $aliModel->OssUploadFile($filePath, $validate, $type);
//                if ($rs['code'] == 1) $fileArr[] = $rs['result']['oss-request-url'];
                if ($rs['code'] == 1) $fileArr[] = $rs['result']['file_path'];
                unset($info); //必须删除这个info，不然文件被锁定
                unlink($filePath);
            }else{
                // 上传失败获取错误信息
                return $file->getError();
            }
        }

        return $fileArr;
    }

    public function copyFileToAli($files = [], $to_item_name = '', $charList = 'http://')
    {
        if(empty($files)) return false;
        if (!is_array($files)) $arr = [$files];
        else $arr = $files;

        $fileArr = [];
        $aliModel = new AliModel();

        foreach($arr as $file){
            $file = ltrim(trim($file), $charList);
            $to_item_name = !empty($to_item_name) ? $to_item_name : $file.'.historyCopy';
            $rs = $aliModel->OssCopyFile($file, $to_item_name);
            if ($rs['result']['out_item_name'] != '') $fileArr[] = $rs['result']['out_item_name'];
        }

        return $fileArr;
    }

    public function getFileFromAli($files = [])
    {
        if(empty($files)) return false;
        if (!is_array($files)) $arr = [$files];
        else $arr = $files;

        $fileArr = [];
        $aliModel = new AliModel();

        foreach($arr as $file){
            $rs = $aliModel->OssGetFile($file);
            if ($rs['code'] == 1) $fileArr[] = $rs['result'];
        }

        return $fileArr;
    }

    public function delFileToAli($files = [])
    {
        if(empty($files)) return false;
        if (!is_array($files)) $arr = [$files];
        else $arr = $files;

        $aliModel = new AliModel();
        $rs = $aliModel->OssDelFile($arr);
        return $rs;
    }

}