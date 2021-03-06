<?php
/**
 * 商家登陆
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/20
 * Time: 9:20
 */

namespace app\shop\controller;


use app\common\model\Shop;
use think\Controller;

class Pub extends Controller
{
    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->assign('sitename', config('sitename'));
    }

    /**
     * 后台用户登陆
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function login(){
        if(request()->isAjax()){
            $name = input('post.uname');
            $password = input('post.password');
            $remember = input('post.online');
            $shopModel = new Shop();
            if($shop = $shopModel->checkShop($name, $password)){
                if(!$shop['enable']){
                    exit_json(-1, '商家被禁止使用，请联系管理员');
                }
                if($remember){
                    cookie('shop_id', $shop['id']);
                    cookie('shop_pwd', $shop['password']);
                }
                session(config('shopkey'), $shop['id']);
                exit_json(200, '登陆成功');
            }else{
                exit_json(-1, '用户名或密码错误');
            }
        }else{
            return view('login');
        }
    }

    /**
     * 删除图片
     */
    public function dropPic()
    {
        if(!session(config('shopKey'))){
            exit;
        }
        $path = input('post.path');
        $relPath = __PUBLIC__.$path;
        delfile($relPath);
    }

    /**
     * 根据id删除数据
     */
    function delData()
    {
        $ids = input('idstr');
        $table = input('table');
        $res = db($table)->where('id', 'in', $ids)->delete();
        if ($res) {
            exit_json();
        } else {
            exit_json('操作失败');
        }
    }



}