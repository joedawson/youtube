<?php

/**
 * Route URI's
 */
Route::group(['prefix' => config('youtube.routes.prefix'), 'namespace' => '\Dawson\Youtube'], function() {

    /**
     * Authentication
     */
    Route::get(
        config('youtube.routes.authentication_uri'),
        'YoutubeController@authentication'
    )->middleware(config('youtube.routes.authentication_uri_middleware'));

    /**
     * Callback from google
     */
    Route::get(config('youtube.routes.redirect_uri'), 'YoutubeController@redirect')->middleware(config('youtube.routes.redirect_back_uri_middleware'));
});
