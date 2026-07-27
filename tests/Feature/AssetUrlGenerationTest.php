<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;

it('uses the current request host for generated asset urls in local development', function () {
    config()->set('app.env', 'local');
    config()->set('app.url', 'https://dev-linebot.pongsonotao.org');
    config()->set('app.asset_url', 'https://dev-linebot.pongsonotao.org');

    $request = Request::create('https://tao_among.test/dashboard', 'GET');
    $this->app->instance('request', $request);

    $provider = new AppServiceProvider($this->app);
    $provider->boot();

    expect($this->app['url']->to('/build/assets/app.js'))->toBe('https://tao_among.test/build/assets/app.js');
});
