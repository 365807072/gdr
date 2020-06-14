<?php
/**
 * BaseModel.php
 *
 * @author Crazypeak create
 * @since 1.0, 2019/6/25 19:00
 */

namespace app\member\model;

use think\Db;

Db::setPrefix('cmf_');
Db::startTrans();

class BaseModel {
    final static function Db(string $table_name = '') {
        return Db::table('cmf_' . $table_name);
    }

    final static function getTableColumn($table_name = '', $where = [], $field = [], $key = '') {
        return self::Db($table_name)->where($where)->column(implode(',', $field), $key);
    }

    final static function getTableOne($table_name = '', $where = [], $field = [],$order = []) {
        return self::Db($table_name)->where($where)->field($field)->order($order)->find();
    }

    final static function getTableSelect($table_name = '', $where = [], $field = [], $order = [], $limit = 20) {
        return self::Db($table_name)->where($where)->field($field)->order($order)->limit($limit)->select();
    }

    static function insertData(array $data, string $sql_name) {

//        $data['creator']     = $data['modifier'] = defined('USER_ID') ? USER_ID : session('admin_id');
        $data['create_time'] = $data['update_time'] = time();

        return self::transactionSql($sql_name, $data);
        //        return $sql ? ['code' => 1, 'id' => $sql] : ['code' => 0, 'msg' => '数据库错误'];
    }

    static function updateData(array $data, string $sql_name) {
        //兼容更新函数12-5，主键不唯一、失效
        if (!isset($data['id']) || empty($data['id'])) {
            unset($data['id']);
            return self::insertData($data, $sql_name);
        }

        $ids = $data['id'];
        is_string($ids) && $ids = explode(',', $ids);
        if (!$ids || empty($ids))
            return ['code' => -4001, 'msg' => '参数错误'];

        unset($data['id']);

//        $data['modifier']    = defined('USER_ID') ? USER_ID : session('admin_id');
        $data['update_time'] = time();

        return self::transactionSql($sql_name, $data, ['id' => ['IN', $ids]]);
    }

    static function deleteData($ids, string $sql_name) {
        if (!$ids && empty($ids))
            return ['code' => -11, 'msg' => '参数错误'];

        is_string($ids) && $ids = explode(',', $ids);

//        $data['modifier']          = defined('USER_ID') ? USER_ID : session('admin_id');
        $data['update_time'] = time();
//        $data['logically_deleted'] = 1;

        return self::transactionSql($sql_name, $data, ['id' => ['IN', $ids]]);
    }

    static function transactionSql($sql_name, $data, $where = FALSE) {// 启动事务
        try {
            $sql_name = substr($sql_name, 0, 1) == '.' ? substr($sql_name, 1) : (config('database.prefix') . $sql_name);

            $sql = $where ?
                Db::setTable($sql_name)->where($where)->update($data) :
                Db::setTable($sql_name)->insertGetId($data);

            return ['code' => 1, 'id' => $sql, 'msg' => '数据库成功'];
        }
        catch (\Exception $e) {
            //                                    print_r('<pre>');
            //                                    print_r($e);

            return [
                'code' => 0,
//                'code' => '-80' . $e->getCode(),
                'msg'  => $e->getMessage(),
                //                'msg'=>'数据库错误',
            ];
        }
    }

    final static function commitSql() {
        //提交事务
        Db::commit();
    }

    final static function rollbackSql() {
        //提交事务
        Db::rollback();
    }


}