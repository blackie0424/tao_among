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
        // 準備部落分類標籤
        $tribeTexts = [];
        if (!empty($fish['tribal_classifications'])) {
            foreach ($fish['tribal_classifications'] as $tc) {
                $tribeText = $tc['tribe'] ?? '';
                if (!empty($tc['food_category'])) {
                    $tribeText .= ' - ' . $tc['food_category'];
                }
                if ($tribeText) {
                    $tribeTexts[] = new FlexText([
                        'type' => 'text',
                        'text' => $tribeText,
                        'size' => 'sm',
                        'color' => '#999999',
                        'wrap' => true,
                        'margin' => 'sm',
                    ]);
                }
            }
        }

        // 建立卡片內容
        $bodyContents = [
            new FlexText([
                'type' => 'text',
                'text' => $fish['name'],
                'weight' => 'bold',
                'size' => 'xl',
                'wrap' => true,
            ]),
        ];

        // 加入部落分類
        if (!empty($tribeTexts)) {
            foreach ($tribeTexts as $tribeText) {
                $bodyContents[] = $tribeText;
            }
        }

        $bubble = new FlexBubble([
            'type' => 'bubble',
            'hero' => new FlexImage([
                'type' => 'image',
                'url' => $fish['display_image_url'] ?? $fish['image_url'],
                'size' => 'full',
                'aspectRatio' => '20:13',
                'aspectMode' => 'cover',
            ]),
            'body' => new FlexBox([
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => $bodyContents,
            ]),
        ]);

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
            return [$this->buildFishCard($fishes[0])];
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
