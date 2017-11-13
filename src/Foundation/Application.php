<?php
/**
 * Created by PhpStorm.
 * Oauth: wenanzhe
 * Date: 16/12/5
 * Time: 04:21
 */

namespace Sml2h3\EasyAli\Foundation;

use Illuminate\Support\ServiceProvider;
use Pimple\Container;

class Application extends Container
{
    /**
     * Service Providers.
     *
     * @var array
     */
    protected $providers = [
        ServiceProviders\ServerServiceProvider::class,
        ServiceProviders\OauthServiceProvider::class,
        ServiceProviders\MaterialServiceProvider::class,
        ServiceProviders\CardServiceProvider::class,
    ];

    /**
     * Application constructor.
     * @param array $config
     * 此处代码借鉴EASYWECHAT
     */
    public function __construct($config)
    {
        parent::__construct();

        $this['config'] = function () use ($config) {
            return new Config($config);
        };

        $this->registerProviders();

    }
    /**
     * Add a provider.
     *
     * @param string $provider
     *
     * @return Application
     */
    public function addProvider($provider)
    {
        array_push($this->providers, $provider);

        return $this;
    }

    /**
     * Set providers.
     *
     * @param array $providers
     */
    public function setProviders(array $providers)
    {
        $this->providers = [];

        foreach ($providers as $provider) {
            $this->addProvider($provider);
        }
    }

    /**
     * Return all providers.
     *
     * @return array
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * Magic get access.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function __get($id)
    {
        return $this->offsetGet($id);
    }

    /**
     * Magic set access.
     *
     * @param string $id
     * @param mixed  $value
     */
    public function __set($id, $value)
    {
        $this->offsetSet($id, $value);
    }


    /**
     * @param void
     *
     */
    protected function registerProviders(){
        foreach ($this->providers as $provider){
            $this->register(new $provider());
        }
    }
}