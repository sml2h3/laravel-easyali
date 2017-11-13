<?php
/**
 *
 * 获取用户信息类
 * Created by PhpStorm.
 * Oauth: wenanzhe
 * Date: 2017/11/8
 * Time: 06:25
 */

namespace Sml2h3\EasyAli\Oauth;
use Sml2h3\EasyAli\AopClient\AopClient;
use Sml2h3\EasyAli\Oauth\SystemOauth;
use Sml2h3\EasyAli\User\UserShare;

class Oauth{
    /** Request 参数
     * @var
     */

    protected $request;
    /** AopClient类
     * @var AopClient
     */
    protected $aop;

    protected $sys_oauth;

    protected $user_info;

    /** 配置文件
     * @var
     */
    protected $config;

    /** 授权模式 */
    protected $scope;

    /**
     * 获取auth_token
     * @var string
     */
    protected $auth_code_url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm";


    public function __construct($config)
    {
        $this->config = $config;
        $this->scope = $this->config['scope'];
        $this->request = $_REQUEST;

        $this->aop = new AopClient;
        $this->sys_oauth = new SystemOauth();
        $this->user_info = new UserShare();
    }


    protected function getToken($auth_token){
        if(!$this->aop->checkEmpty($auth_token)){
            $this->sys_oauth = new SystemOauth();
            $this->sys_oauth->setCode($auth_token);
            $this->sys_oauth->setGrantType("authorization_code");
            $result = $this->aop->aopclient_request_execute($this->sys_oauth, $this->config);
            return $result;
        }else{
            return "缺少app_auth_code参数，请详细阅读文档";
        }
    }

    protected function getUserInfo($access_token){
        $result = $this->aop->aopclient_request_execute($this->user_info, $this->config, $access_token);
        return $result;
    }

    public function user(){
        $auth_code = $this->request['auth_code'];
        $token = $this->getToken($auth_code);
        return $token;
        if('10000' != $access_token = $token->alipay_system_oauth_token_response->code){
            return $token->alipay_system_oauth_token_response->sub_msg;
        }
        $access_token = $token->alipay_system_oauth_token_response->access_token;


        $userinfo = $this->getUserInfo($access_token);
        if('10000' != $userinfo->alipay_user_info_share_response->code){
            return $token->alipay_user_info_share_response->sub_msg;
        }

        return $userinfo;
    }

    /**
     * 获取Auth_Code
     */
    public function redirect(){
        $auth_token_url = $this->get_auth_token_url();
        if(false !== $auth_token_url){
            return redirect()->to($auth_token_url);
        }else{
            return "缺少参数";
        }
    }

    protected function get_auth_token_url(){
        $redirect_uri = $this->config['redirect_uri'];
        $appid = $this->config['app_id'];
        if($this->aop->checkEmpty($redirect_uri)){
            //记录日志
            return false;
        }
        if($this->aop->checkEmpty($appid)){
            //记录日志
            return false;
        }
        return $this->auth_code_url."?app_id=".$appid."&scope=".$this->scope."&redirect_uri=".urlencode($redirect_uri);
    }
}