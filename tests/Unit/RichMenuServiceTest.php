<?php

use App\Services\RichMenuService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

it('clearDefault 呼叫 LINE 的取消預設 endpoint', function () {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200)]));
    $stack->push(Middleware::history($history));
    $client = new Client(['handler' => $stack]);

    $service = (new ReflectionClass(RichMenuService::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty($service, 'httpClient');
    $property->setValue($service, $client);

    $service->clearDefault();

    expect($history)->toHaveCount(1)
        ->and($history[0]['request']->getMethod())->toBe('DELETE')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/v2/bot/richmenu/default');
});
