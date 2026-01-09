<?php

namespace Tests\Feature;

use App\Models\Fish;
use App\Models\CaptureRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class CreateFishCaptureMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_fish_with_capture_method()
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        // 確保 config 設定
        $captureMethods = config('fish_options.capture_methods');
        $validMethod = array_key_first($captureMethods);

        $response = $this->post('/fish', [
            'name' => 'Test Fish',
            'image' => 'test-image.jpg',
            'capture_method' => $validMethod,
        ]);

        $response->assertRedirect();

        $fish = Fish::where('name', 'Test Fish')->first();
        $this->assertNotNull($fish);

        $captureRecord = CaptureRecord::where('fish_id', $fish->id)->first();
        $this->assertNotNull($captureRecord);
        $this->assertEquals($validMethod, $captureRecord->capture_method);
    }

    public function test_cannot_create_fish_with_invalid_capture_method()
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $response = $this->postJson('/fish', [
            'name' => 'Test Fish Invalid',
            'image' => 'test-image.jpg',
            'capture_method' => 'invalid_method',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['capture_method']);

        $this->assertDatabaseMissing('fish', ['name' => 'Test Fish Invalid']);
    }

    public function test_cannot_create_fish_without_capture_method()
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $response = $this->postJson('/fish', [
            'name' => 'Test Fish Missing',
            'image' => 'test-image.jpg',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['capture_method']);

        $this->assertDatabaseMissing('fish', ['name' => 'Test Fish Missing']);
    }

    public function test_create_page_receives_capture_methods_prop()
    {
        $response = $this->get('/fish/create');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CreateFish')
            ->has('captureMethods')
        );
    }
}
