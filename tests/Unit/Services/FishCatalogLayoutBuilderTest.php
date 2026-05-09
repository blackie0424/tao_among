<?php

use App\Contracts\StorageServiceInterface;
use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\TribalClassification;
use App\Services\GoogleDocs\FishCatalogLayoutBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery as m;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->storage = m::mock(StorageServiceInterface::class);
    $this->builder = new FishCatalogLayoutBuilder($this->storage);
});

it('builds front and back layout with display image qr code and tribal knowledge table', function () {
    $fish = Fish::factory()->create([
        'name' => '飛魚',
        'image' => 'fallback.jpg',
        'audio_filename' => 'test-audio.m4a',
    ]);

    $frontRecord = CaptureRecord::factory()->create([
        'fish_id' => $fish->id,
        'image_path' => 'display-front.jpg',
        'capture_method' => '魚叉',
        'capture_date' => '2026-05-01',
    ]);

    CaptureRecord::factory()->create([
        'fish_id' => $fish->id,
        'image_path' => 'back-photo.jpg',
        'capture_method' => '網捕',
        'capture_date' => '2026-05-02',
    ]);

    TribalClassification::factory()->forTribe('imowrod')->create([
        'fish_id' => $fish->id,
        'food_category' => 'oyod',
        'processing_method' => '去魚鱗',
    ]);

    TribalClassification::factory()->forTribe('iraraley')->create([
        'fish_id' => $fish->id,
        'food_category' => 'rahet',
        'processing_method' => '剝皮',
    ]);

    $fish->update(['display_capture_record_id' => $frontRecord->id]);
    $fish = Fish::with([
        'displayCaptureRecord',
        'captureRecords' => fn ($query) => $query->orderByDesc('capture_date'),
        'tribalClassifications',
    ])->findOrFail($fish->id);

    $this->storage
        ->shouldReceive('getUrl')
        ->once()
        ->with('audios', 'test-audio.m4a', null)
        ->andReturn('https://media.example/audio/test-audio.m4a');

    $layout = $this->builder->build($fish);

    expect($layout['front']['heading'])->toBe('基本資料')
        ->and($layout['front']['image_filename'])->toBe('display-front.jpg')
        ->and($layout['front']['name_line'])->toBe('名稱：飛魚')
        ->and($layout['front']['capture_method_line'])->toBe('捕獲方式：魚叉')
        ->and($layout['front']['knowledge_heading'])->toBe('地方知識')
        ->and($layout['front']['qr_code_url'])->toBe('https://quickchart.io/qr?text=https%3A%2F%2Fmedia.example%2Faudio%2Ftest-audio.m4a&size=220')
        ->and($layout['front']['knowledge_table'])->toBe([
            ['部落', '食用類別', '處理方式'],
            ['Imowrod', 'oyod', '去魚鱗'],
            ['Iraraley', 'rahet', '剝皮'],
        ])
        ->and($layout['back']['image_filename'])->toBe('back-photo.jpg')
        ->and($layout['back']['lines'])->toBe([
            '生態：',
            '分布：',
            '傳統價值：',
            '魚餌：',
        ]);
});

it('falls back to fish image and omits qr code or duplicate back image when unavailable', function () {
    $fish = Fish::factory()->create([
        'name' => '鬼頭刀',
        'image' => 'shared-photo.jpg',
        'audio_filename' => null,
    ]);

    CaptureRecord::factory()->create([
        'fish_id' => $fish->id,
        'image_path' => 'shared-photo.jpg',
        'capture_method' => '網捕',
        'capture_date' => '2026-05-03',
    ]);

    $fish = Fish::with([
        'displayCaptureRecord',
        'captureRecords' => fn ($query) => $query->orderByDesc('capture_date'),
        'tribalClassifications',
    ])->findOrFail($fish->id);

    $layout = $this->builder->build($fish);

    expect($layout['front']['image_filename'])->toBe('shared-photo.jpg')
        ->and($layout['front']['capture_method_line'])->toBe('捕獲方式：網捕')
        ->and($layout['front']['qr_code_url'])->toBeNull()
        ->and($layout['front']['knowledge_table'])->toBe([
            ['部落', '食用類別', '處理方式'],
            ['Imowrod', '尚未紀錄', '尚未紀錄'],
            ['Iraraley', '尚未紀錄', '尚未紀錄'],
        ])
        ->and($layout['back']['image_filename'])->toBeNull();
});
