<?php

namespace App\Services\Line;

use LINE\Clients\MessagingApi\Model\FlexBubble;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\TextMessage;

class LineFishMessageBuilder
{
    public function buildFishCard(array $fish, ?array $contextTribes = null, bool $isEditor = false): FlexMessage
    {
        $primaryTribes = (! empty($contextTribes))
            ? $contextTribes
            : ['iraraley', 'imowrod'];

        $tribalData = [];
        $otherTribeData = [];

        if (! empty($fish['tribal_classifications'])) {
            foreach ($fish['tribal_classifications'] as $tc) {
                $tribe = $tc['tribe'] ?? '';
                $entry = [
                    'food_category' => $tc['food_category'] ?? null,
                    'processing_method' => $tc['processing_method'] ?? null,
                    'notes' => $tc['notes'] ?? null,
                ];

                if (in_array($tribe, $primaryTribes, true)) {
                    $tribalData[$tribe] = $entry;
                } elseif (empty($contextTribes) && (! empty($entry['food_category']) || ! empty($entry['processing_method']) || ! empty($entry['notes']))) {
                    $otherTribeData[$tribe] = $entry;
                }
            }
        }

        $hasAudio = ! empty($fish['audio_url']);

        $bodyContents = [[
            'type' => 'text',
            'text' => $fish['name'],
            'weight' => 'bold',
            'size' => 'xl',
            'wrap' => true,
            'color' => '#1a1a2e',
        ]];

        if ($hasAudio) {
            $bodyContents[] = [
                'type' => 'button',
                'style' => 'primary',
                'height' => 'sm',
                'margin' => 'sm',
                'color' => '#2c6b8a',
                'action' => [
                    'type' => 'postback',
                    'label' => '🔊 播放發音',
                    'data' => "action=play_audio&fish_id={$fish['id']}&fish_name={$fish['name']}",
                    'displayText' => "播放 {$fish['name']} 的發音",
                ],
            ];
        }

        $bodyContents[] = [
            'type' => 'separator',
            'margin' => 'md',
        ];

        $tribeColors = $this->getTribeColors();

        foreach ($primaryTribes as $tribeKey) {
            $data = $tribalData[$tribeKey] ?? [];
            $foodCategory = ! empty($data['food_category']) ? $data['food_category'] : '尚未紀錄';
            $processingMethod = ! empty($data['processing_method']) ? $data['processing_method'] : '尚未紀錄';
            $color = $tribeColors[$tribeKey] ?? '#333333';
            $label = '🏘️ '.$this->formatTribeLabel($tribeKey);

            $bodyContents[] = [
                'type' => 'box',
                'layout' => 'vertical',
                'margin' => 'md',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => $label,
                        'size' => 'sm',
                        'weight' => 'bold',
                        'color' => $color,
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'horizontal',
                        'margin' => 'xs',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '食用分類',
                                'size' => 'xs',
                                'color' => '#888888',
                                'flex' => 3,
                            ],
                            [
                                'type' => 'text',
                                'text' => $foodCategory,
                                'size' => 'xs',
                                'color' => '#333333',
                                'flex' => 5,
                                'wrap' => true,
                            ],
                        ],
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'horizontal',
                        'margin' => 'xs',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '魚鱗處理',
                                'size' => 'xs',
                                'color' => '#888888',
                                'flex' => 3,
                            ],
                            [
                                'type' => 'text',
                                'text' => $processingMethod,
                                'size' => 'xs',
                                'color' => '#333333',
                                'flex' => 5,
                                'wrap' => true,
                            ],
                        ],
                    ],
                ],
            ];
        }

        if (! empty($otherTribeData)) {
            $bodyContents[] = [
                'type' => 'separator',
                'margin' => 'md',
            ];
            $bodyContents[] = [
                'type' => 'text',
                'text' => '🔍 其他部落田調',
                'size' => 'xs',
                'weight' => 'bold',
                'color' => '#777777',
                'margin' => 'md',
            ];

            foreach ($otherTribeData as $tribe => $data) {
                $parts = [];

                if (! empty($data['food_category'])) {
                    $parts[] = $data['food_category'];
                }
                if (! empty($data['processing_method'])) {
                    $parts[] = $data['processing_method'];
                }
                if (! empty($data['notes'])) {
                    $parts[] = $data['notes'];
                }

                $bodyContents[] = [
                    'type' => 'text',
                    'text' => $this->formatTribeLabel($tribe).'：'.implode(' / ', $parts),
                    'size' => 'xs',
                    'color' => '#999999',
                    'wrap' => true,
                    'margin' => 'xs',
                ];
            }
        }

        $footerContents = [];

        $captureCount = $fish['capture_records_count'] ?? 0;
        if ($captureCount > 0) {
            $footerContents[] = [
                'type' => 'button',
                'style' => 'secondary',
                'height' => 'sm',
                'action' => [
                    'type' => 'postback',
                    'label' => "📸 查看捕獲紀錄（{$captureCount} 筆）",
                    'data' => "action=view_captures&fish_id={$fish['id']}&fish_name={$fish['name']}",
                    'displayText' => "查看 {$fish['name']} 的捕獲紀錄",
                ],
            ];
        }

        $footerContents[] = [
            'type' => 'button',
            'style' => 'secondary',
            'height' => 'sm',
            'action' => [
                'type' => 'postback',
                'label' => '📚 瀏覽進階知識',
                'data' => "action=browse_knowledge&fish_id={$fish['id']}",
                'displayText' => "瀏覽 {$fish['name']} 的進階知識",
            ],
        ];

        if ($isEditor) {
            $footerContents[] = [
                'type' => 'button',
                'style' => 'secondary',
                'height' => 'sm',
                'action' => [
                    'type' => 'postback',
                    'label' => '🧠 新增進階知識',
                    'data' => "action=start_add_knowledge&fish_id={$fish['id']}",
                    'displayText' => '新增進階知識',
                ],
            ];
            $footerContents[] = [
                'type' => 'button',
                'style' => 'secondary',
                'height' => 'sm',
                'action' => [
                    'type' => 'postback',
                    'label' => '✏️ 修改名稱',
                    'data' => "action=start_rename&fish_id={$fish['id']}",
                    'displayText' => '修改名稱',
                ],
            ];
            $footerContents[] = [
                'type' => 'button',
                'style' => 'secondary',
                'height' => 'sm',
                'action' => [
                    'type' => 'postback',
                    'label' => '⚡ 批次捕獲紀錄',
                    'data' => "action=start_batch_capture_record&fish_id={$fish['id']}",
                    'displayText' => '批次新增捕獲紀錄',
                ],
            ];
            $footerContents[] = [
                'type' => 'button',
                'style' => 'secondary',
                'height' => 'sm',
                'action' => [
                    'type' => 'postback',
                    'label' => '🎤 提供發音',
                    'data' => "action=start_add_audio&fish_id={$fish['id']}",
                    'displayText' => '提供發音',
                ],
            ];
        }

        $bubble = FlexBubble::fromAssocArray([
            'type' => 'bubble',
            'hero' => [
                'type' => 'image',
                'url' => $fish['display_image_url'] ?? $fish['image_url'],
                'size' => 'full',
                'aspectRatio' => '20:13',
                'aspectMode' => 'cover',
                'action' => [
                    'type' => 'uri',
                    'uri' => $fish['display_image_url'] ?? $fish['image_url'],
                ],
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => $bodyContents,
                'spacing' => 'none',
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'sm',
                'contents' => $footerContents,
            ],
        ]);

        return new FlexMessage([
            'type' => 'flex',
            'altText' => $fish['name'],
            'contents' => $bubble,
        ]);
    }

    public function buildFishListMessage(array $fishes, bool $isEditor = false): array
    {
        $count = count($fishes);

        if ($count === 0) {
            return [
                new TextMessage([
                    'type' => 'text',
                    'text' => '找不到符合的魚類資料，請嘗試其他關鍵字。',
                ]),
            ];
        }

        if ($count === 1) {
            return [$this->buildFishCardWithQuickReply($fishes[0], $isEditor)];
        }

        if ($count <= 10) {
            $bubbles = [];
            foreach ($fishes as $fish) {
                $bubbles[] = $this->buildFishCard($fish, null, $isEditor)->getContents();
            }

            return [
                new FlexMessage([
                    'type' => 'flex',
                    'altText' => "找到 {$count} 筆魚類資料",
                    'contents' => [
                        'type' => 'carousel',
                        'contents' => $bubbles,
                    ],
                ]),
            ];
        }

        $nameList = array_slice(array_column($fishes, 'name'), 0, 10);
        $text = "找到 {$count} 筆符合的魚類：\n\n";
        foreach ($nameList as $index => $name) {
            $text .= ($index + 1).". {$name}\n";
        }
        $text .= "\n請輸入更精確的名稱。";

        return [
            new TextMessage([
                'type' => 'text',
                'text' => $text,
            ]),
        ];
    }

    public function buildCaptureRecordsCarousel(array $captureRecords, string $fishName): FlexMessage
    {
        $bubbles = [];

        foreach ($captureRecords as $record) {
            $bodyContents = [
                [
                    'type' => 'text',
                    'text' => $fishName,
                    'weight' => 'bold',
                    'size' => 'lg',
                    'wrap' => true,
                ],
                [
                    'type' => 'text',
                    'text' => '捕獲紀錄',
                    'size' => 'xs',
                    'color' => '#999999',
                    'margin' => 'sm',
                ],
            ];

            if (! empty($record['tribe'])) {
                $bodyContents[] = [
                    'type' => 'text',
                    'text' => '🏘️部落:'.$record['tribe'],
                    'size' => 'sm',
                    'margin' => 'sm',
                ];
            }
            if (! empty($record['location'])) {
                $bodyContents[] = [
                    'type' => 'text',
                    'text' => '📍地點:'.$record['location'],
                    'size' => 'sm',
                    'wrap' => true,
                    'margin' => 'md',
                ];
            }
            if (! empty($record['capture_method'])) {
                $bodyContents[] = [
                    'type' => 'text',
                    'text' => '🎣捕獲方式:'.$record['capture_method'],
                    'size' => 'sm',
                    'wrap' => true,
                    'margin' => 'sm',
                ];
            }
            if (! empty($record['capture_date'])) {
                $bodyContents[] = [
                    'type' => 'text',
                    'text' => '📅捕獲日期:'.$record['capture_date'],
                    'size' => 'sm',
                    'margin' => 'sm',
                ];
            }
            if (! empty($record['notes'])) {
                $bodyContents[] = [
                    'type' => 'text',
                    'text' => '📝備註:'.$record['notes'],
                    'size' => 'xs',
                    'wrap' => true,
                    'color' => '#666666',
                    'margin' => 'md',
                ];
            }

            $bubbles[] = [
                'type' => 'bubble',
                'hero' => [
                    'type' => 'image',
                    'url' => $record['image_url'],
                    'size' => 'full',
                    'aspectRatio' => '20:13',
                    'aspectMode' => 'cover',
                    'action' => [
                        'type' => 'uri',
                        'uri' => $record['image_url'],
                    ],
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => $bodyContents,
                ],
            ];
        }

        return new FlexMessage([
            'type' => 'flex',
            'altText' => "{$fishName} 的捕獲紀錄",
            'contents' => [
                'type' => 'carousel',
                'contents' => $bubbles,
            ],
        ]);
    }

    public function buildFishCardWithQuickReply(array $fish, bool $isEditor = false): FlexMessage
    {
        $card = $this->buildFishCard($fish, null, $isEditor);
        $quickReplyItems = [];

        if ($fish['name'] === '我不知道') {
            $quickReplyItems[] = [
                'type' => 'action',
                'action' => [
                    'type' => 'postback',
                    'label' => '🔄 換一隻',
                    'data' => 'action=random_unknown_fish',
                    'displayText' => '換一隻',
                ],
            ];
        }

        if (! empty($quickReplyItems)) {
            $card->setQuickReply(['items' => $quickReplyItems]);
        }

        return $card;
    }

    public function buildFishBrowseCarousel(array $fishes, bool $hasMore, string $nextPageData, string $title, ?array $contextTribes = null, bool $isEditor = false): array
    {
        if (empty($fishes)) {
            return [
                new TextMessage([
                    'type' => 'text',
                    'text' => '目前沒有符合條件的魚類資料。',
                ]),
            ];
        }

        $bubbles = [];
        foreach ($fishes as $fish) {
            $bubbles[] = $this->buildFishCard($fish, $contextTribes, $isEditor)->getContents();
        }

        $carouselMessage = new FlexMessage([
            'type' => 'flex',
            'altText' => $title.'（共 '.count($fishes).' 筆）',
            'contents' => [
                'type' => 'carousel',
                'contents' => $bubbles,
            ],
        ]);

        if ($hasMore) {
            $carouselMessage->setQuickReply([
                'items' => [[
                    'type' => 'action',
                    'action' => [
                        'type' => 'postback',
                        'label' => '下一頁 →',
                        'data' => $nextPageData,
                        'displayText' => '繼續瀏覽下一頁',
                    ],
                ]],
            ]);
        }

        return [$carouselMessage];
    }

    private function getTribeColors(): array
    {
        return [
            'iraraley' => '#2c6b8a',
            'imowrod' => '#2c7a66',
            'ivalino' => '#8a2c3b',
            'iranmeilek' => '#6b8a2c',
            'iratay' => '#8a6b2c',
            'yayo' => '#5e2c8a',
        ];
    }

    private function formatTribeLabel(string $tribe): string
    {
        return ucfirst($tribe);
    }
}
