<?php
/**
 * 订单控制器
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018.5.29
 * Time: 14:59
 */

namespace app\app\controller;


use app\common\model\Pay;

class Order extends BaseUser
{
    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * 生成订单
     */
    public function makeOrder()
    {
        $shop_id = input('shop_id');
        $res = self::checkShop($shop_id);
        if ($res !== true) {
            exit_json(-1, $res);
        }
        $address_id = input('address_id');
        $dispatch_time = input('dispatch_time');
        $remarks = input('remarks');
        $coupon_id = input('coupon_id') ? input('coupon_id') : 0;
        //配送方式 0 店铺配送 1 到店自取
        $dispatch_type = input('dispatch_type');
        //订单来源 购物车下单2 立即购买下单1
        $type = input('type');
        //支付方式 1 支付宝 2 微信 3 余额支付
        $pay_type = input('pay_type');
        $time = model('shop')->getDispatchTime($shop_id);
        $open_time = model('shop')->getOpenTime($shop_id);
        $goodList = $_POST['good_list'];
        $good_list = [];
        $goodList = json_decode($goodList, true);
        foreach ($goodList as $good) {
            $temp = $good;
            $good_list[] = $temp;
        }
        //处理数组数据
        if (count($good_list) == 0) {
            exit_json(-1, '订单为空');
        }
        //校验收货地址和配送时间合法性开始
        $address = [
            'id' => 0,
            'area_name' => '',
            'address' => '',
            'user_name' => '',
            'user_telephone' => ''
        ];
        if ($dispatch_type == 0) {
            if (!$address_id) {
                exit_json(-1, '收货地址不能为空, 请选择到店自提');
            }
            $address = model('user_address')->where(['user_id' => USER_ID, 'shop_id' => $shop_id, 'id' => $address_id])->find();
            if (!$address) {
                exit_json(-1, '超出配送区域, 请选择到店自提');
            }
            if ($dispatch_time < $time['open_time'] || $dispatch_time > $time['end_time']) {
                exit_json(-1, '配送时间不在店铺配送时间段');
            }
        } else {
            if ($dispatch_time < $open_time['open_time'] || $dispatch_time > $open_time['end_time']) {
                exit_json(-1, '配送时间不在店铺营业时间段');
            }
        }
        //校验收货地址和配送时间合法性结束
        $shop_cost = 0;  //订单总金额
        foreach ($good_list as $value) {
            $shop_cost += $value['total_price'];
        }
        $shop_cost = sprintf("%.2f", $shop_cost);
        $coupon_fee = 0;
        $couponModel = model('UserCoupon');
        //校验优惠券合法性开始
        if ($coupon_id) {
            $coupon = $couponModel->where(['id' => $coupon_id, 'userid' => USER_ID])->find();
            if ($coupon['status'] != 0) {
                exit_json(-1, '购物券异常');
            }
            if ($coupon['min_cost'] > $shop_cost) {
                exit_json(-1, '支付金额小于购物券最低消费金额');
            } else {
                $coupon_fee = $coupon['cost'];
                $coupon->save(['status' => 1]);
            }
        }
        //校验优惠券合法性结束
        $shop_name = model('shop')->where('id', $shop_id)->value('shopname');
        $orderInfo = [
            'order_no' => getOrderNo(),
            'address_id' => $address['id'],
            'receiver_address' => $address['area_name'] . '-' . $address['address'],
            'receiver_name' => $address['user_name'],
            'receiver_telephone' => $address['user_telephone'],
            'dispatch_time' => date('Y-m-d') . " " . $dispatch_time,
            'remarks' => $remarks,
            'coupon_id' => $coupon_id,
            'dispatch_type' => $dispatch_type,
//            'pay_type' => $pay_type,
            'shop_id' => $shop_id,
            'good_list' => $good_list,
            'user_id' => USER_ID,
            'shop_name' => $shop_name,
            'shop_cost' => $shop_cost,
            'real_cost' => $shop_cost - $coupon_fee,
            'coupon_fee' => $coupon_fee
        ];
        $order = model('order');
        $res = $order->makeOrder($orderInfo);
        if ($res['code'] == 1) {
            $pay_data = ['payStatus' => 0, 'aliOrderString' => '', 'payMessage' => '支付参数异常', 'weixinOrderString' => new \stdClass()];
            if ($type == 1) {
            } elseif ($type == 2) {
                //购物车购买
                model('shopcart')->where('user_id', USER_ID)->delete();
            } else {
                $pay_data['payMessage'] = "参数错误";
                exit_json(1, '订单生成成功', $pay_data);
            }
            //余额支付
            if ($pay_type == 3) {
                $payRes = $this->payByRemain($orderInfo['order_no'], $pay_type);
                exit_json(1, '生成订单成功', $payRes);
            }
            $payModel = new Pay();
            $orderString = $payModel->payOrder($orderInfo['order_no'], $pay_type, 'order');
            if ($orderString === false) {
                exit_json(1, '生成订单成功', $pay_data);
            } else {
                if ($pay_type == 1) {
                    $pay_data['aliOrderString'] = $orderString;
                } else {
                    $pay_data['weixinOrderString'] = $orderString;
                }
                $pay_data['payStatus'] = 1;
                $pay_data['payMessage'] = '订单可支付';
                exit_json(1, '生成订单成功', $pay_data);
            }
        } else {
            exit_json(-1, $res['msg']);
        }
    }


