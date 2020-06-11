<?php

namespace Dawson\Youtube;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dawson\Youtube\Facades\Youtube;

class YoutubeController extends Controller
{
    public function authentication() {
        return redirect()->to(Youtube::createAuthUrl());
    }

    public function redirect(Request $request) {
        if(!$request->has('code')) {
            throw new Exception('$_GET[\'code\'] is not set. Please re-authenticate.');
        }

        $token = Youtube::authenticate($request->get('code'));

        Youtube::setUser(auth()->user());
        Youtube::saveAccessTokenToDB($token);

        return redirect(config('youtube.routes.redirect_back_uri', '/'));
    }
}
