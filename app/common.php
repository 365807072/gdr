<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Route;
use think\Cache;
use traits\SystemLog;



/**
 * 数组 转 对象
 *
 * @param array $arr 数组
 * @return object
 */
if (! function_exists('array_to_object'))
{
    function array_to_object($arr)
    {
        if (gettype($arr) != 'array')  return;
        foreach ($arr as $k => $v) { if (gettype($v) == 'array' || getType($v) == 'object')  $arr[$k] = (object)array_to_object($v); }
        return (object)$arr;
    }
}

/**
 * 对象 转 数组
 *
 * @param object $obj 对象
 * @return array
 */
if (! function_exists('object_to_array'))
{
    function object_to_array($obj)
    {
        $obj = (array)$obj;
        foreach ($obj as $k => $v)
        {
            if (gettype($v) == 'resource')  return;
            if (gettype($v) == 'object' || gettype($v) == 'array')  $obj[$k] = (array)object_to_array($v);
        }
        return $obj;
    }
}

// 应用公共文件
//把unicode转化成中文
if (! function_exists('decodeUnicode')) {
    function decodeUnicode($str)
    {
        return preg_replace_callback
        (
            '/\\\\u([0-9a-f]{4})/i',
            $greet = function ($matches) {
                return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE");
            },
            $str
        );
    }
}
/**
 * 返回 Redis 实例
 * @return mixed
 */
if (! function_exists('getRedisInstance')) {
    function getRedisInstance($pconnect = false, $redisDb = null) {

        $r = new \Redis();
        if ($pconnect) {
            $r->pconnect(\think\Env::get('REDIS_HOST','127.0.0.1'),\think\Env::get('REDIS_PORT',6379));
        } else {
            $r->connect(\think\Env::get('REDIS_HOST','127.0.0.1'), \think\Env::get('REDIS_PORT',6379));
        }

        $auth = \think\Env::get('REDIS_PASSWORD',null);
        if (!is_null($auth)) {
            $r->auth($auth);
        }

        $rDb = $redisDb ?$redisDb : \think\Env::get('REDIS_DB',0);
        if (!empty($rDb)) {
            $r->select($rDb);
        }

        return $r;
    }
}

// 格式化json数据
if (! function_exists('format_json')) {
    function format_json($arr, $html = true)
    {
        // 预处理数组数据，将数组数据进行初次矫正
        $json = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        // 统计循环次数初始化
        $tabcount = 0;
        // 结果值初始化
        $result = '';
        // 引号控制开关
        $inquote = false;
        // 匹配循环条件控制开关
        $ignorenext = false;
        // 采用格式方案默认html
        if ($html) {
            // 空格递进
            $tab = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
            // 回车换行
            $newline = "<br/>";
        } else {
            // 空格递进
            $tab = "\t";
            // 回车换行
            $newline = "\n";
        }
        // 循环整个json长度
        for ($i = 0; $i < strlen($json); $i++) {
            // 为每一个字符串单个进行匹配
            $char = $json[$i];
            // 如果默认为false 关闭状态
            if ($ignorenext) {
                // 结赋值
                $result .= $char;
                // 继续默认
                $ignorenext = false;
            } else {
                // 匹配方案
                switch ($char) {
                    // 左花括号处理
                    case '{':
                        // 初始值++
                        $tabcount++;
                        // 如果初始值大于第一次 瓶装所有结果加上替换次数
                        if ($tabcount > 1) $result .= $newline . $tab . $char . $newline . str_repeat($tab, $tabcount);
                        // 否则使用固定格式
                        else $result .= $newline . $char . $newline . str_repeat($tab, $tabcount);
                        break;
                    // 右花括号处理
                    case '}':
                        // 初始值--
                        $tabcount--;
                        // 取出结果的空格瓶装所有信息
                        $result = trim($result) . $newline . str_repeat($tab, $tabcount) . $char;
                        break;
                    // 单引号方案
                    case ',':
                        // 替换字符串瓶装字符串取得结果
                        $result .= $char . $newline . str_repeat($tab, $tabcount);
                        break;
                    // 双引号方案
                    case '"':
                        // 双引号如果是真的时候
                        $inquote = !$inquote;
                        // 直接得到结果
                        $result .= $char;
                        break;
                    case '\\':
                        // 对文章内字符处理替换时候的规则，将结果变为true进行如果里面的操作，重置下一个字符
                        if ($inquote) $ignorenext = true;
                        // 得到结果返回
                        $result .= $char;
                        break;
                    default:
                        // 默认得到返回结果
                        $result .= $char;
                        break;
                }
            }
        }
        return $result;
    }
}


