<?php
namespace app\space\model;

use think\Db;
use think\Model;
use app\logic\AliOssLogic;

class SpaceModel extends Model
{
    public function get_space_list($map = [], $field = [], $page='1', $limit='10', $order = ['s.create_time' => 'desc'])
    {
        $data = Db::name('space s')->join('member_space ms', ['s.user_id = ms.user_id'])->where($map)->field($field)->paginate($limit, false, ['page' => $page])->toArray();
        return $data;
    }

    public function get_space($map = [], $field = [])
    {
        $data = Db::name('space')->where($map)->field($field)->find();
        return $data;
    }

    public function get_spaces($map = [], $field = [])
    {
        $data = Db::name('space')->where($map)->field($field)->select()->toArray();
        return $data;
    }

    public function get_space_detail_list($map = [], $field = [], $page='1', $limit='10', $order = ['sd.create_time'=>'asc'])
    {
        $data = Db::name('space_details sd')->join('space s', ['s.id = sd.space_id'])->where($map)->field($field)->order($order)->paginate($limit, false, ['page' => $page])->toArray();
        return $data;
    }

    public function get_space_detail($map = [], $field = [])
    {
        $data = Db::name('space_details')->where($map)->field($field)->find();
        return $data;
    }

    public function get_space_details($map = [], $field = [])
    {
        $data = Db::name('space_details')->where($map)->field($field)->select()->toArray();
        return $data;
    }

    public function get_user_space($map = [], $field = [])
    {
        $data = Db::name('member_space')->where($map)->field($field)->find();
        if(empty($data)){
            // 无则创建
            Db::name('member_space')->insert([
                'user_id' => $map['user_id'],
                'create_time' => time(),
                'update_time' => time()
            ]);
            $data = Db::name('member_space')->where($map)->field($field)->find();
        }
        $space = Db::name('space')->where([
            'user_id' => $data['user_id']
        ])->field([
            'count(*) as count',
            'IFNULL( SUM( CASE WHEN type = 0 THEN 1 ELSE 0 END ), 0) as processing_count',
            'IFNULL( SUM( CASE WHEN type = 1 THEN 1 ELSE 0 END ), 0) as over_count',
            'IFNULL( SUM( CASE WHEN type = 2 THEN 1 ELSE 0 END ), 0) as history_count',
            'IFNULL( SUM( CASE WHEN type = 0 AND is_remind = 1 THEN 1 ELSE 0 END ), 0) AS processing_remind',
            'IFNULL( SUM( CASE WHEN type = 1 AND is_remind = 1 THEN 1 ELSE 0 END ), 0) AS over_remind',
            'IFNULL( SUM( CASE WHEN type = 2 AND is_remind = 1 THEN 1 ELSE 0 END ), 0) AS history_remind'
        ])->find();
        $spaceList['count'] = $space['count'];
        foreach ($space as $key => $value){
            if($key === 'count') continue;
            $item = explode('_', $key);
            $spaceList[$item[0]][$item[1]] = $value;
            unset($item);
        }
        $data['spaceList'] = $spaceList;
        unset($data['user_id']);
        return $data?$data:[];
    }

