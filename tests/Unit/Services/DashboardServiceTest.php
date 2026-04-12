<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;
use App\Models\User;
use App\Models\FishAudio;
use App\Models\FishNote;
use App\Models\CaptureRecord;
use App\Models\TribalClassification;
use App\Services\DashboardService;

uses(TestCase::class, RefreshDatabase::class);

// =========================================================
// getTribes()
// =========================================================

describe('getTribes()', function () {

    it('包含 config/fish_options.php 中所有設定的部落', function () {
        $service  = new DashboardService();
        $tribes   = $service->getTribes();
        $expected = config('fish_options.tribes');

        foreach ($expected as $tribe) {
            expect($tribes->contains($tribe))->toBeTrue("部落 {$tribe} 應存在清單中");
        }
    });

    it('補充資料庫中額外的部落值（不重複）', function () {
        $fish = Fish::factory()->create();
        // 建立不在固定清單內的部落名稱
        TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish->id]);

        $service = new DashboardService();
        $tribes  = $service->getTribes();

        // 固定清單 + 資料庫中的 ivalino 都應存在（ivalino 不在固定清單 Iranmeylek 等大寫版本中）
        expect($tribes->unique()->count())->toBe($tribes->count());
    });
});

// =========================================================
// getFishStats()
// =========================================================

describe('getFishStats() - 全部模式', function () {

    it('回傳全部魚種總數', function () {
        Fish::factory()->count(5)->create();

        $service = new DashboardService();
        $stats   = $service->getFishStats(null);

        expect($stats['total'])->toBe(5);
    });

    it('with_capture_record 正確計算有捕獲紀錄的魚種數', function () {
        $fishWithRecord    = Fish::factory()->create();
        $fishWithoutRecord = Fish::factory()->create();
        CaptureRecord::factory()->create(['fish_id' => $fishWithRecord->id]);

        $service = new DashboardService();
        $stats   = $service->getFishStats(null);

        expect($stats['with_capture_record'])->toBe(1);
    });

    it('with_tribal_classification 正確計算有部落分類的魚種數', function () {
        $fishWithTc    = Fish::factory()->create();
        $fishWithoutTc = Fish::factory()->create();
        TribalClassification::factory()->create(['fish_id' => $fishWithTc->id]);

        $service = new DashboardService();
        $stats   = $service->getFishStats(null);

        expect($stats['with_tribal_classification'])->toBe(1);
    });
});

describe('getFishStats() - 部落篩選模式', function () {

    it('只計算有該部落 TribalClassification 的魚種', function () {
        $fish1 = Fish::factory()->create();
        $fish2 = Fish::factory()->create();
        $fish3 = Fish::factory()->create();

        // fish1 有 ivalino 的分類
        TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish1->id]);
        // fish2 有 iranmeilek 的分類
        TribalClassification::factory()->forTribe('iranmeilek')->create(['fish_id' => $fish2->id]);
        // fish3 無分類

        $service = new DashboardService();
        $stats   = $service->getFishStats('ivalino');

        expect($stats['total'])->toBe(1);
    });

    it('也計算有該部落捕獲紀錄但無分類的魚種', function () {
        $fishWithRecord = Fish::factory()->create();
        CaptureRecord::factory()->create([
            'fish_id' => $fishWithRecord->id,
            'tribe'   => 'ivalino',
        ]);

        $service = new DashboardService();
        $stats   = $service->getFishStats('ivalino');

        expect($stats['total'])->toBe(1);
    });
});

// =========================================================
// getTribalStats()
// =========================================================

describe('getTribalStats() - 全部模式', function () {

    it('回傳 by_tribe 依部落分佈', function () {
        $fish1 = Fish::factory()->create();
        $fish2 = Fish::factory()->create();
        TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish1->id]);
        TribalClassification::factory()->forTribe('iranmeilek')->create(['fish_id' => $fish2->id]);

        $service = new DashboardService();
        $stats   = $service->getTribalStats(null);

        expect($stats['by_tribe'])->toHaveCount(2);
        expect($stats['by_food_category'])->toBeEmpty();
        expect($stats['by_processing_method'])->toBeEmpty();
    });

    it('total 等於 TribalClassification 總筆數', function () {
        TribalClassification::factory()->count(5)->create();

        $service = new DashboardService();
        $stats   = $service->getTribalStats(null);

        expect($stats['total'])->toBe(5);
    });
});