if ( ! function_exists('curl_get'))
{
    function curl_get($url,$data = array(),$header=array()) {

        $curl = curl_init($url);
        if(!empty($header)) {
            curl_setopt($curl,CURLOPT_HTTPHEADER,$header);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); //不直接显示回传结果
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        if(!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        $rs = curl_exec($curl);
        curl_close($curl);
        return $rs;
    }
}


if (! function_exists('passVerification')) {
    function passVerification($pass)
    {

        if ($pass == null) return '密码不能为空';
        if (strlen($pass) > 32) return '密码长度不能大于32个字符';
        if (strlen($pass) < 6) return '密码长度不能小于6个字符';
        $pass = md5($pass.config('app.security_keys'));
        return $pass;
    }
}
/**
 * [ipVerificationSet 登录次数IP验证规则模拟缓存]
 * @param  [int] $num  [限制请求次数]
 * @param  [int] $time [设置从新开启时常]
 * @return [str]       [条件不匹配时候返回提示性字符串]
 * @return [bool]      [符合条件是返回真]
 */
if (! function_exists('ipVerificationSet')) {
    function ipVerificationSet($num, $time)
    {
        $data = [];
        //获取用户ip地址
        $ip = request()->ip();
        //获取缓存中登录前的ip信息
        $redisKey  = "loginIpCount|".$ip;
        $datacache = Cache::get($redisKey);
        //如果缓存中的该缓存名不存在
        if ($datacache == false) {
            //初始化存入缓存的内容
            $datacache['ip'] = $ip;
            // 初始化缓存次数
            $datacache['num'] = 1;
            //设置缓存名为输入的用户信息将次数与用户名存进去//半小时过期
            Cache::set($redisKey, $datacache, $time);
        } else {
            //如果数缓存中的用户与输入的用户相等
            if ($datacache['ip'] == $ip) {
                //重置赋值保证更新
                $datacache['ip'] = $ip;
                //进行缓存+1的动作操作
                $datacache['num'] += 1;
                Cache::set($redisKey, $datacache, $time);
                //如果缓存中动作加1的操作次数超过上限// 返回错误信息
                if ($datacache['num'] > $num) return returnMsg('10001','login_times_too_many');
            } else {
                //记录一个新的缓存，存储人员信息
                $datacache['ip'] = $ip;
                //给新的缓存一个登录次数的初始值
                $datacache['num'] = 1;
                //缓存这个数组
                $datacache = Cache::set($redisKey, $data, $time);
            }
        }
        return true;
    }
}
/**
 * [ipVerificationClear 清除ip请求的缓存]
 * @return [str] [不符合条件的时候，返回提示信息]
 * @return [bool] [符合的时候返回真的条件]
 */
if (! function_exists('ipVerificationClear')) {
    function ipVerificationClear()
    {
        Cache::rm(request()->ip());
    }
}

/**
 * [userVerification 登录次数验证User规则模拟缓存]
 * @param  [str] $data [传入需要验证的限制的账号]
 * @return [str]       [条件不匹配时候返回提示性字符串]
 * @return [bool]       [符合条件是返回真]
 */
if (! function_exists('userVerification')) {
    function userVerification($username, $num, $time)
    {
        //获取缓存中登录前的用户信息
        $redisKey  = "loginAccountCount|".$username;
        $datacache = Cache::get($redisKey);
        //如果缓存中的该缓存名不存在
        if ($datacache === false) {
            //初始化存入缓存的内容
            $datacache['user'] = $username;
            // 初始化缓存次数
            $datacache['num'] = 1;
            //设置缓存名为输入的用户信息将次数与用户名存进去//半小时过期
            Cache::set($redisKey, $datacache, $time);
        } else {
            //如果数缓存中的用户与输入的用户相等
            if ($datacache['user'] == $username) {
                //重置赋值保证更新
                $datacache['user'] = $username;
                //进行缓存+1的动作操作
                $datacache['num'] += 1;
                //存储用户数据进入缓存
                Cache::set($redisKey, $datacache, $time);
                //如果缓存中动作加1的操作次数超过上限// 返回错误信息
                if ($datacache['num'] > $num) return '该账户登录次数过多，请半小时后在次尝试登录';
            } else {
                //记录一个新的缓存，存储人员信息
                $datacache['user'] = $username;
                //给新的缓存一个登录次数的初始值
                $datacache['num'] = 1;
                //缓存这个数组
                $datacache = Cache::set($redisKey, $username, $time);
            }
        }
        return true;
    }
}
/**
 *获取操作系统版本
 *
 *@return string
 */
if (! function_exists('getSystem')) {
    function getSystem()
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return '未知操作系统';
        }
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $os = '';
        if (strpos($agent, 'win') !== false) {
            if (strpos($agent, 'nt 5.1') !== false) {
                $os = 'Windows XP';
            } elseif (strpos($agent, 'nt 5.2') !== false) {
                $os = 'Windows 2003';
            } elseif (strpos($agent, 'nt 5.0') !== false) {
                $os = 'Windows 2000';
            } elseif (strpos($agent, 'nt 6.0') !== false) {
                $os = 'Windows Vista';
            } elseif (strpos($agent, 'nt 6.1') !== false) {
                $os = 'Windows 7';
            } elseif (strpos($agent, 'nt 10.0') !== false) {
                $os = 'Windows 10';
            } elseif (strpos($agent, 'nt') !== false) {
                $os = 'Windows NT';
            } elseif (strpos($agent, 'win 9x') !== false && strpos($agent, '4.90') !== false) {
                $os = 'Windows ME';
            } elseif (strpos($agent, '98') !== false) {
                $os = 'Windows 98';
            } elseif (strpos($agent, '95') !== false) {
                $os = 'Windows 95';
            } elseif (strpos($agent, '32') !== false) {
                $os = 'Windows 32';
            } elseif (strpos($agent, 'ce') !== false) {
                $os = 'Windows CE';
            }
        } elseif (strpos($agent, 'linux') !== false) {
            $os = 'Linux';
        } elseif (strpos($agent, 'unix') !== false) {
            $os = 'Unix';
        } elseif (strpos($agent, 'sun') !== false && strpos($agent, 'os') !== false) {
            $os = 'SunOS';
        } elseif (strpos($agent, 'ibm') !== false && strpos($agent, 'os') !== false) {
            $os = 'IBM OS/2';
        } elseif (strpos($agent, 'mac') !== false) {
            $os = 'Mac';
        } elseif (strpos($agent, 'powerpc') !== false) {
            $os = 'PowerPC';
        } elseif (strpos($agent, 'aix') !== false) {
            $os = 'AIX';
        } elseif (strpos($agent, 'hpux') !== false) {
            $os = 'HPUX';
        } elseif (strpos($agent, 'netbsd') !== false) {
            $os = 'NetBSD';
        } elseif (strpos($agent, 'bsd') !== false) {
            $os = 'BSD';
        } elseif (strpos($agent, 'osf1') !== false) {
            $os = 'OSF1';
        } elseif (strpos($agent, 'irix') !== false) {
            $os = 'IRIX';
        } elseif (strpos($agent, 'freebsd') !== false) {
            $os = 'FreeBSD';
        } elseif (strpos($agent, 'teleport') !== false) {
            $os = 'teleport';
        } elseif (strpos($agent, 'flashget') !== false) {
            $os = 'flashget';
        } elseif (strpos($agent, 'webzip') !== false) {
            $os = 'webzip';
        } elseif (strpos($agent, 'offline') !== false) {
            $os = 'offline';
        } else {
            $os = '未知操作系统';
        }
        return $os;
    }
}
/**
 *获取浏览器版本
 *
 *@return string
 */
