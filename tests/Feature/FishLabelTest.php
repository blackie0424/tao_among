<?php

use App\Models\FishLabel;
use App\Models\TribalClassification;
use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('FishLabel Model', function () {

    it('can create a fish label', function () {
        $label = FishLabel::create([
            'group' => 'food_category',
            'name' => 'oyod',
        ]);

        expect($label)->toBeInstanceOf(FishLabel::class);
        expect($label->group)->toBe('food_category');
        expect($label->name)->toBe('oyod');
    });

    it('can create a label with 其他 group', function () {
        $label = FishLabel::create([
            'group' => '其他',
            'name' => '老人魚',
        ]);

        expect($label->group)->toBe('其他');
        expect($label->name)->toBe('老人魚');
    });

    it('has correct fillable attributes', function () {
        $label = new FishLabel();
        expect($label->getFillable())->toBe(['group', 'name']);
    });

    it('can be associated with multiple tribal classifications', function () {
        $fish = Fish::factory()->create();
        $label = FishLabel::create(['group' => 'food_category', 'name' => 'rahet']);

        $tc1 = TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish->id]);
        $tc2 = TribalClassification::factory()->forTribe('iranmeilek')->create(['fish_id' => $fish->id]);

        $tc1->labels()->attach($label->id);
        $tc2->labels()->attach($label->id);

        expect($label->tribalClassifications)->toHaveCount(2);
    });
});

describe('TribalClassification labels relationship', function () {

    it('can attach multiple labels to a tribal classification', function () {
        $fish = Fish::factory()->create();
        $tc = TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish->id]);

        $label1 = FishLabel::create(['group' => 'food_category', 'name' => 'rahet']);
        $label2 = FishLabel::create(['group' => '其他', 'name' => '老人魚']);

        $tc->labels()->attach([$label1->id, $label2->id]);

        expect($tc->labels)->toHaveCount(2);
        expect($tc->labels->pluck('name')->toArray())->toContain('rahet', '老人魚');
    });

    it('can attach labels from different groups', function () {
        $fish = Fish::factory()->create();
        $tc = TribalClassification::factory()->forTribe('imowrod')->create(['fish_id' => $fish->id]);

        $food = FishLabel::create(['group' => 'food_category', 'name' => 'oyod']);
        $processing = FishLabel::create(['group' => 'processing', 'name' => '去魚鱗']);
        $special = FishLabel::create(['group' => '其他', 'name' => '孕婦適合']);

        $tc->labels()->attach([$food->id, $processing->id, $special->id]);

        $groups = $tc->labels->pluck('group')->unique()->values()->toArray();
        sort($groups);
        expect($groups)->toBe(['food_category', 'processing', '其他']);
    });

    it('different tribes can have different labels for the same fish', function () {
        $fish = Fish::factory()->create();

        $tc_ivalino = TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish->id]);
        $tc_iranmeilek = TribalClassification::factory()->forTribe('iranmeilek')->create(['fish_id' => $fish->id]);

        $rahet = FishLabel::create(['group' => 'food_category', 'name' => 'rahet']);
        $oyod = FishLabel::create(['group' => 'food_category', 'name' => 'oyod']);
        $old_man = FishLabel::create(['group' => '其他', 'name' => '老人魚']);
        $pregnant = FishLabel::create(['group' => '其他', 'name' => '孕婦適合']);

        $tc_ivalino->labels()->attach([$rahet->id, $old_man->id]);
        $tc_iranmeilek->labels()->attach([$oyod->id, $pregnant->id]);

        expect($tc_ivalino->labels->pluck('name')->toArray())->toContain('rahet', '老人魚');
        expect($tc_iranmeilek->labels->pluck('name')->toArray())->toContain('oyod', '孕婦適合');
    });

    it('can detach a label from a tribal classification', function () {
        $fish = Fish::factory()->create();
        $tc = TribalClassification::factory()->forTribe('iratay')->create(['fish_id' => $fish->id]);

        $label1 = FishLabel::create(['group' => 'food_category', 'name' => 'rahet']);
        $label2 = FishLabel::create(['group' => '其他', 'name' => '老人魚']);

        $tc->labels()->attach([$label1->id, $label2->id]);
        $tc->labels()->detach($label1->id);

        $tc->refresh();
        expect($tc->labels)->toHaveCount(1);
        expect($tc->labels->first()->name)->toBe('老人魚');
    });

    it('can sync labels on a tribal classification', function () {
        $fish = Fish::factory()->create();
        $tc = TribalClassification::factory()->forTribe('yayo')->create(['fish_id' => $fish->id]);

        $label1 = FishLabel::create(['group' => 'food_category', 'name' => 'rahet']);
        $label2 = FishLabel::create(['group' => '其他', 'name' => '老人魚']);
        $label3 = FishLabel::create(['group' => 'food_category', 'name' => 'oyod']);

        $tc->labels()->attach([$label1->id, $label2->id]);
        $tc->labels()->sync([$label3->id]);

        $tc->refresh();
        expect($tc->labels)->toHaveCount(1);
        expect($tc->labels->first()->name)->toBe('oyod');
    });
});
