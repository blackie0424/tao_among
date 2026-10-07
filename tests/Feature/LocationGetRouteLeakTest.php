<?php

use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\Topic;
use App\Models\TopicItem;
use App\Models\TribalClassification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('classifies every GET route and scans viewer data routes for location leaks', function () {
    $fish = Fish::factory()->create(['name' => '路由掃描魚']);
    CaptureRecord::factory()->create([
        'fish_id' => $fish->id,
        'location' => 'ZZLOCATIONMARK',
        'tribe' => 'ivalino',
    ]);
    $topic = Topic::factory()->create(['slug' => 'route-scan-topic', 'is_published' => true]);
    $topicItem = TopicItem::factory()->create(['topic_id' => $topic->id, 'is_published' => true]);
    $classification = TribalClassification::factory()->create(['fish_id' => $fish->id, 'tribe' => 'ivalino']);
    $viewer = User::factory()->lineViewer()->create();
    $editor = User::factory()->lineEditor()->create();

    $scan = [
        '/' => '/',
        'dashboard' => '/dashboard',
        'fish/{id}' => "/fish/{$fish->id}",
        'fish/{id}/capture-records' => "/fish/{$fish->id}/capture-records",
        'fish/{id}/knowledge' => "/fish/{$fish->id}/knowledge",
        'fish/{id}/knowledge-list' => "/fish/{$fish->id}/knowledge-list",
        'fish/{id}/reference-knowledge' => "/fish/{$fish->id}/reference-knowledge",
        'fish/{id}/tribal-classifications' => "/fish/{$fish->id}/tribal-classifications",
        'login' => '/login',
        'fishs' => '/fishs',
        'search' => '/search',
        'prefix/api/capture-records' => '/prefix/api/capture-records',
        'prefix/api/fish' => '/prefix/api/fish',
        'prefix/api/fish/{id}' => "/prefix/api/fish/{$fish->id}",
        'prefix/api/fish/{id}/compact' => "/prefix/api/fish/{$fish->id}/compact",
        'prefix/api/fish/{id}/notes' => "/prefix/api/fish/{$fish->id}/notes",
        'prefix/api/fish/{fish_id}/tribal-classifications' => "/prefix/api/fish/{$fish->id}/tribal-classifications",
        'prefix/api/fishs/latest-at' => '/prefix/api/fishs/latest-at',
        'prefix/api/health-check' => '/prefix/api/health-check',
        'prefix/api/topics' => '/prefix/api/topics',
        'prefix/api/tribal-classifications/{id}' => "/prefix/api/tribal-classifications/{$classification->id}",
        'prefix/api/user' => '/prefix/api/user',
        'prefix/api/fishs/filter' => '/prefix/api/fishs/filter?filter_type=tribe&filter_value='.'ivalino',
        'prefix/api/fishs/random' => '/prefix/api/fishs/random',
        'prefix/api/fishs/random-unknown' => '/prefix/api/fishs/random-unknown',
        'prefix/api/fishs/search' => '/prefix/api/fishs/search?q='.urlencode($fish->name),
        'topics/{slug}' => "/topics/{$topic->slug}",
        'topics/{slug}/{itemId}' => "/topics/{$topic->slug}/{$topicItem->id}",
    ];

    $excluded = [
        'admin' => 'admin only',
        'admin/references' => 'admin only',
        'admin/references/create' => 'admin only',
        'admin/references/{reference}/edit' => 'admin only and requires a reference',
        'admin/topic-items' => 'admin only',
        'admin/topic-items/create' => 'admin only',
        'admin/topic-items/{topicItem}/edit' => 'admin only',
        'admin/topics' => 'admin only',
        'admin/topics/{topic}/edit' => 'admin only',
        'api/oauth2-callback' => 'Swagger OAuth integration',
        'auth/line' => 'external LINE OAuth redirect',
        'auth/line/callback' => 'external LINE OAuth callback',
        'auth/line/complete' => 'LINE OAuth completion form',
        'docs' => 'Swagger documentation',
        'docs/asset/{asset}' => 'Swagger static asset',
        'fish-report' => 'admin report page',
        'fish/batch-create' => 'editor only',
        'fish/{id}/audio-list' => 'editor only media manager',
        'fish/{id}/audio/create' => 'editor only form',
        'fish/{id}/audio/{audio}/edit' => 'editor only form requiring audio fixture',
        'fish/{id}/capture-records/batch-create' => 'editor only form',
        'fish/{id}/capture-records/create' => 'editor only legacy redirect',
        'fish/{id}/capture-records/{record_id}/edit' => 'editor only form',
        'fish/{id}/edit' => 'editor only form',
        'fish/{id}/knowledge-manager' => 'editor only manager',
        'fish/{id}/knowledge/create' => 'editor only form',
        'fish/{id}/knowledge/{note}/edit' => 'editor only form requiring note fixture',
        'fish/{id}/media-manager' => 'editor only manager',
        'fish/{id}/merge' => 'editor only merge page',
        'fish/{id}/reference-knowledge/create' => 'editor only form',
        'fish/{id}/reference-knowledge/{knowledge}/edit' => 'editor only form requiring knowledge fixture',
        'fish/{id}/tribal-classifications/create' => 'editor only form',
        'line-users' => 'admin only',
        'sanctum/csrf-cookie' => 'framework CSRF endpoint',
        'storage/{path}' => 'local storage file endpoint',
        'swagger/documentation' => 'Swagger UI',
        'up' => 'framework health endpoint',
        'workspace' => 'editor only workspace',
    ];

    $registered = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->map(fn ($route) => $route->uri())
        ->unique()
        ->sort()
        ->values()
        ->all();
    $classified = collect(array_keys($scan))
        ->merge(array_keys($excluded))
        ->sort()
        ->values()
        ->all();

    expect($classified)->toBe($registered);

    $this->actingAs($viewer);
    foreach ($scan as $uri => $url) {
        $response = $this->get($url);
        expect($response->getStatusCode(), "GET {$uri} returned a server error")
            ->toBeLessThan(500)
            ->and($response->getContent(), "GET {$uri} leaked capture location")
            ->not->toContain('ZZLOCATIONMARK');
    }

    $editorCanObserveMarker = false;
    $this->actingAs($editor);
    foreach ($scan as $url) {
        if (str_contains($this->get($url)->getContent(), 'ZZLOCATIONMARK')) {
            $editorCanObserveMarker = true;
            break;
        }
    }

    expect($editorCanObserveMarker, 'Positive control failed: scanned routes cannot expose the marker')
        ->toBeTrue();
});