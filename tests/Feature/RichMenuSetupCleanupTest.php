<?php

use App\Services\RichMenuService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;

function richMenuCleanupServiceWithResponses(array $responses, array &$history): RichMenuService
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    $client = new Client(['handler' => $stack]);
    $service = (new ReflectionClass(RichMenuService::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty($service, 'httpClient');
    $property->setValue($service, $client);

    return $service;
}

it('deleteAllMenus 遇 OA Manager 預設時記錄警告並繼續刪除 Messaging API 選單', function () {
    Log::spy();
    $history = [];
    $service = richMenuCleanupServiceWithResponses([
        new Response(403),
        new Response(200, [], json_encode([
            'richmenus' => [['richMenuId' => 'richmenu-api-created']],
        ])),
        new Response(200),
    ], $history);

    $service->deleteAllMenus();

    Log::shouldHaveReceived('warning')->once();
    expect($history)->toHaveCount(3)
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/v2/bot/user/all/richmenu')
        ->and($history[1]['request']->getMethod())->toBe('GET')
        ->and($history[1]['request']->getUri()->getPath())->toBe('/v2/bot/richmenu/list')
        ->and($history[2]['request']->getMethod())->toBe('DELETE')
        ->and($history[2]['request']->getUri()->getPath())
        ->toBe('/v2/bot/richmenu/richmenu-api-created');
});
