<?php
/**
 * 支付结果通知
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018.6.4
 * Time: 09:31
 */

namespace app\admin\controller;


use app\common\model\ChargeOrder;
use app\common\model\Order;
use app\common\model\Pay;
use app\common\model\PayOrder;
use think\Controller;
use think\Log;

class PayResult extends Controller
{
    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * 支付通知入口
     */
    public function index()
    {
        $pay = new Pay();
        $is_refund = 1;
        if ($_POST) {
            //支付宝支付来源
            $pay_type = 1;
            $log_path = LOG_PATH . 'ali';
            if ($_POST['trade_status'] == 'TRADE_FINISHED') {
                $is_refund = 0;
            }elseif ($_POST['trade_status'] == 'TRADE_SUCCESS'){

            }else{
                Log::error("订单关闭");
                echo 'SUCCESS';
                exit();
            }
        } else {
            //微信支付来源
            $pay_type = 2;
            $xml = file_get_contents('php://input');
            $_POST = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
            $log_path = LOG_PATH . 'weixin';
        }
        @mkdir($log_path);
        file_put_contents($log_path . DS . date('Y-m-d') . '.txt', date('H:m:s') . json_encode($_POST) . '\n', FILE_APPEND);
        if (isset($is_refund) || $_POST['result_code'] == 'SUCCESS') {
            $validRes = $pay->validate($pay_type);
            if ($validRes === false) {
                Log::error('签名错误');
            }
            $orderInfo = $this->formatRes($validRes, $pay_type);
            $orderInfo['is_refund'] = $is_refund;
            $payOrder = new PayOrder();
            try {
                $pay_order = $payOrder->where('id', $orderInfo['out_trade_no'])->find();
                if ($orderInfo['total_money'] != $pay_order['total_money']) {
                    Log::error('支付金额错误');
                } else {
                    $orderInfo['order_no'] = $pay_order['order_no'];
                    if ($pay_order['pay_status'] == 0) {
                        $pay_order->save(['pay_status' => 1]);
                        if($pay_order['pay_event'] == 'order'){
                            //商品订单更改订单状态
                            $order = new Order();
                            $order->payOkOrder($orderInfo, $pay_type);
                        }
                        if($pay_order['pay_event'] == 'account'){
                            //会员充值
                            $order = new ChargeOrder();
                            $order->payOkOrder($orderInfo, $pay_type);
                        }

                    } else {
                        Log::error('订单已支付');
                    }
                }
            } catch (\Exception $e) {
                Log::error($pay_type.'支付参数错误。'.$e->getMessage());
            }
        }
        if ($pay_type == 1) {
            echo 'SUCCESS';
        } else {
            echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }
    }

    /**
     * 格式化支付返回信息
     * @param $orderInfo
     * @param $pay_type
     * @return array
     */
    private function formatRes($orderInfo, $pay_type)
    {
        $payInfo = [];
        if ($pay_type == 1) {
            $payInfo['out_trade_no'] = substr($orderInfo['out_trade_no'], 0, -4);
            $payInfo['trade_no'] = $orderInfo['trade_no'];
            $payInfo['total_money'] = $orderInfo['total_amount'];
//            $payInfo['seller_id'] = $orderInfo['seller_id'];
        } else if ($pay_type == 2) {
            $payInfo['out_trade_no'] = substr($orderInfo['out_trade_no'], 0, -4);
            $payInfo['trade_no'] = $orderInfo['transaction_id'];
            $payInfo['total_money'] = $orderInfo['total_fee']/100;
        }
        return $payInfo;
    }


}