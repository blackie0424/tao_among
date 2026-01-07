<?php

use App\Models\Fish;
use App\Models\FishNote;
use App\Models\FishAudio;
use App\Models\FishSize;
use App\Models\CaptureRecord;
use App\Models\TribalClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ==================== 基礎驗證測試 ====================

it('驗證必須提供 target_fish_id', function () {
    $response = $this->postJson('/prefix/api/fish/merge', [
        'source_fish_ids' => [2],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['target_fish_id']);
});

it('驗證必須提供 source_fish_ids', function () {
    $fish = Fish::factory()->create();

    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => $fish->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['source_fish_ids']);
});

it('驗證 source_fish_ids 必須為陣列', function () {
    $fish = Fish::factory()->create();

    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => $fish->id,
        'source_fish_ids' => 'not-an-array',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['source_fish_ids']);
});

it('驗證 target_fish_id 必須存在', function () {
    $source = Fish::factory()->create();

    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => 99999,
        'source_fish_ids' => [$source->id],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['target_fish_id']);
});

it('驗證 source_fish_ids 必須存在', function () {
    $target = Fish::factory()->create();

    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => $target->id,
        'source_fish_ids' => [99999],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['source_fish_ids.0']);
});

it('驗證無法將魚類合併到自己', function () {
    $fish = Fish::factory()->create();

    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => $fish->id,
        'source_fish_ids' => [$fish->id],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['source_fish_ids.0']);
});

// ==================== 情境 A：無衝突合併 ====================

it('情境 A-1：無衝突時全部資料成功合併', function () {
    // 建立主魚類
    $target = Fish::factory()->create(['name' => '黃鰭鮪']);
    FishNote::factory()->count(3)->create(['fish_id' => $target->id]);
    FishAudio::factory()->count(2)->create(['fish_id' => $target->id]);
    CaptureRecord::factory()->count(5)->create(['fish_id' => $target->id]);
    TribalClassification::create([
        'fish_id' => $target->id,
        'tribe' => 'ivalino',
        'food_category' => 'oyod',
        'processing_method' => '去魚鱗',
        'notes' => '好吃',
    ]);
    TribalClassification::create([
        'fish_id' => $target->id,
        'tribe' => 'yayo',
        'food_category' => 'rahet',
        'processing_method' => '不去魚鱗',
    ]);

    // 建立被併入魚類（無衝突的部落）
    $source = Fish::factory()->create(['name' => '黃旗魚']);
    FishNote::factory()->count(2)->create(['fish_id' => $source->id]);
    FishAudio::factory()->count(1)->create(['fish_id' => $source->id]);
    CaptureRecord::factory()->count(3)->create(['fish_id' => $source->id]);
    TribalClassification::create([
        'fish_id' => $source->id,
        'tribe' => 'iraraley',
        'food_category' => 'oyod',
        'processing_method' => '去魚鱗',
        'notes' => '常見',
    ]);
    TribalClassification::create([
        'fish_id' => $source->id,
        'tribe' => 'iranmeilek',
        'food_category' => '不食用',
        'processing_method' => '不食用',
    ]);

    // 執行合併
    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => $target->id,
        'source_fish_ids' => [$source->id],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '合併成功',
        ]);

    // 驗證資料合併結果
    expect(FishNote::where('fish_id', $target->id)->count())->toBe(5); // 3 + 2
    expect(FishAudio::where('fish_id', $target->id)->count())->toBe(3); // 2 + 1
    expect(CaptureRecord::where('fish_id', $target->id)->count())->toBe(8); // 5 + 3
    expect(TribalClassification::where('fish_id', $target->id)->count())->toBe(4); // 2 + 2 (無衝突)

    // 驗證部落分類全部保留
    expect(TribalClassification::where('fish_id', $target->id)->pluck('tribe')->toArray())
        ->toContain('ivalino', 'yayo', 'iraraley', 'iranmeilek');

    // 驗證來源魚類被軟刪除
    expect(Fish::find($source->id))->toBeNull();
    expect(Fish::withTrashed()->find($source->id))->not->toBeNull();
});

// ==================== 情境 B：部落分類衝突 ====================

