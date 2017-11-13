<?php
/**
 *
 *  RSA(2)加解密类
 *
 * Created by PhpStorm.
 * Oauth: wenanzhe
 * Date: 2017/11/7
 * Time: 05:45
 */

namespace Sml2h3\EasyAli\AopClient;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Exception;
use Sml2h3\EasyAli\Oauth\EncryptParseItem;
use Sml2h3\EasyAli\Oauth\EncryptResponseData;
use Sml2h3\EasyAli\Oauth\SignData;

class AopClient{
    //应用ID
    public $appId;

    //私钥文件路径
    public $rsaPrivateKeyFilePath;

    //私钥值
    public $rsaPrivateKey;

    //网关地址
    public $gatewayUrl = "https://openapi.alipay.com/gateway.do";

    //返回数据格式
    public $format = "json";
    //api版本
    public $apiVersion = "1.0";

    // 表单提交字符集编码
    public $postCharset = "UTF-8";

    //使用文件读取文件格式，请只传递该值
    public $alipayPublicKey = null;

    //使用读取字符串格式，请只传递该值
    public $alipayrsaPublicKey;


    public $debugInfo = false;

    private $fileCharset = "UTF-8";

    private $RESPONSE_SUFFIX = "_response";

    private $ERROR_RESPONSE = "error_response";

    private $SIGN_NODE_NAME = "sign";


    //加密XML节点名称
    private $ENCRYPT_XML_NODE_NAME = "response_encrypted";

    private $needEncrypt = false;


    //签名类型
    public $signType = "RSA2";

    //加密密钥和类型

    public $encryptKey;

    public $encryptType = "AES";

    protected $alipaySdkVersion = "alipay-sdk-php-20161101";

    /**
     * RSA(2)验签
     * @param $params
     * @param string $sign_type
     * @return boolean
     */

    public function rsaCheck($params, $sign_type = "RSA"){
        $sign = $params['sign'];
        $params['sign'] = null;
        return $this->verify($this->getSignContent($params), $sign, $sign_type);
    }


