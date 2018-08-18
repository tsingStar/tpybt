<?php
/**
 * 平台设置
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-07-07
 * Time: 09:37
 */

namespace app\admin\controller;


class Site extends BaseController
{

    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * 用户注册协议编辑
     */
    public function register()
    {
        $reg = db('web_register')->where('id', 1)->find();
        if (request()->isAjax()) {
            $res = db('web_register')->where('id', 1)->update(['content' => input('content'), 'title' => input('title')]);
            if ($res) {
                exit_json();
            } else {
                exit_json(-1, '保存失败');
            }
        }
        $this->assign('title', '用户注册协议');
        $this->assign('item', $reg);
        return $this->fetch();

    }

    /**
     * 关于我们
     */
    public function about_us()
    {
        $reg = db('web_about_us')->where('id', 1)->find();
        if (request()->isAjax()) {
            $res = db('web_about_us')->where('id', 1)->update(['content' => input('content'), 'title' => input('title')]);
            if ($res) {
                exit_json();
            } else {
                exit_json(-1, '保存失败');
            }
        }
        $this->assign('title', '关于我们');
        $this->assign('item', $reg);
        return $this->fetch('register');

    }

    /**
     * 联系我们
     */
    public function contact_us()
    {
        $reg = db('web_contact_us')->find();
        if (request()->isAjax()) {
            db('web_contact_us')->where('1=1')->delete();
            $res = db('web_contact_us')->insert(['telephone' => input('telephone'), 'complaints_phone' => input('complaints_phone')]);
            if ($res) {
                exit_json();
            } else {
                exit_json(-1, '保存失败');
            }
        }
        $this->assign('item', $reg);
        return $this->fetch();
    }

    /**
     * 首页广告位
     */
    public function indexAdv()
    {
        $list = model('AdvIndex')->order('create_time desc')->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 广告位添加
     */
    public function advAdd()
    {
        $adv_id = input('adv_id');
        $item = model('AdvIndex')->where('id', $adv_id)->find();
        if (request()->isAjax()) {
            $data = input('post.');
            if ($item) {
                $res = $item->allowField(true)->save($data);
            } else {
                $res = model('AdvIndex')->allowField(true)->save($data);
            }
            if ($res) {
                exit_json();
            } else {
                exit_json(-1, '操作失败');
            }
        }
        $this->assign('item', $item);

        return $this->fetch();
    }

    public function advDel()
    {
        $res = model('AdvIndex')->where('id', input('adv_id'))->delete();
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '删除失败');
        }

    }
}