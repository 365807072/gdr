<?php
namespace app\validate;

class SearchValidate extends BaseValidate
{
    protected $rule =
        [
            'id'                            =>          'require|number|gt:0',
            'profession_id'                 =>          'require|number|gt:0',
            'keyword'                       =>          'require',
            'job_time'                      =>          'require',
            'modify_time'                   =>          'require',
            'workings'                      =>          'require',
            'type'                          =>          'require|number|in:0,1,2',
            'money'                         =>          'require',
            'old_password'                  =>          'require',
            'password'                      =>          'require|length:6,20',
            'repassword'                    =>          'require|confirm:password',
            'pay_type'                      =>          'require|number|in:0,1,2',
            'image'                         =>          'require',
            'faceid_image'                  =>          'require',
            'backid_image'                  =>          'require',
            'handid_image'                  =>          'require',
            'business_licence'              =>          'require',
            'money'                         =>          'require|number|gt:0',
            'date'                          =>          'require',
        ];

    protected $message =
        [

        ];

    protected $scene =
        [
            'search'					    =>			['type'],
            'lsg_personal'					=>			['id'],
            'id_check'					    =>			['id'],
            'job_book'					    =>			['id','profession_id'],
            'job_book_date'					=>			['id','profession_id','date'],
            'book_submit'					=>			['profession_id','job_time','modify_time','workings'],
            'lsg_passwordReset'				=>			['old_password','password','repassword'],
            'certificationMargin'			=>			['type','pay_type','money'],
            'marginFee'			            =>			['type'],
            'certificationEdit'			    =>			['type'],
            'certificationEdit_personal'	=>			['image','faceid_image','backid_image','handid_image'],
            'certificationEdit_business'	=>			['business_licence'],
        ];
}