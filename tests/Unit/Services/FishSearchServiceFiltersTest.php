<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;
use App\Models\TribalClassification;
use App\Models\CaptureRecord;
use App\Services\FishSearchService;
use App\Services\FishService;

class FishSearchServiceFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected FishSearchService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new FishSearchService(app(FishService::class));
    }

    public function test_name_filter_case_insensitive(): void
    {
        $f1 = Fish::factory()->create(['name' => 'Scarlet Runner']);
        $f2 = Fish::factory()->create(['name' => 'Blue Tang']);
        $res = $this->svc->paginate(['name' => 'scarlet', 'perPage' => 50]);
        $names = array_column($res['items'], 'name');
        $this->assertContains($f1->name, $names);
        $this->assertNotContains($f2->name, $names);
    }

    public function test_tribe_exact_case_insensitive(): void
    {
        $f1 = Fish::factory()->create(['name' => 'Tribe Match']);
        TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $f1->id]);
        $f2 = Fish::factory()->create(['name' => 'Tribe Miss']);
        TribalClassification::factory()->forTribe('iranmeilek')->create(['fish_id' => $f2->id]);
        $res = $this->svc->paginate(['tribe' => 'IVALINO', 'perPage' => 50]);
        $names = array_column($res['items'], 'name');
        $this->assertContains($f1->name, $names);
        $this->assertNotContains($f2->name, $names);
    }

    public function test_processing_method_like(): void
    {
        $f1 = Fish::factory()->create(['name' => 'Proc A']);
        TribalClassification::factory()->create(['fish_id' => $f1->id, 'processing_method' => '去魚鱗']);
        $f2 = Fish::factory()->create(['name' => 'Proc B']);
        TribalClassification::factory()->create(['fish_id' => $f2->id, 'processing_method' => '剝皮']);
        $res = $this->svc->paginate(['processing_method' => '去魚', 'perPage' => 50]);
        $names = array_column($res['items'], 'name');
        $this->assertContains($f1->name, $names);
        $this->assertNotContains($f2->name, $names);
    }

    public function test_capture_location_like_and_method_like(): void
    {
        $f1 = Fish::factory()->create(['name' => 'Cap A']);
        CaptureRecord::factory()->create(['fish_id' => $f1->id, 'location' => 'Harbor Bay', 'capture_method' => '網捕']);
        $f2 = Fish::factory()->create(['name' => 'Cap B']);
        CaptureRecord::factory()->create(['fish_id' => $f2->id, 'location' => 'Open Ocean', 'capture_method' => '釣魚']);
        $res = $this->svc->paginate(['capture_location' => 'harbor', 'capture_method' => '網', 'perPage' => 50]);
        $names = array_column($res['items'], 'name');
        $this->assertContains($f1->name, $names);
        $this->assertNotContains($f2->name, $names);
    }

    public function test_combined_filters_and_logic(): void
    {
        $target = Fish::factory()->create(['name' => 'Combo Fish']);
        TribalClassification::factory()->create(['fish_id' => $target->id, 'tribe' => 'ivalino', 'processing_method' => '去魚鱗']);
        CaptureRecord::factory()->create(['fish_id' => $target->id, 'location' => 'Shallow Reef', 'capture_method' => '網捕']);

        // 干擾：名稱符合但 tribe 不同
        $noise1 = Fish::factory()->create(['name' => 'Combo Something']);
        TribalClassification::factory()->create(['fish_id' => $noise1->id, 'tribe' => 'iranmeilek', 'processing_method' => '去魚鱗']);
        CaptureRecord::factory()->create(['fish_id' => $noise1->id, 'location' => 'Shallow Reef', 'capture_method' => '網捕']);

        // 執行複合過濾
        $res = $this->svc->paginate([
            'name' => 'combo',
            'tribe' => 'ivalino',
            'processing_method' => '去魚',
            'capture_location' => 'reef',
            'capture_method' => '網',
            'perPage' => 50,
        ]);
        $names = array_column($res['items'], 'name');
        $this->assertEquals([$target->name], $names);
    }
}
