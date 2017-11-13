<?php
/**
 *
 *  接收来自阿里的验签、消息以及事件等的统一入口类
 * Created by PhpStorm.
 * Oauth: wenanzhe
 * Date: 2017/11/7
 * Time: 06:14
 */

namespace Sml2h3\EasyAli\Server;
use Sml2h3\EasyAli\AopClient\AopClient;
use Sml2h3\EasyAli\Gateway\Gateway;

class Server{

    protected $request;
    protected $aop;
    protected $config;
    protected $gw;

    public function __construct($config)
    {
        $this->config = $config;
        $this->request = $_REQUEST;
        $this->aop = new AopClient;
        $this->gw = new Gateway($this->config);
    }

    public function server(){
        //验证签名
        $sign_verify = $this->sign_verify();
        if(!$sign_verify){
            if("alipay.service.check" == $this->request['service']){
                //按照官方的要求返回所需要的格式
                return $this->gw->verifygw(false);
            }else{
                return 'sign verify fail.';
            }
        }
        if("alipay.service.check" == $this->request['service']){
            //按照官方的要求返回所需要的格式
            return $this->gw->verifygw(true);
        }else{
            return 'sign verify fail.';
        }
    }

    /**
     * 验签操作
     *
     *
     */
    protected function sign_verify(){
        if($this->aop->checkEmpty($this->config['alipay_public_key'])){
            $this->aop->alipayrsaPublicKey = $this->config['alipay_public_key'];
        }else if($this->aop->checkEmpty($this->config['alipay_public_key_path'])){
            $this->aop->alipayPublicKey = $this->config['alipay_public_key_path'];
        }else{
            return false;
        }
        return $this->aop->rsaCheck($this->request, "RSA2");
    }
}