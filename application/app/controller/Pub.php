<?php
/**
 * App用户通用类
 * Created by PhpStorm.
 * User: tsingStar
 * Date: 2018/1/19
 * Time: 8:50
 */

namespace app\app\controller;


use app\common\model\RongYun;
use app\common\model\SendSms;
use app\common\model\SixunOpera;
use app\common\model\User;
use think\Controller;
use think\Exception;
use think\Log;
use think\Request;

class Pub extends Controller
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function test()
    {
        ignore_user_abort(1);
        set_time_limit(0);
        $i = 1;
        while (true){
//            $msg = pushMess();
            sleep('5');
            if($i>20){
                break;
            }
            $i++;
            Log::error("$i");
        }
        exit();
    }

    public function testOrder()
    {
        $orderInfo = model('order')->where('order_no', '201806190912184404919')->find();
        $order_det = model('orderDet')->where('order_no', '201806190912184404919')->select();
        $orderInfo['order_det'] = $order_det;
        $s = new SixunOpera();
        try{
            $s->writeOrder('001', $orderInfo);
        }catch (Exception $e){
            exit_json(-1, $e->getMessage());
        }
        exit_json();
        
    }


    function getConnStatus()
    {
        $mssql = new SixunOpera();
        var_dump($mssql->testConn());

    }

    /**
     *
     * 会员登陆
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function login()
    {

        $telephone = input('post.phone');
        $password = input('post.password');
        $jiguangToken = input('post.jiguangToken');
        $userModel = new User();
        $user = $userModel->where('phone', 'eq', $telephone)->find();
        if($user){
            if($user['password'] != md5($password)){
                exit_json(-1, '密码错误');
            }
            if($user['status']!=0){
                exit_json(-1, '当前用户不存在');
            }
            $userModel->isUpdate(true)->save(['jiguangToken'=>$jiguangToken], ['id'=>$user['id']]);
            exit_json(1, '登陆成功', $userModel->getUserInfo($user));
        }else{
            exit_json(-1, '会员不存在');
        }
    }

    /**
     * 会员注册
     */
    function register()
    {
        $data = input('post.');
        if (!$data['password']) {
            exit_json(-1, '密码格式不正确');
        }
        $smsModel = new SendSms($data['phone']);
        $vres = $smsModel->checkVcode($data['code']);
        if (!$vres) {
            exit_json(-1, $smsModel->getError());
        }
        $data['password'] = md5($data['password']);
        $userModel = new User();
        $userModel->allowField(true)->isUpdate(false)->save($data);
        $userid = $userModel->getLastInsID();
        if ($userid) {
            $jiguangToken = input('post.jiguangToken');
            $rongYun = new RongYun();
            $token = $rongYun->getToken('vip'.$userid, $data['phone'], config('default_img'));
            $userModel->save(['rongyunToken'=>$token, 'jiguangToken'=>$jiguangToken], ['id'=>$userid]);
            //注册赠送优惠券
            model('UserCoupon')->giveRegisterCoupon($userid);
            $user = $userModel->find($userid);
            exit_json(1, '注册成功', $userModel->getUserInfo($user));
        } else {
            exit_json(-1, '注册失败');
        }
    }

    /**
     * 重置密码
     */
    function resetPassword()
    {
        $data = input('post.');
        if(!$data['phone'] || !$data['code'] || !$data['password']){
            exit_json(-1, '参数错误');
        }
        $smsModel = new SendSms($data['phone']);
        $vres = $smsModel->checkVcode($data['code']);
        if(!$vres){
            exit_json(-1, '验证码错误');
        }
        $userModel = new User();
        $res = $userModel->save(['password'=>md5($data['password'])], ['phone'=>$data['phone']]);
        if($res){
            exit_json();
        }else{
            exit_json(-1, '重置密码失败');
        }
    }

    /**
     * 获取验证码
     */
    function getVcode()
    {
        //sendType  1 注册 2 忘记密码
        $telephone = input('telephone');
        $sendType = input('sendType');
        if (!test_tel($telephone)) {
            exit_json(-1, '手机号不合法');
        }
        $sendSms = new SendSms($telephone, config('netease.tempId'), $sendType);
        $res = $sendSms->sendVcode();
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, $sendSms->getError());
        }
    }


}