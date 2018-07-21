<?php
/**
 * 订单控制器
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018.6.5
 * Time: 13:43
 */

namespace app\shop\controller;


use app\common\model\SixunOpera;
use think\Log;

class Order extends ShopBase
{
    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * 订单列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        if(request()->isPost()){
            $order = model('order');
            $where = [];
            $where['a.shop_id'] = SHOP_ID;
            if (input('searchKey') && input('searchValue')) {
                $where[input('searchKey')] = input('searchValue');
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
            return $this->fetch('index');
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
        $where['a.shop_id'] = SHOP_ID;
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
        return $this->fetch('index');
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
     * 待配送订单
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function dispatchList()
    {
        $where = [];
        $where['a.order_status'] = 1;
        $where['a.is_send'] = 0;
        $where['a.shop_id'] = SHOP_ID;
        $order_list = model('order')->alias('a')->join('user b', 'a.user_id=b.id')->field('a.*, b.username, b.phone')->where($where)->order('a.create_time desc')->select();
        $order_pre = model('order')->alias('a')->where($where)->order('id desc')->value('id');
        $this->assign('order_pre', $order_pre);
        $this->assign('list', $order_list);
        return $this->fetch('dispatch_list');
    }

    /**
     * 获取是否有新订单
     */
    public function getNewOrder()
    {
//        $order_id = input('order_id');
        if(!SHOP_ID){
            exit_json(-1);
        }
        $where = [];
        $where['order_status'] = 1;
        $where['pay_status'] = 1;
        $where['is_send'] = 0;
        $where['shop_id'] = SHOP_ID;
        $count = model('order')->where($where)->order('id desc')->count();
        if ($count>0) {
            exit_json();
        } else {
            exit_json(-1);
        }

    }

    /**
     * 退款申请订单
     * @return mixed
     */
    public function refundList()
    {
        try{
            $orderList = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id')->join('user c', 'a.user_id=c.id')->field('a.*, b.order_id, b.refund_money, b.create_time refund_time, b.remarks, b.status, c.username, c.phone')->where([
                'a.shop_id' => SHOP_ID,
                'a.is_apply_refund' => 1,
                'b.status' => 0
            ])->select();
        } catch (\Exception $e){
            $orderList = [];
        }
        $this->assign('list', $orderList);
        return $this->fetch('refundList');
    }

    /**
     * 确认退款
     */
    public function order_refund()
    {
        $order_no = input('order_no');
        $status = input('status');
        $order_refund = model('order_refund')->where('order_no', $order_no)->find();
        $order = model('order')->where('order_no', $order_no)->find();
        $token = model('user')->where('id', $order['user_id'])->value('jiguangToken');
        //添加退款通知
        if ($status == 1) {
            //确认退款
            model('order_refund')->startTrans();
            $res = $order_refund->save(['status' => 1, 'money' => input('money')]);
            $res1 = $order_refund->refundOrder($order_no);
            if($res && $res1){
                pushMess('您申请的退款订单已同意', ['id'=>$order_refund['order_id'], 'url'=>'', 'scene'=>'order_refund'], ["registration_id"=>["$token"]]);
                model('order_refund')->commit();
                exit_json(1, '操作成功');
            }else{
                model('order_refund')->rollback();
                exit_json(-1, '退款失败');
            }
        } elseif ($status == 0) {
            pushMess('您申请的退款订单已拒绝，请联系客服。', ['id'=>$order_refund['order_id'], 'url'=>'', 'scene'=>'order_refund'], ["registration_id"=>["$token"]]);
            //拒绝退款
            $order_refund->save(['status' => 2, 'reason' => input('reason')]);
            model('order')->save(['is_apply_refund' => 3, 'order_status'=>1], ['order_no' => $order_no]);
            exit_json(1, '操作成功');
        } else {
            exit_json(-1, '参数错误');
        }
    }

    /**
     * 下载订单
     */
    public function downloadOrder()
    {
        if (request()->isPost()) {
            $start_time = input('start_time');
            $end_time = input('end_time');
            $order_status = input('order_status');

            $where['create_time'] = [
                ['egt', strtotime($start_time)],
                ['elt', strtotime($end_time . "+1 day")]
            ];
            $where['order_status'] = $order_status;
            $order_list = model('order')->where($where)->select();
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
        if($order['is_send'] == 1){
            exit_json(-1, '订单已处理');
        }
        $res = $order->save(['is_send' => 1, 'send_time' => date('Y-m-d H:i:s')]);
        if ($res) {
            //添加发货通知
            $token = model('user')->where('id', $order['user_id'])->value('jiguangToken');
            pushMess('您的订单已配送', ['id'=>$order_id, 'url'=>'' , 'scene'=>'order'], ["registration_id"=>["$token"]]);

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
     * 积分兑换订单
     */
    public function scoreList()
    {
        $list = model('order_score')->alias('a')->join('gift b', 'a.good_id=b.id')->join('user c', 'a.user_id=c.id')->field('a.*, b.good_name, c.username')->where('a.shop_id', SHOP_ID)->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 确认收获
     */
    public function sure_order_score()
    {
        $order_id = input('order_id');
        $order = model('order_score')->where('id', $order_id)->find();
        if ($order['status'] == 0) {
            $res = $order->save(['status' => 1]);
            if ($res) {
                exit_json();
            } else {
                exit_json(-1, '操作失败');
            }
        } else {
            exit_json(-1, '订单已处理');
        }
    }

}