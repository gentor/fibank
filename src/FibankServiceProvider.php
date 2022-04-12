<?php

namespace Gentor\Fibank;


use Illuminate\Support\ServiceProvider;
use Gentor\Fibank\Service\Ecomm;

/**
 * Class FibankServiceProvider
 *
 * @package Gentor\Fibank
 */
class FibankServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('fibank', function ($app) {
            return new Ecomm($app['config']['fibank']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['fibank'];
    }

}