<?php
/**
 * Created by PhpStorm.
 * Oauth: wenanzhe
 * Date: 16/12/5
 * Time: 04:23
 */

namespace Sml2h3\EasyAli\Foundation\ServiceProviders;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Sml2h3\EasyAli\Card\Card;
class CardServiceProvider implements ServiceProviderInterface
{

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple)
    {

        $pimple['card'] = function ($pimple) {
            $card = new Card(
                $pimple['config']
            );
            return $card;
        };
    }
}