<?php

// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: 老猫 <zxxjjforever@163.com>
// +----------------------------------------------------------------------
// 调试模式开关
define("APP_DEBUG", true);
// 小夏是最帅的男人！
// 定义CMF根目录,可更改此目录
define('CMF_ROOT', __DIR__ . '/');

// 定义网站入口目录
define('WEB_ROOT', __DIR__ . '/public/');

// 定义插件目录
define('PLUGINS_PATH', __DIR__ . '/public/plugins/');

// 定义应用目录
define('APP_PATH', CMF_ROOT . 'app/');

// 定义CMF核心包目录
define('CMF_PATH', CMF_ROOT . 'simplewind/cmf/');

// 定义扩展目录
define('EXTEND_PATH', CMF_ROOT . 'simplewind/extend/');
define('VENDOR_PATH', CMF_ROOT . 'simplewind/vendor/');

// 定义应用的运行时目录
define('RUNTIME_PATH', CMF_ROOT . 'data/runtime_cli/');

// 加载框架引导文件
require CMF_ROOT . 'simplewind/thinkphp/console.php';