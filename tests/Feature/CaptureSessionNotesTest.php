<?php

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function sessionNotesFixture(string $notes = 'ZZSESSIONNOTEMARK'): array
{
    $fish = Fish::factory()->create();
    $session = CaptureSession::factory()->create(['notes' => $notes]);
    $record = CaptureRecord::factory()->create(['fish_id' => $fish->id, 'session_id' => $session->id]);

    return compact('fish', 'session', 'record');
}

it('shows session notes only to location-authorized roles on both record outlets', function (string $role) {
    ['fish' => $fish] = sessionNotesFixture();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get("/fish/{$fish->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('captureRecords.0.session_notes', 'ZZSESSIONNOTEMARK'));
    $this->get("/fish/{$fish->id}/capture-records")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('fish.captureRecords.0.session_notes', 'ZZSESSIONNOTEMARK'));
})->with(['editor', 'admin']);

it('removes session notes for viewer on both record outlets', function () {
    ['fish' => $fish] = sessionNotesFixture();
    $viewer = User::factory()->lineViewer()->create();

    $this->actingAs($viewer)->get("/fish/{$fish->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->missing('captureRecords.0.session_notes'));
    $this->get("/fish/{$fish->id}/capture-records")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->missing('fish.captureRecords.0.session_notes'));
});

it('reflects edited session notes without copying them to capture records', function () {
    ['fish' => $fish, 'session' => $session, 'record' => $record] = sessionNotesFixture('原始備註');
    $editor = User::factory()->lineEditor()->create();

    $session->update(['notes' => '更新後備註']);

    $this->actingAs($editor)->get("/fish/{$fish->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('captureRecords.0.session_notes', '更新後備註'));
    expect($record->fresh()->notes)->not->toBe('更新後備註');
});

it('does not add session notes to unrelated API responses', function () {
    ['fish' => $fish] = sessionNotesFixture();
    $editor = User::factory()->lineEditor()->create();

    $this->actingAs($editor)->getJson('/prefix/api/fishs/search?q='.$fish->name)
        ->assertOk()
        ->assertJsonMissing(['session_notes' => 'ZZSESSIONNOTEMARK']);
});

it('keeps session-notes relation queries fixed as record counts grow', function (string $route) {
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $session = CaptureSession::factory()->create(['notes' => '備註']);
    $counts = ['one' => 0, 'many' => 0];
    $phase = 'one';

    DB::listen(function ($query) use (&$counts, &$phase): void {
        $sql = strtolower(str_replace(['`', '"'], '', $query->sql));
        if (str_contains($sql, 'from capture_sessions')) {
            $counts[$phase]++;
        }
    });

    CaptureRecord::factory()->create(['fish_id' => $fish->id, 'session_id' => $session->id]);
    $this->actingAs($editor)->get(str_replace('{fish}', (string) $fish->id, $route))->assertOk();

    CaptureRecord::query()->where('fish_id', $fish->id)->delete();
    CaptureRecord::factory()->count(20)->create(['fish_id' => $fish->id, 'session_id' => $session->id]);
    $phase = 'many';
    $this->get(str_replace('{fish}', (string) $fish->id, $route))->assertOk();

    expect($counts)->toBe(['one' => 1, 'many' => 1]);
})->with(['/fish/{fish}', '/fish/{fish}/capture-records']);
