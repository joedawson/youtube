<?php

Route::get(config('youtube.redirect_uri'), function(\Illuminate\Http\Request $request) {

	if(is_null($request->get('code')))
	{
		return redirect()->to('/');
	}

	dd($request->all());

});