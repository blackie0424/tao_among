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

it('stores and retrieves indexed images for imageSet', function () {
    $this->store->putIndexedImages('user-1', ['set-abc', [1 => 'a.jpg', 3 => 'c.jpg'], 3]);

    [$setId, $indexed, $total] = $this->store->getIndexedImages('user-1');

    expect($setId)->toBe('set-abc')
        ->and($indexed)->toBe([1 => 'a.jpg', 3 => 'c.jpg'])
        ->and($total)->toBe(3);
});

it('returns null indexed images when not set', function () {
    expect($this->store->getIndexedImages('user-x'))->toBeNull();
});

it('clears indexed images along with the session', function () {
    $this->store->startSession('user-1', 123);
    $this->store->putIndexedImages('user-1', ['set-abc', [1 => 'a.jpg'], 2]);
    $this->store->clear('user-1');

    expect($this->store->getIndexedImages('user-1'))->toBeNull();
});
