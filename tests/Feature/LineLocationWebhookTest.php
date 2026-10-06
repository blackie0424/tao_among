<?php

use App\Contracts\LineMessagingClientInterface;
use App\Contracts\LineUserServiceInterface;
use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('removes capture location for a viewer through the real LINE webhook route', function () {
    Cache::flush();
    config(['line.channel_secret' => 'test-line-secret']);
    $fish = Fish::factory()->create(['name' => 'Webhook地名魚']);
    CaptureRecord::factory()->create([
        'fish_id' => $fish->id,
        'location' => 'ZZLOCATIONMARK',
        'tribe' => config('fish_options.tribes')[0],
    ]);

    $messages = new ArrayObject();
    $messaging = Mockery::mock(LineMessagingClientInterface::class);
    $messaging->shouldReceive('validateSignature')->once()->andReturnTrue();
    $messaging->shouldReceive('getUserProfile')->once()->andReturn([
        'displayName' => 'Viewer',
        'pictureUrl' => null,
    ]);
    $messaging->shouldReceive('replyMessage')->once()
        ->andReturnUsing(function ($token, $replyMessages) use ($messages): void {
            $messages->exchangeArray($replyMessages);
        });
    $this->app->instance(LineMessagingClientInterface::class, $messaging);

    $lineUsers = Mockery::mock(LineUserServiceInterface::class);
    $lineUsers->shouldReceive('upsert')->once()->andReturn(new User(['role' => 'viewer']));
    $lineUsers->shouldReceive('getRole')->once()->andReturn('viewer');
    $this->app->instance(LineUserServiceInterface::class, $lineUsers);

    $payload = [
        'destination' => 'Udestination',
        'events' => [[
            'type' => 'postback',
            'mode' => 'active',
            'timestamp' => 1720000000000,
            'source' => ['type' => 'user', 'userId' => 'Uviewer'],
            'webhookEventId' => '01WEBHOOKLOCATION',
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
    expect($json)->not->toContain('ZZLOCATIONMARK')
        ->and($json)->not->toContain('📍地點')
        ->and($json)->toContain(config('fish_options.tribes')[0]);
});