    /**
     * RSA单独签名方法，未做字符串处理,字符串处理见getSignContent()
     * @param $data 待签名字符串
     * @param $privatekey 商户私钥，根据keyfromfile来判断是读取字符串还是读取文件，false:填写私钥字符串去回车和空格 true:填写私钥文件路径
     * @param $signType 签名方式，RSA:SHA1     RSA2:SHA256
     * @param $keyfromfile 私钥获取方式，读取字符串还是读文件
     * @return string
     * @author mengyu.wh
     */
    public function alonersaSign($data,$privatekey,$signType = "RSA",$keyfromfile=false) {

        if(!$keyfromfile){
            $priKey = $privatekey;
            $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($priKey, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
        }else{
            $priKey = file_get_contents($privatekey);
            $res = openssl_get_privatekey($priKey);
        }

        if(!$res){
            return "请检查秘钥配置";
        }

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }

        if($keyfromfile){
            openssl_free_key($res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }


    /**
     * @param $params
     * @return string
     */

    public function getSignContent($params) {
        ksort($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {

                // 转换成目标字符集
                $v = $this->characet($v, $this->postCharset);

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }

    protected function verify($data, $sign, $signType = 'RSA'){
        if($this->checkEmpty($this->alipayPublicKey)){
            //此时采用当行文本的读取模式
            $pubKey= $this->alipayrsaPublicKey;
            $res = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($pubKey, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";
        }else if($this->checkEmpty($this->alipayrsaPublicKey)){
            //读取公钥文件

            $pubKey = file_get_contents($this->alipayPublicKey);
            //转换为openssl格式密钥
            $res = openssl_get_publickey($pubKey);
        }else{
            return false;
        }
        if("RSA2" == $signType){
            // RSA2加密模式
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
        }else{
            // RSA加密模式
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }

        if(!$this->checkEmpty($this->alipayPublicKey)) {
            //释放资源
            openssl_free_key($res);
        }

        return $result;

    }

    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    public function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }

     /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    protected function characet($data, $targetCharset) {

        if (!empty($data)) {
            $fileType = $this->fileCharset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //				$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }


        return $data;
    }

    public function execute($request, $authToken = null, $appInfoAuthtoken = null) {

        $this->setupCharsets($request);

        //		//  如果两者编码不一致，会出现签名验签或者乱码
        if (strcasecmp($this->fileCharset, $this->postCharset)) {

            // writeLog("本地文件字符集编码与表单提交编码不一致，请务必设置成一样，属性名分别为postCharset!");
            throw new Exception("文件编码：[" . $this->fileCharset . "] 与表单提交编码：[" . $this->postCharset . "]两者不一致!");
        }

        $iv = null;

        if (!$this->checkEmpty($request->getApiVersion())) {
            $iv = $request->getApiVersion();
        } else {
            $iv = $this->apiVersion;
        }


        //组装系统参数
        $sysParams["app_id"] = $this->appId;
        $sysParams["version"] = $iv;
        $sysParams["format"] = $this->format;
        $sysParams["sign_type"] = $this->signType;
        $sysParams["method"] = $request->getApiMethodName();
        $sysParams["timestamp"] = date("Y-m-d H:i:s");
        $sysParams["auth_token"] = $authToken;
        $sysParams["alipay_sdk"] = $this->alipaySdkVersion;
        $sysParams["terminal_type"] = $request->getTerminalType();
        $sysParams["terminal_info"] = $request->getTerminalInfo();
        $sysParams["prod_code"] = $request->getProdCode();
        $sysParams["notify_url"] = $request->getNotifyUrl();
        $sysParams["charset"] = $this->postCharset;
        $sysParams["app_auth_token"] = $appInfoAuthtoken;


        //获取业务参数
        $apiParams = $request->getApiParas();
        $apiParams = array_merge($sysParams,$apiParams);

        if (method_exists($request,"getNeedEncrypt") &&$request->getNeedEncrypt()){

            $sysParams["encrypt_type"] = $this->encryptType;

            if ($this->checkEmpty($apiParams['biz_content'])) {

                throw new Exception(" api request Fail! The reason : encrypt request is not supperted!");
            }

            if ($this->checkEmpty($this->encryptKey) || $this->checkEmpty($this->encryptType)) {

                throw new Exception(" encryptType and encryptKey must not null! ");
            }

            if ("AES" != $this->encryptType) {

                throw new Exception("加密类型只支持AES");
            }

            // 执行加密
            $enCryptContent = encrypt($apiParams['biz_content'], $this->encryptKey);
            $apiParams['biz_content'] = $enCryptContent;

        }

        //签名
        $sysParams["sign"] = $this->generateSign($apiParams, $this->signType);
//        return $apiParams;
        //系统参数放入GET请求串
        $requestUrl = $this->gatewayUrl . "?";
        foreach ($sysParams as $sysParamKey => $sysParamValue) {
            $requestUrl .= "$sysParamKey=" . urlencode($this->characet($sysParamValue, $this->postCharset)) . "&";
        }
        $requestUrl = substr($requestUrl, 0, -1);

        //发起HTTP请求
        try {
            $resp = $this->curl($requestUrl, $apiParams);
        } catch (Exception $e) {

//            $this->logCommunicationError($sysParams["method"], $requestUrl, "HTTP_ERROR_" . $e->getCode(), $e->getMessage());
            return false;
        }

        //解析AOP返回结果
        $respWellFormed = false;


        // 将返回结果转换本地文件编码
        $r = iconv($this->postCharset, $this->fileCharset . "//IGNORE", $resp);



        $signData = null;

        if ("json" == $this->format) {

            $respObject = json_decode($r);
            if (null !== $respObject) {
                $respWellFormed = true;
                $signData = $this->parserJSONSignData($request, $resp, $respObject);
            }
        } else if ("xml" == $this->format) {

            $respObject = @ simplexml_load_string($resp);
            if (false !== $respObject) {
                $respWellFormed = true;

                $signData = $this->parserXMLSignData($request, $resp);
            }
        }


        //返回的HTTP文本不是标准JSON或者XML，记下错误日志
        if (false === $respWellFormed) {
//            $this->logCommunicationError($sysParams["method"], $requestUrl, "HTTP_RESPONSE_NOT_WELL_FORMED", $resp);
            return false;
        }

        // 验签
        $this->checkResponseSign($request, $signData, $resp, $respObject);

        // 解密
        if (method_exists($request,"getNeedEncrypt") &&$request->getNeedEncrypt()){

            if ("json" == $this->format) {


                $resp = $this->encryptJSONSignSource($request, $resp);

                // 将返回结果转换本地文件编码
                $r = iconv($this->postCharset, $this->fileCharset . "//IGNORE", $resp);
                $respObject = json_decode($r);
            }else{

                $resp = $this->encryptXMLSignSource($request, $resp);

                $r = iconv($this->postCharset, $this->fileCharset . "//IGNORE", $resp);
                $respObject = @ simplexml_load_string($r);

            }
        }

        return $respObject;
    }

    protected function curl($url, $postFields = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $postBodyString = "";
        $encodeArray = Array();
        $postMultipart = false;


        if (is_array($postFields) && 0 < count($postFields)) {

            foreach ($postFields as $k => $v) {
                if ("@" != substr($v, 0, 1)) //判断是不是文件上传
                {

                    $postBodyString .= "$k=" . urlencode($this->characet($v, $this->postCharset)) . "&";
                    $encodeArray[$k] = $this->characet($v, $this->postCharset);
                } else //文件上传用multipart/form-data，否则用www-form-urlencoded
                {
                    $postMultipart = true;
                    $encodeArray[$k] = new \CURLFile(substr($v, 1));
                }

            }
            unset ($k, $v);
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postMultipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodeArray);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString, 0, -1));
            }
        }

        if ($postMultipart) {

            $headers = array('content-type: multipart/form-data;charset=' . $this->postCharset . ';boundary=' . $this->getMillisecond());
        } else {

            $headers = array('content-type: application/x-www-form-urlencoded;charset=' . $this->postCharset);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);




        $reponse = curl_exec($ch);

        if (curl_errno($ch)) {

            throw new Exception(curl_error($ch), 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode) {
                throw new Exception($reponse, $httpStatusCode);
            }
        }

        curl_close($ch);
        return $reponse;
    }

    protected function getMillisecond() {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }

    public function parserXMLSignData($request, $responseContent) {


        $signData = new SignData();

        $signData->sign = $this->parserXMLSign($responseContent);
        $signData->signSourceData = $this->parserXMLSignSource($request, $responseContent);


        return $signData;


    }
    public function parserXMLSignSource($request, $responseContent) {


        $apiName = $request->getApiMethodName();
        $rootNodeName = str_replace(".", "_", $apiName) . $this->RESPONSE_SUFFIX;


        $rootIndex = strpos($responseContent, $rootNodeName);
        $errorIndex = strpos($responseContent, $this->ERROR_RESPONSE);
        //		$this->echoDebug("<br/>rootNodeName:" . $rootNodeName);
        //		$this->echoDebug("<br/> responseContent:<xmp>" . $responseContent . "</xmp>");


        if ($rootIndex > 0) {

            return $this->parserXMLSource($responseContent, $rootNodeName, $rootIndex);
        } else if ($errorIndex > 0) {

            return $this->parserXMLSource($responseContent, $this->ERROR_RESPONSE, $errorIndex);
        } else {

            return null;
        }


    }
    public function parserXMLSource($responseContent, $nodeName, $nodeIndex) {
        $signDataStartIndex = $nodeIndex + strlen($nodeName) + 1;
        $signIndex = strpos($responseContent, "<" . $this->SIGN_NODE_NAME . ">");
        // 签名前-逗号
        $signDataEndIndex = $signIndex - 1;
        $indexLen = $signDataEndIndex - $signDataStartIndex + 1;

        if ($indexLen < 0) {
            return null;
        }


        return substr($responseContent, $signDataStartIndex, $indexLen);


    }
    public function parserJSONSignData($request, $responseContent, $responseJSON) {

        $signData = new SignData();

        $signData->sign = $this->parserJSONSign($responseJSON);
        $signData->signSourceData = $this->parserJSONSignSource($request, $responseContent);


        return $signData;

    }
    public 	function parserJSONSource($responseContent, $nodeName, $nodeIndex) {
        $signDataStartIndex = $nodeIndex + strlen($nodeName) + 2;
        $signIndex = strpos($responseContent, "\"" . $this->SIGN_NODE_NAME . "\"");
        // 签名前-逗号
        $signDataEndIndex = $signIndex - 1;
        $indexLen = $signDataEndIndex - $signDataStartIndex;
        if ($indexLen < 0) {

            return null;
        }

        return substr($responseContent, $signDataStartIndex, $indexLen);

    }

    public function parserJSONSignSource($request, $responseContent) {

        $apiName = $request->getApiMethodName();
        $rootNodeName = str_replace(".", "_", $apiName) . $this->RESPONSE_SUFFIX;

        $rootIndex = strpos($responseContent, $rootNodeName);
        $errorIndex = strpos($responseContent, $this->ERROR_RESPONSE);


        if ($rootIndex > 0) {

            return $this->parserJSONSource($responseContent, $rootNodeName, $rootIndex);
        } else if ($errorIndex > 0) {

            return $this->parserJSONSource($responseContent, $this->ERROR_RESPONSE, $errorIndex);
        } else {

            return null;
        }


    }

    public function parserJSONSign($responseJSon) {

        return $responseJSon->sign;
    }


    public function parserXMLSign($responseContent) {
        $signNodeName = "<" . $this->SIGN_NODE_NAME . ">";
        $signEndNodeName = "</" . $this->SIGN_NODE_NAME . ">";

        $indexOfSignNode = strpos($responseContent, $signNodeName);
        $indexOfSignEndNode = strpos($responseContent, $signEndNodeName);


        if ($indexOfSignNode < 0 || $indexOfSignEndNode < 0) {
            return null;
        }

        $nodeIndex = ($indexOfSignNode + strlen($signNodeName));

        $indexLen = $indexOfSignEndNode - $nodeIndex;

        if ($indexLen < 0) {
            return null;
        }

        // 签名
        return substr($responseContent, $nodeIndex, $indexLen);

    }
    /**
     * 验签
     * @param $request
     * @param $signData
     * @param $resp
     * @param $respObject
     * @throws Exception
     */
    public function checkResponseSign($request, $signData, $resp, $respObject) {

        if (!$this->checkEmpty($this->alipayPublicKey) || !$this->checkEmpty($this->alipayrsaPublicKey)) {


            if ($signData == null || $this->checkEmpty($signData->sign) || $this->checkEmpty($signData->signSourceData)) {

                throw new Exception(" check sign Fail! The reason : signData is Empty");
            }


            // 获取结果sub_code
            $responseSubCode = $this->parserResponseSubCode($request, $resp, $respObject, $this->format);


            if (!$this->checkEmpty($responseSubCode) || ($this->checkEmpty($responseSubCode) && !$this->checkEmpty($signData->sign))) {

                $checkResult = $this->verify($signData->signSourceData, $signData->sign, $this->signType);


                if (!$checkResult) {

                    if (strpos($signData->signSourceData, "\\/") > 0) {

                        $signData->signSourceData = str_replace("\\/", "/", $signData->signSourceData);

                        $checkResult = $this->verify($signData->signSourceData, $signData->sign, $this->signType);

                        if (!$checkResult) {
                            throw new Exception("check sign Fail! [sign=" . $signData->sign . ", signSourceData=" . $signData->signSourceData . "]");
                        }

                    } else {

                        throw new Exception("check sign Fail! [sign=" . $signData->sign . ", signSourceData=" . $signData->signSourceData . "]");
                    }

                }
            }


        }
    }

    function parserResponseSubCode($request, $responseContent, $respObject, $format) {

        if ("json" == $format) {

            $apiName = $request->getApiMethodName();
            $rootNodeName = str_replace(".", "_", $apiName) . $this->RESPONSE_SUFFIX;
            $errorNodeName = $this->ERROR_RESPONSE;

            $rootIndex = strpos($responseContent, $rootNodeName);
            $errorIndex = strpos($responseContent, $errorNodeName);

            if ($rootIndex > 0) {
                // 内部节点对象
                $rInnerObject = $respObject->$rootNodeName;
            } elseif ($errorIndex > 0) {

                $rInnerObject = $respObject->$errorNodeName;
            } else {
                return null;
            }

            // 存在属性则返回对应值
            if (isset($rInnerObject->sub_code)) {

                return $rInnerObject->sub_code;
            } else {

                return null;
            }


        } elseif ("xml" == $format) {

            // xml格式sub_code在同一层级
            return $respObject->sub_code;

        }


    }

    // 获取加密内容

    private function encryptJSONSignSource($request, $responseContent) {

        $parsetItem = $this->parserEncryptJSONSignSource($request, $responseContent);

        $bodyIndexContent = substr($responseContent, 0, $parsetItem->startIndex);
        $bodyEndContent = substr($responseContent, $parsetItem->endIndex, strlen($responseContent) + 1 - $parsetItem->endIndex);

        $bizContent = decrypt($parsetItem->encryptContent, $this->encryptKey);
        return $bodyIndexContent . $bizContent . $bodyEndContent;

    }


    private function parserEncryptJSONSignSource($request, $responseContent) {

        $apiName = $request->getApiMethodName();
        $rootNodeName = str_replace(".", "_", $apiName) . $this->RESPONSE_SUFFIX;

        $rootIndex = strpos($responseContent, $rootNodeName);
        $errorIndex = strpos($responseContent, $this->ERROR_RESPONSE);


        if ($rootIndex > 0) {

            return $this->parserEncryptJSONItem($responseContent, $rootNodeName, $rootIndex);
        } else if ($errorIndex > 0) {

            return $this->parserEncryptJSONItem($responseContent, $this->ERROR_RESPONSE, $errorIndex);
        } else {

            return null;
        }


    }


    private function parserEncryptJSONItem($responseContent, $nodeName, $nodeIndex) {
        $signDataStartIndex = $nodeIndex + strlen($nodeName) + 2;
        $signIndex = strpos($responseContent, "\"" . $this->SIGN_NODE_NAME . "\"");
        // 签名前-逗号
        $signDataEndIndex = $signIndex - 1;

        if ($signDataEndIndex < 0) {

            $signDataEndIndex = strlen($responseContent)-1 ;
        }

        $indexLen = $signDataEndIndex - $signDataStartIndex;

        $encContent = substr($responseContent, $signDataStartIndex+1, $indexLen-2);


        $encryptParseItem = new EncryptParseItem();

        $encryptParseItem->encryptContent = $encContent;
        $encryptParseItem->startIndex = $signDataStartIndex;
        $encryptParseItem->endIndex = $signDataEndIndex;

        return $encryptParseItem;

    }

    // 获取加密内容

    private function encryptXMLSignSource($request, $responseContent) {

        $parsetItem = $this->parserEncryptXMLSignSource($request, $responseContent);

        $bodyIndexContent = substr($responseContent, 0, $parsetItem->startIndex);
        $bodyEndContent = substr($responseContent, $parsetItem->endIndex, strlen($responseContent) + 1 - $parsetItem->endIndex);
        $bizContent = decrypt($parsetItem->encryptContent, $this->encryptKey);

        return $bodyIndexContent . $bizContent . $bodyEndContent;

    }

    private function parserEncryptXMLSignSource($request, $responseContent) {


        $apiName = $request->getApiMethodName();
        $rootNodeName = str_replace(".", "_", $apiName) . $this->RESPONSE_SUFFIX;


        $rootIndex = strpos($responseContent, $rootNodeName);
        $errorIndex = strpos($responseContent, $this->ERROR_RESPONSE);
        //		$this->echoDebug("<br/>rootNodeName:" . $rootNodeName);
        //		$this->echoDebug("<br/> responseContent:<xmp>" . $responseContent . "</xmp>");


        if ($rootIndex > 0) {

            return $this->parserEncryptXMLItem($responseContent, $rootNodeName, $rootIndex);
        } else if ($errorIndex > 0) {

            return $this->parserEncryptXMLItem($responseContent, $this->ERROR_RESPONSE, $errorIndex);
        } else {

            return null;
        }


    }

    private function parserEncryptXMLItem($responseContent, $nodeName, $nodeIndex) {

        $signDataStartIndex = $nodeIndex + strlen($nodeName) + 1;

        $xmlStartNode="<".$this->ENCRYPT_XML_NODE_NAME.">";
        $xmlEndNode="</".$this->ENCRYPT_XML_NODE_NAME.">";

        $indexOfXmlNode=strpos($responseContent,$xmlEndNode);
        if($indexOfXmlNode<0){

            $item = new EncryptParseItem();
            $item->encryptContent = null;
            $item->startIndex = 0;
            $item->endIndex = 0;
            return $item;
        }

        $startIndex=$signDataStartIndex+strlen($xmlStartNode);
        $bizContentLen=$indexOfXmlNode-$startIndex;
        $bizContent=substr($responseContent,$startIndex,$bizContentLen);

        $encryptParseItem = new EncryptParseItem();
        $encryptParseItem->encryptContent = $bizContent;
        $encryptParseItem->startIndex = $signDataStartIndex;
        $encryptParseItem->endIndex = $indexOfXmlNode+strlen($xmlEndNode);

        return $encryptParseItem;

    }


    function echoDebug($content) {

        if ($this->debugInfo) {
            echo "<br/>" . $content;
        }

    }



    public function aopclient_request_execute($request, $config, $token = NULL){
        $this->gatewayUrl = $config['gatewayUrl'];
        $this->appId = $config ['app_id'];
        $this->rsaPrivateKey = $config['merchant_private_key'];
        $this->alipayrsaPublicKey = $config['alipay_public_key'];
        $this->signType = $config['sign_type'];
        $this->apiVersion = "1.0";
        return $this->execute($request, $token);
    }

    private function setupCharsets($request) {
        if ($this->checkEmpty($this->postCharset)) {
            $this->postCharset = 'UTF-8';
        }
        $str = preg_match('/[\x80-\xff]/', $this->appId) ? $this->appId : print_r($request, true);
        $this->fileCharset = mb_detect_encoding($str, "UTF-8, GBK") == 'UTF-8' ? 'UTF-8' : 'GBK';
    }

    public function generateSign($params, $signType = "RSA") {
        return $this->sign($this->getSignContent($params), $signType);
    }

    protected function sign($data, $signType = "RSA") {
        if($this->checkEmpty($this->rsaPrivateKeyFilePath)){
            $priKey=$this->rsaPrivateKey;
            $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($priKey, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
        }else {
            $priKey = file_get_contents($this->rsaPrivateKeyFilePath);
            $res = openssl_get_privatekey($priKey);
        }

        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }

        if(!$this->checkEmpty($this->rsaPrivateKeyFilePath)){
            openssl_free_key($res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }

}