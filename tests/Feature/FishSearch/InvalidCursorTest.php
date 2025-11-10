<?php

namespace Tests\Feature\FishSearch;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;

class InvalidCursorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FR-006 INVALID_CURSOR: last_id 非法時回傳 422 JSON
     */
    public function test_invalid_cursor_returns_422_json(): void
    {
        Fish::factory()->count(3)->create();
        $response = $this->getJson('/fishs?last_id=0');
        $response->assertStatus(422)
            ->assertJson(['error' => 'INVALID_CURSOR']);
    }
}
