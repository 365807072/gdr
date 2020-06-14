<?php

namespace app\third;

//环信即时通信
class HxIM
{
    private $app_key = '1123190816030622#linshigong';
    private $org_name = '1123190816030622';
    private $app_name = 'linshigong';
    private $client_id = 'YXA6-YgqwLl3TcuzHKQkOWkNMw';
    private $client_secret = 'YXA6QY7R-HMN2aGZMBsaJ0DppAo9jYA';
    private $token = '';
    private static $instance = null;

    private function __construct(){
        $this->getToken();
    }

    private function __clone(){}

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    //获取token
    function getToken() {
        $redis = getRedisInstance();
        $token = $redis->get('HxTOKEN');
        if (!$token) {
            $url = "https://a1.easemob.com/{$this->org_name}/{$this->app_name}/token";
            $data = array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
            );
            $rs = $this->curl_method($url, $data);
            $rs = json_decode( $rs, true );
            $redis->set('HxTOKEN', $rs['access_token']);
            $redis->expire('HxTOKEN', time()+$rs['expires_in']-60);  //提前1分钟失效
            $token = $rs['access_token'];
        }
        $this->token = $token;
    }

    //注册用户 单个
    function createUser($user) {
        $url = "https://a1.easemob.com/{$this->org_name}/{$this->app_name}/users";
        $data = array(
            'username' => $user, //账号
            'password' => '123456', //密码
            'nickname' => $user
        );
        $header = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        );
        $this->curl_method($url, $data, $header, "POST");
    }

    //删除用户 单个
    function delUser($user) {
        $user = 'deluser'; //删除的用户
        $url = "https://a1.easemob.com/{$this->org_name}/{$this->app_name}/users/{$user}";
        $header = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        );
        $rs = $this->curl_method($url, [], $header, 'DELETE');
        return json_decode( $rs, true );
    }

    //发送消息
    function sendMessage() {
        $user = array('ceshi');
        $msg = '测试';
        $url = "https://a1.easemob.com/{$this->org_name}/{$this->app_name}/message";
        $data = array(
            'target_type'   =>  'users',
            'target'        =>  $user,
            'msg'           =>  $msg,
            'type'          =>  'type',
        );
        $header = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        );
        $rs = $this->curl_method($url, $data, $header, 'POST');
        return json_decode( $rs, true );
    }

    //下载内容，此类直接调用此方法
    public function downContent($time){
        $path = 'C:/wzl/phpEnv/www/localhost/xiaojudian/application/third/';//设置根路径
        $sub_dir = substr($time,0,8);;//截取时间的天如20180822作为文件夹
        //如果本地存贮了聊天文件，由于访问限制，每个文件都是一小时的聊天记录
        $find_file = realpath($path).'/'.$sub_dir.'/'.$time;
        if(file_exists($find_file)){
            $txt = file_get_contents($find_file);
            if(empty(trim($txt))) return '该时段暂无数据3';
            //这段代码是直接返回 然后入数据表
            $aa = explode("\n",trim($txt));
            foreach($aa as $key => $val){
                $info[$key] = json_decode($val,true);
            }
            return $info;
        }

        $result = $this->getChat($time); //调用下载方法，返回的是文件下载URL
        if(isset($result['error'])) return '该时段暂无数据1';
        if(!isset($result['data'][0]['url'])) return '该时段暂无数据2';
        //请求httpcopy方法，返回下载好的压缩文件
        $downData = $this->httpcopy($result['data'][0]['url'],$path);
        if($downData == 'fail') return '下载失败';
        //解压缩后返回文件路径
        $ungz = $this->unzipGz($downData);
        //return $ungz;//www/wwwroot/PHP/storage/chatrescore/20180820/2018082014
        if($ungz == 'fail') return '解压失败';
        if(!file_exists($ungz)) return '文件不存在';
        $txt = file_get_contents($ungz);
        if(empty(trim($txt))) return '该时段暂无数据3';
        $aa = explode("\n",trim($txt));
        foreach($aa as $key => $val){
            $info[$key] = json_decode($val,true);
        }
        return $info;
    }

    //根据时间返回下载地址
    public function getChat($time){
        //请求url
        $url = "https://a1.easemob.com/{$this->org_name}/{$this->app_name}/chatmessages/{$time}";
        $header = array("Content-Type”:”application/json","Authorization:Bearer ".$this->token);
        $result = $this->curl_method($url, '', $header, 'GET');
        return json_decode($result,true);
    }

    //下载远程文件
    public function httpcopy($url,$path, $files="", $timeout=60) {
        $file_a = empty($files) ? pathinfo($url,PATHINFO_BASENAME) : $files;
        //分割
        $file = explode('?',$file_a)[0];
        $sub_dir = substr($file,0,8);
        $dir = realpath($path).'/'.$sub_dir;

        if(!is_dir($dir)){
            @mkdir($dir,0755,true);
        }
        $url = str_replace(" ","%20",$url);
        $temp = file_get_contents($url);
        if(@file_put_contents($dir.'/'.$file, $temp)) {
            return $dir.'/'.$file;
        } else {
            return 'fail';
        }
    }

    //解压gz压缩包
    public function unzipGz($gz_file){
        $buffer_size = 1000000; //限定大小
        $out_file_name = str_replace('.gz', '', $gz_file);
        $file = gzopen($gz_file, 'rb');
        $out_file = fopen($out_file_name, 'wb');
        $str='';
        if(!gzeof($file)) {
            fwrite($out_file, gzread($file, $buffer_size));
            fclose($out_file);
            gzclose($file);
            return $out_file_name;
        } else {
            return 'fail';
        }
    }

    function curl_method($url, $data, $header = [], $method = "POST") {
        //初使化init方法
        $ch = curl_init();
        //指定URL
        curl_setopt($ch,CURLOPT_URL, $url);
        //设定请求后返回结果
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode( $data ));
                break;
            case 'GET': break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data); //设置请求体，提交数据包
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        //忽略证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //header头信息
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        //设置超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        //发送请求
        $output = curl_exec($ch);
        //关闭curl
        curl_close($ch);
        //返回数据
        return $output;
    }
}