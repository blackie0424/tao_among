<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;
use App\Models\TribalClassification;
use App\Services\FishSearchService;
use App\Services\FishService;

uses(TestCase::class, RefreshDatabase::class);

it('分頁查詢時正確回傳 hasMore', function () {
    Fish::factory()->count(6)->create();
    $service = new FishSearchService(app(FishService::class));
    $result = $service->paginate(['perPage' => 5]);
    
    expect($result['items'])->toHaveCount(5);
    expect($result['pageInfo']['hasMore'])->toBeTrue();
    expect($result['pageInfo']['nextCursor'])->not->toBeNull();
    
    $lastReturnedId = end($result['items'])['id'];
    expect($result['pageInfo']['nextCursor'])->toBe($lastReturnedId);
});

it('items 包含必要欄位結構', function () {
    Fish::factory()->create();
    $service = new FishSearchService(app(FishService::class));
    $result = $service->paginate(['perPage' => 10]);
    
    expect($result)->toHaveKey('items');
    expect($result['items'])->not->toBeEmpty();
    
    $firstItem = $result['items'][0];
    expect($firstItem)->toHaveKeys(['id', 'name', 'image_url']);
    expect($firstItem)->not->toHaveKey('tribal_classifications');
});

it('部落篩選可正常查詢', function () {
    $fish = Fish::factory()->create();
    
    // 建立 3 個部落分類
    TribalClassification::create([
        'fish_id' => $fish->id,
        'tribe' => 'ivalino',
        'food_category' => 'oyod',
        'processing_method' => '去魚鱗',
    ]);
    TribalClassification::create([
        'fish_id' => $fish->id,
        'tribe' => 'iranmeilek',
        'food_category' => 'rahet',
        'processing_method' => '不去魚鱗',
    ]);
    TribalClassification::create([
        'fish_id' => $fish->id,
        'tribe' => 'imowrod',
        'food_category' => 'oyod',
        'processing_method' => '剝皮',
    ]);
    
    $service = new FishSearchService(app(FishService::class));
    $result = $service->paginate(['perPage' => 10, 'tribe' => 'ivalino']);
    
    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0]['id'])->toBe($fish->id);
});

it('沒有部落分類的魚類也能正常回傳', function () {
    Fish::factory()->create();
    // 不建立任何 tribal_classifications
    
    $service = new FishSearchService(app(FishService::class));
    $result = $service->paginate(['perPage' => 10]);
    
    expect($result['items'])->toHaveCount(1);
    $item = $result['items'][0];
    expect($item)->toHaveKeys(['id', 'name', 'image_url']);
    expect($item)->not->toHaveKey('tribal_classifications');
});

it('items 不包含多餘欄位', function () {
    $fish = Fish::factory()->create();
    
    TribalClassification::create([
        'fish_id' => $fish->id,
        'tribe' => 'ivalino',
        'food_category' => 'oyod',
        'processing_method' => '去魚鱗',
        'notes' => '這是測試備註',
    ]);
    
    $service = new FishSearchService(app(FishService::class));
    $result = $service->paginate(['perPage' => 10]);
    
    $item = $result['items'][0];
    
    // 應該包含的欄位
    expect($item)->toHaveKeys(['id', 'name', 'image_url']);
    
    // 不應該包含 tribal_classifications
    expect($item)->not->toHaveKey('tribal_classifications');
});