    /**
     * 获取订单基础信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderInfo()
    {
        $type = input('type');
        $shop_id = input('shop_id');
        $good_list = [];
        $shop_cost = 0;
        if ($type == 1) {
            // 立即购买
            $good_id = input('good_id');
            $prop_id = input('prop_id') ? input('prop_id') : 0;
            $num = input('num');
            if (!$good_id) {
                exit_json(-1, '商品参数错误');
            }
            if ($num <= 0) {
                exit_json(-1, '商品数量错误');
            }
            $res = $this->getGoodList($good_id, $num, $prop_id);
            if ($res['code'] == -1) {
                exit_json(-1, $res['msg']);
            }
            $temp = $res['data'];
            $shop_cost += $temp['total_price'];
            $good_list[] = $temp;
        } else if ($type == 2) {
            //购物车购买
            $good_cart = model('Shopcart')->where(['shop_id' => $shop_id, 'user_id' => USER_ID])->select();
            foreach ($good_cart as $good) {
                $res = $this->getGoodList($good['good_id'], $good['num'], $good['prop_id']);
                if ($res['code'] == -1) {
                    exit_json(-1, $res['msg']);
                }
                $temp = $res['data'];
                $shop_cost += $temp['total_price'];
                $good_list[] = $temp;
            }
        } else {
            exit_json(-1, '参数错误');
        }


        $default_address = model('user_address')->where(['user_id' => USER_ID, 'is_default' => 1, 'shop_id' => $shop_id])->find();
        if(!$default_address){
            $default_address = new \stdClass();
        }
        $coupon_list = model('userCoupon')->field('id coupon_id, name, cost, start_time, end_time, min_cost, status')->where(['status' => 0, 'start_time' => ['lt', date('Y-m-d H:i:s')], 'end_time' => ['gt', date('Y-m-d H:i:s')], 'min_cost' => ['lt', $shop_cost], 'userid' => USER_ID])->select();
        $shop_name = model('shop')->where('id', $shop_id)->value('shopname');
        $data = [
            'good_list' => $good_list,
            'shop_cost' => $shop_cost,
            'default_address' => $default_address,
            'coupon_list' => $coupon_list,
            'shop_name' => $shop_name
        ];
        exit_json(1, '请求成功', $data);
    }

    /**
     * 生成订单前订单详情列表
     * @param $good_id
     * @param $num
     * @param int $prop_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getGoodList($good_id, $num, $prop_id = 0)
    {
        $data = [
            'code' => 1,
            'msg' => '',
            'data' => []
        ];
        $good = model('goods')->where(['id' => $good_id, 'is_live' => 1])->find();
        if (!$good) {
            $data['code'] = -1;
            $data['msg'] = '商品不存在或已下架';
        }
        $temp['good_image'] = explode(',', $good['img'])[0];
        $temp['good_name'] = $good['name'];
        $temp['num'] = $num;
        $temp['sale_price'] = $good['sale_price'];
        $temp['active_price'] = $good['active_price'];
        $temp['good_id'] = $good_id;
        $temp['gno'] = $good['gno'];
        $temp['prop_id'] = $prop_id;
        $temp['prop_name'] = $good['guige'] ?: $good['goodattr'];
        if ($prop_id == 0) {
            if ($good['count'] < $num) {
                $data['code'] = -1;
                $data['msg'] = '商品库存不足';
            }
        } else {
            $p = model('goods_prop')->where(['id' => $prop_id, 'good_id' => $good_id])->find();
            $temp['sale_price'] = $p['prop_price'];
            $temp['active_price'] = $p['prop_active_price'];
            $temp['prop_name'] = $p['prop_name'];
            if ($p['num'] < $num) {
                $data['code'] = -1;
                $data['msg'] = '商品库存不足';
            }
        }
        $temp['total_price'] = sprintf('%.2f', $temp['num'] * $temp['active_price']);
        $data['data'] = $temp;
        return $data;
    }

    /**
     * 获取订单列表
     */
    public function getOrderList()
    {
        $order = model("order");
        $time = time() - 30 * 60;
        $order->save(['order_status' => 3], ['user_id' => USER_ID, 'create_time' => ['lt', $time], 'pay_status' => 0]);
        $order_status = input('order_status');
        $where = ['user_id' => USER_ID, 'is_del' => 0];
        if (isset($order_status) && trim($order_status) != '') {
            $where['order_status'] = $order_status;
        }
        $page = input('page') ? input('page') : 1;
        $pageNum = input('pageNum') ? input('pageNum') : 10;
        $offset = ($page - 1) * $pageNum;
        $order_list = $order->where($where)->limit($offset, $pageNum)->order('create_time desc')->select();
        $list = $order->formatList($order_list);
        exit_json(1, '请求成功', $list);
    }

