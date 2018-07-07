<?php
/**
 * 用户控制器
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/23
 * Time: 13:44
 */

namespace app\app\controller;


use app\common\model\MoneyLogMonth;
use app\common\model\Pay;
use app\common\model\RongYun;
use app\common\model\SixunOpera;

class User extends BaseUser
{
    private $userModel;

    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->userModel = new \app\common\model\User();
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo()
    {
        //TODO 注释添加会员
        $res = $this->userModel->where('id', 'eq', USER_ID)->find();
        if ($res) {
            $data = $this->userModel->getUserInfo($res);
            exit_json(1, '请求成功', $data);
        } else {
            exit_json(-1, '会员信息不存在');
        }
    }

    /**
     * 思迅卡内信息同步到App会员
     */
    public function asyncVip()
    {
        $userModel = new \app\common\model\User();
        $userModel->asyncVip();
        exit_json();
    }

    /**
     * 编辑会员信息
     */
    public function editUser()
    {
        $userModel = new \app\common\model\User();
        $data = input('post.');
        $file = request()->file('headImg');
        if ($file) {
            $headPath = __UPLOAD__ . '/headImg';
            $res = $file->move($headPath, md5(USER_ID));
            $headImg = '/upload/headImg/' . $res->getSaveName();
            $data['logo'] = $headImg;
        }
        unset($data['userid']);
        $u = $userModel->where($data)->find();
        if ($u['id'] > 0) {
            exit_json(1, '更新成功');
        }
        unset($data['id']);
        $bol = $userModel->allowField(true)->save($data, ['id' => USER_ID]);
        if ($bol) {
            $rongYun = new RongYun();
            $user = model('user')->where(['id' => USER_ID])->find();
            $rongYun->refresh('vip' . USER_ID, $user['username'], __URL__ . $user['logo']);
            $sixun = new SixunOpera();
            $sixun->asyncVip($user);
            exit_json(1, '更新成功');
        } else {
            exit_json(-1, '保存失败');
        }
    }

    /**
     * 会员签到
     */
    public function signLog()
    {
        $res = $this->userModel->SignLog();
        if ($res) {
            exit_json(1, '请求成功', ['message' => '签到成功']);
        } else {
            $msg = $this->userModel->getError();
            exit_json(1, '请求成功', ['message' => '今日已签到']);
        }
    }

    /**
     * 获取收藏列表
     */
    public function getCollection()
    {
        $good_arr = model('userCollection')->where('user_id', USER_ID)->select();
        $good_list = [];
        foreach ($good_arr as $good) {
            $cate = model('shop_cate')->where('id', $good['cate_id'])->find();
            $good_list[$cate['id']]['cate_name'] = $cate['name'];
            $g = model('goods')->field('id good_id, shop_id, name, img, sale_price, active_price')->where('id', $good['good_id'])->find();
            $g['img'] = explode(',', $g['img'])[0];
//            if ($g['active_price'] == 0) {
//                $g['active_price'] = $g['sale_price'];
//            }
            $good_list[$cate['id']]['good_list'][] = $g;
        }
        exit_json(1, '请求成功', array_values($good_list));
    }

    /**
     * 获取当前店铺配送小区列表
     */
    public function getAreaList()
    {
        $shop_id = input('shop_id');
        $areaList = db('shop_dispatch_area')->where('shop_id', $shop_id)->select();
        exit_json(1, '获取小区列表成功', $areaList);
    }

