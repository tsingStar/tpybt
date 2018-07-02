<?php
/**
 * 金额变动日志
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-06-20
 * Time: 15:42
 */

namespace app\common\model;


use think\Model;

class MoneyLog extends Model
{

    protected $autoWriteTimestamp = true;
    protected $updateTime = false;
    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * 金额变动日志添加
     * @param int $user_id 用户id
     * @param double $money 变动金额
     * @param string $type 操作来源  支付宝  微信  余额
     * @param string $desc 操作描述
     * @param string $order_no  订单编号
     */
    public function writeLog($user_id, $money, $type, $desc, $order_no=""){
        $data = [
            'user_id'=>$user_id,
            'money'=>$money,
            'type'=>$type,
            'desc'=>$desc,
            'order_no'=>$order_no
        ];
        $this->save($data);
    }
}