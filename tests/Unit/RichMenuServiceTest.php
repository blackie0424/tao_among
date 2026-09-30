<?php

use App\Services\RichMenuService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

function richMenuServiceWithResponses(array $responses, array &$history): RichMenuService
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    $client = new Client(['handler' => $stack]);
    $service = (new ReflectionClass(RichMenuService::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty($service, 'httpClient');
    $property->setValue($service, $client);

    return $service;
}

it('setDefault 使用 LINE 官方設定預設 endpoint', function () {
    $history = [];
    $service = richMenuServiceWithResponses([new Response(200)], $history);

    $service->setDefault('richmenu-123');

    expect($history)->toHaveCount(1)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())
        ->toBe('/v2/bot/user/all/richmenu/richmenu-123');
});

it('getDefaultRichMenuId 查詢並回傳目前預設 ID', function () {
    $history = [];
    $service = richMenuServiceWithResponses([
        new Response(200, [], json_encode(['richMenuId' => 'richmenu-current'])),
    ], $history);

    expect($service->getDefaultRichMenuId())->toBe('richmenu-current')
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/v2/bot/user/all/richmenu');
});

it('getDefaultRichMenuId 在未設定時回傳 null', function () {
    $history = [];
    $service = richMenuServiceWithResponses([new Response(404)], $history);

    expect($service->getDefaultRichMenuId())->toBeNull();
});

it('getDefaultRichMenuId 在 OA Manager 設定預設時拋出明確錯誤', function () {
    $history = [];
    $service = richMenuServiceWithResponses([new Response(403)], $history);

    expect(fn () => $service->getDefaultRichMenuId())
        ->toThrow(RuntimeException::class, 'Official Account Manager');
});

it('clearDefault 使用 LINE 官方取消預設 endpoint', function () {
    $history = [];
    $service = richMenuServiceWithResponses([new Response(200)], $history);

    $service->clearDefault();

    expect($history)->toHaveCount(1)
        ->and($history[0]['request']->getMethod())->toBe('DELETE')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/v2/bot/user/all/richmenu');
});

it('clearDefault 不再吞掉 404', function () {
    $history = [];
    $service = richMenuServiceWithResponses([new Response(404)], $history);

    expect(fn () => $service->clearDefault())->toThrow(ClientException::class);
});
