<?php

namespace App\Services;

use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\FlexBubble;
use LINE\Clients\MessagingApi\Model\FlexBox;
use LINE\Clients\MessagingApi\Model\FlexText;
use LINE\Clients\MessagingApi\Model\FlexImage;
use LINE\Parser\SignatureValidator;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class LineBotService
{
    protected $client;
    protected $channelSecret;
    protected $channelAccessToken;

    public function __construct()
    {
        $this->channelSecret = config('line.channel_secret');
        $this->channelAccessToken = config('line.channel_access_token');

        $config = new Configuration();
        $config->setAccessToken($this->channelAccessToken);
        
        $httpClient = new Client();
        $this->client = new MessagingApiApi($httpClient, $config);
    }

    /**
     * 驗證 LINE Webhook 簽章
     */
    public function validateSignature(string $body, string $signature): bool
    {
        return SignatureValidator::validateSignature($body, $this->channelSecret, $signature);
    }

    /**
     * 回覆訊息
     */
    public function replyMessage(string $replyToken, array $messages): void
    {
        try {
            $request = new ReplyMessageRequest([
                'replyToken' => $replyToken,
                'messages' => $messages,
            ]);

            $this->client->replyMessage($request);
        } catch (\Exception $e) {
            Log::error('LINE Bot reply message failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 建立單筆魚類卡片訊息 (Flex Message)
     */
    public function buildFishCard(array $fish): FlexMessage
    {
        // 準備部落分類標籤（永遠顯示 iraraley 和 imorod 兩個部落）
        $tribeLabels = [
            'iraraley' => 'Iraraley',
            'imorod' => 'Imorod',
        ];
        
        // 將現有的部落分類資料建立 mapping
        $tribalData = [];
        if (!empty($fish['tribal_classifications'])) {
            foreach ($fish['tribal_classifications'] as $tc) {
                $tribalData[$tc['tribe']] = $tc['food_category'] ?? '';
            }
        }
        
        // 建立顯示文字（確保兩個部落都會顯示）
        $tribeTexts = [];
        foreach ($tribeLabels as $tribeKey => $tribeName) {
            // 如果有資料就顯示，沒有就顯示「尚未紀錄」
            $foodCategory = $tribalData[$tribeKey] ?? '尚未紀錄';
            
            // 顯示格式：部落名稱 - 食用分類
            $displayText = $tribeName . ' - ' . $foodCategory;
            
            $tribeTexts[] = [
                'type' => 'text',
                'text' => $displayText,
                'size' => 'sm',
                'color' => '#666666',
                'wrap' => true,
                'margin' => 'sm',
            ];
        }

        // 建立卡片內容（使用陣列格式）
        $bodyContents = [
            [
                'type' => 'text',
                'text' => $fish['name'],
                'weight' => 'bold',
                'size' => 'xl',
                'wrap' => true,
            ],
        ];

        // 加入部落分類（永遠都會有兩行）
        foreach ($tribeTexts as $tribeText) {
            $bodyContents[] = $tribeText;
        }

        // 建立 Flex Bubble 的資料結構
        $bubbleData = [
            'type' => 'bubble',
            'hero' => [
                'type' => 'image',
                'url' => $fish['display_image_url'] ?? $fish['image_url'],
                'size' => 'full',
                'aspectRatio' => '20:13',
                'aspectMode' => 'cover',
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => $bodyContents,
            ],
        ];

        // 如果有捕獲紀錄，加入「查看捕獲紀錄」按鈕
        if (!empty($fish['capture_records_count']) && $fish['capture_records_count'] > 0) {
            $bubbleData['footer'] = [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'sm',
                'contents' => [
                    [
                        'type' => 'button',
                        'style' => 'link',
                        'height' => 'sm',
                        'action' => [
                            'type' => 'postback',
                            'label' => "📸 查看捕獲紀錄({$fish['capture_records_count']})",
                            'data' => "action=view_captures&fish_id={$fish['id']}&fish_name={$fish['name']}",
                            'displayText' => "查看 {$fish['name']} 的捕獲紀錄",
                        ],
                    ],
                ],
            ];
        }

        $bubble = FlexBubble::fromAssocArray($bubbleData);

        return new FlexMessage([
            'type' => 'flex',
            'altText' => $fish['name'],
            'contents' => $bubble,
        ]);
    }

    /**
     * 建立魚類列表訊息（多筆資料時使用）
     */
    public function buildFishListMessage(array $fishes): array
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
            $messages = [$this->buildFishCard($fishes[0])];
            
            // 如果有音檔，加入音檔訊息
            if (!empty($fishes[0]['audio_url'])) {
                $messages[] = new \LINE\Clients\MessagingApi\Model\AudioMessage([
                    'type' => 'audio',
                    'originalContentUrl' => $fishes[0]['audio_url'],
                    'duration' => 5000, // 預設 5 秒，實際長度需要從資料庫取得
                ]);
            }
            
            return $messages;
        }

        if ($count <= 10) {
            // 使用輪播卡片 (Carousel)
            $bubbles = [];
            foreach ($fishes as $fish) {
                $bubbles[] = $this->buildFishCard($fish)->getContents();
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

        // 超過 10 筆，僅列出前 10 筆名稱
        $nameList = array_slice(array_column($fishes, 'name'), 0, 10);
        $text = "找到 {$count} 筆符合的魚類：\n\n";
        foreach ($nameList as $index => $name) {
            $text .= ($index + 1) . ". {$name}\n";
        }
        $text .= "\n請輸入更精確的名稱。";

        return [
            new TextMessage([
                'type' => 'text',
                'text' => $text,
            ]),
        ];
    }

    /**
     * 建立捕獲紀錄輪播訊息
     */
    public function buildCaptureRecordsCarousel(array $captureRecords, string $fishName): FlexMessage
    {
        $bubbles = [];
        
        foreach ($captureRecords as $record) {
            // 準備顯示內容
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

            // 加入各項資訊
            if (!empty($record['tribe'])) {
                $bodyContents[] = [
                    'type' => 'text',
                    'text' => '🏘️部落:' . $record['tribe'],
                    'size' => 'sm',
                    'margin' => 'sm',
                ];
            }

            if (!empty($record['location'])) {
                $bodyContents[] = [
                    'type' => 'text',
                    'text' => '📍地點:' . $record['location'],
                    'size' => 'sm',
                    'wrap' => true,
                    'margin' => 'md',
                ];
            }

            if (!empty($record['capture_method'])) {
                $bodyContents[] = [
                    'type' => 'text',
                    'text' => '🎣捕獲方式:' . $record['capture_method'],
                    'size' => 'sm',
                    'wrap' => true,
                    'margin' => 'sm',
                ];
            }

            if (!empty($record['capture_date'])) {
                $bodyContents[] = [
                    'type' => 'text',
                    'text' => '📅捕獲日期:' . $record['capture_date'],
                    'size' => 'sm',
                    'margin' => 'sm',
                ];
            }

            if (!empty($record['notes'])) {
                $bodyContents[] = [
                    'type' => 'text',
                    'text' => '📝備註:' . $record['notes'],
                    'size' => 'xs',
                    'wrap' => true,
                    'color' => '#666666',
                    'margin' => 'md',
                ];
            }

            // 建立單張捕獲紀錄卡片
            $bubbles[] = [
                'type' => 'bubble',
                'hero' => [
                    'type' => 'image',
                    'url' => $record['image_url'],
                    'size' => 'full',
                    'aspectRatio' => '20:13',
                    'aspectMode' => 'cover',
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

    /**
     * 建立使用說明訊息
     */
    public function buildHelpMessage(): TextMessage
    {
        return new TextMessage([
            'type' => 'text',
            'text' => "歡迎使用魚類資料查詢機器人！\n\n使用方式：\n直接輸入魚類名稱即可查詢相關資料。\n\n範例：\n• 黑鯛\n• 石斑\n• 紅目",
        ]);
    }
}
