<?php
namespace app\validate;

class ParttimeValidate extends BaseValidate
{
    protected $rule =
        [
            'id'                            =>          'require|number|gt:0',
            'job_id'                        =>          'require|number|gt:0',
            'file_id'                       =>          'require|number|gt:0',
            'type_id'                       =>          'require|number',
            'is_upFile'                     =>          'require|number|in:0,1',
            'title'                         =>          'require',
            'content'                       =>          'require',
            'modify_time'                   =>          'require',
            'workings'                      =>          'require',
            'pay_type'                      =>          'require|number|in:0,1,2',
            'deadline'                      =>          'require',
            'type'                          =>          'require',
        ];

    protected $message =
        [
            'deadline.require'              =>          '截止日期不能为空',
            'title.require'                 =>          '标题不能为空'
        ];

    protected $scene =
        [
            'lsg_put_job'					=>			['title','type'],
            'lsg_details'					=>			['id'],
            'id_check'					    =>			['id'],
            'do_job'					    =>			['job_id'],
            'lsg_report'					=>			['type_id'],
            'add_job_comment'				=>			['job_id','content'],
            'upVoucher'				        =>			['job_id'],
            'editVoucher'				    =>			['job_id','file_id','is_upFile'],
            'initPay'				        =>			['job_id','pay_type'],
            'partTimeVoucher'               =>          ['job_id'],
            'modifyAgain'                   =>          ['job_id','file_id']
        ];
}