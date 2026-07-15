<?php

namespace App\Services;

use App\Models\Fish;
use LINE\Clients\MessagingApi\Model\FlexMessage;

class LineBatchCaptureMessageBuilder
{
    /**
     * @param string[] $images
     * @param array{tribe?:?string,location?:?string,capture_method?:?string,capture_date?:?string,notes?:?string} $form
     * @param array<int, array{label:string,data:string,display_text?:?string,style?:?string,color?:?string}> $actions
     */
    public function buildSummaryCard(Fish $fish, array $images, array $form, array $actions, ?string $notice = null): FlexMessage
    {
        $captureMethodLabel = filled($form['capture_method'] ?? null)
            ? config('fish_options.capture_methods.' . $form['capture_method'], $form['capture_method'])
            : '未選擇';

        $rows = [
            '魚類：' . $fish->name,
            '照片數量：' . count($images) . ' 張',
            '部落：' . ($form['tribe'] ?? '未選擇'),
            '地點：' . ($form['location'] ?? '未填寫'),
            '捕獲方式：' . $captureMethodLabel,
            '日期：' . ($form['capture_date'] ?? '未選擇'),
            '備註：' . ($form['notes'] ?? '未填寫'),
        ];

        return new FlexMessage([
            'type' => 'flex',
            'altText' => '批次捕獲紀錄摘要',
            'contents' => [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => array_merge([
                        [
                            'type' => 'text',
                            'text' => '請確認批次捕獲紀錄',
                            'weight' => 'bold',
                            'size' => 'lg',
                        ],
                        ...($notice ? [[
                            'type' => 'text',
                            'text' => $notice,
                            'size' => 'sm',
                            'margin' => 'md',
                            'wrap' => true,
                            'color' => '#666666',
                        ]] : []),
                    ], array_map(fn ($text) => [
                        'type' => 'text',
                        'text' => $text,
                        'size' => 'sm',
                        'margin' => 'md',
                        'wrap' => true,
                    ], $rows)),
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => array_map(function ($action, $index) {
                        $button = [
                            'type' => 'button',
                            'style' => $action['style'] ?? ($index === 0 ? 'primary' : 'secondary'),
                            'height' => 'sm',
                            'action' => [
                                'type' => 'postback',
                                'label' => $action['label'],
                                'data' => $action['data'],
                                'displayText' => $action['display_text'] ?? $action['label'],
                            ],
                        ];

                        $color = $action['color'] ?? ($index === 0 ? '#00B900' : null);
                        if ($color) {
                            $button['color'] = $color;
                        }

                        return $button;
                    }, $actions, array_keys($actions)),
                ],
            ],
        ]);
    }

    /**
     * @param array<int, array{tribe:string,location:string,capture_method:string,capture_date:string}> $sessions
     */
    public function buildSessionPickerMessage(array $sessions): FlexMessage
    {
        $bubbles = [];

        foreach ($sessions as $session) {
            $methodLabel = config('fish_options.capture_methods.' . $session['capture_method'], $session['capture_method']);
            $postbackData = http_build_query([
                'action'         => 'select_capture_session',
                'tribe'          => $session['tribe'],
                'location'       => $session['location'],
                'capture_method' => $session['capture_method'],
                'capture_date'   => $session['capture_date'],
            ]);

            $bubbles[] = [
                'type' => 'bubble',
                'body' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'contents' => [
                        ['type' => 'text', 'text' => $session['capture_date'], 'weight' => 'bold', 'size' => 'lg'],
                        ['type' => 'text', 'text' => $session['tribe'], 'size' => 'sm', 'margin' => 'md', 'wrap' => true],
                        ['type' => 'text', 'text' => $session['location'], 'size' => 'sm', 'margin' => 'sm', 'wrap' => true],
                        ['type' => 'text', 'text' => $methodLabel, 'size' => 'sm', 'margin' => 'sm', 'wrap' => true, 'color' => '#666666'],
                    ],
                ],
                'footer' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'contents' => [[
                        'type'   => 'button',
                        'style'  => 'primary',
                        'color'  => '#00B900',
                        'height' => 'sm',
                        'action' => [
                            'type'        => 'postback',
                            'label'       => '使用此筆',
                            'data'        => $postbackData,
                            'displayText' => '使用此筆',
                        ],
                    ]],
                ],
            ];
        }

        $bubbles[] = [
            'type' => 'bubble',
            'body' => [
                'type'     => 'box',
                'layout'   => 'vertical',
                'contents' => [
                    ['type' => 'text', 'text' => '手動填寫', 'weight' => 'bold', 'size' => 'lg'],
                    ['type' => 'text', 'text' => '自行輸入捕獲資訊', 'size' => 'sm', 'margin' => 'md', 'color' => '#666666'],
                ],
            ],
            'footer' => [
                'type'     => 'box',
                'layout'   => 'vertical',
                'contents' => [[
                    'type'   => 'button',
                    'style'  => 'secondary',
                    'height' => 'sm',
                    'action' => [
                        'type'        => 'postback',
                        'label'       => '手動填寫',
                        'data'        => 'action=skip_session_picker',
                        'displayText' => '手動填寫',
                    ],
                ]],
            ],
        ];

        return new FlexMessage([
            'type'     => 'flex',
            'altText'  => '請選擇最近捕獲資訊',
            'contents' => [
                'type'     => 'carousel',
                'contents' => $bubbles,
            ],
        ]);
    }

    /**
     * @param array<int, array{label:string,data:string,display_text?:?string,style?:?string,color?:?string}> $actions
     */
    public function buildOptionSelectorCard(string $title, string $description, array $actions): FlexMessage
    {
        $bodyContents = [
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
                'margin' => 'md',
                'color' => '#666666',
                'wrap' => true,
            ],
        ];

        foreach ($actions as $action) {
            $button = [
                'type' => 'button',
                'style' => $action['style'] ?? 'secondary',
                'height' => 'sm',
                'margin' => 'md',
                'action' => [
                    'type' => 'postback',
                    'label' => $action['label'],
                    'data' => $action['data'],
                    'displayText' => $action['display_text'] ?? $action['label'],
                ],
            ];

            $color = $action['color'] ?? null;
            if ($color) {
                $button['color'] = $color;
            }

            $bodyContents[] = $button;
        }

        return new FlexMessage([
            'type' => 'flex',
            'altText' => $title,
            'contents' => [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => $bodyContents,
                ],
            ],
        ]);
    }
}
