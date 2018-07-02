<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/19
 * Time: 14:22
 */

namespace app\shop\controller;


use app\common\model\RongYun;
use app\common\model\Shop;
use think\Request;

class Index extends ShopBase
{

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    function index()
    {
        $shopModel = new Shop();
        $shopInfo = $shopModel->getShopInfo(SHOP_ID);
        if(!$token = $shopInfo['rongyunToken']){
            $rongyun = new RongYun();
            $token = $rongyun->getToken('shop'.SHOP_ID, $shopInfo['shopname'], __URL__.$shopInfo['shoplogo']?__URL__.$shopInfo['shoplogo']:config('default_img'));
            $shopModel->save(['rongyunToken'=>$token], ['id'=>SHOP_ID]);
        }
        require_once APP_PATH.'shop/leftmenu.php';
        $this->assign('leftmenu', $leftmenu);
        $this->assign('token', $token);
        $this->assign('shopInfo', $shopInfo);
        return $this->fetch();

    }

    /**
     * 商家退出
     */
    function logout()
    {
        session(config('shopkey'), null);
        cookie('shop_id', null);
        cookie('shop_pwd', null);
        return redirect(url('shop/Index/index'));
    }


    public function welcome()
    {
        return $this->fetch();
    }


}