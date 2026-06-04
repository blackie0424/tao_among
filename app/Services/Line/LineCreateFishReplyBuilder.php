<?php

namespace App\Services\Line;

use App\Services\LineBatchCaptureMessageBuilder;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\TextMessage;

class LineCreateFishReplyBuilder
{
    public function __construct(
        private readonly LineBatchCaptureMessageBuilder $messageBuilder,
    ) {
    }

    public function buildTribeSelectionMessage(): FlexMessage
    {
        $actions = array_map(fn ($tribe) => [
            'label'        => ucfirst($tribe),
            'data'         => "action=select_create_fish_tribe&tribe={$tribe}",
            'display_text' => $tribe,
            'style'        => 'secondary',
        ], config('fish_options.tribes', []));

        $actions[] = [
            'label'        => '❌ 取消',
            'data'         => 'action=cancel_create_fish',
            'display_text' => '取消新增',
            'style'        => 'secondary',
            'color'        => '#aaaaaa',
        ];

        return $this->messageBuilder->buildOptionSelectorCard(
            '請選擇捕獲部落',
            '請選擇本次捕獲所屬部落。',
            $actions
        );
    }

    public function buildCaptureMethodSelectionMessage(): FlexMessage
    {
        $actions = [];
        foreach (config('fish_options.capture_methods', []) as $value => $label) {
            $actions[] = [
                'label'        => $label,
                'data'         => "action=select_create_fish_method&capture_method={$value}",
                'display_text' => $label,
                'style'        => 'secondary',
            ];
        }

        $actions[] = [
            'label'        => '❌ 取消',
            'data'         => 'action=cancel_create_fish',
            'display_text' => '取消新增',
            'style'        => 'secondary',
            'color'        => '#aaaaaa',
        ];

        return $this->messageBuilder->buildOptionSelectorCard(
            '請選擇捕獲方式',
            '請選擇本次捕獲使用的方式。',
            $actions
        );
    }

    public function buildTextMessage(string $text, array $quickReplyItems = []): TextMessage
    {
        $payload = ['type' => 'text', 'text' => $text];

        if (! empty($quickReplyItems)) {
            $payload['quickReply'] = ['items' => $quickReplyItems];
        }

        return new TextMessage($payload);
    }
}
