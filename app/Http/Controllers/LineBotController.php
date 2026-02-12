<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\LineBotService;
use App\Http\Controllers\ApiFishController;
use App\Models\Fish;
use Illuminate\Support\Facades\Log;
use LINE\Parser\EventRequestParser;
use LINE\Parser\Exception\InvalidSignatureException;
use LINE\Webhook\Model\MessageEvent;
use LINE\Webhook\Model\TextMessageContent;
use LINE\Webhook\Model\PostbackEvent;

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
                } elseif ($event instanceof PostbackEvent) {
                    $this->handlePostback($event, $event->getReplyToken());
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
        $userId = $event->getSource()->getUserId();

        // 空白訊息，回傳使用說明
        if (empty($text)) {
            $this->lineBotService->replyMessage($replyToken, [
                $this->lineBotService->buildHelpMessage(),
            ]);
            return;
        }

        // 檢查是否正在修改名稱
        $renamingFishId = \Cache::get("line_user_{$userId}_renaming_fish");
        if ($renamingFishId) {
            $this->handleRenameFish($userId, $renamingFishId, $text, $replyToken);
            return;
        }

        // 檢查是否為「隨機命名」關鍵字
        if (in_array(strtolower($text), ['隨機命名', 'random', '隨機'])) {
            $this->handleRandomUnknownFish($replyToken);
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

    /**
     * 處理「隨機命名」請求
     */
    protected function handleRandomUnknownFish(string $replyToken): void
    {
        try {
            // 查詢隨機的「我不知道」魚類
            $request = Request::create('/prefix/api/fishs/random-unknown', 'GET');
            $response = $this->apiFishController->randomUnknownFish();
            $data = $response->getData(true);

            if (empty($data['data'])) {
                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => '目前沒有待命名的魚類。',
                    ]),
                ]);
                return;
            }

            $fish = $data['data'];

            // 建立帶 Quick Reply 的魚類卡片
            $card = $this->lineBotService->buildFishCardWithQuickReply($fish);

            // 回覆訊息
            $this->lineBotService->replyMessage($replyToken, [$card]);

        } catch (\Exception $e) {
            Log::error('LINE Bot handle random unknown fish failed', [
                'error' => $e->getMessage(),
            ]);

            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '查詢時發生錯誤，請稍後再試。',
                ]),
            ]);
        }
    }

    /**
     * 處理修改魚類名稱
     */
    protected function handleRenameFish(string $userId, int $fishId, string $newName, string $replyToken): void
    {
        try {
            // 直接更新資料庫（不透過 HTTP Request）
            $fish = Fish::find($fishId);
            
            if (!$fish) {
                throw new \Exception('Fish not found');
            }

            $fish->update(['name' => $newName]);

            // 清除狀態
            \Cache::forget("line_user_{$userId}_renaming_fish");

            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => "✅ 已將魚類名稱更新為：{$newName}",
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('LINE Bot rename fish failed', [
                'userId' => $userId,
                'fishId' => $fishId,
                'newName' => $newName,
                'error' => $e->getMessage(),
            ]);

            // 清除狀態
            \Cache::forget("line_user_{$userId}_renaming_fish");

            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '❌ 更新名稱時發生錯誤，請稍後再試。',
                ]),
            ]);
        }
    }

    /**
     * 處理 Postback 事件
     */
    protected function handlePostback($event, string $replyToken): void
    {
        try {
            // 解析 postback data
            $data = $event->getPostback()->getData();
            parse_str($data, $params);
            $userId = $event->getSource()->getUserId();

            Log::info('LINE Bot received postback', ['data' => $data, 'params' => $params]);

            // 處理隨機魚類請求
            if ($params['action'] === 'random_unknown_fish') {
                $this->handleRandomUnknownFish($replyToken);
                return;
            }

            // 處理開始修改名稱
            if ($params['action'] === 'start_rename') {
                $fishId = $params['fish_id'];
                
                // 儲存狀態到 Cache（5 分鐘過期）
                \Cache::put("line_user_{$userId}_renaming_fish", $fishId, now()->addMinutes(5));

                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => '請輸入新的魚類名稱：',
                    ]),
                ]);
                return;
            }

            // 處理查看捕獲紀錄請求
            if ($params['action'] === 'view_captures') {
                $fishId = $params['fish_id'];
                $fishName = $params['fish_name'] ?? '';

                // 重新查詢該魚類的完整資料（利用現有的 search API）
                $request = Request::create('/prefix/api/fishs/search', 'GET', ['q' => $fishName]);
                $response = $this->apiFishController->search($request);
                $data = $response->getData(true);

                if (!empty($data['data'])) {
                    // 找到對應的魚類資料
                    $fish = collect($data['data'])->firstWhere('id', (int)$fishId);

                    if ($fish && !empty($fish['capture_records'])) {
                        // 建立捕獲紀錄輪播訊息
                        $message = $this->lineBotService->buildCaptureRecordsCarousel(
                            $fish['capture_records'],
                            $fish['name']
                        );

                        // 回覆訊息
                        $this->lineBotService->replyMessage($replyToken, [$message]);
                    } else {
                        // 沒有捕獲紀錄
                        $this->lineBotService->replyMessage($replyToken, [
                            new \LINE\Clients\MessagingApi\Model\TextMessage([
                                'type' => 'text',
                                'text' => '目前沒有捕獲紀錄。',
                            ]),
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('LINE Bot handle postback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 回覆錯誤訊息
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '處理請求時發生錯誤，請稍後再試。',
                ]),
            ]);
        }
    }
}
