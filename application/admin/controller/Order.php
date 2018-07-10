<?php
/**
 * 平台订单
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-07-07
 * Time: 15:21
 */

namespace app\admin\controller;


class Order extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * 全部订单
     */
    public function orderList()
    {
//        $order = model('order');
//        $where = [];
//        $where['shop_id'] = SHOP_ID;
//        $where['create_time'] = ['gt', strtotime(date('Y-m-d'))];
//        $order_list = $order->where($where)->select();
//        $this->assign('list', $order_list);
        if (request()->isPost()) {
            $order = model('order');
            $where = [];
            if (input('searchKey') && input('searchValue')) {
                $where[input('a.searchKey')] = input('searchValue');
            }

            if (input('order_status') !== "") {
                $where['a.order_status'] = input('order_status');
            }
            if (input('start_time')) {
                $where['a.create_time'] = ['egt', strtotime(input('start_time'))];
            }
            if (input('end_time')) {
                $where['a.create_time'] = ['elt', strtotime(input('end_time') . '+1 day')];
            }
            if (input('start_time') && input('end_time')) {
                $where['a.create_time'] = [
                    ['egt', strtotime(input('start_time'))],
                    ['elt', strtotime(input('end_time') . '+1 day')]
                ];
            }
            $order_list = $order->alias('a')->join('user b', 'a.user_id=b.id')->field('a.*, b.username, b.phone')->where($where)->select();
            $this->assign('list', $order_list);
            return $this->fetch('orderList');
        }
        $this->assign('list', []);
        return $this->fetch();
    }

    /**
     * 根据条件获取订单信息
     */
    public function orderData()
    {
        $order = model('order');
        $where = [];
        if (input('searchKey') && input('searchValue')) {
            $where[input('a.searchKey')] = input('searchValue');
        }

        if (input('order_status') !== "") {
            $where['a.order_status'] = input('order_status');
        }
        if (input('start_time')) {
            $where['a.create_time'] = ['egt', strtotime(input('start_time'))];
        }
        if (input('end_time')) {
            $where['a.create_time'] = ['elt', strtotime(input('end_time') . '+1 day')];
        }
        if (input('start_time') && input('end_time')) {
            $where['a.create_time'] = [
                ['egt', strtotime(input('start_time'))],
                ['elt', strtotime(input('end_time') . '+1 day')]
            ];
        }
        $order_list = $order->alias('a')->join('user b', 'a.user_id=b.id')->field('a.*, b.username, b.phone')->where($where)->order('a.create_time desc')->select();
        $this->assign('list', $order_list);
        return $this->fetch('orderList');
    }

    /**
     * 订单详情
     */
    public function order_detail()
    {
        $order_id = input('order_id');
        $order = model('order');
        $item = $order->alias('a')->join('user b', 'a.user_id=b.id')->field('a.*, b.username, b.phone')->where('a.id', $order_id)->find();
        $order_det = model('order_det');
        $good_list = $order_det->where('order_no', $item['order_no'])->select();
        $this->assign('item', $item);
        $this->assign('good_list', $good_list);
        return $this->fetch();
    }




    /**
     * 下载订单
     */
    public function downloadOrder()
    {
        if (request()->isPost()) {
            $start_time = input('start_time');
            $end_time = input('end_time');
            $where['a.create_time'] = [
                ['egt', strtotime($start_time)],
                ['elt', strtotime($end_time . "+1 day")]
            ];
            $where['a.pay_status'] = 1;
            $order_list = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id', 'left')->field('a.*, b.money refund_money, b.create_time refund_time, b.status refund_status')->where($where)->select();
            $this->assign('list', $order_list);
            $this->assign('filename', date('Y-m-d') . '.xls');
            return $this->fetch('excel');
        }
        return $this->fetch();
    }

    /**
     * 配送订单
     */
    public function send_order()
    {
        $order_id = input('order_id');
        $order = model('order')->where('id', $order_id)->find();
        $res = $order->save(['is_send' => 1, 'send_time' => date('Y-m-d H:i:s')]);
        if ($res) {
            try {
                $sixun = new SixunOpera();
                $order['order_det'] = model('order_det')->where('order_no', $order['order_no'])->select();
                $sixun->writeOrder($order);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
            exit_json();
        } else {
            exit_json(-1, '操作失败');
        }
    }

    /**
     * 确认收获
     */
    public function sure_order()
    {
        $order_id = input('order_id');
        $order = model('order')->where('id', $order_id)->find();
        if ($order['order_status'] == 1) {
            $res = $order->save(['order_status' => 2, 'sure_time' => date('Y-m-d H:i:s')]);
            if ($res) {
                exit_json();
            } else {
                exit_json(-1, '操作失败');
            }
        } else {
            exit_json(-1, '订单状态错误');
        }
    }

    /**
     * 订单打印
     */
    public function order_print()
    {
        $order_id = input('order_id');
        $order = model('order')->alias('a')->join('shop b', 'a.shop_id=b.id')->join('user c', 'a.user_id=c.id')->where('a.id', $order_id)->field('a.*, b.address shop_address, b.phone, c.username user_name, c.phone user_phone')->find();
        $order_det = model('order_det')->where('order_no', $order['order_no'])->select();
        $order['order_det'] = $order_det;
        $this->assign('order', $order);
        return $this->fetch();
    }

    /**
     * 当日订单
     */
    public function todayList()
    {

        $order = model('order');
        $where = [];
        if (input('searchKey') && input('searchValue')) {
            $where[input('a.searchKey')] = input('searchValue');
        }

        if (input('order_status') !== "") {
            $where['a.order_status'] = input('order_status');
        }
        $where['a.create_time'] = [
            ['egt', strtotime(date('Y-m-d'))],
            ['elt', strtotime(date('Y-m-d') . '+1 day')]
        ];
        $order_list = $order->alias('a')->join('user b', 'a.user_id=b.id')->field('a.*, b.username, b.phone')->where($where)->select();
        $this->assign('list', $order_list);
        $this->assign('is_show', 1);
        return $this->fetch('orderList');
    }


}