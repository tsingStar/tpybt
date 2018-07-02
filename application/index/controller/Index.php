<?php
namespace app\index\controller;

use think\Controller;

class Index extends Controller
{
    public function index()
    {
        return $this->fetch('404');
    }

    public function report()
    {
        $r_id = input('r_id');
        $rep = model('report')->alias('a')->join('shop b', 'a.shop_id=b.id')->field('a.*, b.shopname')->where('a.id', $r_id)->find();
        $this->assign('rep', $rep);
        return $this->fetch();
        
    }
}
