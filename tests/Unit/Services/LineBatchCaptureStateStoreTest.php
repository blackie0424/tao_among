<?php

use App\Services\LineBatchCapture\LineBatchCaptureStateStore;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function () {
    Cache::flush();
    $this->store = app(LineBatchCaptureStateStore::class);
});

it('starts a batch capture session with default empty payloads', function () {
    $this->store->startSession('user-1', 123);

    expect($this->store->getFishId('user-1'))->toBe(123)
        ->and($this->store->getState('user-1'))->toBe('waiting_images')
        ->and($this->store->getImages('user-1'))->toBe([])
        ->and($this->store->getForm('user-1'))->toBe([]);
});

it('clears a batch capture session', function () {
    $this->store->startSession('user-1', 123);
    $this->store->clear('user-1');

    expect($this->store->getFishId('user-1'))->toBeNull()
        ->and($this->store->getState('user-1'))->toBeNull()
        ->and($this->store->getImages('user-1'))->toBe([])
        ->and($this->store->getForm('user-1'))->toBe([]);
});
