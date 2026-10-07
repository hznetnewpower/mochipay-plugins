<?php
namespace MochiPay\Bagisto\Providers;
use Illuminate\Support\ServiceProvider;
class MochiPayServiceProvider extends ServiceProvider
{
    public function register()
    {
        require_once __DIR__.'/../lib/bootstrap.php';
        $this->mergeConfigFrom(__DIR__.'/../Config/paymentmethods.php','payment_methods');
        $this->app['config']->set('core',array_merge($this->app['config']->get('core',[]),require __DIR__.'/../Config/system.php'));
    }
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang','mochipay');
    }
}
