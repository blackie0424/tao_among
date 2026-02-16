<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\LineBotService;
use App\Http\Controllers\ApiFishController;
use App\Models\Fish;
use App\Models\FishAudio;
use Illuminate\Support\Facades\Log;
use LINE\Parser\EventRequestParser;
use LINE\Parser\Exception\InvalidSignatureException;
use LINE\Webhook\Model\MessageEvent;
use LINE\Webhook\Model\TextMessageContent;
use LINE\Webhook\Model\AudioMessageContent;
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
                    } elseif ($message instanceof AudioMessageContent) {
                        $this->handleAudioMessage($event, $event->getReplyToken());
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

            // 檢查是否有音檔
            if (empty($fish->audio_filename)) {
                // 沒有音檔，詢問是否要新增
                $message = new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => "✅ 已將魚類名稱更新為：{$newName}\n\n是否要新增發音？",
                ]);
                
                $message->setQuickReply([
                    'items' => [
                        [
                            'type' => 'action',
                            'action' => [
                                'type' => 'postback',
                                'label' => '🎤 新增發音',
                                'data' => "action=start_add_audio&fish_id={$fishId}",
                                'displayText' => '新增發音',
                            ],
                        ],
                        [
                            'type' => 'action',
                            'action' => [
                                'type' => 'postback',
                                'label' => '❌ 不用了',
                                'data' => 'action=skip',
                                'displayText' => '不用了',
                            ],
                        ],
                    ],
                ]);
            } else {
                // 已有音檔
                $message = new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => "✅ 已將魚類名稱更新為：{$newName}",
                ]);
            }

            $this->lineBotService->replyMessage($replyToken, [$message]);

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
     * 處理語音訊息
     */
    protected function handleAudioMessage(MessageEvent $event, string $replyToken): void
    {
        $userId = $event->getSource()->getUserId();
        $messageId = $event->getMessage()->getId();
        
        Log::info('LINE Bot received audio message', [
            'userId' => $userId,
            'messageId' => $messageId,
        ]);
        
        // 檢查是否正在新增發音
        $fishId = \Cache::get("line_user_{$userId}_adding_audio");
        
        if (!$fishId) {
            // 沒有在新增發音狀態，提示用戶
            Log::warning('LINE Bot audio received without active state', [
                'userId' => $userId,
            ]);
            
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '請先點擊「🎤 新增發音」按鈕',
                ]),
            ]);
            return;
        }
        
        try {
            // 檢查時長（LINE 提供的是毫秒，容許 5.1 秒以內）
            $duration = $event->getMessage()->getDuration();
            
            Log::info('LINE Bot audio duration check', [
                'userId' => $userId,
                'fishId' => $fishId,
                'duration' => $duration,
            ]);
            
            if ($duration > 5100) { // 5100ms = 5.1秒，給予 100ms 容差
                // 清除使用者狀態
                \Cache::forget("line_user_{$userId}_adding_audio");
                
                Log::warning('LINE Bot audio duration exceeded', [
                    'userId' => $userId,
                    'fishId' => $fishId,
                    'duration' => $duration,
                    'max_allowed' => 5100,
                ]);
                
                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => '❌ 錄音超過 5 秒，請重新錄製',
                    ]),
                ]);
                return;
            }
            
            Log::info('LINE Bot downloading audio content', [
                'userId' => $userId,
                'fishId' => $fishId,
                'messageId' => $messageId,
            ]);
            
            // 下載語音內容
            try {
                $audioBlob = $this->lineBotService->getMessageContent($messageId);
            } catch (\Exception $e) {
                // 清除使用者狀態
                \Cache::forget("line_user_{$userId}_adding_audio");
                
                Log::error('LINE Bot failed to download audio from LINE API', [
                    'userId' => $userId,
                    'fishId' => $fishId,
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'exception_class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => '❌ 無法下載音檔，請稍後再試',
                    ]),
                ]);
                return;
            }
            
            // 記錄音檔的詳細資訊
            Log::info('LINE Bot audio details', [
                'userId' => $userId,
                'fishId' => $fishId,
                'messageId' => $messageId,
                'size' => strlen($audioBlob),
                'duration' => $duration,
                'first_bytes' => bin2hex(substr($audioBlob, 0, 16)), // 記錄前 16 bytes
            ]);
            
            // 驗證音檔
            if (!$this->validateAudioBlob($audioBlob)) {
                // 清除使用者狀態
                \Cache::forget("line_user_{$userId}_adding_audio");
                
                Log::warning('LINE Bot audio validation failed', [
                    'userId' => $userId,
                    'fishId' => $fishId,
                    'messageId' => $messageId,
                ]);
                
                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => '❌ 音檔格式不正確，請重新錄製',
                    ]),
                ]);
                return;
            }
            
            // 儲存音檔（傳遞實際 duration）
            $this->saveFishAudio($userId, $fishId, $audioBlob, $duration, $replyToken);
            
        } catch (\Exception $e) {
            // 清除使用者狀態
            \Cache::forget("line_user_{$userId}_adding_audio");
            
            Log::error('LINE Bot handle audio message failed', [
                'userId' => $userId,
                'fishId' => $fishId,
                'messageId' => $messageId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '❌ 處理音檔時發生錯誤，請稍後再試',
                ]),
            ]);
        }
    }

    /**
     * 驗證音檔格式和完整性
     *
     * @param string $audioBlob 音檔二進位資料
     * @return bool 驗證是否通過
     */
    protected function validateAudioBlob(string $audioBlob): bool
    {
        // 檢查檔案大小（至少 100 bytes）
        $size = strlen($audioBlob);
        if ($size < 100) {
            Log::error('Audio blob too small', ['size' => $size]);
            return false;
        }
        
        Log::info('Audio blob size validation passed', ['size' => $size]);
        
        // 檢查 M4A 檔案簽名（magic bytes）
        // M4A 檔案通常在前 32 bytes 內包含 "ftyp" 標記
        $header = substr($audioBlob, 0, min(32, $size));
        $hasFtypSignature = strpos($header, 'ftyp') !== false;
        
        if (!$hasFtypSignature) {
            Log::warning('Audio blob missing M4A signature', [
                'header_hex' => bin2hex($header),
                'header_length' => strlen($header),
            ]);
            // 不拒絕，因為有些 M4A 格式可能略有不同
            // 但記錄警告以便追蹤
        } else {
            Log::info('Audio blob M4A signature found', [
                'ftyp_position' => strpos($header, 'ftyp'),
            ]);
        }
        
        // 驗證通過
        Log::info('Audio blob validation passed', [
            'size' => $size,
            'has_ftyp' => $hasFtypSignature,
        ]);
        
        return true;
    }

    /**
     * 儲存魚類發音檔案（使用 LINE 專用的上傳服務）
     */
    protected function saveFishAudio(string $userId, int $fishId, string $audioBlob, int $duration, string $replyToken): void
    {
        try {
            // 使用 LINE 專用的上傳服務
            $lineUploadService = app(\App\Services\LineUploadService::class);
            
            Log::info('LINE Bot saving audio', [
                'userId' => $userId,
                'fishId' => $fishId,
                'blobSize' => strlen($audioBlob),
                'duration' => $duration,
            ]);
            
            // 上傳音檔到 S3（LINE 專用流程）
            try {
                $filename = $lineUploadService->uploadLineAudio($audioBlob);
            } catch (\Exception $e) {
                // 清除使用者狀態
                \Cache::forget("line_user_{$userId}_adding_audio");
                
                Log::error('LINE Bot failed to upload audio to S3', [
                    'userId' => $userId,
                    'fishId' => $fishId,
                    'blobSize' => strlen($audioBlob),
                    'duration' => $duration,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'exception_class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                throw new \Exception('Failed to upload audio to S3: ' . $e->getMessage(), 0, $e);
            }
            
            // 更新 Fish 的 audio_filename
            try {
                $fish = Fish::find($fishId);
                
                if (!$fish) {
                    // 清除使用者狀態
                    \Cache::forget("line_user_{$userId}_adding_audio");
                    
                    Log::error('LINE Bot fish not found when saving audio', [
                        'userId' => $userId,
                        'fishId' => $fishId,
                        'filename' => $filename,
                    ]);
                    
                    // 嘗試刪除已上傳的音檔（避免孤兒檔案）
                    try {
                        $audioFolder = app(\App\Contracts\StorageServiceInterface::class)->getAudioFolder();
                        \Storage::disk('s3')->delete($audioFolder . '/' . $filename);
                        Log::info('LINE Bot deleted orphaned audio file', [
                            'filename' => $filename,
                        ]);
                    } catch (\Exception $deleteException) {
                        Log::error('LINE Bot failed to delete orphaned audio file', [
                            'filename' => $filename,
                            'error' => $deleteException->getMessage(),
                        ]);
                    }
                    
                    throw new \Exception('Fish not found with ID: ' . $fishId);
                }
                
                // 更新 fish 表的主要音檔檔名
                $fish->update([
                    'audio_filename' => $filename,
                ]);
                
                // 在 fish_audios 表中創建詳細記錄
                FishAudio::create([
                    'fish_id' => $fishId,
                    'name' => $fish->name, // 使用魚類名稱作為音檔名稱
                    'locate' => $filename,
                    'duration' => $duration, // 儲存實際長度（毫秒）
                ]);
                
            } catch (\Exception $e) {
                // 清除使用者狀態
                \Cache::forget("line_user_{$userId}_adding_audio");
                
                Log::error('LINE Bot failed to update database', [
                    'userId' => $userId,
                    'fishId' => $fishId,
                    'filename' => $filename,
                    'duration' => $duration,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'exception_class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // 嘗試刪除已上傳的音檔（避免孤兒檔案）
                try {
                    $audioFolder = app(\App\Contracts\StorageServiceInterface::class)->getAudioFolder();
                    \Storage::disk('s3')->delete($audioFolder . '/' . $filename);
                    Log::info('LINE Bot deleted orphaned audio file after database failure', [
                        'filename' => $filename,
                    ]);
                } catch (\Exception $deleteException) {
                    Log::error('LINE Bot failed to delete orphaned audio file after database failure', [
                        'filename' => $filename,
                        'error' => $deleteException->getMessage(),
                    ]);
                }
                
                throw new \Exception('Failed to update database: ' . $e->getMessage(), 0, $e);
            }
            
            // 清除狀態
            \Cache::forget("line_user_{$userId}_adding_audio");
            
            Log::info('LINE Bot audio saved successfully', [
                'userId' => $userId,
                'fishId' => $fishId,
                'filename' => $filename,
                'duration' => $duration,
            ]);
            
            // 回覆成功訊息
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => "✅ 發音檔已成功新增！\n💡 不滿意可再次錄製覆蓋",
                ]),
            ]);
            
        } catch (\Exception $e) {
            // 確保使用者狀態被清除
            \Cache::forget("line_user_{$userId}_adding_audio");
            
            Log::error('LINE Bot save fish audio failed', [
                'userId' => $userId,
                'fishId' => $fishId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '❌ 儲存音檔時發生錯誤，請稍後再試',
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

            // 處理開始新增發音
            if ($params['action'] === 'start_add_audio') {
                $fishId = $params['fish_id'];
                
                // 儲存狀態到 Cache（5 分鐘過期）
                \Cache::put("line_user_{$userId}_adding_audio", $fishId, now()->addMinutes(5));

                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => "請錄製魚類發音（限 5 秒）\n💡 不滿意可再次錄製覆蓋",
                    ]),
                ]);
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