    /**
     * 订单支付
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function payOrder()
    {
        $order_no = input('order_no');
        $pay_type = input('pay_type');
        $orderInfo = model('order')->where('order_no', $order_no)->find();
        if ($orderInfo['user_id'] != USER_ID) {
            exit_json(-1, '不是当前登陆用户订单');
        }
        if ($orderInfo['order_status'] != 0) {
            exit_json(-1, '订单状态不支持支付');
        }
        if ($orderInfo->getData('create_time') < time() - 30 * 60) {
            $orderInfo->save(['order_status' => 4]);
            exit_json(-1, '订单超时');
        }
        if ($pay_type == 3) {
            $payRes = $this->payByRemain($order_no, $pay_type);
            if ($payRes['payStatus'] == 1) {
                exit_json(1, $payRes['payMessage'], ['weixinOrderString' => new \stdClass(), 'aliOrderString' => ""]);
            } else {
                exit_json(-1, $payRes['payMessage']);
            }
        } else {
            $payModel = new Pay();
            $orderString = $payModel->payOrder($order_no, $pay_type, 'order');
            if ($orderString === false) {
                exit_json(-1, '支付参数错误');
            }
            if ($pay_type == 1) {
                exit_json(1, '请求成功', ['weixinOrderString' => new \stdClass(), 'aliOrderString' => $orderString]);
            } else {
                exit_json(1, '请求成功', ['weixinOrderString' => $orderString, 'aliOrderString' => ""]);
            }
        }
    }

    /**
     * 取消订单
     */
    public function cancelOrder()
    {
        $order_no = input('order_no');
        $order = model('order')->where(['order_no' => $order_no])->find();
        if ($order['user_id'] != USER_ID) {
            exit_json(-1, '不是当前登陆用户订单');
        }
        if ($order['order_status'] != 0) {
            exit_json(-1, '已支付订单不可以取消');
        } else {
            model('order')->where('order_no', $order_no)->delete();
            model('orderDet')->where('order_no', $order_no)->delete();
            exit_json();
        }
    }

