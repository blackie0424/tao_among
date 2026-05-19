<?php

use App\Services\Line\LineFishMessageBuilder;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use Tests\TestCase;

class LineFishMessageBuilderTest extends TestCase
{
    private LineFishMessageBuilder $service;
    private array $baseFish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LineFishMessageBuilder();
        $this->baseFish = [
            'id' => 1,
            'name' => '測試魚',
            'image_url' => 'https://example.com/image.jpg',
            'display_image_url' => 'https://example.com/display.jpg',
            'audio_url' => null,
            'capture_records_count' => 0,
            'tribal_classifications' => [],
        ];
    }

    public function test_build_fish_card_editor_sees_footer_buttons(): void
    {
        $message = $this->service->buildFishCard($this->baseFish, null, true);

        $json = $this->extractBubbleJson($message);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertContains('✏️ 修改名稱', $footerLabels);
        $this->assertContains('🎤 提供發音', $footerLabels);
        $this->assertContains('⚡ 批次捕獲紀錄', $footerLabels);
        $this->assertContains('🧠 新增進階知識', $footerLabels);
    }

    public function test_build_fish_card_without_audio_has_no_audio_buttons_in_body(): void
    {
        $message = $this->service->buildFishCard($this->baseFish, null, false);

        $json = $this->extractBubbleJson($message);
        $bodyLabels = $this->extractBodyButtonLabels($json);

        $audioButtons = array_filter(
            $bodyLabels,
            fn ($label) => str_contains($label, '發音') || str_contains($label, '🔊') || str_contains($label, '🔇')
        );

        $this->assertEmpty($audioButtons);
    }

    public function test_build_fish_list_message_multiple_returns_carousel(): void
    {
        $messages = $this->service->buildFishListMessage([
            $this->baseFish,
            array_merge($this->baseFish, ['id' => 2, 'name' => '魚二']),
        ], true);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(FlexMessage::class, $messages[0]);

        $json = json_decode(json_encode($messages[0]->jsonSerialize()), true);
        $this->assertSame('carousel', $json['contents']['type'] ?? null);
        $this->assertCount(2, $json['contents']['contents'] ?? []);
    }

    public function test_build_capture_records_carousel_returns_flex_message(): void
    {
        $message = $this->service->buildCaptureRecordsCarousel([
            [
                'tribe' => 'iraraley',
                'location' => '海邊',
                'capture_method' => '釣魚',
                'capture_date' => '2025-01-01',
                'notes' => '備註',
                'image_url' => 'https://example.com/capture.jpg',
            ],
        ], '測試魚');

        $json = json_decode(json_encode($message->jsonSerialize()), true);

        $this->assertSame('測試魚 的捕獲紀錄', $message->getAltText());
        $this->assertSame('carousel', $json['contents']['type'] ?? null);
        $this->assertCount(1, $json['contents']['contents'] ?? []);
    }

    private function extractBubbleJson(FlexMessage $message): array
    {
        return json_decode(json_encode($message->jsonSerialize()), true);
    }

    private function extractFooterButtonLabels(array $bubbleJson): array
    {
        $bubble = $bubbleJson['contents'] ?? $bubbleJson;
        $footerContents = $bubble['footer']['contents'] ?? [];

        return array_map(fn ($item) => $item['action']['label'] ?? '', $footerContents);
    }

    private function extractBodyButtonLabels(array $bubbleJson): array
    {
        $bubble = $bubbleJson['contents'] ?? $bubbleJson;
        $bodyContents = $bubble['body']['contents'] ?? [];
        $labels = [];

        foreach ($bodyContents as $item) {
            if (($item['type'] ?? '') === 'button' && isset($item['action']['label'])) {
                $labels[] = $item['action']['label'];
            }
        }

        return $labels;
    }
}
