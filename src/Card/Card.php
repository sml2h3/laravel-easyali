<?php
/**
 * Created by PhpStorm.
 * User: wenanzhe
 * Date: 2017/11/13
 * Time: 03:20
 */
namespace Sml2h3\EasyAli\Card;
use Sml2h3\EasyAli\AopClient\AopClient;
use Sml2h3\EasyAli\Card\AlipayMarketingCardTemplateCreateRequest;
use Sml2h3\EasyAli\Card\AlipayMarketingCardOpenRequest;

class Card{
    protected $config;
    protected $card_create;
    protected $card_open;
    protected $aop;
    protected $setting = array();

    public function __construct($config)
    {
        $this->config = $config;
        $this->aop = new AopClient();
        $this->card_open = new AlipayMarketingCardOpenRequest();
        $this->card_create = new AlipayMarketingCardTemplateCreateRequest();
    }
    public function create($baseInfo = array()){
        $this->setting = $baseInfo;
        $this->card_create->setBizContent(json_encode($this->setting,JSON_UNESCAPED_UNICODE));
        return $this->aop->aopclient_request_execute($this->card_create,$this->config);
    }
    public function open($access_token, $baseInfo = array()){
        $this->setting = $baseInfo;
        $this->card_open->setBizContent(json_encode($this->setting,JSON_UNESCAPED_UNICODE));
        return $this->aop->aopclient_request_execute($this->card_open,$this->config, $access_token);
    }
}