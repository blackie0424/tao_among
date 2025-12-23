<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;
use App\Models\TribalClassification;
use App\Services\FishSearchService;
use App\Services\FishService;

uses(TestCase::class, RefreshDatabase::class);

it('分頁查詢時正確回傳 hasMore 並包含 tribal_classifications 欄位', function () {
    Fish::factory()->count(6)->create();
    $service = new FishSearchService(app(FishService::class));
    $result = $service->paginate(['perPage' => 5]);
    
    expect($result['items'])->toHaveCount(5);
    expect($result['pageInfo']['hasMore'])->toBeTrue();
    expect($result['pageInfo']['nextCursor'])->not->toBeNull();
    
    $lastReturnedId = end($result['items'])['id'];
    expect($result['pageInfo']['nextCursor'])->toBe($lastReturnedId);
    
    // 驗證 tribal_classifications 欄位存在
    expect($result['items'][0])->toHaveKey('tribal_classifications');
    expect($result['items'][0]['tribal_classifications'])->toBeArray();
});

it('items 包含 tribal_classifications 欄位結構', function () {
    Fish::factory()->create();
    $service = new FishSearchService(app(FishService::class));
    $result = $service->paginate(['perPage' => 10]);
    
    expect($result)->toHaveKey('items');
    expect($result['items'])->not->toBeEmpty();
    
    $firstItem = $result['items'][0];
    expect($firstItem)->toHaveKeys(['id', 'name', 'image_url', 'tribal_classifications']);
    expect($firstItem['tribal_classifications'])->toBeArray();
});

it('tribal_classifications 包含 tribe 和 food_category 資料', function () {
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
    $result = $service->paginate(['perPage' => 10]);
    
    expect($result['items'])->toHaveCount(1);
    $item = $result['items'][0];
    expect($item['tribal_classifications'])->toHaveCount(3);
    
    // 驗證每個分類都有 tribe 和 food_category
    foreach ($item['tribal_classifications'] as $tc) {
        expect($tc)->toHaveKeys(['tribe', 'food_category']);
        expect($tc['tribe'])->not->toBeEmpty();
    }
    
    // 驗證特定值
    $tribes = array_column($item['tribal_classifications'], 'tribe');
    expect($tribes)->toContain('ivalino', 'iranmeilek', 'imowrod');
    
    $foodCategories = array_column($item['tribal_classifications'], 'food_category');
    expect($foodCategories)->toContain('oyod', 'rahet');
});

it('沒有部落分類的魚類回傳空陣列', function () {
    Fish::factory()->create();
    // 不建立任何 tribal_classifications
    
    $service = new FishSearchService(app(FishService::class));
    $result = $service->paginate(['perPage' => 10]);
    
    expect($result['items'])->toHaveCount(1);
    $item = $result['items'][0];
    expect($item)->toHaveKey('tribal_classifications');
    expect($item['tribal_classifications'])->toBeArray()->toBeEmpty();
});

it('tribal_classifications 只包含 tribe 和 food_category 欄位', function () {
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
    
    $tc = $result['items'][0]['tribal_classifications'][0];
    
    // 應該包含的欄位
    expect($tc)->toHaveKeys(['tribe', 'food_category']);
    
    // 不應該包含的欄位
    expect($tc)->not->toHaveKeys([
        'processing_method',
        'notes',
        'id',
        'fish_id',
        'created_at',
        'updated_at'
    ]);
});
