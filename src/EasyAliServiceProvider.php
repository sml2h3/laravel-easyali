<?php
namespace Sml2h3\EasyAli;
use Illuminate\Support\ServiceProvider;
use Sml2h3\EasyAli\Foundation\Application;
class EasyAliServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/Config/aliconfig.php' => config_path('aliconfig.php'),
        ]);
    }
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Application::class, function ($app) {
            $app = new Application(config('aliconfig'));
            return $app;
        });

        $this->app->alias(Application::class, 'easyali');

    }
}