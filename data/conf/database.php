<?php
/**
 * 配置文件
 */
use think\Env;
return [
    // 数据库类型
    'type'     => 'mysql',
    // 服务器地址
    'hostname' => Env::get('DB_HOST','182.92.201.22'),
    // 数据库名
    'database' => Env::get('DB_DATABASE','gdr'),
    // 用户名
    'username' => Env::get('DB_USER','gdr'),
    // 密码
    'password' => Env::get('DB_PASSWD','KfH8fAy7MLpabD5A'),
    // 端口
    'hostport' => Env::get('DB_PORT','3306'),
    // 数据库表前缀
    'prefix'   =>Env::get('TABLE_PREFIX','cmf_'),
    // 数据库编码默认采用utf8
    'charset'  => 'utf8mb4',
    "authcode" => 'jQwj4WjZYDjTO5zChr',
    //#COOKIE_PREFIX#
];
