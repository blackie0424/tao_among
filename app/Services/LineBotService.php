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
     *
     * @param array       $fish          魚類資料
     * @param array|null  $contextTribes 要顯示的指定部落（null = 顯示兩個預設部落）
     *                                    例： ['iraraley']、['imowrod']、['iraraley','imowrod']
     */
    public function buildFishCard(array $fish, ?array $contextTribes = null): FlexMessage
    {
        // 決定要渲染哪些「指定部落」區塊
        // - null / 空陣列 → 顯示 iraraley + imowrod 兩個
        // - ['iraraley'] → 只顯示 Iraraley
        // - ['imowrod']  → 只顯示 Imowrod
        $primaryTribes = (!empty($contextTribes))
            ? $contextTribes
            : ['iraraley', 'imowrod'];

        // 建立部落資料 mapping（以 tribe 為 key）
        $tribalData = [];
        $otherTribeData = [];

        if (!empty($fish['tribal_classifications'])) {
            foreach ($fish['tribal_classifications'] as $tc) {
                $tribe = $tc['tribe'] ?? '';
                $entry = [
                    'food_category'     => $tc['food_category'] ?? null,
                    'processing_method' => $tc['processing_method'] ?? null,
                    'notes'             => $tc['notes'] ?? null,
                ];

                if (in_array($tribe, $primaryTribes)) {
                    $tribalData[$tribe] = $entry;
                } else {
                    // 其他部落——只有在「有任何非空值」時才記錄
                    if (!empty($entry['food_category']) || !empty($entry['processing_method']) || !empty($entry['notes'])) {
                        $otherTribeData[$tribe] = $entry;
                    }
                }
            }
        }

        // ==========================================
        // Body：魚名 + 發音按鈕（並排）
        // ==========================================
        $hasAudio = !empty($fish['audio_url']);

        $audioAction = $hasAudio
            ? [
                'type'        => 'postback',
                'label'       => '🔊 播放發音',
                'data'        => "action=play_audio&fish_id={$fish['id']}&fish_name={$fish['name']}",
                'displayText' => "播放 {$fish['name']} 的發音",
            ]
            : [
                'type'  => 'postback',
                'label' => '🔇 尚無發音',
                'data'  => "action=no_audio&fish_id={$fish['id']}&fish_name={$fish['name']}",
            ];

        $bodyContents = [
            // 魚名（完整一行）
            [
                'type'   => 'text',
                'text'   => $fish['name'],
                'weight' => 'bold',
                'size'   => 'xl',
                'wrap'   => true,
                'color'  => '#1a1a2e',
            ],
            // 發音按鈕（魚名下方）
            [
                'type'   => 'button',
                'style'  => $hasAudio ? 'primary' : 'secondary',
                'height' => 'sm',
                'margin' => 'sm',
                'color'  => $hasAudio ? '#2c6b8a' : '#aaaaaa',
                'action' => $audioAction,
            ],
            [
                'type'   => 'separator',
                'margin' => 'md',
            ],
        ];

        // ==========================================
        // Body：指定兩部落區塊（Iraraley / Imowrod）
        // ==========================================
        $primaryTribeConfig = [
            'iraraley' => ['label' => '🏘️ Iraraley', 'color' => '#2c6b8a'],
            'imowrod'  => ['label' => '🏡 Imowrod',  'color' => '#2c7a66'],
        ];

        foreach ($primaryTribeConfig as $tribeKey => $config) {
            // 只渲染 $primaryTribes 指定的部落區塊
            if (!in_array($tribeKey, $primaryTribes)) {
                continue;
            }

            $data            = $tribalData[$tribeKey] ?? [];
            $foodCategory    = !empty($data['food_category']) ? $data['food_category'] : '尚未紀錄';
            $processingMethod = !empty($data['processing_method']) ? $data['processing_method'] : '尚未紀錄';

            $bodyContents[] = [
                'type'    => 'box',
                'layout'  => 'vertical',
                'margin'  => 'md',
                'contents' => [
                    // 部落名稱標題
                    [
                        'type'   => 'text',
                        'text'   => $config['label'],
                        'size'   => 'sm',
                        'weight' => 'bold',
                        'color'  => $config['color'],
                    ],
                    // 食用分類行
                    [
                        'type'    => 'box',
                        'layout'  => 'horizontal',
                        'margin'  => 'xs',
                        'contents' => [
                            [
                                'type'  => 'text',
                                'text'  => '食用分類',
                                'size'  => 'xs',
                                'color' => '#888888',
                                'flex'  => 3,
                            ],
                            [
                                'type'  => 'text',
                                'text'  => $foodCategory,
                                'size'  => 'xs',
                                'color' => '#333333',
                                'flex'  => 5,
                                'wrap'  => true,
                            ],
                        ],
                    ],
                    // 魚鱗處理行
                    [
                        'type'    => 'box',
                        'layout'  => 'horizontal',
                        'margin'  => 'xs',
                        'contents' => [
                            [
                                'type'  => 'text',
                                'text'  => '魚鱗處理',
                                'size'  => 'xs',
                                'color' => '#888888',
                                'flex'  => 3,
                            ],
                            [
                                'type'  => 'text',
                                'text'  => $processingMethod,
                                'size'  => 'xs',
                                'color' => '#333333',
                                'flex'  => 5,
                                'wrap'  => true,
                            ],
                        ],
                    ],
                ],
            ];
        }

        // ==========================================
        // Body：其他部落田調資料（僅有資料時才顯示）
        // ==========================================
        if (!empty($otherTribeData)) {
            $bodyContents[] = [
                'type'   => 'separator',
                'margin' => 'md',
            ];
            $bodyContents[] = [
                'type'   => 'text',
                'text'   => '🔍 其他部落田調',
                'size'   => 'xs',
                'weight' => 'bold',
                'color'  => '#777777',
                'margin' => 'md',
            ];

            foreach ($otherTribeData as $tribe => $data) {
                $parts = [];
                if (!empty($data['food_category']))     $parts[] = $data['food_category'];
                if (!empty($data['processing_method'])) $parts[] = $data['processing_method'];
                if (!empty($data['notes']))             $parts[] = $data['notes'];

                $bodyContents[] = [
                    'type'  => 'text',
                    'text'  => ucfirst($tribe) . '：' . implode(' / ', $parts),
                    'size'  => 'xs',
                    'color' => '#999999',
                    'wrap'  => true,
                    'margin' => 'xs',
                ];
            }
        }

        // ==========================================
        // Footer：捕獲紀錄按鈕 + 發音按鈕
        // ==========================================
        $footerContents = [];

        // 捕獲紀錄按鈕（有紀錄才顯示）
        $captureCount = $fish['capture_records_count'] ?? 0;
        if ($captureCount > 0) {
            $footerContents[] = [
                'type'   => 'button',
                'style'  => 'secondary',
                'height' => 'sm',
                'action' => [
                    'type'        => 'postback',
                    'label'       => "📸 查看捕獲紀錄（{$captureCount} 筆）",
                    'data'        => "action=view_captures&fish_id={$fish['id']}&fish_name={$fish['name']}",
                    'displayText' => "查看 {$fish['name']} 的捕獲紀錄",
                ],
            ];
        }

        // ==========================================
        // 組裝完整 Bubble
        // ==========================================
        $bubbleData = [
            'type' => 'bubble',
            'hero' => [
                'type'        => 'image',
                'url'         => $fish['display_image_url'] ?? $fish['image_url'],
                'size'        => 'full',
                'aspectRatio' => '20:13',
                'aspectMode'  => 'cover',
                'action'      => [
                    'type' => 'uri',
                    'uri'  => $fish['display_image_url'] ?? $fish['image_url'],
                ],
            ],
            'body' => [
                'type'     => 'box',
                'layout'   => 'vertical',
                'contents' => $bodyContents,
                'spacing'  => 'none',
            ],
            'footer' => [
                'type'     => 'box',
                'layout'   => 'vertical',
                'spacing'  => 'sm',
                'contents' => $footerContents,
            ],
        ];

        $bubble = FlexBubble::fromAssocArray($bubbleData);

        return new FlexMessage([
            'type'     => 'flex',
            'altText'  => $fish['name'],
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
            // 單一結果：使用帶 Quick Reply 的卡片
            // （發音已整合在卡片內的「🔊 播放發音」按鈕，不再自動附加音檔）
            return [$this->buildFishCardWithQuickReply($fishes[0])];
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
                    'action' => [
                        'type' => 'uri',
                        'uri'  => $record['image_url'],
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

    /**
     * 建立魚類卡片（帶 Quick Reply 按鈕）
     */
    public function buildFishCardWithQuickReply(array $fish): FlexMessage
    {
        $card = $this->buildFishCard($fish);
        
        $quickReplyItems = [];
        
        // Random 模式：顯示「修改名稱」（當名稱是「我不知道」時）
            $quickReplyItems[] = [
                'type' => 'action',
                'action' => [
                    'type' => 'postback',
                    'label' => '✏️ 修改名稱',
                    'data' => "action=start_rename&fish_id={$fish['id']}",
                    'displayText' => '修改名稱',
                ],
            ];
        
        // 「提供發音」按鈕（無論有無音檔都顯示，田調工具盡量蒐集）
        $quickReplyItems[] = [
            'type' => 'action',
            'action' => [
                'type' => 'postback',
                'label' => '🎤 提供發音',
                'data' => "action=start_add_audio&fish_id={$fish['id']}",
                'displayText' => '提供發音',
            ],
        ];
        
        // Random 模式：顯示「換一隻」
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
        
        // 加入 Quick Reply（如果有按鈕的話）
        if (!empty($quickReplyItems)) {
            $card->setQuickReply(['items' => $quickReplyItems]);
        }
        
        return $card;
    }

    /**
     * 建立分頁瀏覽輪播訊息（供圖文選單使用）
     *
     * @param array       $fishes         最多 10 筆魚類資料
     * @param bool        $hasMore        是否還有下一頁
     * @param string      $nextPageData   下一頁的 postback data
     * @param string      $title          本次瀏覽標題（用於 altText）
     * @param array|null  $contextTribes  要顯示的指定部落（null = 顯示兩個預設部落）
     */
    public function buildFishBrowseCarousel(array $fishes, bool $hasMore, string $nextPageData, string $title, ?array $contextTribes = null): array
    {
        if (empty($fishes)) {
            return [
                new TextMessage([
                    'type' => 'text',
                    'text' => '目前沒有符合條件的魚類資料。',
                ]),
            ];
        }

        // 建立各張魚類卡片（使用 buildFishCard，傳入部落 context）
        $bubbles = [];
        foreach ($fishes as $fish) {
            $bubbles[] = $this->buildFishCard($fish, $contextTribes)->getContents();
        }

        $carouselMessage = new FlexMessage([
            'type' => 'flex',
            'altText' => $title . '（共 ' . count($fishes) . ' 筆）',
            'contents' => [
                'type' => 'carousel',
                'contents' => $bubbles,
            ],
        ]);

        // 如果有下一頁，加入 Quick Reply「下一頁」按鈕
        if ($hasMore) {
            $carouselMessage->setQuickReply([
                'items' => [
                    [
                        'type' => 'action',
                        'action' => [
                            'type' => 'postback',
                            'label' => '下一頁 →',
                            'data' => $nextPageData,
                            'displayText' => '繼續瀏覽下一頁',
                        ],
                    ],
                ],
            ]);
        }

        return [$carouselMessage];
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

    /**
     * 下載 LINE 訊息內容（例如語音檔）
     *
     * 注意：LINE API 回傳的可能是 SplFileObject 或字串
     * 我們需要確保正確讀取 binary 資料，避免編碼問題導致音檔損壞
     */
    public function getMessageContent(string $messageId): string
    {
        try {
            $httpClient = new \GuzzleHttp\Client();
            $config = new \LINE\Clients\MessagingApi\Configuration();
            $config->setAccessToken(config('line.channel_access_token'));
            
            $blobClient = new \LINE\Clients\MessagingApi\Api\MessagingApiBlobApi($httpClient, $config);
            
            Log::info('LINE Bot downloading message content', [
                'messageId' => $messageId,
            ]);
            
            $content = $blobClient->getMessageContent($messageId);
            
            // 處理不同的回傳類型
            if ($content instanceof \SplFileObject) {
                // 如果是 SplFileObject，讀取全部內容
                $content->rewind();
                $binaryData = '';
                while (!$content->eof()) {
                    $binaryData .= $content->fread(8192); // 每次讀取 8KB
                }
                $content = $binaryData;
            } elseif (is_resource($content)) {
                // 如果是 resource，讀取全部內容
                rewind($content);
                $content = stream_get_contents($content);
            }
            // 如果已經是字串，直接使用
            
            Log::info('LINE Bot message content downloaded successfully', [
                'messageId' => $messageId,
                'size' => strlen($content),
                'first_bytes' => bin2hex(substr($content, 0, 16)),
            ]);
            
            return $content;
            
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('LINE Bot failed to download message content - HTTP error', [
                'messageId' => $messageId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
                'response_body' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new \Exception(
                'Failed to download message content from LINE API: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
            
        } catch (\Exception $e) {
            Log::error('LINE Bot failed to download message content - unexpected error', [
                'messageId' => $messageId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new \Exception(
                'Failed to download message content: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
