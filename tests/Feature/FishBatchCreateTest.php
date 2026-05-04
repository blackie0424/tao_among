<?php

use App\Models\Fish;
use App\Models\CaptureRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────
// GET /fish/batch-create
// ─────────────────────────────────────────────

it('renders batch create page with required props', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/fish/batch-create');

    $response->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('BatchCreateFish')
                ->has('tribes')
                ->has('capture_methods')
                ->has('upload_limits')
                ->where('upload_limits.max_files_desktop', fn ($v) => is_int($v) && $v > 0)
                ->where('upload_limits.max_files_mobile', fn ($v) => is_int($v) && $v > 0)
        );
});

it('redirects unauthenticated users away from batch create page', function () {
    $response = $this->get('/fish/batch-create');

    $response->assertStatus(302);
});

// ─────────────────────────────────────────────
// POST /fish/batch-create
// ─────────────────────────────────────────────

it('creates fish and multiple capture records from batch create', function () {
    $user = User::factory()->create();

    $payload = [
        'name'           => 'Batch Fish',
        'filenames'      => ['photo1.jpg', 'photo2.jpg', 'photo3.jpg'],
        'tribe'          => 'iraraley',
        'location'       => '海邊',
        'capture_method' => 'mamasil',
        'capture_date'   => '2026-05-01',
        'notes'          => '測試備註',
    ];

    $response = $this->actingAs($user)->post('/fish/batch-create', $payload);

    // 建立一筆 Fish
    $fish = Fish::where('name', 'Batch Fish')->first();
    expect($fish)->not->toBeNull();

    // 建立 3 筆 CaptureRecord
    expect(CaptureRecord::where('fish_id', $fish->id)->count())->toBe(3);

    // 重定向至詳情頁
    $response->assertRedirect("/fish/{$fish->id}");
    $response->assertSessionHas('success');
});

it('uses first filename as fish display image', function () {
    $user = User::factory()->create();

    $payload = [
        'name'      => 'Display Fish',
        'filenames' => ['first.jpg', 'second.jpg'],
    ];

    $this->actingAs($user)->post('/fish/batch-create', $payload);

    $fish = Fish::where('name', 'Display Fish')->first();
    $firstRecord = CaptureRecord::where('fish_id', $fish->id)
        ->where('image_path', 'first.jpg')
        ->first();

    expect($fish->display_capture_record_id)->toBe($firstRecord->id);
});

it('uses default name when name is empty or missing', function () {
    $user = User::factory()->create();

    $payload = [
        'name'      => '',
        'filenames' => ['photo.jpg'],
    ];

    $this->actingAs($user)->post('/fish/batch-create', $payload);

    $fish = Fish::where('name', '我不知道')->first();
    expect($fish)->not->toBeNull();
});

it('creates capture records with shared capture info for each photo', function () {
    $user = User::factory()->create();

    $payload = [
        'name'           => 'Info Fish',
        'filenames'      => ['a.jpg', 'b.jpg'],
        'tribe'          => 'iraraley',
        'location'       => '溪流',
        'capture_method' => 'mamasil',
        'capture_date'   => '2026-04-15',
        'notes'          => '共用備註',
    ];

    $this->actingAs($user)->post('/fish/batch-create', $payload);

    $fish = Fish::where('name', 'Info Fish')->first();
    $records = CaptureRecord::where('fish_id', $fish->id)->get();

    foreach ($records as $record) {
        expect($record->tribe)->toBe('iraraley');
        expect($record->location)->toBe('溪流');
        expect($record->capture_method)->toBe('mamasil');
        expect($record->notes)->toBe('共用備註');
    }
});

it('fails validation when filenames is missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/fish/batch-create', [
        'name' => 'No Files Fish',
    ]);

    $response->assertSessionHasErrors('filenames');
});

it('fails validation when filenames is empty array', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/fish/batch-create', [
        'name'      => 'Empty Files Fish',
        'filenames' => [],
    ]);

    $response->assertSessionHasErrors('filenames');
});

it('fails validation when a filename entry is not a string', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/fish/batch-create', [
        'name'      => 'Bad Files Fish',
        'filenames' => [123, null],
    ]);

    $response->assertSessionHasErrors(['filenames.0', 'filenames.1']);
});

it('redirects unauthenticated users away from batch create post', function () {
    $response = $this->post('/fish/batch-create', [
        'name'      => 'Sneaky Fish',
        'filenames' => ['photo.jpg'],
    ]);

    $response->assertStatus(302);
    expect(Fish::count())->toBe(0);
});

it('rolls back all changes when an error occurs mid-batch', function () {
    $user = User::factory()->create();

    // 傳入包含 null 的 filenames 讓批次中途拋出例外
    $payload = [
        'name'      => 'Rollback Fish',
        'filenames' => ['ok.jpg', null],
    ];

    $this->actingAs($user)->post('/fish/batch-create', $payload);

    // 驗證失敗時不應有任何資料寫入
    expect(Fish::where('name', 'Rollback Fish')->count())->toBe(0);
    expect(CaptureRecord::count())->toBe(0);
});
