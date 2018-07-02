<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/22
 * Time: 9:31
 */

namespace app\common\model;


class SendSms
{
    private $ServerApi;
    private $sendType;
    private $error;
    private $phone;
    private $tempId;

    public function __construct($phone, $tempId='', $sendType = '')
    {
        vendor('netease/ServerApi');
        $this->ServerApi = new \ServerAPI(config('netease.appKey'), config('netease.appSecret'));
        $this->phone = $phone;
        $this->sendType = $sendType;
        $this->tempId = $tempId;
    }

    /**
     * 发送验证码
     * @return bool
     */
    public function sendVcode()
    {
        $userModel = new User();
        switch ($this->sendType) {
            case 1:
                $res = $userModel->checkIsExist($this->phone);
                if($res === false){
                    $this->error = '手机号已注册';
                    return false;
                }
                break;
            case 2:

                break;
            case 3:

                break;
            default:
                $this->error = '发送类型错误';
                return false;
                break;
        }
        $res = $this->ServerApi->sendSmsCode($this->tempId, $this->phone);
        if ($res['code'] === 200) {
            return true;
        } else {
            $this->error = $res['msg'];
            return false;
        }
    }

    /**
     * 检验验证码
     * @param $vcode
     * @return bool
     */
    public function checkVcode($vcode)
    {
        $res = $this->ServerApi->verifycode($this->phone, $vcode);
        if($res['code'] === 200){
            return true;
        }else{
            $this->error = $res['code'];
            return false;
        }
    }

    public function getError()
    {
        return $this->error;
    }
}