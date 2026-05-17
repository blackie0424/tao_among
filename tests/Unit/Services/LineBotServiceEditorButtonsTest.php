<?php

use App\Services\Line\LineFishMessageBuilder;
use App\Services\Line\LineMenuMessageBuilder;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use Tests\TestCase;

/**
 * TDD 測試：editor 專屬按鈕整合至卡片 footer
 *
 * 需求：
 * 1. 「修改名稱」和「提供發音」按鈕整合到圖卡 footer（不再放 Quick Reply）
 * 2. 只有 editor/admin 才能看到這兩個按鈕（透過 isEditor 參數控制）
 */
class LineBotServiceEditorButtonsTest extends TestCase
{
    private array $baseFish;
    private LineFishMessageBuilder $fishMessageBuilder;
    private LineMenuMessageBuilder $menuMessageBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fishMessageBuilder = new LineFishMessageBuilder();
        $this->menuMessageBuilder = new LineMenuMessageBuilder();

        $this->baseFish = [
            'id'                      => 1,
            'name'                    => '測試魚',
            'image_url'               => 'https://example.com/image.jpg',
            'display_image_url'       => 'https://example.com/display.jpg',
            'audio_url'               => null,
            'capture_records_count'   => 0,
            'tribal_classifications'  => [],
        ];
    }

    // ------------------------------------------------------------------
    // buildFishCard 測試
    // ------------------------------------------------------------------

    /**
     * editor 看圖卡時，footer 應包含「修改名稱」按鈕
     */
    public function test_build_fish_card_editor_sees_rename_button_in_footer(): void
    {
        $message = $this->fishMessageBuilder->buildFishCard($this->baseFish, null, true);

        $json = $this->extractBubbleJson($message);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertContains('✏️ 修改名稱', $footerLabels, 'editor 應能在 footer 看到「修改名稱」按鈕');
    }

    /**
     * editor 看圖卡時，footer 應包含「提供發音」按鈕
     */
    public function test_build_fish_card_editor_sees_audio_button_in_footer(): void
    {
        $message = $this->fishMessageBuilder->buildFishCard($this->baseFish, null, true);

        $json = $this->extractBubbleJson($message);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertContains('🎤 提供發音', $footerLabels, 'editor 應能在 footer 看到「提供發音」按鈕');
    }

    /**
     * editor 看圖卡時，footer 應包含「批次捕獲紀錄」按鈕
     */
    public function test_build_fish_card_editor_sees_batch_capture_button_in_footer(): void
    {
        $message = $this->fishMessageBuilder->buildFishCard($this->baseFish, null, true);

        $json = $this->extractBubbleJson($message);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertContains('⚡ 批次捕獲紀錄', $footerLabels, 'editor 應能在 footer 看到「批次捕獲紀錄」按鈕');
    }

    /**
     * viewer（非 editor）看圖卡時，footer 不應包含「修改名稱」按鈕
     */
    public function test_build_fish_card_viewer_does_not_see_rename_button(): void
    {
        $message = $this->fishMessageBuilder->buildFishCard($this->baseFish, null, false);

        $json = $this->extractBubbleJson($message);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertNotContains('✏️ 修改名稱', $footerLabels, 'viewer 不應看到「修改名稱」按鈕');
    }

    /**
     * viewer 看圖卡時，footer 不應包含「提供發音」按鈕
     */
    public function test_build_fish_card_viewer_does_not_see_audio_button(): void
    {
        $message = $this->fishMessageBuilder->buildFishCard($this->baseFish, null, false);

        $json = $this->extractBubbleJson($message);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertNotContains('🎤 提供發音', $footerLabels, 'viewer 不應看到「提供發音」按鈕');
    }

    /**
     * viewer 看圖卡時，footer 不應包含「批次捕獲紀錄」按鈕
     */
    public function test_build_fish_card_viewer_does_not_see_batch_capture_button(): void
    {
        $message = $this->fishMessageBuilder->buildFishCard($this->baseFish, null, false);

        $json = $this->extractBubbleJson($message);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertNotContains('⚡ 批次捕獲紀錄', $footerLabels, 'viewer 不應看到「批次捕獲紀錄」按鈕');
    }

    /**
     * 預設（不傳 isEditor）等同 viewer，不含 editor 按鈕
     */
    public function test_build_fish_card_defaults_to_viewer_behavior(): void
    {
        $message = $this->fishMessageBuilder->buildFishCard($this->baseFish);

        $json = $this->extractBubbleJson($message);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertNotContains('✏️ 修改名稱', $footerLabels);
        $this->assertNotContains('🎤 提供發音', $footerLabels);
        $this->assertNotContains('⚡ 批次捕獲紀錄', $footerLabels);
    }

    /**
     * editor 按鈕的 postback data 應帶有正確的 fish_id
     */
    public function test_build_fish_card_editor_buttons_have_correct_fish_id(): void
    {
        $fish = array_merge($this->baseFish, ['id' => 42]);
        $message = $this->fishMessageBuilder->buildFishCard($fish, null, true);

        $json = $this->extractBubbleJson($message);
        $footerActions = $this->extractFooterButtonActions($json);

        $renameAction = $this->findActionByLabel($footerActions, '✏️ 修改名稱');
        $audioAction  = $this->findActionByLabel($footerActions, '🎤 提供發音');
        $batchCaptureAction = $this->findActionByLabel($footerActions, '⚡ 批次捕獲紀錄');

        $this->assertNotNull($renameAction, '應找到修改名稱 action');
        $this->assertStringContainsString('fish_id=42', $renameAction['data'] ?? '', 'postback data 應含 fish_id=42');

        $this->assertNotNull($audioAction, '應找到提供發音 action');
        $this->assertStringContainsString('fish_id=42', $audioAction['data'] ?? '', 'postback data 應含 fish_id=42');

        $this->assertNotNull($batchCaptureAction, '應找到批次捕獲紀錄 action');
        $this->assertSame('postback', $batchCaptureAction['type'] ?? null);
        $this->assertStringContainsString('action=start_batch_capture_record', $batchCaptureAction['data'] ?? '');
        $this->assertStringContainsString('fish_id=42', $batchCaptureAction['data'] ?? '');
    }

    /**
     * editor 圖卡在有捕獲紀錄時，footer 同時有「查看捕獲紀錄」及 editor 按鈕
     */
    public function test_build_fish_card_editor_with_capture_records(): void
    {
        $fish = array_merge($this->baseFish, ['capture_records_count' => 5]);
        $message = $this->fishMessageBuilder->buildFishCard($fish, null, true);

        $json = $this->extractBubbleJson($message);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertContains('✏️ 修改名稱', $footerLabels);
        $this->assertContains('🎤 提供發音', $footerLabels);
        $this->assertContains('⚡ 批次捕獲紀錄', $footerLabels);
        // 捕獲紀錄按鈕也應保留
        $captureButton = array_filter($footerLabels, fn ($l) => str_contains($l, '查看捕獲紀錄'));
        $this->assertNotEmpty($captureButton, '有捕獲紀錄時應顯示查看捕獲紀錄按鈕');
    }

    // ------------------------------------------------------------------
    // buildFishCardWithQuickReply 測試
    // ------------------------------------------------------------------

    /**
     * editor 的 Quick Reply 不應再包含「修改名稱」（已移入圖卡）
     */
    public function test_build_fish_card_with_quick_reply_editor_no_rename_in_qr(): void
    {
        $message = $this->fishMessageBuilder->buildFishCardWithQuickReply($this->baseFish, true);

        $qrLabels = $this->extractQuickReplyLabels($message);

        $this->assertNotContains('✏️ 修改名稱', $qrLabels, '修改名稱已移入卡片，Quick Reply 不應再有');
    }

    /**
     * editor 的 Quick Reply 不應再包含「提供發音」（已移入圖卡）
     */
    public function test_build_fish_card_with_quick_reply_editor_no_audio_in_qr(): void
    {
        $message = $this->fishMessageBuilder->buildFishCardWithQuickReply($this->baseFish, true);

        $qrLabels = $this->extractQuickReplyLabels($message);

        $this->assertNotContains('🎤 提供發音', $qrLabels, '提供發音已移入卡片，Quick Reply 不應再有');
    }

    /**
     * viewer 的 Quick Reply 也不應包含編輯按鈕
     */
    public function test_build_fish_card_with_quick_reply_viewer_no_editor_buttons_in_qr(): void
    {
        $message = $this->fishMessageBuilder->buildFishCardWithQuickReply($this->baseFish, false);

        $qrLabels = $this->extractQuickReplyLabels($message);

        $this->assertNotContains('✏️ 修改名稱', $qrLabels);
        $this->assertNotContains('🎤 提供發音', $qrLabels);
        $this->assertNotContains('⚡ 批次捕獲紀錄', $qrLabels);
    }

    /**
     * 「我不知道」魚類的「換一隻」Quick Reply 應保留
     */
    public function test_build_fish_card_with_quick_reply_unknown_fish_keeps_swap_button(): void
    {
        $unknownFish = array_merge($this->baseFish, ['name' => '我不知道']);
        $message = $this->service->buildFishCardWithQuickReply($unknownFish, false);

        $qrLabels = $this->extractQuickReplyLabels($message);

        $this->assertContains('🔄 換一隻', $qrLabels, '「我不知道」應保留「換一隻」Quick Reply');
    }

    /**
     * 一般魚類的 Quick Reply 清單可以為空（整合後 QR 僅剩換一隻才有意義）
     */
    public function test_build_fish_card_with_quick_reply_normal_fish_viewer_may_have_empty_qr(): void
    {
        $message = $this->service->buildFishCardWithQuickReply($this->baseFish, false);

        // Quick Reply 可能為 null 或為空陣列，不應有 editor 按鈕
        $qrLabels = $this->extractQuickReplyLabels($message);
        $this->assertNotContains('✏️ 修改名稱', $qrLabels);
        $this->assertNotContains('🎤 提供發音', $qrLabels);
    }

    // ------------------------------------------------------------------
    // buildFishBrowseCarousel 測試
    // ------------------------------------------------------------------

    /**
     * editor 瀏覽 Carousel 時，每張卡的 footer 應含 editor 按鈕
     */
    public function test_build_fish_browse_carousel_editor_each_bubble_has_editor_buttons(): void
    {
        $fishes = [$this->baseFish, array_merge($this->baseFish, ['id' => 2, 'name' => '魚二'])];

        [$carouselMessage] = $this->fishMessageBuilder->buildFishBrowseCarousel(
            $fishes,
            false,
            'action=next_page',
            '測試瀏覽',
            null,
            true // isEditor
        );

        $json = json_decode(json_encode($carouselMessage->jsonSerialize()), true);
        $bubbles = $json['contents']['contents'] ?? [];

        $this->assertCount(2, $bubbles);

        foreach ($bubbles as $i => $bubble) {
            $footerLabels = $this->extractFooterButtonLabelsFromBubble($bubble);
            $this->assertContains('✏️ 修改名稱', $footerLabels, "第 {$i} 張卡片應有修改名稱");
            $this->assertContains('🎤 提供發音', $footerLabels, "第 {$i} 張卡片應有提供發音");
            $this->assertContains('⚡ 批次捕獲紀錄', $footerLabels, "第 {$i} 張卡片應有批次捕獲紀錄");
        }
    }

    /**
     * viewer 瀏覽 Carousel 時，所有卡片均不含 editor 按鈕
     */
    public function test_build_fish_browse_carousel_viewer_no_editor_buttons(): void
    {
        $fishes = [$this->baseFish, array_merge($this->baseFish, ['id' => 2, 'name' => '魚二'])];

        [$carouselMessage] = $this->fishMessageBuilder->buildFishBrowseCarousel(
            $fishes,
            false,
            'action=next_page',
            '測試瀏覽',
            null,
            false // isEditor
        );

        $json = json_decode(json_encode($carouselMessage->jsonSerialize()), true);
        $bubbles = $json['contents']['contents'] ?? [];

        foreach ($bubbles as $i => $bubble) {
            $footerLabels = $this->extractFooterButtonLabelsFromBubble($bubble);
            $this->assertNotContains('✏️ 修改名稱', $footerLabels, "第 {$i} 張卡片不應有修改名稱");
            $this->assertNotContains('🎤 提供發音', $footerLabels, "第 {$i} 張卡片不應有提供發音");
            $this->assertNotContains('⚡ 批次捕獲紀錄', $footerLabels, "第 {$i} 張卡片不應有批次捕獲紀錄");
        }
    }

    // ------------------------------------------------------------------
    // buildBrowseTribesCarousel 測試
    // ------------------------------------------------------------------

    /**
     * 部落選單應改為單一 Flex bubble，避免使用者滑動 carousel。
     */
    public function test_build_browse_tribes_carousel_returns_single_flex_bubble(): void
    {
        config(['fish_options.tribes' => ['iraraley', 'imowrod', 'ivalino']]);

        [$message] = $this->menuMessageBuilder->buildBrowseTribesMenu();

        $json = $this->extractBubbleJson($message);

        $this->assertSame('flex', $json['type']);
        $this->assertSame('bubble', $json['contents']['type']);
        $this->assertStringContainsString('請選擇部落', $message->getAltText());
    }

    /**
     * 部落選單應顯示說明文字，讓使用者知道可直接點選部落。
     */
    public function test_build_browse_tribes_carousel_contains_instruction_text(): void
    {
        config(['fish_options.tribes' => ['iraraley', 'imowrod']]);

        [$message] = $this->menuMessageBuilder->buildBrowseTribesMenu();

        $json = $this->extractBubbleJson($message);
        $bodyTexts = $this->extractBodyTextContents($json);

        $this->assertContains('點選下列部落，觀看該部落的魚類資訊', $bodyTexts);
    }

    /**
     * 部落選單應一次列出所有部落按鈕，且沿用既有 postback data。
     */
    public function test_build_browse_tribes_carousel_lists_all_tribes_with_existing_postback_contract(): void
    {
        config(['fish_options.tribes' => ['iraraley', 'imowrod', 'ivalino']]);

        [$message] = $this->menuMessageBuilder->buildBrowseTribesMenu();

        $json = $this->extractBubbleJson($message);
        $actions = $this->extractBodyButtonActions($json);

        $this->assertCount(3, $actions);
        $this->assertSame('Iraraley', $actions[0]['label'] ?? null);
        $this->assertSame('action=browse_tribe_data&tribe=iraraley', $actions[0]['data'] ?? null);
        $this->assertSame('Imowrod', $actions[1]['label'] ?? null);
        $this->assertSame('action=browse_tribe_data&tribe=imowrod', $actions[1]['data'] ?? null);
        $this->assertSame('Ivalino', $actions[2]['label'] ?? null);
        $this->assertSame('action=browse_tribe_data&tribe=ivalino', $actions[2]['data'] ?? null);
    }

    // ------------------------------------------------------------------
    // buildFishListMessage 測試
    // ------------------------------------------------------------------

    /**
     * editor 搜尋單筆結果，回傳的卡片含 editor 按鈕
     */
    public function test_build_fish_list_message_single_editor_has_buttons(): void
    {
        $messages = $this->fishMessageBuilder->buildFishListMessage([$this->baseFish], true);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(FlexMessage::class, $messages[0]);

        $json = $this->extractBubbleJson($messages[0]);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertContains('✏️ 修改名稱', $footerLabels);
        $this->assertContains('🎤 提供發音', $footerLabels);
        $this->assertContains('⚡ 批次捕獲紀錄', $footerLabels);
    }

    /**
     * viewer 搜尋單筆結果，回傳的卡片不含 editor 按鈕
     */
    public function test_build_fish_list_message_single_viewer_no_buttons(): void
    {
        $messages = $this->fishMessageBuilder->buildFishListMessage([$this->baseFish], false);

        $this->assertCount(1, $messages);
        $json = $this->extractBubbleJson($messages[0]);
        $footerLabels = $this->extractFooterButtonLabels($json);

        $this->assertNotContains('✏️ 修改名稱', $footerLabels);
        $this->assertNotContains('🎤 提供發音', $footerLabels);
        $this->assertNotContains('⚡ 批次捕獲紀錄', $footerLabels);
    }

    /**
     * editor 搜尋多筆結果，carousel 每張卡均含 editor 按鈕
     */
    public function test_build_fish_list_message_multiple_editor_has_buttons(): void
    {
        $fishes = [
            $this->baseFish,
            array_merge($this->baseFish, ['id' => 2, 'name' => '魚二']),
        ];
        $messages = $this->fishMessageBuilder->buildFishListMessage($fishes, true);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(FlexMessage::class, $messages[0]);

        $json = json_decode(json_encode($messages[0]->jsonSerialize()), true);
        $bubbles = $json['contents']['contents'] ?? [];

        foreach ($bubbles as $i => $bubble) {
            $footerLabels = $this->extractFooterButtonLabelsFromBubble($bubble);
            $this->assertContains('✏️ 修改名稱', $footerLabels, "Carousel 第 {$i} 張應有修改名稱");
            $this->assertContains('⚡ 批次捕獲紀錄', $footerLabels, "Carousel 第 {$i} 張應有批次捕獲紀錄");
        }
    }

    // ------------------------------------------------------------------
    // 私有輔助方法
    // ------------------------------------------------------------------

    /**
     * 從 FlexMessage（單張 bubble）序列化取得 JSON 陣列
     */
    private function extractBubbleJson(FlexMessage $message): array
    {
        return json_decode(json_encode($message->jsonSerialize()), true);
    }

    /**
     * 從 bubble JSON 提取 footer 所有按鈕的 label 清單
     */
    private function extractFooterButtonLabels(array $bubbleJson): array
    {
        $bubble = $bubbleJson['contents'] ?? $bubbleJson;
        return $this->extractFooterButtonLabelsFromBubble($bubble);
    }

    /**
     * 從單張 bubble 陣列提取 footer 按鈕 label 清單
     */
    private function extractFooterButtonLabelsFromBubble(array $bubble): array
    {
        $footerContents = $bubble['footer']['contents'] ?? [];
        return array_map(fn ($item) => $item['action']['label'] ?? '', $footerContents);
    }

    /**
     * 從 bubble JSON 提取 footer 所有按鈕的 action 清單
     */
    private function extractFooterButtonActions(array $bubbleJson): array
    {
        $bubble = $bubbleJson['contents'] ?? $bubbleJson;
        $footerContents = $bubble['footer']['contents'] ?? [];
        return array_map(fn ($item) => $item['action'] ?? [], $footerContents);
    }

    /**
     * 根據 label 在 actions 中找到對應的 action
     */
    private function findActionByLabel(array $actions, string $label): ?array
    {
        foreach ($actions as $action) {
            if (($action['label'] ?? '') === $label) {
                return $action;
            }
        }
        return null;
    }

    /**
     * 從 FlexMessage 的 Quick Reply 提取 label 清單
     */
    private function extractQuickReplyLabels(FlexMessage $message): array
    {
        $json = json_decode(json_encode($message->jsonSerialize()), true);
        $items = $json['quickReply']['items'] ?? [];
        return array_map(fn ($item) => $item['action']['label'] ?? '', $items);
    }

    /**
     * 從 bubble JSON 提取 body 所有內容的 action label 清單（含按鈕）
     */
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

    /**
     * 從 bubble JSON 提取 body 內所有文字內容。
     */
    private function extractBodyTextContents(array $bubbleJson): array
    {
        $bubble = $bubbleJson['contents'] ?? $bubbleJson;
        $bodyContents = $bubble['body']['contents'] ?? [];
        $texts = [];

        foreach ($bodyContents as $item) {
            if (($item['type'] ?? '') === 'text' && isset($item['text'])) {
                $texts[] = $item['text'];
            }
        }

        return $texts;
    }

    /**
     * 從 bubble JSON 提取 body 所有按鈕 action。
     */
    private function extractBodyButtonActions(array $bubbleJson): array
    {
        $bubble = $bubbleJson['contents'] ?? $bubbleJson;
        $bodyContents = $bubble['body']['contents'] ?? [];
        $actions = [];

        foreach ($bodyContents as $item) {
            if (($item['type'] ?? '') === 'button' && isset($item['action'])) {
                $actions[] = $item['action'];
            }
        }

        return $actions;
    }
}
