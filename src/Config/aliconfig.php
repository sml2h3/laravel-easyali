<?php
return [
    //应用ID,您的APPID。
    'app_id' => "",

    //商户私钥，您的原始格式私钥,一行字符串
    'merchant_private_key' => "",

    //商户应用公钥,一行字符串
    'merchant_public_key' => "",

    //支付宝公钥,两种方式输入,二选一。查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥,二选一
    'alipay_public_key' => "",

    'alipay_public_key_path' => "",

    //编码格式只支持GBK。
    'charset' => "GBK",

    //支付宝网关
    'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

    //签名方式
    'sign_type'=> "RSA2",

    //授权回调页面
    'redirect_uri' => "",

    //接口权限值，目前只支持auth_user（获取用户信息、网站支付宝登录）、auth_base（用户信息授权）、auth_ecard（商户会员卡）、auth_invoice_info（支付宝闪电开票）、auth_puc_charge（生活缴费）五个值;多个scope时用”,”分隔，如scope为”auth_user,auth_ecard”时，此时获取到的access_token，既可以用来获取用户信息，又可以给用户发送会员卡。
    'scope' => "auth_user,auth_ecard",
];