it('情境 B-1：部落分類衝突時保留主魚類資料', function () {
    // 建立主魚類
    $target = Fish::factory()->create(['name' => '黃鰭鮪']);
    $targetClassification = TribalClassification::create([
        'fish_id' => $target->id,
        'tribe' => 'ivalino',
        'food_category' => 'oyod',
        'processing_method' => '去魚鱗',
        'notes' => '族人常吃',
    ]);
    TribalClassification::create([
        'fish_id' => $target->id,
        'tribe' => 'yayo',
        'food_category' => 'rahet',
        'processing_method' => '不去魚鱗',
        'notes' => '口感不佳',
    ]);

    // 建立被併入魚類（有衝突的部落）
    $source = Fish::factory()->create(['name' => '黃旗魚']);
    $sourceClassification = TribalClassification::create([
        'fish_id' => $source->id,
        'tribe' => 'ivalino', // 與主魚類衝突
        'food_category' => 'rahet',
        'processing_method' => '剝皮',
        'notes' => '偶爾捕獲',
    ]);
    TribalClassification::create([
        'fish_id' => $source->id,
        'tribe' => 'iraraley', // 無衝突
        'food_category' => 'oyod',
        'processing_method' => '去魚鱗',
        'notes' => '美味',
    ]);

    // 執行合併
    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => $target->id,
        'source_fish_ids' => [$source->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.conflicts_resolved.tribal_classifications', 1);

    // 驗證 Ivalino 部落分類保留主魚類的資料
    $ivalinoClassification = TribalClassification::where('fish_id', $target->id)
        ->where('tribe', 'ivalino')
        ->first();

    expect($ivalinoClassification->food_category)->toBe('oyod'); // 主魚類的
    expect($ivalinoClassification->processing_method)->toBe('去魚鱗'); // 主魚類的
    expect($ivalinoClassification->notes)->toBe('族人常吃'); // 主魚類的
    expect($ivalinoClassification->id)->toBe($targetClassification->id); // 保留原本的記錄

    // 驗證被併入的 Ivalino 資料已刪除
    expect(TribalClassification::withTrashed()->find($sourceClassification->id)->deleted_at)
        ->not->toBeNull();

    // 驗證無衝突的 Iraraley 成功轉移
    expect(TribalClassification::where('fish_id', $target->id)
        ->where('tribe', 'iraraley')
        ->exists())->toBeTrue();

    // 驗證總共有 3 個部落分類
    expect(TribalClassification::where('fish_id', $target->id)->count())->toBe(3);
});

// ==================== 情境 C：尺寸衝突 ====================

it('情境 C-3：主魚類有尺寸時保留主魚類的尺寸', function () {
    $target = Fish::factory()->create(['name' => '黃鰭鮪']);
    $targetSize = FishSize::create([
        'fish_id' => $target->id,
        'parts' => ['手指1', '手指2', '半掌1', '手掌'],
    ]);

    $source = Fish::factory()->create(['name' => '黃旗魚']);
    $sourceSize = FishSize::create([
        'fish_id' => $source->id,
        'parts' => ['手指1', '半掌2', '手掌', '下臂1'],
    ]);

    // 執行合併
    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => $target->id,
        'source_fish_ids' => [$source->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.conflicts_resolved.fish_size', 1);

    // 驗證保留主魚類的尺寸
    $finalSize = FishSize::where('fish_id', $target->id)->first();
    expect($finalSize->parts)->toBe(['手指1', '手指2', '半掌1', '手掌']); // 主魚類的
    expect($finalSize->id)->toBe($targetSize->id); // 保留原本的記錄

    // 驗證被併入的尺寸已刪除
    expect(FishSize::find($sourceSize->id))->toBeNull();
    expect(FishSize::withTrashed()->find($sourceSize->id))->not->toBeNull();
});

it('情境 C-3：主魚類無尺寸時轉移被併入魚類的尺寸', function () {
    $target = Fish::factory()->create(['name' => '黃鰭鮪']);
    // 主魚類沒有尺寸資料

    $source = Fish::factory()->create(['name' => '黃旗魚']);
    $sourceSize = FishSize::create([
        'fish_id' => $source->id,
        'parts' => ['手指1', '半掌2', '手掌', '下臂1'],
    ]);

    // 執行合併
    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => $target->id,
        'source_fish_ids' => [$source->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.transferred.fish_size', true);

    // 驗證尺寸已轉移到主魚類
    $finalSize = FishSize::where('fish_id', $target->id)->first();
    expect($finalSize)->not->toBeNull();
    expect($finalSize->parts)->toBe(['手指1', '半掌2', '手掌', '下臂1']);
    expect($finalSize->id)->toBe($sourceSize->id); // 轉移的是原本的記錄

    // 驗證來源魚類無尺寸資料
    expect(FishSize::where('fish_id', $source->id)->exists())->toBeFalse();
});

// ==================== 預覽功能測試 ====================

it('可以預覽合併操作並檢測衝突', function () {
    $target = Fish::factory()->create(['name' => '黃鰭鮪']);
    TribalClassification::create([
        'fish_id' => $target->id,
        'tribe' => 'ivalino',
        'food_category' => 'oyod',
        'processing_method' => '去魚鱗',
    ]);
    FishSize::create(['fish_id' => $target->id, 'parts' => ['手指1']]);

    $source = Fish::factory()->create(['name' => '黃旗魚']);
    FishNote::factory()->count(2)->create(['fish_id' => $source->id]);
    FishAudio::factory()->count(1)->create(['fish_id' => $source->id]);
    TribalClassification::create([
        'fish_id' => $source->id,
        'tribe' => 'ivalino', // 衝突
        'food_category' => 'rahet',
        'processing_method' => '剝皮',
    ]);
    FishSize::create(['fish_id' => $source->id, 'parts' => ['手指1', '半掌1']]); // 衝突

    $response = $this->postJson('/prefix/api/fish/merge/preview', [
        'target_fish_id' => $target->id,
        'source_fish_ids' => [$source->id],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '預覽成功',
        ])
        ->assertJsonPath('data.summary.notes_to_transfer', 2)
        ->assertJsonPath('data.summary.audios_to_transfer', 1)
        ->assertJsonPath('data.summary.classifications_conflicts', 1);

    // 驗證衝突資訊
    $conflicts = $response->json('data.conflicts');
    expect($conflicts)->toHaveKey('tribal_classifications');
    expect($conflicts)->toHaveKey('fish_size');
    expect($conflicts['tribal_classifications'][0]['tribe'])->toBe('ivalino');
    expect($conflicts['tribal_classifications'][0]['resolution'])->toBe('keep_target');
});

// ==================== 批次合併測試 ====================

it('可以同時合併多條魚類', function () {
    $target = Fish::factory()->create(['name' => '黃鰭鮪']);
    FishNote::factory()->count(1)->create(['fish_id' => $target->id]);

    $source1 = Fish::factory()->create(['name' => '黃旗魚A']);
    FishNote::factory()->count(2)->create(['fish_id' => $source1->id]);

    $source2 = Fish::factory()->create(['name' => '黃旗魚B']);
    FishNote::factory()->count(3)->create(['fish_id' => $source2->id]);

    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => $target->id,
        'source_fish_ids' => [$source1->id, $source2->id],
    ]);

    $response->assertStatus(200);

    // 驗證所有筆記都合併了
    expect(FishNote::where('fish_id', $target->id)->count())->toBe(6); // 1 + 2 + 3

    // 驗證兩條來源魚類都被刪除
    expect(Fish::find($source1->id))->toBeNull();
    expect(Fish::find($source2->id))->toBeNull();
    expect($response->json('data.merged_fish_ids'))->toBe([$source1->id, $source2->id]);
});

// ==================== Transaction 測試 ====================

it('合併失敗時會 rollback 所有變更', function () {
    $target = Fish::factory()->create(['name' => '黃鰭鮪']);
    $initialNoteCount = FishNote::factory()->count(3)->create(['fish_id' => $target->id])->count();

    $source = Fish::factory()->create(['name' => '黃旗魚']);
    FishNote::factory()->count(2)->create(['fish_id' => $source->id]);

    // 嘗試合併一個不存在的魚類 ID，應該會失敗
    $response = $this->postJson('/prefix/api/fish/merge', [
        'target_fish_id' => $target->id,
        'source_fish_ids' => [$source->id, 99999], // 99999 不存在
    ]);

    $response->assertStatus(422);

    // 驗證沒有任何資料被改變
    expect(FishNote::where('fish_id', $target->id)->count())->toBe($initialNoteCount);
    expect(Fish::find($source->id))->not->toBeNull(); // 來源魚類沒被刪除
});
