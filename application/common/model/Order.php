<?php
/**
 * 订单类
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018.6.2
 * Time: 10:33
 */

namespace app\common\model;


use think\Log;
use think\Model;

class Order extends Model
{
    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }

    protected $autoWriteTimestamp = true;

    /**
     * 格式化订单
     * @param $order_list
     * @return array
     */
    public function formatList($order_list)
    {
        $list = [];
        foreach ($order_list as $order) {
            $list[] = self::formatOne($order);
        }
        return $list;
    }

    /**
     * 格式化单个订单
     * @param $order
     * @return array
     */
    public function formatOne($order)
    {
        $data = [];
        $data['order_no'] = $order['order_no'];
        $data['order_det'] = \model('OrderDet')->getDetail($order['order_no']);
        $data['good_num'] = \model('OrderDet')->where('order_no', $order['order_no'])->sum('num');
        $data['order_money'] = $order['real_cost'];
        $data['order_status'] = $order['order_status'];
        $data['is_apply_refund'] = $order['is_apply_refund'];
        $data['create_time'] = $order['create_time'];
        $data['shop_name'] = $order['shop_name'];
        $data['receiver_name'] = $order['receiver_name'];
        $data['receiver_address'] = $order['receiver_address'];
        $data['receiver_telephone'] = $order['receiver_telephone'];
        $data['pay_type'] = $order['pay_type'];
        $data['coupon_fee'] = $order['coupon_fee'];
        $data['pay_time'] = $order['pay_time'];
        $data['send_time'] = $order['send_time'];
        $data['sure_time'] = $order['sure_time'];
        $data['dispatch_type'] = $order['dispatch_type'];
        return $data;

    }

    /**
     * 生成订单及订单详情
     * @param $orderInfo
     * @return array
     * @throws \think\exception\PDOException
     */
    public function makeOrder($orderInfo)
    {
        $orderDet = new OrderDet();
        $orderDet->startTrans();
        $this->startTrans();
        $res1 = $orderDet->saveOrderDet($orderInfo);
        $res2 = $this->allowField(true)->save($orderInfo);

        if ($res1['code'] == -1 || !$res2) {
            $orderDet->rollback();
            $this->rollback();
            $data = ['code' => -1];
        } else {
            $this->commit();
            $orderDet->commit();
            $data = ['code' => 1];
        }
        return $data;
    }

    /**
     * 订单支付成功
     * @param $orderInfo
     * @param $pay_type
     * @return bool
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function payOkOrder($orderInfo, $pay_type)
    {
        $order = $this->where([
            'order_no' => $orderInfo['order_no'],
            'order_status' => 0,
            'pay_status' => 0
        ])->find();
        if (!$order) {
            $this->error = '订单已处理或订单不存在';
            return false;
        }
        if ($order['coupon_id']) {
            //如果有优惠券id 处理优惠券
            \model('UserCoupon')->save(['status' => 2], ['id' => $order['coupon_id']]);
        }
        if ($pay_type == 3) {
            $res = $this->save(['order_status' => 1, 'pay_status' => 1, 'pay_type' => $pay_type, 'pay_time' => date('Y-m-d H:i')], ['order_no' => $orderInfo['order_no']]);
            $sixun = new SixunOpera();
            $card_id = \model('user')->where('id', $order['user_id'])->value('card_id');
            $vip = $sixun->getCardInfo($card_id);
            $cost = $vip['cost'] - $order['real_cost'];
            $sixun->set_residual_amt($cost, $card_id);
        } else {
            $res = $this->save([
                'order_status' => 1,
                'pay_status' => 1,
                'pay_type' => $pay_type,
                'is_refund' => $orderInfo['is_refund'],
                'trade_no' => $orderInfo['trade_no'],
                'pay_time' => date('Y-m-d H:i:s')
            ], [
                'order_no' => $orderInfo['order_no']
            ]);
        }
        if ($res) {
            $o = $this->where('order_no', $orderInfo['order_no'])->find();
            //增加金额变动记录
            $moneyLog = new MoneyLog();
            $moneyLog->writeLog($o['user_id'], -$o['real_cost'], config('pay_type')[$o['pay_type']], $o['shop_name'] . '-订单支付', $o['order_no']);
            MoneyLogMonth::addLog($o['user_id'], date('Y-m'), $o['real_cost'], 'dec');
            //订单支付成功处理赠送积分等
            PayResultOther::setScore($o);

            //处理订单商品库存
            $det = new OrderDet();
            $det->decGoods($o['order_no']);

            //推送订单支付成功
            $shop_id = $order['shop_id'];
            $employee = new Employee();
            $tokens = $employee->where('shop_id', $shop_id)->column('jiguangToken');
            $shopModel = new Shop();
            $jiguangToken = $shopModel->where('id', $shop_id)->value('jiguangToken');
            $tokens[] = $jiguangToken;
            pushMess('你有新的订单待处理', ['scene'=>'1'], ['registration_id'=>$tokens], 2);

            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取订单数量
     * @param $user_id
     * @return array
     */
    public static function getNum($user_id)
    {
        $total = self::where(['user_id'=>$user_id, 'is_del'=>0])->count();
        $receive= self::where(['user_id'=>$user_id, 'pay_status'=>1, 'is_send'=>1, 'sure_time'=>'', 'is_del'=>0])->count();
        $pay = self::where(['user_id'=>$user_id, 'pay_status'=>0, 'order_status'=>0, 'is_del'=>0])->count();
        return ['total' => $total, 'receive' => $receive, 'pay' => $pay];
    }

}