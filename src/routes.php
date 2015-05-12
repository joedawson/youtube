<?php

/**
 * Route URI's
 */
Route::group(['prefix' => config('youtube.route_base_uri')], function() {

	Route::get(config('youtube.authentication_uri'), function()
	{

		return redirect()->to(Youtube::createAuthUrl());

	});

	Route::get(config('youtube.redirect_uri'), function(\Illuminate\Http\Request $request)
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