<?php
namespace app\third;

use think\Request;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

class AliSms
{
    private $accessKeyId = 'LTAI4FvxqzffQsfhYaChNm7Q';
    private $accessSecret = 'SN8PjpXx0irRjDERXqCNDbDtbsb8Wr';
    private $regionId = 'cn-hangzhou';
    private $SignName = '工大人';
    private $TemplateCode = 'SMS_180780158';

    public function __construct()
    {
        AlibabaCloud::accessKeyClient($this->accessKeyId, $this->accessSecret)
            ->regionId($this->regionId)
            ->asDefaultClient();
    }

    /**
     * 发送验证码短信
     * @param string $phone 手机号，多个逗号拼接
     * @param $code 验证码
     * @return bool
     */
    public function sendSms($phone, $code)
    {
        if(!$phone) return false;

        try {
            $result = AlibabaCloud::rpc()
                ->product('Dysmsapi')
                ->version('2017-05-25')
                ->action('SendSms')
                ->method('POST')
                ->host('dysmsapi.aliyuncs.com')
                ->options([
                    'query' => [
                        'RegionId' => $this->regionId,
                        'PhoneNumbers' => $phone,
                        'SignName' => $this->SignName,
                        'TemplateCode' => $this->TemplateCode,
                        'TemplateParam' => "{\"code\":\"$code\"}",
                    ],
                ])
                ->request();
            $result = $result->toArray();
            if($result['Code'] == 'OK' && $result['Message'] == 'OK') return true;
            else return false;
        } catch (ClientException $e) {
            echo $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            echo $e->getErrorMessage() . PHP_EOL;
        }
    }
}