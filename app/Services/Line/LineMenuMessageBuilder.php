<?php

namespace App\Services\Line;

use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\TextMessage;

class LineMenuMessageBuilder
{
    public function buildBrowseTribesMenu(): array
    {
        $tribes = config('fish_options.tribes', []);
        $tribeColors = $this->getTribeColors();

        $bodyContents = [
            [
                'type' => 'text',
                'text' => '請選擇部落',
                'size' => 'xl',
                'weight' => 'bold',
                'color' => '#1a1a2e',
            ],
            [
                'type' => 'text',
                'text' => '點選下列部落，觀看該部落的魚類資訊',
                'size' => 'sm',
                'color' => '#666666',
                'wrap' => true,
                'margin' => 'md',
            ],
        ];

        foreach ($tribes as $tribe) {
            $label = $this->formatTribeLabel($tribe);
            $bodyContents[] = [
                'type' => 'button',
                'style' => 'primary',
                'height' => 'sm',
                'margin' => 'md',
                'color' => $tribeColors[$tribe] ?? '#444444',
                'action' => [
                    'type' => 'postback',
                    'label' => $label,
                    'data' => "action=browse_tribe_data&tribe={$tribe}",
                    'displayText' => '瀏覽 ' . $label . ' 部落資料',
                ],
            ];
        }

        return [
            new FlexMessage([
                'type' => 'flex',
                'altText' => '📖 請選擇部落',
                'contents' => [
                    'type' => 'bubble',
                    'size' => 'giga',
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'spacing' => 'sm',
                        'contents' => $bodyContents,
                    ],
                ],
            ]),
        ];
    }

    public function buildHelpMessage(): TextMessage
    {
        return new TextMessage([
            'type' => 'text',
            'text' => "歡迎使用魚類資料查詢機器人！\n\n使用方式：\n直接輸入魚類名稱即可查詢相關資料。\n\n範例：\n• 黑鯛\n• 石斑\n• 紅目",
        ]);
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
