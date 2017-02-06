<?php

/**
 * Route URI's
 */
Route::group(['prefix' => config('youtube.routes.prefix')], function() {

	/**
	 * Authentication
	 */
	Route::get(config('youtube.routes.authentication_uri'), function()
	{
		return redirect()->to(Youtube::createAuthUrl());
	});

	/**
	 * Redirect
	 */
	Route::get(config('youtube.routes.redirect_uri'), function(Illuminate\Http\Request $request)
	{
		$code = $request->get('code');

		if(is_null($code)) {
			throw new Exception('$_GET[\'code\'] is not set.');
		} else {
			$token = Youtube::authenticate($code);
			Youtube::saveAccessTokenToDB($token);
		}

		return redirect('/');
	});

});