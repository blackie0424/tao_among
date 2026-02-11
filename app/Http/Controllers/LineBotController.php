<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\LineBotService;
use App\Http\Controllers\ApiFishController;
use Illuminate\Support\Facades\Log;
use LINE\Parser\EventRequestParser;
use LINE\Parser\Exception\InvalidSignatureException;
use LINE\Webhook\Model\MessageEvent;
use LINE\Webhook\Model\TextMessageContent;

class LineBotController extends Controller
{
    protected $lineBotService;
    protected $apiFishController;

    public function __construct(LineBotService $lineBotService, ApiFishController $apiFishController)
    {
        $this->lineBotService = $lineBotService;
        $this->apiFishController = $apiFishController;
    }

    /**
     * LINE Webhook 端點
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            // 取得請求內容
            $body = $request->getContent();
            $signature = $request->header('X-Line-Signature');

            if (!$signature) {
                Log::warning('LINE Webhook: Missing signature');
                return response()->json(['error' => 'Missing signature'], 400);
            }

            // 驗證簽章
            if (!$this->lineBotService->validateSignature($body, $signature)) {
                Log::warning('LINE Webhook: Invalid signature');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            // 解析事件（使用靜態方法，參數順序：body, channelSecret, signature）
            $channelSecret = config('line.channel_secret');
            $events = EventRequestParser::parseEventRequest($body, $channelSecret, $signature);

            // 處理每個事件
            foreach ($events->getEvents() as $event) {
                if ($event instanceof MessageEvent) {
                    $message = $event->getMessage();
                    
                    if ($message instanceof TextMessageContent) {
                        $this->handleTextMessage($event, $event->getReplyToken());
                    }
                }
            }

            return response()->json(['status' => 'ok']);

        } catch (InvalidSignatureException $e) {
            Log::error('LINE Webhook: Invalid signature exception', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);

        } catch (\Exception $e) {
            Log::error('LINE Webhook: Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * 處理文字訊息
     */
    protected function handleTextMessage(MessageEvent $event, string $replyToken): void
    {
        $message = $event->getMessage();
        $text = trim($message->getText());

        // 空白訊息，回傳使用說明
        if (empty($text)) {
            $this->lineBotService->replyMessage($replyToken, [
                $this->lineBotService->buildHelpMessage(),
            ]);
            return;
        }

        // 搜尋魚類
        $this->searchFish($text, $replyToken);
    }

    /**
     * 搜尋魚類並回應
     */
    protected function searchFish(string $keyword, string $replyToken): void
    {
        try {
            // 建立搜尋請求
            $searchRequest = Request::create('/api/fishs/search', 'GET', [
                'q' => $keyword,
            ]);

            // 呼叫現有的搜尋 API
            $response = $this->apiFishController->search($searchRequest);
            $data = $response->getData(true);

            // 取得搜尋結果
            $fishes = $data['data'] ?? [];

            // 建立回應訊息
            $messages = $this->lineBotService->buildFishListMessage($fishes);

            // 回覆訊息
            $this->lineBotService->replyMessage($replyToken, $messages);

        } catch (\Exception $e) {
            Log::error('LINE Bot search fish failed', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);

            // 回覆錯誤訊息
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '查詢時發生錯誤，請稍後再試。',
                ]),
            ]);
        }
    }
}