if (! function_exists('getBrowser')) {
    function getBrowser()
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return '';
        }
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $browser = '';
        $browser_ver = '';
        if (preg_match('/LBBROWSER/i', $agent, $regs)) {
            $browser = '猎豹浏览器';
            $browser_ver = '';
        } elseif (preg_match('/TaoBrowser\/([^\s]+)/i', $agent, $regs)) {
            $browser = '淘宝浏览器';
            $browser_ver = '';
        } elseif (preg_match('/theworld/i', $agent, $regs)) {
            $browser = '世界之窗浏览器';
            $browser_ver = '';
        } elseif (preg_match('/MSIE\s([^\s|;]+)/i', $agent, $regs)) {
            $browser = 'Internet Explorer';
            $browser_ver = $regs[1];
        } elseif (preg_match('/FireFox\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'FireFox';
            $browser_ver = $regs[1];
        } elseif (preg_match('/QQBrowser\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'QQBrowser';
            $browser_ver = $regs[1];
        } elseif (preg_match('/BIDUBrowser\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'BIDUBrowser';
            $browser_ver = $regs[1];
        } elseif (preg_match('/Maxthon/i', $agent, $regs)) {
            $browser = '(Internet Explorer ' . $browser_ver . ') Maxthon';
            $browser_ver = '';
        } elseif (preg_match('/Opera[\s|\/]([^\s]+)/i', $agent, $regs)) {
            $browser = 'Opera';
            $browser_ver = $regs[1];
        } elseif (preg_match('/OPR[\s|\/]([^\s]+)/i', $agent, $regs)) {
            $browser = 'Opera';
            $browser_ver = $regs[1];
        } elseif (preg_match('/OmniWeb\/(v*)([^\s|;]+)/i', $agent, $regs)) {
            $browser = 'OmniWeb';
            $browser_ver = $regs[2];
        } elseif (preg_match('/Chrome\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'Chrome';
            $browser_ver = $regs[1];
        } elseif (preg_match('/Netscape([\d]*)\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'Netscape';
            $browser_ver = $regs[2];
        } elseif (preg_match('/safari\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'Safari';
            $browser_ver = $regs[1];
        } elseif (preg_match('/NetCaptor\s([^\s|;]+)/i', $agent, $regs)) {
            $browser = '(Internet Explorer ' . $browser_ver . ') NetCaptor';
            $browser_ver = $regs[1];
        } elseif (preg_match('/Lynx\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'Lynx';
            $browser_ver = $regs[1];
        }
        if (!empty($browser)) {
            return addslashes($browser . ' ' . $browser_ver);
        } else {
            return '未知浏览器';
        }
    }
}

if (! function_exists('get_ip')) {
    function get_ip()
    {
        static $ip = false;
        if ($ip !== false) return $ip;
        foreach (array('HTTP_CLIENT_IP', 'HTTP_INCAP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $aah) {
            if (!isset($_SERVER[$aah])) continue;
            $cur2ip = $_SERVER[$aah];
            $curip = explode('.', $cur2ip);
            if (count($curip) !== 4) break; // If they've sent at least one invalid IP, break out
            foreach ($curip as $sup)
                if (($sup = intval($sup)) < 0 or $sup > 255)
                    break 2;
            $curip_bin = $curip[0] << 24 | $curip[1] << 16 | $curip[2] << 8 | $curip[3];
            foreach (array(
                         //hexadecimal ip  ip mask
                         array(0x7F000001, 0xFFFF0000), // 127.0.*.*
                         array(0x0A000000, 0xFFFF0000), // 10.0.*.*
                         array(0xC0A80000, 0xFFFF0000), // 192.168.*.*
                     ) as $ipmask) {
                if (($curip_bin & $ipmask[1]) === ($ipmask[0] & $ipmask[1])) break 2;
            }
            return $ip = $cur2ip;
        }
        return $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "";
    }
}
/**
 * [userVerificationClear 用户请求规则限制清除]
 * @param  [str] $user [传入用户名称]
 * @return [str]       [条件不匹配时候返回提示性字符串]
 * @return [bool]       [符合条件是返回真]
 */
if (! function_exists('userVerificationClear')) {
    function userVerificationClear($user)
    {
        Cache::rm($user);
    }
}

/**
 * 文件上传到ali-oos
 * @return [array]   [1,jpg,2.jpg]
 */
if (! function_exists('uploadPicToAli')) {
    function uploadPicToAli($key='img', $validate = [], $type = false) {
        $files = request()->file($key);
        if (!is_array($files)) $arr = [$files];
        else $arr = $files;
        $fileArr = [];
        foreach($arr as $file){
            // 移动到框架应用根目录/public/uploads/ 目录下
            $info = $file->move(ROOT_PATH . 'public' . DS . 'upload');
            if($info){
                $filePath = $info->getPathName();
                $rs = (new app\third\AliModel())->OssUploadFile($filePath, $validate, $type);
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
}

if (! function_exists('uploadPic')) {
    function uploadPic($uniname,$imageType,$base64_image){
        $imagefile = dirname(ROOT_PATH)."/frontend/spread/upload";

        //根据日期新建目录
        $dirdate = date('Ym');
        $dirname = $imagefile."/".$dirdate;
        if(!file_exists($dirname)){
            !mkdir($dirname, 0777, true);
        }
        //保存头部照片
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image, $result_head)){
            $type = $result_head[2];
            $allowedExts = array("gif", "jpeg", "jpg", "png");
            if(!in_array($type, $allowedExts)) return returnMsg(10033,"photo_type_error");
            $returnUrl = "/upload/".$dirdate."/".$imageType.$uniname.".{$type}";
            $imageUrl = $dirname."/".$imageType.$uniname.".{$type}";
            $pic=base64_decode(str_replace($result_head[1], '', $base64_image));
            $size = strlen($pic);
            if ($size>1024000) return returnMsg(10031,"photo_sive_too_big");
            if (!file_put_contents($imageUrl, $pic)){
                return returnMsg(10032,"photo_upload_fail");
            }
        }else{
            return returnMsg(10032,"photo_upload_fail");
        }

        return returnMsg(0,"success",['returnUrl'=>$returnUrl]);
    }
}


/*封装一个返回函数
 * $code int 状态码
 * $msg string 提示信息
 * $data array 返回数据
 * */
if (! function_exists('returnMsg')) {
    function returnMsg($code, $msg = '', $data = [], $vars = [], $return = 'json')
    {

        $msg = lang($msg,$vars);
        $data = nullToStr($data);
        return [
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ];
    }
}

/**
 * null转空
 */
if (! function_exists('nullToStr')) {
    function nullToStr($arr)
    {
        if ($arr === null) return [];
        if (!is_array($arr)) return $arr;
        foreach ($arr as $k=>$v){
            if(is_null($v) || $v=='null') $arr[$k] = '';
            if(is_int($v) || is_float($v) || is_string($v)) $arr[$k] = strval(del0($v));
            if(is_array($v)) $arr[$k] = nullToStr($v);
        }
        return $arr;
    }
}

/**
 * 去除多余的0
 */
if ( ! function_exists('del0'))
{
    function del0($s)
    {
        $s = trim(strval($s));
        if (preg_match('#^-?\d+?\.0+$#', $s)) {
            return preg_replace('#^(-?\d+?)\.0+$#','$1',$s);
        }
        if (preg_match('#^-?\d+?\.[0-9]+?0+$#', $s)) {
            return preg_replace('#^(-?\d+\.[0-9]+?)0+$#','$1',$s);
        }
        return $s;
    }
}

/**
 * 将数组的下标按要求格式返回
 *
 * @param array $data 需要格式化的数组
 * @param string $type 返回下标字符串格式，可选范围 hump、underline
 * @return array 格式化后的数组
 */
if (! function_exists('arrayKeyTrans')) {
    function arrayKeyTrans($data = [], $type = 'hump') {
        $fun     = 'hump' == $type ? 'lineToHump' : 'humpToLine';
        $newData = [];

        foreach ($data as $key => $val) {
            $newKey = $fun($key);
            $newData[$newKey] = $val;
        }

        return $newData;
    }
}


/**
 *
 * 下划线式字符串转成驼锋式字符串
 *
 * @param string $str 待格式化字符串
 * @return null|string|string[]
 */

if (! function_exists('lineToHump')) {
    function lineToHump($str = '') {
        $str = preg_replace_callback('/([-_]+([a-z]{1}))/i',function($matches){
            return strtoupper($matches[2]);
        },$str);
        return $str;
    }
}

/**
 * 驼锋式字符串转成下划线式字符串
 *
 * @param string $str 待格式化字符串
 * @return null|string|string[]
 */
if (! function_exists('humpToLine')) {
    function humpToLine($str = ''){
        $str = preg_replace_callback('/([A-Z]{1})/',function($matches){
            return '_'.strtolower($matches[0]);
        },$str);
        return $str;
    }
}

/**
 * 隐藏字符串，格式化显示
 *
 * @param $username 待隐藏的字符串
 * @return string 隐藏后的字符串
 */
if (! function_exists('hideStringFormat')) {
    function hideStringFormat($string, $bengin=0, $len = 4, $type = 0, $glue = "*") {
        if (empty($string))
            return false;
        $array = array();
        if ($type == 0 || $type == 1 || $type == 4) {
            $strlen = $length = mb_strlen($string);
            while ($strlen) {
                $array[] = mb_substr($string, 0, 1, "utf8");
                $string = mb_substr($string, 1, $strlen, "utf8");
                $strlen = mb_strlen($string);
            }
        }
        if ($type == 0) {
            for ($i = $bengin; $i < ($bengin + $len); $i++) {
                if (isset($array[$i]))
                    $array[$i] = $glue;
            }
            $string = implode("", $array);
        }else if ($type == 1) {
            $array = array_reverse($array);
            for ($i = $bengin; $i < ($bengin + $len); $i++) {
                if (isset($array[$i]))
                    $array[$i] = $glue;
            }
            $string = implode("", array_reverse($array));
        }else if ($type == 2) {
            $array = explode($glue, $string);
            $array[0] = hideStr($array[0], $bengin, $len, 1);
            $string = implode($glue, $array);
        } else if ($type == 3) {
            $array = explode($glue, $string);
            $array[1] = hideStr($array[1], $bengin, $len, 0);
            $string = implode($glue, $array);
        } else if ($type == 4) {
            $left = $bengin;
            $right = $len;
            $tem = array();
            for ($i = 0; $i < ($length - $right); $i++) {
                if (isset($array[$i]))
                    $tem[] = $i >= $left ? $glue : $array[$i];
            }
            $array = array_chunk(array_reverse($array), $right);
            $array = array_reverse($array[0]);
            for ($i = 0; $i < $right; $i++) {
                $tem[] = $array[$i];
            }
            $string = implode("", $tem);
        }
        return $string;
    }
}

if (! function_exists('getCountryName')) {
    function getCountryName() {
        $ipUrl = 'http://ip.taobao.com/service/getIpInfo.php';
        $data = http($ipUrl, ['ip' => request()->ip()]);
        //    halt($data['data']['data']['country']);
        if($data['status'] == API_SUCCESS && $data['data']['code'] == 0)
            $countryName = $data['data']['data']['country'];
        else $countryName = '';
        return $countryName;
    }
}

/**
 * 异位或加密解密函数
 * @param $value - 需要加密的值
 * @param int $type - 加密或解密(0:加密,1:解密)
 * @return int|mixed - 返回加密或解密后的字符串结果
 */
if (! function_exists('enctyption')) {
    function enctyption($value, $type = 0)
    {
        //获取用户配置的key,并md5加密
        $key = md5(config('app.encrypt_key'));
        if (!$type) {
            //加密
            $value = $value ^ $key;
            return str_replace('=', '', base64_encode($value));
        }
        //解密
        return base64_decode($value) ^ $key;
    }
}


if (! function_exists('sendSMS')) {
    function sendSMS($mobile, $msg) {
        $ch = curl_init();
        /***在这里需要注意的是，要提交的数据不能是二维数组或者更高
         *例如array('name'=>serialize(array('tank','zhang')),'sex'=>1,'birth'=>'20101010')
         *例如array('name'=>array('tank','zhang'),'sex'=>1,'birth'=>'20101010')这样会报错的*/
        $data = [
            'Id'        => '2675',
            'Name'      => '聚品测试',
            'Psw'       => '123456',
            //            'Message'   => urlencode('【微开云】 验证码：' . $smsParam['code'] . ' 3分钟内有效'),
            'Message'   => urlencode('【微开云】 ' . $msg),
            'Phone'     => $mobile,
            'Timestamp' => 0,
        ];

        $curl_string = '';
        foreach($data as $key => $value) {
            $curl_string .= $key . '=' . $value . '&';
        }

        curl_setopt($ch, CURLOPT_URL, 'http://124.172.234.157:8180/service.asmx/SendMessage' . 'Str?' . substr($curl_string, 0, -1));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        //        curl_setopt($ch, CURLOPT_POST, 1);
        //        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        //执行命令
        $data = curl_exec($ch);
        //关闭URL请求
        curl_close($ch);
        //显示获得的数据
        //        print_r($data);

        $code    = explode(':', explode(',', $data)[0])[1];
        $message = explode(':', explode(',', $data)[1])[1];
        //短信发送成功返回True，失败返回false
        if($data && $code == '1') {
            return ['status' => 1, 'msg' => $message];
        } else {
            return ['status' => $code, 'msg' => $message];
        }
    }
}


/**
 * 写记录
 * @param String/Array $params 要记录的数据
 * @param String $file 文件名.该记录会保存到 data 目录下
 * @param Int $fsize 文件大小M为单位.默认为1M
 * @param bool $only 只写一个文件
 * @return null
 */
if (! function_exists('writeDebug')){
    function writeDebug($params, $filename = 'debug', $fsize = 1, $only = false)
    {
        is_scalar($params) or ($params = var_export($params, true)); //是简单数据
        if (!$params) {
            return false;
        }
        clearstatcache();

        $dir = TEMP_PATH.date('Ym');
        if(!is_dir($dir))
        {

            @mkdir($dir,0777); //创建文件夹
            @chmod($dir, 0777);
        }
        if (!$only) {

            $date = date('Ymd');
            $file = $dir . DIRECTORY_SEPARATOR.$filename . $date . '.log';
            $size = file_exists($file) ? @filesize($file) : 0;
            $flag = $size < max(1024, $fsize) * 1024 * 1024; //标志是否附加文件.文件控制在1024M大小
            if (!$flag) {
                rename($file, $dir . DIRECTORY_SEPARATOR.$filename . $date . '-' . time() . ".log");
            }
        } else {
            $flag = true;
            $file = $dir . $filename . '.log';
            $size = file_exists($file) ? @filesize($file) : 0;
        }
        $prefix = '';
        ($size == 0) && $prefix = <<<EOD
＃LOG \n
EOD;
        @file_put_contents($file, $prefix . $params . "\n", $flag ? FILE_APPEND : null);
    }
}



/*
 *  邀请码生成
 */
if (! function_exists('create_invite_code')) {
    function create_invite_code($user_id)
    {
        static $source_string = 'E5FCDG3HQA4B1NOPIJ2RSTUV67MWX89KLYZ';
        $num = $user_id;
        $code = '';
        $i = 0;
        while ($i < 6) {
            $mod = $num % 35;
            $num = ($num - $mod) / 35;
            $code = $source_string[$mod] . $code;
            $i++;
        }
        if (empty($code[3]))
            $code = str_pad($code, 4, '0', STR_PAD_LEFT);
        return $code;
    }
}

/*
 *  邀请码解密
 */
if (! function_exists('invite_code_decode')) {
    function invite_code_decode($code)
    {
        static $source_string = 'E5FCDG3HQA4B1NOPIJ2RSTUV67MWX89KLYZ';
        if (strrpos($code, '0') !== false)
            $code = substr($code, strrpos($code, '0') + 1);
        $len = strlen($code);
        $code = strrev($code);
        $num = 0;
        for ($i = 0; $i < $len; $i++) {
            $num += strpos($source_string, $code[$i]) * pow(35, $i);
        }
        return $num;
    }
}

/*
 * 随机生成拼凑用户名字符串 （手机号码注册用到）
 */
if (! function_exists('getRandomString')) {
    function getRandomString($len, $chars = null)
    {
        if (is_null($chars))
        {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        }
        mt_srand(10000000 * (double)microtime());
        for ($i = 0, $str = '', $lc = strlen($chars) - 1; $i < $len; $i++)
        {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }
}


/**
 * 发送邮件方法
 * @param string $to：接收者邮箱地址
 * @param string $title：邮件的标题
 * @param string $content：邮件内容
 * @return boolean  true:发送成功 false:发送失败
 */
if (! function_exists('sendMail')) {
    function sendMail($to, $title, $content)
    {
        $email_smtp = config('email.mail_smtp_host');
        $email_username = config('email.mail_smtp_user');
        $email_password = config('email.mail_smtp_pass');
        $email_from_name =config('email.mail_smtp_name');
        $arr = ['1' => 'tls', '2' => 'ssl'];
        $email_smtp_secure = $arr[config('email.mail_verify_type')];
        $email_port = config('email.mail_smtp_port');
        if (empty($email_smtp) || empty($email_username) || empty($email_password) || empty($email_from_name)) {
            writeDebug("[" . date('Y-m-d H:i:s') . "] (Email) $to 发送配置不完整,请查看 跟目录下的extra/email.php 配置文件", 'sendEmail');
            return false;
        }
        //实例化PHPMailer核心类
        $phpmailer = new \PHPMailer\PHPMailer\PHPMailer();
        // 设置PHPMailer使用SMTP服务器发送Email
        $phpmailer->IsSMTP();
        // 设置设置smtp_secure
        $phpmailer->SMTPSecure = $email_smtp_secure;
        // 设置port
        $phpmailer->Port = $email_port;
        // 设置为html格式
        $phpmailer->IsHTML(true);
        // 设置邮件的字符编码'
        $phpmailer->CharSet = 'UTF-8';
        // 设置SMTP服务器。
        $phpmailer->Host = $email_smtp;
        // 设置为"需要验证"
        $phpmailer->SMTPAuth = true;
        // 设置用户名
        $phpmailer->Username = $email_username;
        // 设置密码
        $phpmailer->Password = $email_password;
        // 设置邮件头的From字段。
        $phpmailer->From = $email_username;
        // 设置发件人名字
        $phpmailer->FromName = $email_from_name;
        // 添加收件人地址，可以多次使用来添加多个收件人
        if (is_array($to)) {
            foreach ($to as $addressv) {
                $phpmailer->AddAddress($addressv);
            }
        } else {
            $phpmailer->AddAddress($to);
        }
        // 设置邮件标题
        $phpmailer->Subject = $title;
        // 设置邮件正文
        $phpmailer->Body = $content;
        // 发送邮件。
        if ($e = $phpmailer->Send()) {
            writeDebug("[" . date('Y-m-d H:i:s') . "] (Email) $to SUCCESS", 'sendEmail');
            return true;
        } else {

            $phpmailererror = $phpmailer->ErrorInfo;
            writeDebug("[" . date('Y-m-d H:i:s') . "] (Email) $to " . $phpmailererror, 'sendEmail');
            return false;
        }
    }
}

/**
 * @param $arr
 * @param $key_name
 * @return array
 * 将数据库中查出的列表以指定的 id 作为数组的键名
 */
if (! function_exists('convert_arr_key'))
{
    function convert_arr_key($arr, $key_name)
    {
        $arr2 = array();
        foreach($arr as $key => $val){
            $arr2[$val[$key_name]] = $val;
        }
        return $arr2;
    }

}

/**
 * [递归组装数据]
 * @param array $arr 要操作的数据数组
 * @param string $field 父级字段
 * @param int $parent_id  父级ID
 * @param int $level 层级
 * @return array
 */
if (! function_exists('get_tree'))
{
    function get_tree($arr,$field="parent_id",$parent_id=0,$level=0)
    {
        static $list=[];
        foreach($arr as $item){
            if($item[$field]==$parent_id){
                $item['level']=$level;
                $list[]=$item;
                get_tree($arr,$field,$item['id'],$level+1);
            }
        }
        return $list;
    }

}

//递归获取所有的子级ID
if (! function_exists('get_all_child'))
{
    function get_all_child($array,$id){
        $arr = array();
        foreach($array as $v){
            if($v['parent_id'] == $id){
                $arr[] = $v['id'];
                $arr = array_merge($arr,get_all_child($array,$v['id']));
            };
        };
        return $arr;
    }
}

if ( ! function_exists('getFirstCharter'))
{
    //php获取中文字符拼音首字母
    function getFirstCharter($str){
        if(empty($str)){return '';}
        $s0 = mb_substr($str,0,1,'utf-8'); //特殊处理
        if ( $s0 =='亳' ) {
            return 'B';
        }elseif ( $s0 =='衢' ) {
            return 'Q';
        }elseif ( $s0 =='泸' || $s0=='漯' ) {
            return 'L';
        }elseif ( $s0 =='濮' ) {
            return 'P';
        }elseif ($s0 == '儋'){
            return 'D';
        }

        $fchar=ord($str{0});
        if($fchar>=ord('A')&&$fchar<=ord('z')) return strtoupper($str{0});
        $s1=iconv('UTF-8','gb2312',$str);
        $s2=iconv('gb2312','UTF-8',$s1);
        $s=$s2==$str?$s1:$str;
        $asc=ord($s{0})*256+ord($s{1})-65536;
        if($asc>=-20319&&$asc<=-20284) return 'A';
        if($asc>=-20283&&$asc<=-19776) return 'B';
        if($asc>=-19775&&$asc<=-19219) return 'C';
        if($asc>=-19218&&$asc<=-18711) return 'D';
        if($asc>=-18710&&$asc<=-18527) return 'E';
        if($asc>=-18526&&$asc<=-18240) return 'F';
        if($asc>=-18239&&$asc<=-17923) return 'G';
        if($asc>=-17922&&$asc<=-17418) return 'H';
        if($asc>=-17417&&$asc<=-16475) return 'J';
        if($asc>=-16474&&$asc<=-16213) return 'K';
        if($asc>=-16212&&$asc<=-15641) return 'L';
        if($asc>=-15640&&$asc<=-15166) return 'M';
        if($asc>=-15165&&$asc<=-14923) return 'N';
        if($asc>=-14922&&$asc<=-14915) return 'O';
        if($asc>=-14914&&$asc<=-14631) return 'P';
        if($asc>=-14630&&$asc<=-14150) return 'Q';
        if($asc>=-14149&&$asc<=-14091) return 'R';
        if($asc>=-14090&&$asc<=-13319) return 'S';
        if($asc>=-13318&&$asc<=-12839) return 'T';
        if($asc>=-12838&&$asc<=-12557) return 'W';
        if($asc>=-12556&&$asc<=-11848) return 'X';
        if($asc>=-11847&&$asc<=-11056) return 'Y';
        if($asc>=-11055&&$asc<=-10247) return 'Z';
        return null;
    }
}

/**
 * @param $words
 * @return string
 * 分词
 */
if ( ! function_exists('decorateSearch_pre'))
{
    function decorateSearch_pre($words)
    {
        $tempArr = str_split($words);
        $wordArr = array();
        $temp = '';
        $count = 0;
        $chineseLen = 3;
        foreach($tempArr as $word){
            if ($count == $chineseLen){
                $wordArr[] = $temp;
                $temp = '';
                $count = 0;
            }

            // 中文
            if(ord($word) > 127){
                $temp .= $word;
                ++$count;
            }else if (ord($word) != 32){
                $wordArr[] = $word;
            }
        }

        if ($count == $chineseLen){
            $wordArr[] = $temp;
        }
        return  '%'.implode($wordArr, '%').'%';
    }
}

if ( ! function_exists('queryLogistics'))
{
    /**
     * @param $data
     * @return array
     * 当前物流信息
     */
    function queryLogistics($data) {
        $temp = mt_rand(100000000000000000, 999999999999999999)/1000000000000000000;
        $url = 'http://www.kuaidi100.com/query?id=1&type='.$data['type'].'&postid='.$data['courier_number'].'&temp='.$temp.'&phone=';
        $res = json_decode(file_get_contents($url), true);
        return $res;
        $logistics_info = array();
        if(!empty($res) && $res['status'] == 200 && !empty($res['data'][0])){
            $res_logistics = $res['data'][0];
            $logistics_info['date'] = date('m-d', strtotime($res['data'][0]['time']));
            $logistics_info['context'] = $res['data'][0]['context'];
        }
        return $logistics_info;
    }
}

if ( ! function_exists('getCurrentNextTime'))
{
    /**
     * @return array
     * date 时间戳
     * 获取当天0-24点时间，下一天0-24
     */
    function getCurrentNextTime($date = '') {
        $data = [];
        if($date && (!empty($date))) $time = $date;
        else $time = time();
        $data['todayStart'] = strtotime( date('Y-m-d', $time) );
        $data['todayEnd'] = strtotime( date('Y-m-d', $time).' 23:59:59' );
        $data['nextDayStart'] = $data['todayStart']+86400;
        $data['nextDayEnd'] = $data['nextDayStart']+86400;
        $data['yesterday'] = $data['todayStart']-86400;  //上1天
        $data['sevenDay'] = $data['todayStart']-86400*7;  //上7天
        $data['thirtyDay'] = $data['todayStart']-86400*30;  //上30天
        $data['startDay'] = date('Y-m-01', $time);  //本月第一天
        $data['endDay'] = strtotime("{$data['startDay']} +1 month -1 day")+86399; //本月最后一天
        $data['startDay'] = strtotime($data['startDay']);
        return $data;
    }
}

if ( ! function_exists('arrayMakeTree'))
{
    /**
     * 获取数组树形结构
     * @param array $arr
     * @param string $pk 自增id
     * @param string $pid pid
     * @param string $child child
     * @param int $root 节点
     * @author ljh
     * @return array
     */
    function arrayMakeTree($arr = [], $pk = 'id', $pid = 'parent_id', $child = '_child', $root = 0)
    {
        $tree = [];
        foreach ($arr as $key => $item){
            if($item[$pid] == $root){
                // 获取当前pid所有子类
                unset($arr[$key]);
                if(!empty($arr)){
                    $child = arrayMakeTree($arr, $pk, $pid, $child, $item[$pk]);
                    if(!empty($child)){
                        $item['_child'] = $child;
                    }
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }
}

if ( ! function_exists('getTimeDiff'))
{
    /**
     * 比较两个时间戳
     * @param $begin_time 开始时间戳
     * @param $end_time 结束时间戳
     * @author ljh
     * @return array
     */
    function getTimeDiff($begin_time, $end_time)
    {
        if ($begin_time < $end_time) {
            $starttime = $begin_time;
            $endtime = $end_time;
        } else {
            $starttime = $end_time;
            $endtime = $begin_time;
        }

        //计算天数
        $timediff = $endtime - $starttime;
        $days = intval($timediff / 86400);
        //计算小时数
        $remain = $timediff % 86400;
        $hours = intval($remain / 3600);
        //计算分钟数
        $remain = $remain % 3600;
        $mins = intval($remain / 60);
        //计算秒数
        $secs = $remain % 60;
        $res = array("day" => $days, "hour" => $hours, "min" => $mins, "sec" => $secs);
        return $res;
    }
}

/**
 * 生成劵码code
 */
if (!function_exists('createCouponCode')) {
    function createCouponCode() {
        $redis = getRedisInstance();
        $code = mt_rand(100000000, 999999999);
        $rs = $redis->sAdd('orderCode', $code);
        if ($rs == 0) createCouponCode();
        return $code;
    }
}


/**
 * 生成用户邀请码
 */
if (!function_exists('createUserCode')) {
    function createUserCode() {
        $redis = getRedisInstance();
        $code = mt_rand(100000, 999999);
        $rs = $redis->sAdd('userCode', $code);
        if ($rs == 0) createUserCode();
        return $code;
    }
}

/**
 * 返回数组里面的其中一个key的value值作为索引的索引数组
 */
if (!function_exists('getArrayValueAsIndex')) {
    function getArrayValueAsIndex($dataArray, $keyName, $multi = false)
    {
        $dataArray = array_values($dataArray);
        if (empty($dataArray) || !isset($dataArray[0][$keyName])) {
            return array();
        }

        $indexArray = array();
        $recordArray = array();
        if ($multi === false) {
            foreach ($dataArray as $dataItem) {
                if (empty($recordArray[$dataItem[$keyName]])) {
                    $indexArray[$dataItem[$keyName]] = $dataItem;
                    $recordArray[$dataItem[$keyName]] = 1;
                } else if ($recordArray[$dataItem[$keyName]] == 1) {
                    $indexArray[$dataItem[$keyName]] = array(
                        $indexArray[$dataItem[$keyName]],
                        $dataItem
                    );
                    $recordArray[$dataItem[$keyName]] = 2;
                } else {
                    array_push($indexArray[$dataItem[$keyName]], $dataItem);
                }
            }
        } else {
            foreach ($dataArray as $dataItem) {
                if (empty($indexArray[$dataItem[$keyName]])) {
                    $indexArray[$dataItem[$keyName]] = array();
                }
                array_push($indexArray[$dataItem[$keyName]], $dataItem);
            }
        }

        unset($dataArray);
        unset($recordArray);
        return $indexArray;
    }
}

//笛卡尔数组乘积
if (!function_exists('cartesian')) {
    //笛卡尔积
    function cartesian($arr) {
        $result = array_shift($arr);
        while ($arr2 = array_shift($arr)) {
            $arr1 = $result;
            $result = array();
            foreach ($arr1 as $v) {
                foreach ($arr2 as $v2) {
                    if (!is_array($v)) $v = array($v);
                    if (!is_array($v2)) $v2 = array($v2);
                    $result[] = array_merge_recursive($v,$v2);
                }
            }
        }
        return $result;
    }
}

//base64图片正则提取
if (!function_exists('pregBase64')) {
    function pregBase64($str) {
        if (!is_string($str)) return [];
        $preg = '/data:image\/[a-z].*?;base64,([^"]+)"/';
        preg_match_all($preg, $str, $imgArr);
        return $imgArr[0];
    }
}

if ( ! function_exists('getChar'))
{
    /**
     * @param $len
     * @return array
     * 获取excel字母列
     */
    function getChar($len) {
        $st = 65; //A
        $charArr = array();
        for ($i = 0; $i <= $len-1; $i++) {
            $charArr[] = chr($st+$i);
        }
        return $charArr;
    }
}

// 生成订单号
if ( ! function_exists('getOrderNo')) {
    function getOrderNo(){
        return date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8).rand(1000,9999);
    }
}

if ( ! function_exists('xmlToArray')) {
    function xmlToArray($xml){
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlstring), true);
        return $val;
    }
}

if ( ! function_exists('is_cli')) {
    function is_cli(){
        return (PHP_SAPI === 'cli');
    }
}