<?php
/**
 * Created by PhpStorm.
 * Oauth: wenanzhe
 * Date: 2017/11/7
 * Time: 08:05
 */
namespace Sml2h3\EasyAli\Gateway;

use Sml2h3\EasyAli\AopClient\AopClient;

class Gateway{

    protected $aop;
    protected $config;
    protected $request;

    public function __construct($config)
    {
        $this->config = $config;
        $this->request = $_REQUEST;
        $this->aop = new AopClient;
    }

    public function verifygw($is_success){
        $biz_content = $this->request['biz_content'];
        $xml = simplexml_load_string($biz_content);
        $EventType = ( string ) $xml->EventType;
        if ($EventType == "verifygw"){
            $this->aop->rsaPrivateKey = $this->config['merchant_private_key'];
            if ($is_success) {
                $response_xml = "<success>true</success><biz_content>" . $this->config ['merchant_public_key'] . "</biz_content>";

            } else { // echo $response_xml;
                $response_xml = "<success>false</success><error_code>VERIFY_FAILED</error_code><biz_content>" . $this->config ['merchant_public_key'] . "</biz_content>";
            }
            $mysign = $this->aop->alonersaSign($response_xml,$this->config['merchant_private_key'],$this->config['sign_type']);
            $return_xml = "<?xml version=\"1.0\" encoding=\"".$this->config['charset']."\"?><alipay><response>".$response_xml."</response><sign>".$mysign."</sign><sign_type>".$this->config['sign_type']."</sign_type></alipay>";
            return $return_xml;
        }
    }
}