    public function file_add_history($param)
    {
        $checkSpace = $this->check_user_space($param['user_id'], $param['used_space']);
        if($checkSpace !== true) return $checkSpace;

        $exist = Db::name('space')->where(['user_id'=>$param['user_id'], 'job_id'=>$param['space']['job_id'], 'type'=>2])->find();
        if(empty($exist)){
            $space_id = Db::name('space')->insertGetId([
                'user_id' => $param['user_id'],
                'job_id' => $param['space']['job_id'],
                'boss_id' => $param['space']['boss_id'],
                'boss_name' => $param['space']['boss_name'],
                'type' => 2,
                'is_remind' => 0,
                'create_time' => time(),
                'update_time' => time()
            ]);
            $exist = Db::name('space')->where(['id'=>$space_id])->find();
        }else{
            $space_id = $exist['id'];
        }

        try{
            Db::startTrans();

            $charList = 'http://';
            $preg = "/^http?:\\/\\/.+/";
            $pregs = "/^https?:\\/\\/.+/";
            if(preg_match($preg,$param['link'])){
                $charList = 'http://';
            }elseif (preg_match($pregs,$param['link'])){
                $charList = 'https://';
            }

            $aliLogic = new AliOssLogic();
            $link = $aliLogic->copyFileToAli($param['link'], '', $charList)[0];
            if(empty($link)) throw new \Exception('oss copy fail', 1000031);

            $result = Db::name('space_details')->insert([
                'space_id' => $space_id,
                'stage' => $param['stage'],
                'name' => $param['name'],
                'used_space' => $param['used_space'],
                'link' => $link,
                'remarks' => $param['remarks'],
                'create_time' => time(),
                'update_time' => time()
            ]);
            if(!$result) throw new \Exception('space_details table insert fail', 1002001);

            $res = Db::name('space')->where([
                'id' => $space_id
            ])->update([
                'is_remind' => 1,
                'update_time' => time(),
                'used_space' => ($exist['used_space'] + $param['used_space'])
            ]);
            if(false === $res) throw new \Exception('space table update fail', 1001001);

            $r = Db::name('space_details')->where([
                'id' => $param['id']
            ])->update([
                'is_addhistory' => 1,
                'update_time' => time()
            ]);
            if(false === $r) throw new \Exception('space_details table update fail', 1001201);

            $member_space = Db::name('member_space')->where(['user_id' => $exist['user_id']])->find();
            $rr = Db::name('member_space')->where([
                'user_id' => $exist['user_id']
            ])->update([
                'used_space' => ($member_space['used_space'] + $param['used_space']),
                'update_time' => time()
            ]);
            if(false === $rr) throw new \Exception('member_space table update fail', 102001);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    public function check_user_space($user_id, $checkSpace = 0)
    {
        $user = Db::name('member')->where(['id' => $user_id])->find();
        if(empty($user)) return returnMsg(-1, '用户不存在');

        $space = Db::name('member_space')->where(['user_id' => $user_id])->find();
        if(empty($space)){
            // 不存在则创建
            $result = Db::name('member_space')->insert([
                'user_id' => $user_id,
                'create_time' => time(),
                'update_time' => time()
            ]);
            if(!$result) return returnMsg(-10086, 'member_space insert fail');
            $space = Db::name('member_space')->where(['user_id' => $user_id])->find();
        }

        if(($space['used_space'] + $checkSpace) > $space['space']){
            return returnMsg(-1, '空间已满，请整理空间');
        }
        return true;
    }

    public function update_space($map, $data = [])
    {
        $res = Db::name('space')->where($map)->update($data);
        return $res;
    }

    public function delete_space_folder($param)
    {
        try{
            Db::startTrans();

            $space_id = array_unique(array_column($param, 'id'));
            $user_id = $param[0]['user_id'];
            $charList = 'http://';
            $preg = "/^http?:\\/\\/.+/";
            $pregs = "/^https?:\\/\\/.+/";

            $used_space = 0;
            foreach ($param as $k => $val){
                $used_space += $val['used_space'];
            }

            $detail = Db::name('space_details')->where(['space_id' => ['in',$space_id]])->select()->toArray();
            if(!empty($detail)){
                $arr = [];
                foreach($detail as $key => $value){
                    if(preg_match($preg,$value['link'])){
                        $charList = 'http://';
                    }elseif (preg_match($pregs,$value['link'])){
                        $charList = 'https://';
                    }
                    $arr[] = ltrim(trim($value['link'], ''), $charList);
                }

                $aliLogic = new AliOssLogic();
                $res = $aliLogic->delFileToAli($arr);
                if($res['code'] != 1) throw new \Exception('oss delete fail', 1006501);

                $result = Db::name('space_details')->where([
                    'space_id' => ['in',$space_id]
                ])->delete();
                if(!$result) throw new \Exception('space_details table delete fail', 100201);
            }

            $r = Db::name('space')->where([
                'id' => ['in',$space_id]
            ])->delete();
            if(!$r) throw new \Exception('space table delete fail', 106001);

            $member_space = Db::name('member_space')->where(['user_id' => $user_id])->find();
            $rr = Db::name('member_space')->where([
                'user_id' => $user_id
            ])->update([
                'used_space' => ( ($member_space['used_space'] - $used_space) > 0 ? ($member_space['used_space'] - $used_space) : 0 ),
                'update_time' => time()
            ]);
            if(false === $rr) throw new \Exception('member_space table update fail', 102001);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    public function delete_space_details($param)
    {
        try{
            Db::startTrans();

            $ids = array_column($param, 'id');
            $space_id = array_unique(array_column($param, 'space_id'))[0];
            $space = $param['space']; unset($param['space']);
            $charList = 'http://';
            $preg = "/^http?:\\/\\/.+/";
            $pregs = "/^https?:\\/\\/.+/";

            $arr = [];
            $used_space = 0;
            foreach($param as $key => $value){
                if(preg_match($preg,$value['link'])){
                    $charList = 'http://';
                }elseif (preg_match($pregs,$value['link'])){
                    $charList = 'https://';
                }

                $arr[] = ltrim(trim($value['link']), $charList);
                $used_space += $value['used_space'];
            }

            $aliLogic = new AliOssLogic();
            $res = $aliLogic->delFileToAli($arr);
            if($res['code'] != 1) throw new \Exception('oss delete fail', 1006501);

            $result = Db::name('space_details')->where([
                'id' => ['in', $ids]
            ])->delete();
            if(!$result) throw new \Exception('space_details table delete fail', 100201);

            // 检查文件夹下是否还有文件，无则删除
            $spaceExist = Db::name('space_details')->where([
                'space_id' => $space_id
            ])->select()->toArray();
            if(empty($spaceExist)){
                $r = Db::name('space')->where([
                    'id' => $space_id
                ])->delete();
                if(!$r) throw new \Exception('space table delete fail', 106001);
            }else{
                $r = Db::name('space')->where([
                    'id' => $space_id
                ])->update([
                    'used_space' => ($space['used_space'] - $used_space),
                    'update_time' => time()
                ]);
                if(false === $r) throw new \Exception('space table update fail', 106001);
            }

            $member_space = Db::name('member_space')->where(['user_id' => $space['user_id']])->find();
            $rr = Db::name('member_space')->where([
                'user_id' => $space['user_id']
            ])->update([
                'used_space' => ($member_space['used_space'] - $used_space),
                'update_time' => time()
            ]);
            if(false === $rr) throw new \Exception('member_space table update fail', 102001);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    public function history_file_list($user_id, $field = [], $page='1', $limit='10', $order = ['create_time'=>'desc'])
    {
        $spaceIds = Db::name('space')->where([
            'user_id' => $user_id,
            'type' => 2
        ])->column('id');
        if(empty($spaceIds)) return [
            'total' => 0,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => 0,
            'data' => []
        ];

        $data = Db::name('space_details')->where([
            'space_id' => ['in', $spaceIds]
        ])->field($field)->order($order)->paginate($limit, false, ['page' => $page])->toArray();
        return $data;
    }
}