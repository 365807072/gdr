<?php
namespace app\validate;

use think\Request;
use think\Validate;

class BaseValidate extends Validate
{
    public function goCheck($scene='',$data='')
    {

        $request = Request::instance();
        $params = $request->param();
        if ($data) $params = $data;
        $result = $this->scene($scene)->check($params);
        if (!$result) return returnMsg(10000,$this->getError()); //通用参数错误
        else return true;

    }

    /**
     * @param array $arrays 通常传入request.post变量数组
     * @param string $sceneName 场景值
     * @return array array 按照规则key过滤后的变量数组
     */
    public function getDataByScene($arrays, $sceneName)
    {
        $newArray = [];
        foreach ($this->scene[$sceneName] as $key => $value) {
            if (!isset($arrays[$value]))
                continue;
            $newArray[$value] = $arrays[$value];
        }
        return $newArray;
    }

    /**
     * [判断是否为正整数或大于等于0的整数]
     * @param $value [需要判断的值]
     * @param $rule [ =0表示验证大于等于0的整数，>0时验证大于0的整数 ]；
     * 为1表示判断是否为正整数]
     * @return bool
     */
    protected function isPositiveInteger($value, $rule)
    {
        if (!is_numeric($value) || !is_int($value + 0)) {
            return false;
        }
        if ($rule > 0) {
            if (($value + 0) > 0) {
                return true;
            }
        } else {
            if (($value + 0) >= 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * [判断是否为空]
     * @param  [type]  $value [description]
     * @return boolean        [description]
     */
    protected function isNotEmpty($value)
    {
        $value = trim($value);
        if (empty($value)) {
            return false;
        } else {
            return true;
        }
    }


}