    /**
     * 添加收货地址
     */
    public function setAddress()
    {
        $user_name = input('user_name');
        $user_telephone = input('user_telephone');
        $area_name = input('area_name');
        $area_id = input('area_id');
        $address = input('address');
        $shop_id = input('shop_id');
        $data = [
            'user_id' => USER_ID,
            'user_name' => $user_name,
            'user_telephone' => $user_telephone,
            'area_name' => $area_name,
            'area_id' => $area_id,
            'address' => $address,
            'shop_id' => $shop_id
        ];
        if (!db('user_address')->where('user_id', USER_ID)->find()) {
            $data['is_default'] = 1;
        }
        $res = db('user_address')->insert($data);
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '添加失败');
        }
    }

    /**
     * 获取用户配送地址列表
     */
    public function getAddress()
    {
        $addressList = db('user_address')->where('user_id', USER_ID)->select();
        exit_json(1, '请求成功', $addressList);
    }

    /**
     * 编辑配送地址
     */
    public function editAddress()
    {
        $user_name = input('user_name');
        $user_telephone = input('user_telephone');
        $area_name = input('area_name');
        $area_id = input('area_id');
        $address = input('address');
        $shop_id = input('shop_id');
        $address_id = input('address_id');
        $data = [
            'user_id' => USER_ID,
            'user_name' => $user_name,
            'user_telephone' => $user_telephone,
            'area_name' => $area_name,
            'area_id' => $area_id,
            'address' => $address,
            'shop_id' => $shop_id,
            'id' => $address_id
        ];
        $res = db('user_address')->where($data)->find();
        if (!$res) {
            $res = db('user_address')->where('id', $address_id)->update($data);
        }
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '编辑失败');
        }

    }

    /**
     * 删除配送地址
     */
    public function delAddress()
    {
        $address_id = input('address_id');
        if (!$address_id) {
            exit_json(-1, '参数错误');
        }
        $res = db('user_address')->where('id', $address_id)->delete();
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '参数错误');
        }
    }

    /**
     * 设置默认地址
     */
    public function setDefaultAddress()
    {
        $address_id = input('address_id');
        if (!$address_id) {
            exit_json(-1, '参数错误');
        }
        $res = model('user_address')->save(['is_default' => 1], ['id' => $address_id]);
        model('user_address')->save(['is_default' => 0], ['id' => ['neq', $address_id], 'user_id' => USER_ID]);
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '设置默认收获地址失败');
        }
    }

    /**
     * 设置交易密码
     */
    public function setTradePassword()
    {
        $trade_password = trim(input('post.trade_password'));
        if (!$trade_password) {
            exit_json(-1, '交易密码不能为空');
        } else {
            $data = ['trade_password' => md5($trade_password)];
            $u = model('user')->where('id', USER_ID)->find();
            if ($u['trade_password'] == md5($trade_password)) {
                exit_json(1, '设置成功');
            } else {
                if ($u['trade_password']) {
                    exit_json(-1, '交易密码已设置过');
                }
                $res = model('user')->save($data, ['id' => USER_ID]);
                if ($res) {
                    exit_json(1, '设置成功');
                } else {
                    exit_json(-1, '设置失败');
                }
            }
        }
    }

    /**
     * 更改交易密码
     */
    public function modifyTradePassword()
    {
        $new_password = trim(input('new_password'));
        $old_password = trim(input('old_password'));
        if (!$new_password || !$old_password) {
            exit_json(-1, '参数为空');
        }
        if ($new_password == $old_password) {
            exit_json(1, '设置成功');
        } else {
            $user = model('user')->where(['id' => USER_ID, 'trade_password' => md5($old_password)])->find();
            if (!$user['id']) {
                exit_json(-1, '密码错误');
            } else {
                $res = model('user')->allowField(true)->save(['trade_password' => md5($new_password)], ['id' => USER_ID]);
                if ($res) {
                    exit_json(1, '设置成功');
                } else {
                    exit_json(-1, '设置失败');
                }
            }
        }

    }

    /**
     * 获取优惠券列表
     */
    public function getCouponList()
    {
        $coupon_list = model('userCoupon')->field('id coupon_id, name, cost, start_time, end_time, min_cost, status')->where('userid', USER_ID)->select();
        exit_json(1, '获取成功', $coupon_list);
    }

    /**
     * 设置登陆密码
     */
    public function setLoginPassword()
    {
        $user = model('user')->where('id', USER_ID)->find();
        $old_password = trim(input('old_password'));
        $new_password = trim(input('new_password'));
        if (!$old_password || !$new_password) {
            exit_json(-1, '参数错误');
        }
        if ($old_password == $new_password) {
            exit_json(1, '设置成功');
        }
        if ($user['password'] != md5($old_password)) {
            exit_json(-1, '原始密码错误');
        }
        $res = model('user')->save(['password' => md5($new_password)], ['id' => USER_ID]);
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '设置失败');
        }
    }

    /**
     * 获取充值活动
     */
    public function getChargeAct()
    {
        $chargeList = model('charge_act')->where('status', 1)->select();
        exit_json(1, '请求成功', $chargeList);
    }

    /**
     * 会员充值
     */
    public function chargeAccount()
    {
        $money = input('money');
        $given_money = input('given_money');
        $active_id = input('active_id') ? input('active_id') : 0;
        $pay_type = input('pay_type');
        if ($active_id) {
            $res = model('charge_act')->checkLegal($money, $given_money);
            if (!$res) {
                exit_json(-1, '充值参数非法');
            }
        }
        $order_no = getOrderNo();
        $res = model('charge_order')->save([
            'order_no' => $order_no,
            'money' => $money,
            'given_money' => $given_money,
            'active_id' => $active_id,
            'user_id' => USER_ID,
        ]);
        $pay_data = ['payStatus' => 0, 'aliOrderString' => '', 'payMessage' => '支付参数异常', 'weixinOrderString' => new \stdClass()];
        if ($res) {
            $payModel = new Pay();
            $orderString = $payModel->payOrder($order_no, $pay_type, 'account');
            if ($orderString) {
                $pay_data['payStatus'] = 1;
                $pay_data['payMessage'] = '订单可支付';
            }
            if ($pay_type == 1) {
                $pay_data['aliOrderString'] = $orderString;
            }
            if ($pay_type == 2) {
                $pay_data['weixinOrderString'] = $orderString;
            }
            exit_json(1, '订单生成成功', $pay_data);
        } else {
            exit_json(-1, '订单生成失败');
        }
    }

    /**
     * 获取会员卡信息
     */
    public function getCardInfo()
    {
        $user = model('user')->where('id', USER_ID)->find();
        $data = [
            'card_no' => $user['card_no'],
            'card_id' => $user['card_id']
        ];
        exit_json(1, '请求成功', $data);
    }

    /**
     *  获取账单详情
     */
    public function getMoneyLog()
    {
        $page = input('page') ? input('page') : 1;
        $pageNum = input('pageNum') ? input('pageNum') : 20;
        $offset = ($page - 1) * $pageNum;
        $list = model('money_log')->where('user_id', USER_ID)->order('create_time desc')->limit($offset, 20)->select();
        $data = [];
        foreach ($list as $value) {
            $temp['order_no'] = $value['order_no'];
            $temp['desc'] = $value['desc'];
            $temp['create_time'] = $value['create_time'];
            $temp['month'] = date('m', strtotime($value['create_time']));
            $temp['money'] = '￥ '.$value['money'];
            $temp['type'] = $value['type'];
            $moneyAll = MoneyLogMonth::getMonthMoney(date('Y-m', strtotime($value['create_time'])), USER_ID);
            $temp = array_merge($temp, $moneyAll);
//            $date = date('Y-m', strtotime($value['create_time']));
//            $data[$date][] = $value;
            $data[] = $temp;
        }
//        $result = [];
//        foreach ($data as $k=>$v){
//            $temp = MoneyLogMonth::getMonthMoney($k, USER_ID);
//            $temp['date']=$k;
//            $temp['data']=$v;
//            $result[] = $temp;
//        }
        exit_json(1, '请求成功', $data);
    }

    /**
     * 获取账单详情
     */
    public function getMoneyDetail()
    {
        $order_no = input('order_no');
        $order = model('order')->where('order_no', $order_no)->find();
        if (!$order) {
            $order = model('charge_order')->where('order_no', $order_no)->find();
        }
        if ($order) {
            $data['title'] = isset($order['shop_name']) ? $order['shop_name'] . '-订单支付' : '余额充值';
            $data['money'] = isset($order['real_cost']) ? $order['real_cost'] : $order['money'];
            $data['pay_type'] = config('pay_type')[$order['pay_type']];
            $data['pay_time'] = $order['pay_time'];
            $data['create_time'] = $order['create_time'];
            $data['order_no'] = $order_no;
            exit_json(1, '请求成功', $data);
        } else {
            exit_json(-1, '订单信息不存在');
        }


    }

    /**
     * 获取积分列表
     */
    public function getScoreList()
    {
        $page = input('page') ? input('page') : 1;
        $pageNum = input('pageNum') ? input('pageNum') : 10;
        $offset = ($page - 1) * $pageNum;
        $list = model('score_log')->where('user_id', USER_ID)->limit($offset, $pageNum)->order('create_time desc')->select();
        exit_json(1, '请求成功', $list);
    }

    /**
     * 获取联系我们
     */
    public function getContact()
    {
        $shop_id = input('shop_id');
        $shop_phone = model('shop')->where('id', $shop_id)->value('phone');
        $data = db('web_contact_us')->find();
        exit_json(1, '请求成功', [
            'phone'=>$data['telephone'],
            'complaints_phone'=>$data['complaints_phone'],
            'shop_phone'=>$shop_phone
        ]);
    }

}