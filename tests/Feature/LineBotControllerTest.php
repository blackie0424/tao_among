<?php

use App\Http\Controllers\LineBotController;
use App\Services\LineBotService;
use App\Http\Controllers\ApiFishController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineBotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 設定測試環境變數
        config(['line.channel_secret' => 'test_channel_secret']);
        config(['line.channel_access_token' => 'test_access_token']);
    }

    /**
     * 測試缺少簽章的請求應該回傳 400
     */
    public function test_webhook_missing_signature_returns_400(): void
    {
        $response = $this->postJson('/prefix/api/line/webhook', [
            'events' => [],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Missing signature']);
    }

    /**
     * 測試無效的簽章應該回傳 400
     */
    public function test_webhook_invalid_signature_returns_400(): void
    {
        $body = json_encode(['events' => []]);
        
        $response = $this->post('/prefix/api/line/webhook', [], [
            'X-Line-Signature' => 'invalid_signature_value',
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(400);
    }

    /**
     * 測試空白訊息應該回傳使用說明
     * 
     * 注意：這個測試需要 mock LINE SDK，因為實際驗證簽章會失敗
     */
    public function test_build_help_message(): void
    {
        $service = new LineBotService();
        $message = $service->buildHelpMessage();
        
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $message);
        $this->assertStringContainsString('歡迎使用', $message->getText());
    }

    /**
     * 測試建立魚類卡片
     */
    public function test_build_fish_card(): void
    {
        $fishData = [
            'id' => 1,
            'name' => '測試魚',
            'image_url' => 'https://example.com/image.jpg',
            'display_image_url' => 'https://example.com/display.jpg',
            'tribal_classifications' => [
                ['tribe' => 'ivalino', 'food_category' => 'oyod'],
            ],
        ];

        $service = new LineBotService();
        $message = $service->buildFishCard($fishData);

        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $message);
        $this->assertEquals('測試魚', $message->getAltText());
    }

    /**
     * 測試找不到資料時的訊息
     */
    public function test_build_fish_list_message_empty(): void
    {
        $service = new LineBotService();
        $messages = $service->buildFishListMessage([]);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $messages[0]);
        $this->assertStringContainsString('找不到', $messages[0]->getText());
    }

    /**
     * 測試單筆資料時回傳卡片
     */
    public function test_build_fish_list_message_single(): void
    {
        $fishData = [
            [
                'id' => 1,
                'name' => '測試魚',
                'image_url' => 'https://example.com/image.jpg',
                'tribal_classifications' => [],
            ],
        ];

        $service = new LineBotService();
        $messages = $service->buildFishListMessage($fishData);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $messages[0]);
    }

    /**
     * 測試多筆資料時回傳輪播
     */
    public function test_build_fish_list_message_multiple(): void
    {
        $fishData = [
            [
                'id' => 1,
                'name' => '魚1',
                'image_url' => 'https://example.com/1.jpg',
                'tribal_classifications' => [],
            ],
            [
                'id' => 2,
                'name' => '魚2',
                'image_url' => 'https://example.com/2.jpg',
                'tribal_classifications' => [],
            ],
        ];

        $service = new LineBotService();
        $messages = $service->buildFishListMessage($fishData);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $messages[0]);
    }
}
