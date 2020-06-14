<?php
/**
 * Created by PhpStorm.
 * User: Zero
 * Date: 2019/6/3
 * Time: 14:27
 */
namespace app\third;

use think\Db;
use AliPay\AopClient;
use AliPay\Request\AlipayTradeRefundRequest;
use AliPay\Request\AlipayTradeAppPayRequest;
use AliPay\Request\AlipayFundTransToaccountTransferRequest;

//支付宝支付
class AliPay
{
    private $config;

    public function __construct()
    {
        $this->config = config('pay')['ali_pay'];
    }

    public function pay($order, $notify_url)
    {
        $aliPay                     = new AopClient();
        $aliPay->appId              = $this->config->appID;
        $aliPay->rsaPrivateKey      = $this->config->ali_privateKey;
        $aliPay->alipayrsaPublicKey = $this->config->ali_payRsaPublicKey;
        $aliPay->encryptKey         = $this->config->aesKey;
        $aliPay->signType           = 'RSA2';

        $data = [
            'out_trade_no' => $order['order_sn'],        //商户订单号
            'total_amount' => $order['user_money'],      //付款金额
            'subject'      => $order['body'],      //订单名称 可以中文
        ];

//        $request = new AlipayTradePrecreateRequest();
//        $request->setBizContent(json_encode($data));
//        $result       = $aliPay->execute($request);
//        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
//        $resultCode   = $result->$responseNode->code;
//        $result       = [
//            'out_trade_no' => $result->$responseNode->out_trade_no,
//            'qr_code'      => $result->$responseNode->qr_code,
//        ];

        //支付参数生成，用于apk
        $request = new AlipayTradeAppPayRequest();
        $request->setNotifyUrl($notify_url);
        $request->setBizContent(json_encode($data));
        $result = $aliPay->sdkExecute($request);
        return $result;
    }

    //退款
    public function refund($refund, $notify_url)
    {
        $dateTime = date('Y-m-d H:i:s', time());
        $aliPay                     = new AopClient();
        $aliPay->appId              = $this->config->appID;
        $aliPay->rsaPrivateKey      = $this->config->ali_privateKey;
        $aliPay->alipayrsaPublicKey = $this->config->ali_payRsaPublicKey;
        $aliPay->encryptKey         = $this->config->aesKey;
        $aliPay->signType           = 'RSA2';

        $data    = [
            "out_trade_no"   => $refund['order_sn'],
            //商户订单号
            'out_request_no' => $refund['refund_sn'],
            //退款订单号
            "refund_amount"  => $refund['user_money'],
            //退款金额
        ];

        $request = new AlipayTradeRefundRequest();
        $request->setNotifyUrl($notify_url);
        $request->setBizContent(json_encode($data));
        $result = $aliPay->execute($request);

        if($result->alipay_trade_refund_response->code == 10000){
            //退款成功直接操作
            if ($notify_url != 1) {
                $time = date('Y-m-d H:i:s');
                $refundUpArr = array('type' => '2');
                $parttimeUpArr = array('refund_type' => '2');
                Db::startTrans();
                try {
                    $rs = Db::name('parttime_refund')->where(['refund_sn' => $refund['refund_sn']])->update($refundUpArr);
                    if (!$rs) throw new Exception('退款表更新失败');
                    $rs = Db::name('parttime')->where(['order_sn' => $refund['order_sn']])->update($parttimeUpArr);
                    if (!$rs) throw new Exception('临时工表更新');
                    Db::commit();
                    writeDebug($time.'临时工退款回调成功 '.$refund['refund_sn']."\n", 'refund');
                } catch (Exception $e) {
                    Db::rollback();
                    writeDebug($time.'临时工退款回调失败 '.$refund['refund_sn'].':'.$e->getMessage()."\n", 'refund');
                }
            }

            writeDebug($dateTime . ' refund success: order_sn=' . $refund['order_sn'], 'ali_pay', 5, true);
            return true;
        } else {
            writeDebug($dateTime . 'refund fail, msg:' . $result->alipay_trade_refund_response->code.'->'.$result->alipay_trade_refund_response->msg . ' order_sn=' . $refund['order_sn'], 'ali_pay', 5, true);
            return false;
        }
    }

    //转账
    public function transfer($order, $notify_url = '')
    {
        $dateTime = date('Y-m-d H:i:s', time());
        $aliPay                     = new AopClient();
        $aliPay->appId              = $this->config->appID;
        $aliPay->rsaPrivateKey      = $this->config->ali_privateKey;
        $aliPay->alipayrsaPublicKey = $this->config->ali_payRsaPublicKey;
        $aliPay->encryptKey         = $this->config->aesKey;
        $aliPay->signType           = 'RSA2';

        $data = [
            'out_biz_no' => $order['order_sn'],         // 商户端的唯一订单号，对于同一笔转账请求，商户需保证该订单号唯一
            'amount' => $order['trans_amount'],   // 订单总金额，单位为元，精确到小数点后两位，取值范围[0.1,100000000]
            'payee_type' => 'ALIPAY_LOGONID',       // ALIPAY_LOGONID：支付宝登录号，支持邮箱和手机号格式。
            'payee_account' => $order['user'],   // 收款方账户。与payee_type配合使用。付款方和收款方不能是同一个账户。
            'remark' => $order['order_title'],     // 转账业务的标题，用于在支付宝用户的账单里显示
        ];

        $request = new AlipayFundTransToaccountTransferRequest();
//        $request->setNotifyUrl($notify_url);
        $request->setBizContent(json_encode($data));
        $result = $aliPay->execute($request);
        if($result->alipay_fund_trans_toaccount_transfer_response->code == 10000){
            $response = (array)$result->alipay_fund_trans_toaccount_transfer_response;
            // 直接处理
            $user = Db::name('member')->where(['id' => $order['user_id']])->find();

            $user_money = bcsub($user['balance'], $order['trans_amount'], 2);
            //更新转账日志
            $add = [
                'user_id'       =>  $order['user_id'],
                'order_id'      =>  $response['order_id'],
                'order_sn'      =>  $order['order_sn'],
                'pay_no'        =>  $order['user'],
                'amount'        =>  $order['trans_amount'],
                'explain'       =>  $order['order_title'],
                'status'        =>  1,
                'create_time'   =>  time(),
                'update_time'   =>  time(),
            ];
            Db::name('member_transfer_log')->insert($add);
            //更新余额
            Db::name('member')->where(['id' => $order['user_id']])->update(['balance'=>$user_money]);
            //记录余额变动
            $add = [
                'user_id'       =>  $order['user_id'],
                'value'         =>  $order['trans_amount'],
                'type'          =>  0,
                'remark'        =>  $order['order_title'],
                'create_time'   =>  time(),
            ];
            Db::name('member_account_log')->insert($add);

            writeDebug($dateTime . ' transfer success: order_sn=' . $order['order_sn'], 'ali_pay', 5, true);
            return true;
        } else {
            writeDebug($dateTime . 'transfer fail, msg:' . $result->alipay_fund_trans_toaccount_transfer_response->code.'->'.$result->alipay_fund_trans_toaccount_transfer_response->msg . ' order_sn=' . $order['order_sn'], 'ali_pay', 5, true);
            return false;
        }
    }

}