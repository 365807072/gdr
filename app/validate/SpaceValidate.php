<?php
namespace app\validate;

class SpaceValidate extends BaseValidate
{
    protected $rule =
        [
            'space_id'                      =>          'require|number|gt:0',
            'file_id'                       =>          'require',
            'type'                      	=>          'require|number|in:0,1,2',
            'pay_type'                      =>          'require|number|in:0,1,2',
            'product_id'                    =>          'require|number|gt:0',
            'folder_id'                     =>          'require',
        ];

    protected $message =
        [

        ];

    protected $scene =
        [
            'folderList'					=>			['type'],
            'fileList'					    =>			['space_id'],
            'fileAddHistory'				=>			['file_id'],
            'initPay'				        =>			['pay_type','product_id'],
            'folderDel'				        =>			['folder_id']
        ];
}