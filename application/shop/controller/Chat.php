<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018.5.26
 * Time: 11:44
 */

namespace app\shop\controller;


use think\Controller;

class Chat extends Controller
{
    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * 获取聊天用户信息
     */
    public function getChatUserInfo()
    {
        $user_id = input('chat_id');
        $data = [];
        if(strpos( $user_id, 'vip') !== false){
            $id = substr($user_id, 3);
            $user = model('user')->where('id', $id)->find();
            $data = [
                'id'=>$user_id,
                'name'=>$user['username']?$user['username']:$user['phone'],
                'img'=>__URL__.$user['logo']
            ];
        }
        if(strpos($user_id, 'shop') !== false){
            $id = substr($user_id, 4);
            $user = model('shop')->where('id', $id)->find();
            $data = [
                'id'=>$user_id,
                'name'=>$user['shopname']?$user['shopname']:$user['phone'],
                'img'=>__URL__.$user['shoplogo']
            ];
        }
        exit_json(1, '获取成功', $data);
    }

}