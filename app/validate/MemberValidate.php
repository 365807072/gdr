<?php
namespace app\validate;

class MemberValidate extends BaseValidate
{
    protected $rule =
        [
            'username'                       =>         'require',
            'password'                       =>         'require',
            'mobile'                         =>         'require',
            'code'                           =>         'require',
            'type'                           =>         'require',
            'job_id'                         =>         'require|number|gt:0',
            'type_id'                        =>         'require|number|gt:0',
            'worker_id'                      =>         'require|number|gt:0',
            'status'                         =>         'require',
            'remarks'                        =>         'require',
            'show_picture'                   =>         'require',
            'ids'                            =>         'require',
            'people_id'                      =>         'require',
            'money'                          =>         'require',
            'details'                        =>         'require',
            'img'                            =>         'require',
        ];

    protected $message =
        [
            'username.require'              =>          '手机号必须',
            'password.require'              =>          '密码必须',
            'mobile.require'                =>          '手机号必须',
            'code.require'                  =>          '验证码必须',
            'type.require'                  =>          '类型必须',
            'type.in'                       =>          '类型必须',
            'job_id.require'                =>          '兼职ID必须',
            'job_id.number'                 =>          '兼职ID必须',
            'job_id.gt'                     =>          '兼职ID必须',
            'type_id.require'               =>          '举报类型必须',
            'type_id.number'                =>          '举报类型必须',
            'type_id.gt'                    =>          '举报类型必须',
            'worker_id.require'             =>          '工人ID必须',
            'worker_id.number'              =>          '工人ID必须',
            'worker_id.gt'                  =>          '工人ID必须',
            'people_id.require'             =>          'people_id必须',
            'status.require'                =>          '状态必须',
            'status.in'                     =>          '状态必须',
            'remarks.require'               =>          '备注必须',
            'show_picture.require'          =>          '图片必须',
            'ids'                           =>          'ID必须',
            'money.require'                 =>          '金额必填',
            'details.require'               =>          '请填写退款详情',
            'img.require'                   =>          '必须上传至少一张图片',
        ];

    protected $scene =
        [
            'lsg_doLogin'					=>			['username', 'password'],
            'lsg_passwordSend'				=>			['mobile'],
            'lsg_passwordReset'				=>			['mobile', 'code', 'password'],
            'lsg_doRegister'				=>			['username', 'code', 'password'],
            'join_job'				        =>			['type'],
            'signMyStatus'				    =>			['job_id', 'status'],
            'upVoucher'                     =>          ['job_id', 'remarks'],
            'send_job'				        =>			['type'],
            'recruit'				        =>			['job_id'],
            'details'				        =>			['job_id'],
            'partTimeWorkers'				=>			['job_id','type'],
            'confirm'				        =>			['job_id', 'ids'],
            'confirm_people'				=>			['job_id'],
            'del_job'				        =>			['job_id'],
            'ongoing_people'				=>			['job_id'],
            'ongoing_details'				=>			['job_id'],
            'working_status'				=>			['job_id'],
            'RefundOrder'				    =>			['job_id', 'type_id', 'worker_id'],
            'lookVoucher'				    =>			['job_id', 'people_id'],
            'lookVoucher1'				    =>			['job_id', 'worker_id'],
            'refund'                        =>          ['job_id', 'type_id', 'money' ,'details'],
        ];
}