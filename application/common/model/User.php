<?php
/**
 * 用户实体类
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\1\24 0024
 * Time: 17:01
 */

namespace app\common\model;


use think\Model;

class User extends Model
{
    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }

    protected $type = [
        'id' => 'int',
        'card_no' => 'string',
        'card_id' => 'string',
        'email' => 'string',
        'username' => 'string',
        'phone' => 'string'
    ];
    protected $autoWriteTimestamp = true;
    protected $createTime = 'creattime';
    protected $updateTime = false;

    public function userFormat($userInfo)
    {
        $user = [];
        $coupon = new UserCoupon();
        $user['couponNum'] = $coupon->getCouponNum($userInfo['id']);
        $user['orderNum'] = Order::getNum($userInfo['id']);
        $user['userid'] = $userInfo['id'];
        $user['logo'] = $userInfo['logo'];
        $user['username'] = $userInfo['username'];

        //重置用户融云信息
        if(!$userInfo['rongyunToken']){
            $rongYun = new RongYun();
            $token = $rongYun->getToken('vip' . $userInfo['id'], $userInfo['username']?:$userInfo['phone'], $userInfo['logo']?:config('default_img'));
            $this->save(['rongyunToken'=>$token], ['id'=>$userInfo['id']]);
            $user['rongyunToken'] = $token;
        }else{
            $user['rongyunToken'] = $userInfo['rongyunToken'];
        }
        //重置积分
        if($userInfo['card_id']){
            $sixun = new SixunOpera();
            $card = $sixun->getCardInfo($userInfo['card_id']);
            $user['score'] = $card['acc_num'];
        }else{
            $user['score'] = $userInfo['score'];
        }
        $user['cardList'] = $userInfo['cardList'];
        $user['cardFlag'] = $userInfo['cardFlag'];
        $user['cost'] = $userInfo['cost'];
        $user['age'] = $userInfo['age'];
        $user['gender'] = $userInfo['gender'];
        $user['birthday'] = $userInfo['birthday'];
        $user['is_set_trade'] = $userInfo['trade_password'] ? 1 : 0;
        $user['card_no'] = $userInfo['card_no'];
        $user['card_id'] = $userInfo['card_id'];
        $user['create_time'] = $userInfo['creattime'];
        $user['telephone'] = $userInfo['phone'];
        return $user;
    }

    /**
     * 获取用户基本信息
     */
    public function getUserInfo($res)
    {
        $cardFlag = 0;  //是否需要绑定/重新绑定
        $cardList = [];
        $sixunModel = new SixunOpera();
        if (!$res['card_no']) {
            $cardList = $sixunModel->getVipInfo($res['phone']);
            $cardFlag = 1;  //是否需要绑定
            if (count($cardList) < 1) {
                $cardNo = 'a' . $res['id'] . time();
                $cardId = 'a' . time() . $res['id'];
                $this->save(['card_no' => $cardNo, 'card_id' => $cardId], ['id' => $res['id']]);
                //写入会员卡信息
                $sixunModel->addVip($res, $cardNo, $cardId);
                $cardFlag = 0;
                $res['card_no'] = $cardNo;
                $res['card_id'] = $cardId;
            }
        } else {
            $cardInfo = $sixunModel->getCardInfo($res['card_id']);
            if (!$cardInfo) {
                $cardList = $sixunModel->getVipInfo($res['phone']);
                $cardFlag = 1;  //是否需要绑定
                if (count($cardList) < 1) {
                    $cardNo = 'a' . $res['id'] . time();
                    $cardId = 'a' . time() . $res['id'];
                    $this->save(['card_no' => $cardNo, 'card_id' => $cardId], ['id' => $res['id']]);
                    //写入会员卡信息
                    $sixunModel->addVip($res, $cardNo, $cardId);
                    $cardFlag = 0;
                    $res['card_no'] = $cardNo;
                    $res['card_id'] = $cardId;
                }
            }else{
                //同步思迅会员余额和积分，重置app用户基本信息
                $this->save(['cost'=>$cardInfo['cost'], 'score'=>$cardInfo['acc_num']], ['id'=>$res['id']]);
                $res['cost'] = $cardInfo['cost'];
                $res['score'] = $cardInfo['acc_num'];
            }
        }
        $res['cardList'] = $cardList;
        $res['cardFlag'] = $cardFlag;
        return $this->userFormat($res);

    }

    /**
     * 根据手机号和密码查询会员
     * @param $telephone
     * @param $password
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checkUser($telephone, $password)
    {
        $result = [
            'msg' => '',
            'status' => ''
        ];
        $res = $this->where(['telephone' => $telephone])->find();
        if ($res) {
            if ($res['password'] == md5($password)) {
                $result['status'] = true;
            } else {
                $result['status'] = false;
                $result['msg'] = '密码错误';
            }
        } else {
            $result['status'] = false;
            $result['msg'] = '用户不存在';
        }
        return $result;
    }

    /**
     * 根据手机号
     * @param $telephone
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checkIsExist($telephone)
    {
        $res = $this->where('phone', 'eq', $telephone)->find();
        if ($res) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 根据会员ID获取会员卡号
     * @return mixed
     */
    public function getCard($user_id)
    {
        $card_id = $this->where('id', 'eq', $user_id)->value('card_id');
        return $card_id;
    }

    /**
     * 会员信息同步
     */
    public function asyncVip()
    {
        $card_id = input('post.card_id') ? input('post.card_id') : CARD_ID;
        $sixunModel = new SixunOpera();
        $cardInfo = $sixunModel->getCardInfo($card_id);
        $data = [];
        $data['score'] = $cardInfo['acc_num'];
        $data['cost'] = $cardInfo['cost'];
        $data['card_id'] = $cardInfo['card_id'];
        $data['card_no'] = $cardInfo['card_flowno'];
        $this->where('id', USER_ID)->find();
        if (!$this->getAttr('username')) {
            $data['username'] = $cardInfo['vip_name'];
        }
        $this->allowField(true)->save($data, ['id' => USER_ID]);
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function addScore($dayScore)
    {
        //积分字段 acc_num
        $user = $this->find(USER_ID);
        $sixunModel = new SixunOpera();
        $card_id = $user->getAttr('card_id');
        $consume_card = $sixunModel->getConsume($card_id);
        if(!$consume_card){
            $sixunModel->addConsume($user['card_id'], '002');
            $consume_card = $sixunModel->getConsume($user['card_id'], '002');
        }
        $acc_num = $consume_card['vip_acc_amount'];
        $acc_num += $dayScore;
        $tod_score = $user->tod_score + $dayScore;
        //同步思迅会员积分
        $sixunModel->set_core($acc_num, $card_id, $consume_card['branch_no']);
        $user->save(['score' => $acc_num, 'tod_score' => $tod_score]);
    }


    /**
     * 用户签到
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function signLog()
    {
        //添加签到记录
        $signModel = new SignLog();
        $data = [
            'userid' => USER_ID,
            'ip' => getIp()
        ];
        $date = date('Y-m-d', time());
        $sign = $signModel->where('userid', USER_ID)->order('create_time desc')->find();
        if ($sign && $date == $sign['create_time']) {
            $this->error = '今日已签到';
            return false;
        }
        $res = $signModel->allowField(true)->save($data);
        if ($res) {
            $scoreSetModel = new ScoreSet();
            $scoreSet = $scoreSetModel->find();
            $dayScore = $scoreSet['day_score'];
            //添加积分变动记录
            \model('score_log')->save(['score'=>$dayScore, 'type'=>1, 'user_id'=>USER_ID, 'desc'=>'签到赠送积分']);
            //增加签到积分
            $this->addScore($dayScore);
            return true;
        } else {
            $this->error = '签到失败';
            return false;
        }
    }


}