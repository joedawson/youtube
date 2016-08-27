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
	 * @return void
	 */
	public function boot()
    {
		$this->publishes([
			__DIR__.'/config/config.php' => config_path('youtube.php'),
		], 'config');

		$this->publishes([
			__DIR__.'/migrations/' => database_path('/migrations')
		], 'migrations');

		include __DIR__.'/config/routes.php';
    }

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['youtube'] = $this->app->share(function()
		{
			return new Youtube(new \Google_Client);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return ['youtube'];
	}

}
