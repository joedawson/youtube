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
        $this->publishes([
            __DIR__.'/../config/youtube.php' => config_path('youtube.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../migrations/' => database_path('migrations')
        ], 'migrations');

        include __DIR__.'/../routes/web.php';
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->singleton(Contracts\Youtube::class, function () {
            return new Youtube(new \Google_Client);
        });

        $this->app->singleton('youtube', Contracts\Youtube::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            Contracts\Youtube::class,
            'youtube',
        ];
    }
}
