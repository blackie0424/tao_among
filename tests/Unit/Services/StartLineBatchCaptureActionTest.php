<?php

use App\Models\Fish;
use App\Services\LineBatchCapture\Actions\StartLineBatchCaptureAction;
use App\Services\LineBatchCapture\LineBatchCaptureStateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->stateStore = app(LineBatchCaptureStateStore::class);
    $this->action = new StartLineBatchCaptureAction($this->stateStore);
});

it('starts a new batch capture session and clears create fish leftovers', function () {
    $fish = Fish::factory()->create();

    Cache::put('line_user_user-1_create_fish_state', 'waiting_image', now()->addMinutes(5));
    Cache::put('line_user_user-1_create_fish_images', ['old.jpg'], now()->addMinutes(5));

    $result = $this->action->execute('user-1', $fish->id);

    expect($result->successful())->toBeTrue()
        ->and($result->fish()?->id)->toBe($fish->id)
        ->and($this->stateStore->getFishId('user-1'))->toBe($fish->id)
        ->and($this->stateStore->getState('user-1'))->toBe('waiting_images')
        ->and(Cache::get('line_user_user-1_create_fish_state'))->toBeNull()
        ->and(Cache::get('line_user_user-1_create_fish_images'))->toBeNull();
});

it('returns failure when target fish does not exist', function () {
    $result = $this->action->execute('user-1', 99999);

    expect($result->successful())->toBeFalse()
        ->and($result->message())->toBe('❌ 找不到魚類資料，請重新操作。')
        ->and($this->stateStore->getState('user-1'))->toBeNull();
});
