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
    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        //        Log::error($_POST);
    }

    public function test()
    {
        $res = pushMess('你设置的抢购活动还有5分钟开启', ['id' => '123123', 'url' => 'dfsgfdasfsfgsfd', 'scene' => 'sec_active']);
        echo $res;
        exit();
    }

    public function getVersion()
    {
        $version = input('version');
        if ($version == config('version')) {
            exit_json(-1, '当前版本为最新版本');
        } else {
            exit_json(1, '有新版本', ['url' => config('download_url'), 'version' => config('version')]);
        }
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
        if ($user) {
            if ($user['password'] != md5($password)) {
                exit_json(-1, '密码错误');
            }
            if ($user['status'] != 0) {
                exit_json(-1, '当前用户不存在');
            }
            $userModel->isUpdate(true)->save(['jiguangToken' => $jiguangToken], ['id' => $user['id']]);
            exit_json(1, '登陆成功', $userModel->getUserInfo($user));
        } else {
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
            $token = $rongYun->getToken('vip' . $userid, $data['phone'], config('default_img'));
            $userModel->save(['rongyunToken' => $token, 'jiguangToken' => $jiguangToken], ['id' => $userid]);
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
        if (!$data['phone'] || !$data['code'] || !$data['password']) {
            exit_json(-1, '参数错误');
        }
        $smsModel = new SendSms($data['phone']);
        $vres = $smsModel->checkVcode($data['code']);
        if (!$vres) {
            exit_json(-1, '验证码错误');
        }
        $userModel = new User();
        $res = $userModel->save(['password' => md5($data['password'])], ['phone' => $data['phone']]);
        if ($res) {
            exit_json();
        } else {
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