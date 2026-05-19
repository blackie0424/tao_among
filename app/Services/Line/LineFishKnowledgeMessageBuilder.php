<?php

namespace App\Services\Line;

use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\TextMessage;

class LineFishKnowledgeMessageBuilder
{
    public function buildBrowseKnowledgeCard(string $fishName, array $notes, int $totalCount): FlexMessage
    {
        $displayedCount = count($notes);
        $contents = [
            [
                'type' => 'text',
                'text' => "📚 {$fishName}",
                'weight' => 'bold',
                'size' => 'lg',
                'wrap' => true,
            ],
            [
                'type' => 'text',
                'text' => $totalCount > $displayedCount
                    ? "共 {$totalCount} 筆，以下顯示前 {$displayedCount} 筆。"
                    : "共 {$totalCount} 筆進階知識。",
                'size' => 'sm',
                'color' => '#666666',
                'margin' => 'md',
                'wrap' => true,
            ],
        ];

        foreach ($this->groupBrowseNotes($notes) as $group) {
            $sectionContents = [[
                'type' => 'text',
                'text' => $group['title'],
                'weight' => 'bold',
                'size' => 'sm',
                'wrap' => true,
                'color' => '#1a1a2e',
            ]];

            foreach ($group['notes'] as $note) {
                $sectionContents[] = [
                    'type' => 'text',
                    'text' => $note,
                    'size' => 'sm',
                    'wrap' => true,
                    'color' => '#333333',
                ];
            }

            $contents[] = [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'sm',
                'margin' => 'lg',
                'paddingAll' => '12px',
                'backgroundColor' => '#f5f7fb',
                'cornerRadius' => 'md',
                'contents' => $sectionContents,
            ];
        }

        if ($totalCount > $displayedCount) {
            $contents[] = [
                'type' => 'text',
                'text' => '其餘 '.($totalCount - $displayedCount).' 筆請至系統後台查看更多內容。',
                'size' => 'xs',
                'color' => '#666666',
                'margin' => 'lg',
                'wrap' => true,
            ];
        }

        return new FlexMessage([
            'type' => 'flex',
            'altText' => "{$fishName} 的進階知識",
            'contents' => [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => $contents,
                ],
            ],
        ]);
    }

    public function buildLocateSelector(): FlexMessage
    {
        $buttons = array_map(
            fn (string $tribe) => [
                'type' => 'button',
                'style' => 'secondary',
                'height' => 'sm',
                'action' => [
                    'type' => 'postback',
                    'label' => ucfirst($tribe),
                    'data' => "action=select_knowledge_locate&locate={$tribe}",
                    'displayText' => '選擇部落：'.ucfirst($tribe),
                ],
            ],
            config('fish_options.tribes', [])
        );

        return $this->buildSelectionCard(
            '選擇進階知識部落',
            '🧠 新增進階知識',
            '這項知識是從哪一個部落採集到的？',
            $buttons
        );
    }

    public function buildNoteTypeSelector(): FlexMessage
    {
        $buttons = array_map(
            fn (string $noteType) => [
                'type' => 'button',
                'style' => 'secondary',
                'height' => 'sm',
                'action' => [
                    'type' => 'postback',
                    'label' => $noteType,
                    'data' => 'action=select_knowledge_note_type&note_type='.urlencode($noteType),
                    'displayText' => '選擇分類：'.$noteType,
                ],
            ],
            config('fish_options.note_types', [])
        );

        return $this->buildSelectionCard(
            '選擇進階知識分類',
            '🗂️ 選擇知識分類',
            '這項知識是從屬於哪一個分類項目？',
            $buttons
        );
    }

    public function buildNotePromptMessage(): TextMessage
    {
        $message = new TextMessage([
            'type' => 'text',
            'text' => "請輸入進階知識內容：\n可直接用文字回覆，送出後會立即建立。",
        ]);

        $message->setQuickReply([
            'items' => [[
                'type' => 'action',
                'action' => [
                    'type' => 'postback',
                    'label' => '❌ 取消',
                    'data' => 'action=cancel_add_knowledge',
                    'displayText' => '取消新增進階知識',
                ],
            ]],
        ]);

        return $message;
    }

    private function buildSelectionCard(
        string $altText,
        string $title,
        string $description,
        array $buttons
    ): FlexMessage {
        $contents = array_merge($buttons, [[
            'type' => 'button',
            'style' => 'secondary',
            'height' => 'sm',
            'action' => [
                'type' => 'postback',
                'label' => '❌ 取消',
                'data' => 'action=cancel_add_knowledge',
                'displayText' => '取消新增進階知識',
            ],
        ]]);

        return new FlexMessage([
            'type' => 'flex',
            'altText' => $altText,
            'contents' => [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $title,
                            'weight' => 'bold',
                            'size' => 'lg',
                            'wrap' => true,
                        ],
                        [
                            'type' => 'text',
                            'text' => $description,
                            'size' => 'sm',
                            'color' => '#666666',
                            'margin' => 'md',
                            'wrap' => true,
                        ],
                    ],
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => $contents,
                ],
            ],
        ]);
    }

    /**
     * @param  array<int, array{note: string, note_type: string, locate: string}>  $notes
     * @return array<int, array{title: string, notes: array<int, string>}>
     */
    private function groupBrowseNotes(array $notes): array
    {
        $grouped = [];

        foreach ($notes as $note) {
            $noteType = $this->normalizeBrowseLabel($note['note_type'] ?? '', '未分類');
            $locate = $this->normalizeBrowseLabel($note['locate'] ?? '', '未標示', true);
            $groupKey = "{$noteType}|{$locate}";

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'title' => "{$noteType}｜{$locate}",
                    'notes' => [],
                ];
            }

            $grouped[$groupKey]['notes'][] = $note['note'];
        }

        return array_values($grouped);
    }

    private function normalizeBrowseLabel(string $value, string $fallback, bool $uppercaseFirst = false): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return $fallback;
        }

        return $uppercaseFirst ? ucfirst($trimmed) : $trimmed;
    }
}