    /**
     * 确认收获
     */
    public function sureOrder()
    {
        $order_no = input('order_no');
        $orderModel = model('order');
        $order = $orderModel->where('order_no', $order_no)->find();
        if ($order['order_status'] == 1) {
            if ($order['user_id'] != USER_ID) {
                exit_json(-1, '不是当前登陆用户订单');
            }
            $order->save(['order_status' => 2, 'sure_time' => date('Y-m-d H:i')]);
            exit_json();
        } else {
            exit_json(-1, '订单状态错误');
        }
    }

    /**
     * 获取申请退款状态
     */
    public function getRefundStatus()
    {
        $order_no = input('order_no');
        $order = model('order')->where('order_no', $order_no)->find();
        if ($order) {
            if ($order['is_refund'] == 1 && $order['order_status'] == 1) {
                exit_json(1, '请求成功', ['refund_status' => 1]);
            } else {
                exit_json(1, '请求成功', ['refund_status' => -1]);
            }
        } else {
            exit_json(-1, '订单不存在');
        }
    }

    /**
     * 申请退款
     */
    public function refundOrder()
    {
        $order_no = input('order_no');
        $remarks = input('remarks');
        $order = model('order')->where('order_no', $order_no)->find();
        if ($order) {
            if($order['order_status'] != 1 || $order['is_send'] == 1){
                exit_json(-1, '当前订单不支持线上退款');
            }
            if ($order['is_apply_refund'] == 1) {
                exit_json(1, '申请已提交，等待商家审核');
            }
            $order->save(['order_status' => 2, 'is_apply_refund' => 1]);
            model('order_refund')->save([
                'order_id' => $order['id'],
                'order_no' => $order['order_no'],
                'refund_money' => $order['real_cost'],
                'remarks' => $remarks
            ]);
            exit_json(1, '申请已提交，等待商家审核');
        } else {
            exit_json(-1, '订单不存在');
        }
    }

    /**
     * 获取退款详情
     */
    public function getRefund()
    {
        $order_no = input('order_no');
        $refund = model('order_refund')->where('order_no', $order_no)->find();
        if($refund){
            exit_json(1, '请求成功', $refund);
        }else{
            exit_json(-1, '订单不存在');
        }

    }

    /**
     * 删除订单
     */
    public function delOrder()
    {
        $order_no = input('order_no');
        $order = model('order')->where('order_no', $order_no)->find();
        if ($order['user_id'] > 3) {
            $order->save(['is_del' => 1]);
            exit_json();
        } else {
            exit_json(-1, '当前订单状态不允许删除');
        }
    }

    /**
     * 余额支付
     * @param $order_no
     * @param $pay_type
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function payByRemain($order_no, $pay_type)
    {
        $pay_data = ['payStatus' => 0, 'aliOrderString' => '', 'payMessage' => '支付参数异常', 'weixinOrderString' => new \stdClass()];
        $trade_password = input('trade_password');
        $order = model('order');
        $orderInfo = $order->where('order_no', $order_no)->find();
        $user = model('user')->where(['id' => USER_ID, 'trade_password' => md5($trade_password)])->find();
        if (!$user) {
            $pay_data['payMessage'] = "交易密码错误";
            return $pay_data;
        }
        if ($orderInfo['real_cost'] > $user['cost']) {
            $pay_data['payMessage'] = "用户余额不足";
            return $pay_data;
        }
        $r1 = $order->payOkOrder($orderInfo, $pay_type);
        if ($r1 === false) {
            $pay_data['payMessage'] = "订单支付失败";
            return $pay_data;
        }
        $pay_data['payMessage'] = "支付成功";
        $pay_data['payStatus'] = 1;
        return $pay_data;
    }



}