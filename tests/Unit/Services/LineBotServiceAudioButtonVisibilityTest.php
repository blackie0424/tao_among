<?php

use App\Services\LineBotService;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use Tests\TestCase;

/**
 * TDD 測試：發音按鈕條件顯示
 *
 * 需求：
 * - 有音檔 → body 顯示「🔊 播放發音」按鈕，任何角色皆可使用
 * - 無音檔 → body 不顯示任何發音相關按鈕
 *   （editor 透過 footer 的「🎤 提供發音」按鈕新增錄音）
 */
class LineBotServiceAudioButtonVisibilityTest extends TestCase
{
    private LineBotService $service;
    private array $baseFish;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LineBotService();
        $this->baseFish = [
            'id'                     => 10,
            'name'                   => '測試魚',
            'image_url'              => 'https://example.com/image.jpg',
            'display_image_url'      => 'https://example.com/display.jpg',
            'audio_url'              => null,
            'capture_records_count'  => 0,
            'tribal_classifications' => [],
        ];
    }

    // ------------------------------------------------------------------
    // 有音檔
    // ------------------------------------------------------------------

    /** 有音檔時，body 應顯示「🔊 播放發音」按鈕 */
    public function test_with_audio_shows_play_button(): void
    {
        $fish = array_merge($this->baseFish, ['audio_url' => 'https://s3.example.com/audio.m4a']);
        $message = $this->service->buildFishCard($fish);

        $json = $this->extractBubbleJson($message);
        $bodyLabels = $this->extractBodyButtonLabels($json);

        $this->assertContains('🔊 播放發音', $bodyLabels);
    }

    /** 有音檔時，body 不應顯示「尚無發音」相關按鈕 */
    public function test_with_audio_no_no_audio_button(): void
    {
        $fish = array_merge($this->baseFish, ['audio_url' => 'https://s3.example.com/audio.m4a']);
        $message = $this->service->buildFishCard($fish);

        $json = $this->extractBubbleJson($message);
        $bodyLabels = $this->extractBodyButtonLabels($json);

        $noAudioButtons = array_filter($bodyLabels, fn($l) => str_contains($l, '尚無') || str_contains($l, '🔇'));
        $this->assertEmpty($noAudioButtons, '有音檔時不應顯示尚無發音相關按鈕');
    }

    /** 有音檔時，viewer 也能看到「🔊 播放發音」（不受 isEditor 影響） */
    public function test_with_audio_viewer_can_see_play_button(): void
    {
        $fish = array_merge($this->baseFish, ['audio_url' => 'https://s3.example.com/audio.m4a']);
        $message = $this->service->buildFishCard($fish, null, false); // viewer

        $json = $this->extractBubbleJson($message);
        $bodyLabels = $this->extractBodyButtonLabels($json);

        $this->assertContains('🔊 播放發音', $bodyLabels);
    }

    // ------------------------------------------------------------------
    // 無音檔
    // ------------------------------------------------------------------

    /** 無音檔時，body 不應顯示任何發音按鈕（viewer） */
    public function test_without_audio_no_button_for_viewer(): void
    {
        $message = $this->service->buildFishCard($this->baseFish, null, false); // viewer

        $json = $this->extractBubbleJson($message);
        $bodyLabels = $this->extractBodyButtonLabels($json);

        $audioButtons = array_filter($bodyLabels, fn($l) =>
            str_contains($l, '發音') || str_contains($l, '🔊') || str_contains($l, '🔇')
        );
        $this->assertEmpty($audioButtons, '無音檔時 viewer 不應看到任何發音按鈕');
    }

    /** 無音檔時，editor 的 body 也不應顯示發音按鈕（改用 footer 的「提供發音」） */
    public function test_without_audio_no_button_for_editor(): void
    {
        $message = $this->service->buildFishCard($this->baseFish, null, true); // editor

        $json = $this->extractBubbleJson($message);
        $bodyLabels = $this->extractBodyButtonLabels($json);

        $audioButtons = array_filter($bodyLabels, fn($l) =>
            str_contains($l, '🔊') || str_contains($l, '🔇') || str_contains($l, '尚無')
        );
        $this->assertEmpty($audioButtons, '無音檔時 body 不應顯示發音相關按鈕');
    }

    /** 無音檔 + editor：footer 仍保有「🎤 提供發音」按鈕 */
    public function test_without_audio_editor_footer_has_provide_audio_button(): void
    {
        $message = $this->service->buildFishCard($this->baseFish, null, true); // editor

        $json = $this->extractBubbleJson($message);
        $footerContents = ($json['contents'] ?? $json)['footer']['contents'] ?? [];
        $footerLabels = array_map(fn($i) => $i['action']['label'] ?? '', $footerContents);

        $this->assertContains('🎤 提供發音', $footerLabels, '無音檔時 editor footer 應有「提供發音」');
    }

    /** 有音檔 + editor：body 有播放按鈕，footer 仍有「提供發音」（可覆蓋錄音） */
    public function test_with_audio_editor_can_still_provide_new_audio(): void
    {
        $fish = array_merge($this->baseFish, ['audio_url' => 'https://s3.example.com/audio.m4a']);
        $message = $this->service->buildFishCard($fish, null, true); // editor

        $json = $this->extractBubbleJson($message);
        $bodyLabels = $this->extractBodyButtonLabels($json);
        $footerContents = ($json['contents'] ?? $json)['footer']['contents'] ?? [];
        $footerLabels = array_map(fn($i) => $i['action']['label'] ?? '', $footerContents);

        $this->assertContains('🔊 播放發音', $bodyLabels);
        $this->assertContains('🎤 提供發音', $footerLabels, '有音檔時 editor 仍可覆蓋提供新錄音');
    }

    // ------------------------------------------------------------------
    // 輔助方法
    // ------------------------------------------------------------------

    private function extractBubbleJson(FlexMessage $message): array
    {
        return json_decode(json_encode($message->jsonSerialize()), true);
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
