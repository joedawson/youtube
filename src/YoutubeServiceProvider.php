<?php

namespace Dawson\Youtube;

use Illuminate\Support\ServiceProvider;

class YoutubeServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        $config = realpath(__DIR__.'/../config/youtube.php');

        $this->publishes([$config => config_path('youtube.php')], 'config');

        $this->mergeConfigFrom($config, 'youtube');

        $this->publishes([
            __DIR__.'/../migrations/' => database_path('migrations')
        ], 'migrations');

        if($this->app->config->get('youtube.routes.enabled')) {
            include __DIR__.'/../routes/web.php';
        }
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->singleton('youtube', function($app) {
            return new Youtube($app, new \Google_Client);
        });
    }
}