describe('getTribalStats() - 部落篩選模式', function () {

    it('by_food_category 依食用分類列出魚種數量（降冪排列）', function () {
        $fish1 = Fish::factory()->create();
        $fish2 = Fish::factory()->create();
        $fish3 = Fish::factory()->create();

        // 3 隻 oyod、1 隻 rahet（同部落 ivalino）
        TribalClassification::factory()->forTribe('ivalino')->withFoodCategory('oyod')->create(['fish_id' => $fish1->id]);
        TribalClassification::factory()->forTribe('ivalino')->withFoodCategory('oyod')->create(['fish_id' => $fish2->id]);
        TribalClassification::factory()->forTribe('ivalino')->withFoodCategory('rahet')->create(['fish_id' => $fish3->id]);

        // 其他部落的資料不應干擾
        $fish4 = Fish::factory()->create();
        TribalClassification::factory()->forTribe('iranmeilek')->withFoodCategory('oyod')->create(['fish_id' => $fish4->id]);

        $service = new DashboardService();
        $stats   = $service->getTribalStats('ivalino');

        expect($stats['by_food_category'])->toHaveCount(2);
        // 第一名應是 oyod（count=2）
        expect($stats['by_food_category'][0]['label'])->toBe('oyod');
        expect($stats['by_food_category'][0]['count'])->toBe(2);
        // 第二名應是 rahet（count=1）
        expect($stats['by_food_category'][1]['label'])->toBe('rahet');
        expect($stats['by_food_category'][1]['count'])->toBe(1);
    });

    it('by_food_category 空字串顯示為「未分類」', function () {
        $fish = Fish::factory()->create();
        TribalClassification::factory()
            ->forTribe('ivalino')
            ->withFoodCategory('')
            ->create(['fish_id' => $fish->id]);

        $service = new DashboardService();
        $stats   = $service->getTribalStats('ivalino');

        expect($stats['by_food_category'][0]['label'])->toBe('未分類');
    });

    it('by_processing_method 依處理方法列出魚種數量（降冪排列）', function () {
        $fish1 = Fish::factory()->create();
        $fish2 = Fish::factory()->create();
        $fish3 = Fish::factory()->create();

        TribalClassification::factory()->forTribe('ivalino')->withProcessingMethod('去魚鱗')->create(['fish_id' => $fish1->id]);
        TribalClassification::factory()->forTribe('ivalino')->withProcessingMethod('去魚鱗')->create(['fish_id' => $fish2->id]);
        TribalClassification::factory()->forTribe('ivalino')->withProcessingMethod('剝皮')->create(['fish_id' => $fish3->id]);

        $service = new DashboardService();
        $stats   = $service->getTribalStats('ivalino');

        expect($stats['by_processing_method'])->toHaveCount(2);
        expect($stats['by_processing_method'][0]['label'])->toBe('去魚鱗');
        expect($stats['by_processing_method'][0]['count'])->toBe(2);
        expect($stats['by_processing_method'][1]['label'])->toBe('剝皮');
        expect($stats['by_processing_method'][1]['count'])->toBe(1);
    });

    it('by_processing_method 空字串顯示為「未記錄」', function () {
        $fish = Fish::factory()->create();
        TribalClassification::factory()
            ->forTribe('ivalino')
            ->withProcessingMethod('')
            ->create(['fish_id' => $fish->id]);

        $service = new DashboardService();
        $stats   = $service->getTribalStats('ivalino');

        expect($stats['by_processing_method'][0]['label'])->toBe('未記錄');
    });

    it('全部模式時 by_food_category 與 by_processing_method 為空', function () {
        TribalClassification::factory()->count(3)->create();

        $service = new DashboardService();
        $stats   = $service->getTribalStats(null);

        expect($stats['by_food_category'])->toBeEmpty();
        expect($stats['by_processing_method'])->toBeEmpty();
    });

    it('by_tribe 在部落模式下為空', function () {
        $fish = Fish::factory()->create();
        TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish->id]);

        $service = new DashboardService();
        $stats   = $service->getTribalStats('ivalino');

        expect($stats['by_tribe'])->toBeEmpty();
    });
});

// =========================================================
// getCaptureStats()
// =========================================================

describe('getCaptureStats()', function () {

    it('全部模式：回傳 by_tribe 分佈', function () {
        $fish = Fish::factory()->create();
        CaptureRecord::factory()->create(['fish_id' => $fish->id, 'tribe' => 'ivalino']);
        CaptureRecord::factory()->create(['fish_id' => $fish->id, 'tribe' => 'iranmeilek']);

        $service = new DashboardService();
        $stats   = $service->getCaptureStats(null);

        expect($stats['total'])->toBe(2);
        expect($stats['by_tribe'])->toHaveCount(2);
        expect($stats['by_method'])->toBeEmpty();
    });

    it('部落模式：回傳 by_method 分佈', function () {
        $fish = Fish::factory()->create();
        CaptureRecord::factory()->create(['fish_id' => $fish->id, 'tribe' => 'ivalino', 'capture_method' => '釣魚']);
        CaptureRecord::factory()->create(['fish_id' => $fish->id, 'tribe' => 'ivalino', 'capture_method' => '魚叉']);
        CaptureRecord::factory()->create(['fish_id' => $fish->id, 'tribe' => 'iranmeilek', 'capture_method' => '釣魚']);

        $service = new DashboardService();
        $stats   = $service->getCaptureStats('ivalino');

        expect($stats['total'])->toBe(2);
        expect($stats['by_tribe'])->toBeEmpty();
        expect($stats['by_method'])->toHaveCount(2);
    });
});

// =========================================================
// getUserStats()
// =========================================================

describe('getUserStats()', function () {

    it('只計算 source=line 的使用者', function () {
        User::factory()->admin()->create();  // source=web
        User::factory()->lineViewer()->create();

        $service = new DashboardService();
        $stats   = $service->getUserStats();

        expect($stats['total'])->toBe(1);
    });
});
