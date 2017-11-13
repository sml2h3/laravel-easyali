<?php
/**
 * Created by PhpStorm.
 * User: wenanzhe
 * Date: 2017/11/13
 * Time: 01:59
 */
namespace Sml2h3\EasyAli\Material;
use Sml2h3\EasyAli\AopClient\AopClient;
use Sml2h3\EasyAli\Material\AlipayOfflineMaterialImageUploadRequest;
class ImageUpload{
    /**
     *  上传图片文件
     */
    public $image_upload;
    public $aop;
    public $config;

    public function __construct($config)
    {
        $this->config = $config;
        $this->image_upload = new AlipayOfflineMaterialImageUploadRequest();
        $this->aop = new AopClient();
    }

    public function setImageType($type = "jpg"){
        $this->image_upload->setImageType($type);
    }

    public function setImageName($name = ""){
        $this->image_upload->setImageName($name);
    }

    public function setImageContent($path){
        $this->image_upload->setImageContent('@'.$path);
    }

    public function setImagePid($pid){
        $this->setImagePid($pid);
    }

    public function execute(){
        $result = $this->aop->aopclient_request_execute($this->image_upload, $this->config);
        return $result;
    }

}