<?php
/**
 * 商户端
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-07-10
 * Time: 16:31
 */

namespace app\shop\controller;


use app\common\model\Goods;
use app\common\model\SixunOpera;
use think\Controller;

class Mobile extends Controller
{
    private $shop_id;

    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        if (!in_array(request()->action(), ['login'])) {
            $role_id = input('role_id');
            $user_id = input('user_id');
            $shop_id = input('shop_id');
            if ($role_id == 0) {
                $user = model('employee')->where(['id' => $user_id, 'shop_id' => $shop_id])->find();
                if ($user) {
                    $this->shop_id = $shop_id;
                    if (!defined('SHOP_ID')) {
                        define('SHOP_ID', $this->shop_id);
                    }
                    return true;
                }
            } elseif ($role_id == 1) {
                $user = model('shop')->where('id', $shop_id)->find();
                if ($user && $user['id'] == $user_id) {
                    $this->shop_id = $shop_id;
                    if (!defined('SHOP_ID')) {
                        define('SHOP_ID', $this->shop_id);
                    }
                    return true;
                }
            } else {
                exit_json(-1, '参数错误');
            }
            exit_json(-1, '用户不存在');
        }
    }

    /**
     * 商户端登陆
     */
    public function login()
    {
        $telephone = input('telephone');
        $password = input('password');
        $shop = model('shop')->checkShop($telephone, $password);
        $data = [];
        if ($shop) {
            $data['id'] = $shop['id'];
            $data['shop_name'] = $shop['shopname'];
            $data['role_id'] = 1;
            $data['shop_id'] = $shop['id'];

        } else {
            $shop = model('employee')->alias('a')->join('shop b', 'a.shop_id=b.id')->field('a.*, b.shopname')->where(['a.telephone' => $telephone, 'a.password' => $password, 'a.status' => 1])->find();
            if ($shop) {
                $data['id'] = $shop['id'];
                $data['shop_name'] = $shop['shopname'];
                $data['role_id'] = 0;
                $data['shop_id'] = $shop['shop_id'];
            }
        }
        if ($shop) {
            exit_json(1, '登陆成功', $data);
        } else {
            exit_json(-1, '用户登陆信息不正确');
        }
    }

    /**
     * 请求首页数据
     */
    public function getIndex()
    {
        $order_num = model('order')->where('shop_id', $this->shop_id)->count();
        $order_month = model('order')->where(['shop_id' => $this->shop_id, 'create_time' => ['between', [strtotime(date('Y-m')), strtotime(date('Y-m', strtotime('+1 month')))]]])->count();
        $order_today = model('order')->where(['shop_id' => $this->shop_id, 'create_time' => ['between', [strtotime(date('Y-m-d')), strtotime(date('Y-m-d', strtotime('+1 day')))]]])->count();
        $data = [
            'total' => $order_num,
            'month' => $order_month,
            'today' => $order_today
        ];
        exit_json(1, '请求成功', $data);

    }

    /**
     * 获取店铺基本信息
     */
    public function getShopInfo()
    {
        $shop = model('shop')->alias('a')->join('shop_open_time b', 'a.id=b.shop_id')->where('id', $this->shop_id)->field('a.shopname shop_name, a.phone telephone, a.discount, a.address, b.open_time, b.close_time')->find();
        exit_json(1, '请求成功', $shop);
    }

    /**
     * 编辑店铺基本信息
     */
    public function editShopInfo()
    {
        if (input('role_id') == 0) {
            exit_json(-1, '权限不足');
        } else {
            $telephone = input('telephone');
            $open_time = input('open_time');
            $close_time = input('close_time');
            $discount = input('discount');
            //重置商品价格
            if ($discount > 0 && $discount <= 1) {
                $good_ids = model('goods')->where(['shop_id' => $this->shop_id, 'active_id' => 0])->column('id');
                db()->query("update ybt_goods set active_price=sale_price*$discount where id in (" . join(',', $good_ids) . ")");
                db()->query("update ybt_goods_prop set prop_active_price=prop_price*$discount where good_id in (" . join(',', $good_ids) . ")");
            }
            model('shop')->save(['phone' => $telephone, 'discount' => $discount], ['id' => $this->shop_id]);
            db('shop_open_time')->where('shop_id', $this->shop_id)->update(['open_time' => $open_time, 'close_time' => $close_time]);
            exit_json();
        }

    }

    /**
     * 根据条码获取商品信息
     */
    public function getGoodInfo()
    {
        $bar_code = input('bar_code');
        $valid = substr($bar_code, 0, 2);
        if ($valid == 22) {
            $gno = substr($bar_code, 2, 5);
            $flag = true;
            $price = (double)substr($bar_code, -6);
        } else {
            $gno = $bar_code;
            $flag = false;
        }
        $good = model('goods')->where(['shop_id' => $this->shop_id, 'gno' => $gno])->find();
        if ($good) {
            $data['name'] = $good['name'];
            $data['gno'] = $good['gno'];
            if ($flag) {
                $data['prop'] = intval($price / $good['bcost'] * 1000) . 'g';
            } else {
                $data['prop'] = $good['goodattr'];
            }
            exit_json(1, '请求成功', $data);
        } else {
            exit_json(-1, '商品不存在');
        }
    }

    /**
     * 上下架商品
     */
    public function upperAndLower()
    {
        $bar_codes = input('bar_codes');
        $type = input('type');
        if (!$bar_codes || !$type) {
            exit_json(-1, '参数错误');
        }
        $gno = [];
        foreach (explode(',', $bar_codes) as $value) {
            if (strpos('22', $value) !== false) {
                $gno[] = substr($value, 2, 5);
            } else {
                $gno[] = $value;
            }
        }
        if (count($gno) == 0) {
            exit_json(-1, '商品列表为空');
        }
        if ($type == 1) {
            $res = model('goods')->save(['is_live' => 1], ['gno' => ['in', $gno]]);
        } elseif ($type == 2) {
            $res = model('goods')->save(['is_live' => 0], ['gno' => ['in', $gno]]);
        } else {
            exit_json(-1, '参数错误');
        }
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '操作失败');
        }
    }

    /**
     * 获取订单列表
     */
    public function getOrderList()
    {
        $type = input('type');
        $date = input('date');
        $date_max = strtotime($date . '+1 day');
        $date_min = strtotime($date);
        $where['a.create_time'] = [
            'between', [$date_min, $date_max]
        ];
        $where['a.shop_id'] = input('shop_id');
        $page = input('page');
        $pageNum = input('pageNum');
        $offset = $page * $pageNum;
        $list = [];
        $order_status = ['1' => '已完成', '2' => '待发货', '3' => '已发货', '4' => '已退款', '5' => '待退款', '6' => '已拒绝'];
        switch ($type) {
            case 1:
                //已完成
                $where['a.order_status'] = 2;
                $list = model('order')->alias('a')->where($where)->limit($offset, $pageNum)->select();
                $num = model('order')->alias('a')->where($where)->count();
                $total_money = model('order')->alias('a')->where($where)->sum('a.real_cost');
                break;
            case 2:
                //代发货
                $where['a.order_status'] = 1;
                $where['a.pay_status'] = 1;
                $where['a.is_send'] = 0;
                $where['a.is_apply_refund'] = 0;
                $list = model('order')->alias('a')->where($where)->limit($offset, $pageNum)->select();
                $num = model('order')->alias('a')->where($where)->count();
                $total_money = model('order')->alias('a')->where($where)->sum('a.real_cost');
                break;
            case 3:
                //已发货
                $where['a.order_status'] = 1;
                $where['a.pay_status'] = 1;
                $where['a.is_send'] = 1;
                $where['a.is_apply_refund'] = 0;
                $list = model('order')->alias('a')->where($where)->limit($offset, $pageNum)->select();
                $num = model('order')->alias('a')->where($where)->count();
                $total_money = model('order')->alias('a')->where($where)->sum('a.real_cost');
                break;
            case 4:
                //已退款
                $where['a.order_status'] = 2;
                $where['a.pay_status'] = 1;
                $where['a.is_apply_refund'] = 2;
                $where['b.status'] = 1;
                $list = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id', 'left')->field('a.*, b.remarks refund_apply_reason, b.create_time refund_apply_time, b.refund_money apply_money, b.money refund_money, b.reason refund_reason, b.update_time refund_time')->where($where)->limit($offset, $pageNum)->select();
                $num = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id', 'left')->where($where)->count();
                $total_money = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id', 'left')->where($where)->sum('a.real_cost');
                break;
            case 5:
                //待退款
                $where['a.order_status'] = 2;
                $where['a.pay_status'] = 1;
                $where['a.is_apply_refund'] = 1;
                $where['b.status'] = 0;
                $list = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id', 'left')->field('a.*, b.remarks refund_apply_reason, b.create_time refund_apply_time, b.refund_money apply_money, b.money refund_money, b.reason refund_reason, b.update_time refund_time')->where($where)->limit($offset, $pageNum)->select();
                $num = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id', 'left')->where($where)->count();
                $total_money = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id', 'left')->where($where)->sum('a.real_cost');
                break;
            case 6:
                //已拒绝退款
                $where['a.order_status'] = 2;
                $where['a.pay_status'] = 1;
                $where['a.is_apply_refund'] = 3;
                $where['b.status'] = 2;
                $list = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id', 'left')->field('a.*, b.remarks refund_apply_reason, b.create_time refund_apply_time, b.refund_money apply_money, b.money refund_money, b.reason refund_reason, b.update_time refund_time')->where($where)->limit($offset, $pageNum)->select();
                $num = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id', 'left')->where($where)->count();
                $total_money = model('order')->alias('a')->join('order_refund b', 'a.id=b.order_id', 'left')->where($where)->sum('a.real_cost');
                break;
            default:
                exit_json(-1, '参数错误');
        }
        $data = [];
        foreach ($list as $l) {
            $temp = [];
            $temp['num'] = $num;
            $temp['total_money'] = $total_money;
            $temp['order_id'] = $l['id'];
            $temp['order_no'] = $l['order_no'];
            $temp['create_time'] = $l['create_time'];
            $temp['order_status'] = $order_status["$type"];
            $temp['pay_type'] = $l['pay_type'];
            $temp['dispatch_type'] = $l['dispatch_type'];
            $temp['receiver_address'] = $l['receiver_address'];
            $temp['receiver_name'] = $l['receiver_name'];
            $temp['receiver_telephone'] = $l['receiver_telephone'];
            $temp['order_det'] = model('order_det')->where('order_no', $l['order_no'])->field('CONCAT(good_name,\'/\',prop_name) good_name, gno, sale_price, cost, num, cost*num total_money')->select();
            $temp['shop_cost'] = $l['shop_cost'];
            $temp['real_cost'] = $l['real_cost'];
            $temp['coupon_fee'] = $l['coupon_fee'];
            if ($l['is_apply_refund'] > 0) {
                $temp['refund_apply_reason'] = $l['refund_apply_reason'];
                $temp['refund_apply_time'] = date('Y-m-d H:i:s', $l['refund_apply_time']);
                $temp['apply_money'] = $l['apply_money'];
                $temp['refund_money'] = $l['refund_money'];
                $temp['refund_reason'] = $l['refund_reason'];
                $temp['refund_time'] = date('Y-m-d H:i:s', $l['refund_time']);
            } else {
                $temp['refund_apply_reason'] = '';
                $temp['refund_apply_time'] = '';
                $temp['apply_money'] = 0;
                $temp['refund_money'] = 0;
                $temp['refund_reason'] = '';
                $temp['refund_time'] = '';
            }
            $data[] = $temp;
        }
        exit_json(1, '请求成功', $data);
    }

    /**
     * 确认配送
     */
    public function sure_dispatch()
    {
        $order_id = input('order_id');
        $order = model('order')->where('id', $order_id)->find();
        if ($order['is_send'] == 1) {
            exit_json(-1, '订单已处理');
        }
        $res = $order->save(['is_send' => 1, 'send_time' => date('Y-m-d H:i:s')]);
        if ($res) {
            //添加发货通知
            pushMess('您的订单已配送', ['id' => $order_id, 'url' => '', 'scene' => 'order']);
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
     * 确认退款
     */
    public function order_refund()
    {
        $order_no = input('order_no');
        $status = input('status');
        $reason = input('reason');
        $order_refund = model('order_refund')->where(['order_no' => $order_no])->find();
        if (!$order_refund) {
            exit_json(-1, '订单不存在');
        }
        //添加退款通知
        if ($status == 1) {
            //确认退款
            model('order_refund')->startTrans();
            $res = $order_refund->save(['status' => 1, 'money' => input('money'), 'reason' => $reason]);
            $res1 = $order_refund->refundOrder($order_no);
            if ($res && $res1) {
                pushMess('您申请的退款订单已同意', ['id' => $order_refund['order_id'], 'url' => '', 'scene' => 'order_refund']);
                model('order_refund')->commit();
                exit_json(1, '操作成功');
            } else {
                model('order_refund')->rollback();
                exit_json(-1, '退款失败');
            }
        } elseif ($status == 0) {
            //拒绝退款
            $order_refund->save(['status' => 2, 'reason' => $reason]);
            model('order')->save(['is_apply_refund' => 3], ['order_no' => $order_no]);
            exit_json(1, '操作成功');
        } else {
            exit_json(-1, '参数错误');
        }
    }

    /**
     * 商品管理
     */
    public function getGood()
    {
        $good_name = input('good_name');
        $good = new Goods();
        $page = input('page');
        $pageNum = input('pageNum');
        $where['name'] = ['like', "%$good_name%"];
        $list = $good->where([
            'shop_id' => $this->shop_id,
            'name' => ['like', "%$good_name%"]
        ])->field('id, name, active_price, gno, count, goodattr, guige')->limit($page * $pageNum, $pageNum)->select();
        $data = [];
        foreach ($list as $value) {
            $temp = $value;
            $props = model('goods_prop')->where('good_id', $value['id'])->select();
            $prop = [];
            if (count($props) > 0) {
                foreach ($props as $v) {
                    $prop[] = $v['prop_name'];
                }
            } else {
                $prop[] = $value['guige'] ? $value['guige'] : $value['goodattr'];
            }
            unset($temp['goodattr']);
            $temp['guige'] = $prop;
            $data[] = $temp;
        }
        exit_json(1, '请求成功', $data);
    }

    /**
     * 获取配送小区
     */
    public function getDispatchArea()
    {
        $areas = model('shop_dispatch_area')->where('shop_id', $this->shop_id)->select();
        exit_json(1, '请求成功', $areas);

    }

    /**
     * 删除配送区域
     */
    public function delDispatchArea()
    {
        $address_id = input('address_id');
        $res = model('shop_dispatch_area')->where(['id'=>$address_id, 'shop_id'=>$this->shop_id])->delete();
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '操作失败');
        }


    }

    /**
     * 添加配送区域
     */
    public function addDispatchArea()
    {
        $area_name = input('area_name');
        $lat = input('lat');
        $lng = input('lng');
        if(!$area_name || !$lat || !$lng){
            exit_json(-1, '参数不能为空');
        }
        $area = model('shop_dispatch_area')->where([
            'shop_id'=>$this->shop_id,
            'residential_name'=>$area_name,
            'lat'=>$lat,
            'lng'=>$lng
            ])->find();
        if($area){
            exit_json();
        }else{
            $res = model('shop_dispatch_area')->save([
                'shop_id'=>$this->shop_id,
                'residential_name'=>$area_name,
                'lat'=>$lat,
                'lng'=>$lng
            ]);
            if($res){
                exit_json();
            }else{
                exit_json(-1, '添加失败');
            }
        }


        
    }


}