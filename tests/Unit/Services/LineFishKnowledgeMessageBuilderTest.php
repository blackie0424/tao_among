<?php

use App\Services\Line\LineFishKnowledgeMessageBuilder;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use Tests\TestCase;

class LineFishKnowledgeMessageBuilderTest extends TestCase
{
    public function test_build_browse_knowledge_card_groups_notes_and_limits_display(): void
    {
        $builder = new LineFishKnowledgeMessageBuilder;

        $message = $builder->buildBrowseKnowledgeCard('測試魚', [
            ['note_type' => '生態習性', 'locate' => 'ivalino', 'note' => '知識 1'],
            ['note_type' => '生態習性', 'locate' => 'ivalino', 'note' => '知識 2'],
            ['note_type' => '文化意義', 'locate' => 'yayo', 'note' => '知識 3'],
            ['note_type' => '文化意義', 'locate' => 'yayo', 'note' => '知識 4'],
            ['note_type' => '其他', 'locate' => 'iratay', 'note' => '知識 5'],
            ['note_type' => '其他', 'locate' => 'iratay', 'note' => '知識 6'],
        ], 7);

        $this->assertInstanceOf(FlexMessage::class, $message);

        $json = json_decode(json_encode($message->jsonSerialize()), true);
        $texts = $this->flattenTexts($json['contents']);

        $this->assertSame('測試魚 的進階知識', $message->getAltText());
        $this->assertContains('📚 測試魚', $texts);
        $this->assertContains('生態習性｜Ivalino', $texts);
        $this->assertContains('文化意義｜Yayo', $texts);
        $this->assertContains('知識 1', $texts);
        $this->assertContains('知識 6', $texts);
        $this->assertContains('共 7 筆，以下顯示前 6 筆。', $texts);
        $this->assertContains('其餘 1 筆請至系統後台查看更多內容。', $texts);
        $this->assertNotContains('知識 7', $texts);
    }

    private function flattenTexts(array $node): array
    {
        $texts = [];

        if (($node['type'] ?? null) === 'text' && isset($node['text'])) {
            $texts[] = $node['text'];
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $texts = array_merge($texts, $this->flattenTexts($item));
                        }
                    }
                } else {
                    $texts = array_merge($texts, $this->flattenTexts($value));
                }
            }
        }

        return $texts;
    }
}
