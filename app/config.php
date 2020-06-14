<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2019 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
if (file_exists(CMF_DATA . "conf/config.php")) {
    $runtimeConfig = include CMF_DATA . "conf/config.php";
} else {
    $runtimeConfig = [];
}
$configs = [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------


    // 应用命名空间
    'app_namespace'          => 'app',
    // 应用模式状态
    'app_status'             => APP_DEBUG ? 'debug' : 'release',
    // 是否支持多模块
    'app_multi_module'       => true,
    // 入口自动绑定模块
    'auto_bind_module'       => false,
    // 注册的根命名空间
    'root_namespace'         => ['cmf' => CMF_PATH, 'plugins' => WEB_ROOT . 'plugins/', 'themes' => WEB_ROOT . 'themes/', 'api' => CMF_ROOT . 'api/'],
    // 扩展函数文件
    'extra_file_list'        => [CMF_PATH . 'common' . EXT, THINK_PATH . 'helper' . EXT],
    // 默认输出类型
    'default_return_type'    => 'html',
    // 默认AJAX 数据返回格式,可选json xml ...
    'default_ajax_return'    => 'json',
    // 默认JSONP格式返回的处理方法
    'default_jsonp_handler'  => 'jsonpReturn',
    // 默认JSONP处理方法
    'var_jsonp_handler'      => 'callback',
    // 默认时区
    'default_timezone'       => 'PRC',
    // 是否开启多语言
    'lang_switch_on'         => false,
    // 默认全局过滤方法 用逗号分隔多个
    'default_filter'         => 'htmlspecialchars',
    // 默认语言
    'default_lang'           => 'zh-cn',
    // 应用类库后缀
    'class_suffix'           => true,
    // 控制器类后缀
    'controller_suffix'      => true,
    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------
    // 默认模块名
    'default_module'         => 'portal',
    // 禁止访问模块
    'deny_module_list'       => ['common'],
    // 默认控制器名
    'default_controller'     => 'Index',
    // 默认操作名
    'default_action'         => 'index',
    // 默认验证器
    'default_validate'       => '',
    // 默认的空控制器名
    'empty_controller'       => 'Error',
    // 自动搜索控制器
    'controller_auto_search' => false,
    // +----------------------------------------------------------------------
    // | URL设置
    // +----------------------------------------------------------------------
    'pathinfo_depr'          => '/',
    // URL伪静态后缀
    'url_html_suffix'        => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param'       => false,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type'         => 0,
    // 是否开启路由
    'url_route_on'           => true,
    // 路由配置文件（支持配置多个）
    'route_config_file'      => ['route'],
    // 是否强制使用路由
    'url_route_must'         => false,
    // 域名部署
    'url_domain_deploy'      => false,
    // 域名根，如thinkphp.cn
    'url_domain_root'        => '',
    // 是否自动转换URL中的控制器和操作名
    'url_convert'            => true,
    // 默认的访问控制器层
    'url_controller_layer'   => 'controller',
    // 表单请求类型伪装变量
    'var_method'             => '_method',
    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------
    'template'               => [
        // 模板引擎类型 支持 php think 支持扩展
        'type'                    => 'Think',
        // 视图根目录
        'view_base'               => '',
        // 模板路径
        'view_path'               => '',
        // 模板后缀
        'view_suffix'             => 'html',
        // 模板文件名分隔符
        'view_depr'               => DS,
        // 模板引擎普通标签开始标记
        'tpl_begin'               => '{',
        // 模板引擎普通标签结束标记
        'tpl_end'                 => '}',
        // 标签库标签开始标记
        'taglib_begin'            => '<',
        // 标签库标签结束标记
        'taglib_end'              => '>',
        'taglib_build_in'         => 'cmf\\lib\\taglib\\Cmf,cx',
        'tpl_cache'               => APP_DEBUG ? false : true,
        'tpl_deny_php'            => false,
        // +----------------------------------------------------------------------
        // | CMF模板 设置
        // +----------------------------------------------------------------------
        'cmf_theme_path'          => 'themes/',
        'cmf_default_theme'       => 'simpleboot3',
        'cmf_admin_theme_path'    => 'themes/',
        'cmf_admin_default_theme' => 'admin_simpleboot3',
    ],
    // 视图输出字符串内容替换
    'view_replace_str'       => [],
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------
    // 异常页面的模板文件
    'exception_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_exception.tpl',
    // 错误显示信息,非调试模式有效
    'error_message'          => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'         => false,
    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle'       => '',
    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------
    'log'                    => [
        // 日志记录方式，内置 file socket 支持扩展
        'type'  => 'File',
        // 日志保存目录
        'path'  => LOG_PATH,
        // 日志记录级别
        'level' => [],
    ],
    // +----------------------------------------------------------------------
    // | Trace设置 开启 app_trace 后 有效
    // +----------------------------------------------------------------------
    'trace'                  => [
        // 内置Html Console 支持扩展
        'type' => 'Html',
    ],
    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------
    'cache'                  => [
        // 驱动方式
        'type'   => 'File',
        // 缓存保存目录
        'path'   => CACHE_PATH,
        // 缓存前缀
        'prefix' => '',
        // 缓存有效期 0表示永久缓存
        'expire' => 0,
    ],
    // +----------------------------------------------------------------------
    // | 会话设置
    // +----------------------------------------------------------------------
    'session'                => [
        'id'             => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',
        // SESSION 前缀
        'prefix'         => 'think',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
    ],
    // +----------------------------------------------------------------------
    // | Cookie设置
    // +----------------------------------------------------------------------
    'cookie'                 => [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 是否使用 setcookie
        'setcookie' => true,
    ],
    // +----------------------------------------------------------------------
    // | 数据库设置
    // +----------------------------------------------------------------------
    'database'               => [
        // 数据库调试模式
        'debug'           => APP_DEBUG,
        // 数据集返回类型
        'resultset_type'  => 'collection',
        // 自动写入时间戳字段
        'auto_timestamp'  => false,
        // 时间字段取出后的默认时间格式
        'datetime_format' => false,
        // 是否需要进行SQL性能分析
        'sql_explain'     => APP_DEBUG,
    ],
    //分页配置
    'paginate'               => [
        'type'      => '\\cmf\\paginator\\Bootstrap',
        'var_page'  => 'page',
        'list_rows' => 15,
    ],
    'queue'                  => [
        'connector' => '\\cmf\\queue\\connector\\Database'
    ],
    'aliOss'                 => [
        'upload'    =>  [
            'upload_limit_size' => 8*1024*1024
        ]
    ],
    'pay'                    => [
        'ali_pay'       =>  (object)[
            'appID'             =>  2018080560948213,
            'sign_type'         =>  'RSA2',   //签名方式,支持：RSA RSA2
            'ali_privateKey'    =>  'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCRv2ThL0S0x5/DVIxdDV64LDFOHel+c7N8p5PnK9mk7B4PtasEZCT5SM9dCcfHwhOzZmv8g5xBZJuyMmGGgk5EJTohUicMQmkDib/kSn9FMLJ6cTgXjDzj9lKvFdtzPvXQo8cj+1eovB5Nh5m8GH4GPL2J0+o4B5qDGANMXu9/XoyE1rp1PGih9GDuK3+C9HVubNNMNNOQ28ty5WkipEbDuFuQ0Wmadkc6/IQQfMUebXk87xiNhGVJ4Oh2lbHNlSjYicfwEF1UIZR6r67ZMoaSHYF0GC3bwV4eBNh3h/sld603IIO4hRWYwjLf3GVJj7XtM+REvsIi9THYePc4i9zlAgMBAAECggEANOilB3YwW+wn3DHiLpl+7B7m2zPSopv/6V2wPHhXrx4kH5HUfmiYX4KF/IFja+nRWwvwYZ+pZrfOKZdOfhTpsqVM//GhyTqGgslLxuUgZI60C+zz7uBy7lJqW7L0klalx9LcpPxGKzP3UC/MqFK/1SXRrKooWaSw+6wI7n18NeEgz563HKmGnXIsmGJjlIUeoarQiDiuyjCKJrncpE1TAMe5aZ7LXSuGdLCh6NI/sK8nnej1x5hLD0ibM/neROtjEluJXHb+E6/maWzsbJYK0Ld3u75WyVa7UFNm3ZPDi81KBpH0oUJED2nWzspxkp3RDAAA6PKtmJHyiVGnO3HYVQKBgQDXbCYBGrQSRkYUszYDfDr+5wvPowsETLjaNXlrGLjBGVhzkzqlarMJPjdJgHQwLfSyUcBhHVDQbPujgN7fXb0VHlAyedaPpEklj+42qhPAeZZ6BNq3XwB59MnrLwitOSBrVny/7llRnNQ3Kuo42Pqn6YcE9Y3t5MQySwTsZIN+QwKBgQCtM3ecYfYMDt1lPf5XNIuchqc5PH30o2fAUgce42423b9hg7b46au5V4tBlZLLE1OIOmIkyjuUhemOugaNwXcVSlqDezF62KNk8wxigddHD7uyO0yLDcl10taVmN7YKpdkGNSgIKc3Q27xo5u7SzfR2udYWnCUE3/I2A2LOE7JtwKBgH31joM2yfOlg3S3FO4UxaomDpHqE5cABVYRzl0Ap4bA+ycY/0GZkfXlmw4vPcCuHlXl10bxjT9RehGwWKDifpzKjLn24645sUt1TSRJbiMs9P+Dtx9hPTYSG76YUcp3jK02dNsE0tUVjkBzp2B1/poPwhL0QXjIOq/tjYNrgjdhAoGBAJmYxd8qRLAm0pIBKzgcwh7ajxvEGJNGAjFczTHIUSpY9mq9Cxus5fwktbtAsQBksDRkSjsEGVg6dYh8UmmOA1QfLyvgqV2fFy/kFryZFtfPDQ4fZl7+qAVUJ7Zc7mrXQpCjMJScwI3SwGWMSQQMzYDOV1FUSvgWXsE3ULOBCOX7AoGAGIQMzzxrnNt/cagqcyUCcJeS+sC2+2HI3iZTMc/TnKBcPmoG6DsN4J3DsN+t3Zc1XjmJyEwxf/Wx8WiQTjQL9Hc0C3iBjwW8xTyMSGUgh/DKqi7GWQoR86gQxNomtVQN1dSmy0dpLDMty10LljArTKBaEYCPrraveHqg5k7wZ/k=',
            'appPrivateKeyFile' =>  '',
            'ali_payRsaPublicKey'=>  'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAh/T7XLALGbOV4FlTUF38NlzYGEasM377a8oGuyGxtBxVnUKGvXxTopuCv44xgWpGHU+Y8xKukiOQsYyGnTgqDJqjxTb9qLwVYiDdUTXqxDOigGmdxsZpZNwcDiDHhFFkHhyBnau01KMxY1VuWgIP7E++6MfK4sep27vphAtvteGb0liFS3NuJx4KrkDj/DoIMqeeiaB7iBPyExdi5EQsjLKhcZAXf1QIg1V3xXw6MJnRQqtecWnDXoWVCA7paAn8FRfSRoWWs0gp4iwFu+yJ8khECuxLDmWMo2CA8O5Oq4AB3UPbKA9C9dLwsmw7/+6V+KwQplYA8tAcu0xNfjaIyQIDAQAB',
            'appPublicKeyFile'  =>  '',
            'isUseAES'          =>  true,
            'aesKey'            =>  '8DTIuKsGvP+I/QK4mcMY5g==',
            'version'           =>  '1.0',
//            'partner'           =>  1231412,   //收款支付宝用户ID(2088开头)
            'charset'           =>  'utf-8',
            'apiDomain'         =>  '',
        ],
        'wx_pay'    =>  [
            'app_id'            =>  'wxaf7d5b49a44cb52e',
            'mch_id'            =>  '1542338201',
            'key'               =>  '388513c8b8ecde9836125fcee65f66d2',
//            'cert_path'         =>  file_get_contents( dirname( __DIR__ ).'/app/third/pem/cacert.pem'),
//            'key_path'          =>  file_get_contents( dirname( __DIR__ ).'/app/third/pem/apiclient_key.pem'),
            'cert_path'         =>  dirname( __DIR__ ).'/app/third/pem/cacert.pem',
            'key_path'          =>  dirname( __DIR__ ).'/app/third/pem/apiclient_key.pem',
            'sign_type'         =>  'MD5',
        ],
    ]
];
return array_replace_recursive($configs, $runtimeConfig);