<?php

use App\Contracts\LineMessagingClientInterface;
use App\Contracts\LineUserServiceInterface;
use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('applies location visibility through the real LINE webhook route', function (string $role, bool $shouldSeeLocation) {
    Cache::flush();
    config(['line.channel_secret' => 'test-line-secret']);
    $fish = Fish::factory()->create(['name' => 'Webhook地名魚']);
    CaptureRecord::factory()->create([
        'fish_id' => $fish->id,
        'location' => 'ZZLOCATIONMARK',
        'tribe' => 'ivalino',
    ]);

    $messages = new ArrayObject();
    $messaging = Mockery::mock(LineMessagingClientInterface::class);
    $messaging->shouldReceive('validateSignature')->once()->andReturnTrue();
    $messaging->shouldReceive('getUserProfile')->once()->andReturn([
        'displayName' => ucfirst($role),
        'pictureUrl' => null,
    ]);
    $messaging->shouldReceive('replyMessage')->once()
        ->andReturnUsing(function ($token, $replyMessages) use ($messages): void {
            $messages->exchangeArray($replyMessages);
        });
    $this->app->instance(LineMessagingClientInterface::class, $messaging);

    $lineUsers = Mockery::mock(LineUserServiceInterface::class);
    $lineUsers->shouldReceive('upsert')->once()->andReturn(new User(['role' => $role]));
    $lineUsers->shouldReceive('getRole')->once()->andReturn($role);
    $this->app->instance(LineUserServiceInterface::class, $lineUsers);

    $payload = [
        'destination' => 'Udestination',
        'events' => [[
            'type' => 'postback',
            'mode' => 'active',
            'timestamp' => 1720000000000,
            'source' => ['type' => 'user', 'userId' => "U{$role}"],
            'webhookEventId' => "01WEBHOOKLOCATION{$role}",
            'deliveryContext' => ['isRedelivery' => false],
            'replyToken' => 'reply-token-location',
            'postback' => [
                'data' => http_build_query([
                    'action' => 'view_captures',
                    'fish_id' => $fish->id,
                    'fish_name' => $fish->name,
                ]),
            ],
        ]],
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $signature = base64_encode(hash_hmac('sha256', $body, 'test-line-secret', true));

    $response = $this->call(
        'POST',
        '/prefix/api/line/webhook',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LINE_SIGNATURE' => $signature],
        $body
    );

    $response->assertOk()->assertJson(['status' => 'ok']);
    expect($messages)->toHaveCount(1);
    $json = json_encode($messages[0]->jsonSerialize(), JSON_UNESCAPED_UNICODE);
    expect(str_contains($json, 'ZZLOCATIONMARK'))->toBe($shouldSeeLocation)
        ->and(str_contains($json, '📍地點'))->toBe($shouldSeeLocation)
        ->and($json)->toContain('ivalino');
})->with([
    'viewer' => ['viewer', false],
    'editor' => ['editor', true],
    'admin' => ['admin', true],
]);
