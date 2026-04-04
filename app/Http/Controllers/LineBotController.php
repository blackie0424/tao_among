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
use LINE\Webhook\Model\ImageMessageContent;
use LINE\Webhook\Model\PostbackEvent;
use App\Services\UploadService;
use App\Contracts\StorageServiceInterface;

class LineBotController extends Controller
{
    protected $lineBotService;
    protected $apiFishController;
    protected $uploadService;
    protected $storageService;

    public function __construct(
        LineBotService $lineBotService,
        ApiFishController $apiFishController,
        UploadService $uploadService,
        StorageServiceInterface $storageService
    ) {
        $this->lineBotService = $lineBotService;
        $this->apiFishController = $apiFishController;
        $this->uploadService = $uploadService;
        $this->storageService = $storageService;
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
                    } elseif ($message instanceof ImageMessageContent) {
                        $this->handleImageMessage($event, $event->getReplyToken());
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

        // 檢查新增魚類流程狀態
        $createFishState = \Cache::get("line_user_{$userId}_create_fish_state");

        if ($createFishState === 'waiting_image') {
            // 正在等待圖片，文字訊息應被忽略並提示
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '📷 請傳送一張魚類圖片，或點選下方取消放棄新增。',
                    'quickReply' => [
                        'items' => [
                            [
                                'type' => 'action',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '❌ 取消',
                                    'data' => 'action=cancel_create_fish',
                                    'displayText' => '取消新增',
                                ],
                            ],
                        ],
                    ],
                ]),
            ]);
            return;
        }

        if ($createFishState === 'waiting_name_choice') {
            // 正在等待名稱選擇（Quick Reply），文字訊息應被忽略並提示
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '請點選上方按鈕選擇魚類名稱方式，或取消新增。',
                    'quickReply' => [
                        'items' => [
                            [
                                'type' => 'action',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '🔤 使用預設名稱',
                                    'data' => 'action=create_fish_with_default_name',
                                    'displayText' => '使用預設名稱',
                                ],
                            ],
                            [
                                'type' => 'action',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '✏️ 輸入自訂名稱',
                                    'data' => 'action=create_fish_need_name',
                                    'displayText' => '輸入自訂名稱',
                                ],
                            ],
                            [
                                'type' => 'action',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '❌ 取消',
                                    'data' => 'action=cancel_create_fish',
                                    'displayText' => '取消新增',
                                ],
                            ],
                        ],
                    ],
                ]),
            ]);
            return;
        }

        if ($createFishState === 'waiting_custom_name') {
            $this->createFish($userId, $replyToken, $text);
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
     * 處理圖片訊息（新增魚類流程）
     */
    protected function handleImageMessage(MessageEvent $event, string $replyToken): void
    {
        $userId = $event->getSource()->getUserId();
        $state = \Cache::get("line_user_{$userId}_create_fish_state");
        
        // 只有在 waiting_image 狀態才接受圖片
        if ($state !== 'waiting_image') {
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '目前不在新增魚類流程中。如要新增魚類，請點選圖文選單「新增魚類 ➕」。',
                ]),
            ]);
            return;
        }
        
        try {
            // 下載圖片
            $messageId = $event->getMessage()->getId();
            $imageBlob = $this->lineBotService->getMessageContent($messageId);
            
            // 上傳到 S3
            $filename = $this->uploadService->uploadImageFromBlob($imageBlob);
            
            if (!$filename) {
                throw new \Exception('Failed to upload image');
            }
            
            // 儲存圖片檔名到 Cache（5 分鐘）
            \Cache::put("line_user_{$userId}_create_fish_image", $filename, now()->addMinutes(5));
            \Cache::put("line_user_{$userId}_create_fish_state", 'waiting_name_choice', now()->addMinutes(5));
            
            Log::info('LINE Bot image uploaded for fish creation', [
                'userId' => $userId,
                'filename' => $filename,
            ]);
            
            // 回覆選項
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => "✅ 圖片已上傳\n\n請選擇魚類名稱：",
                    'quickReply' => [
                        'items' => [
                            [
                                'type' => 'action',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '🔤 使用預設名稱',
                                    'data' => 'action=create_fish_with_default_name',
                                    'displayText' => '使用預設名稱',
                                ],
                            ],
                            [
                                'type' => 'action',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '✏️ 輸入自訂名稱',
                                    'data' => 'action=create_fish_need_name',
                                    'displayText' => '輸入自訂名稱',
                                ],
                            ],
                            [
                                'type' => 'action',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '🔄 重新上傳圖片',
                                    'data' => 'action=reupload_fish_image',
                                    'displayText' => '重新上傳圖片',
                                ],
                            ],
                            [
                                'type' => 'action',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '❌ 取消',
                                    'data' => 'action=cancel_create_fish',
                                    'displayText' => '取消新增',
                                ],
                            ],
                        ],
                    ],
                ]),
            ]);
            
        } catch (\Exception $e) {
            Log::error('LINE Bot handle image message failed', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '❌ 圖片處理失敗，請稍後再試',
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
                // 保留狀態，讓使用者可以直接重錄
                Log::warning('LINE Bot audio duration exceeded', [
                    'userId' => $userId,
                    'fishId' => $fishId,
                    'duration' => $duration,
                    'max_allowed' => 5100,
                ]);
                
                $retryMessage = new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '❌ 錄音超過 5 秒，請重新錄製（限 5 秒以內）',
                ]);
                $retryMessage->setQuickReply([
                    'items' => [
                        [
                            'type'   => 'action',
                            'action' => [
                                'type'        => 'postback',
                                'label'       => '🔄 重新錄製',
                                'data'        => "action=retry_audio&fish_id={$fishId}",
                                'displayText' => '重新錄製',
                            ],
                        ],
                    ],
                ]);
                $this->lineBotService->replyMessage($replyToken, [$retryMessage]);
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
                // 保留狀態，讓使用者可以重試
                Log::error('LINE Bot failed to download audio from LINE API', [
                    'userId' => $userId,
                    'fishId' => $fishId,
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'exception_class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $retryMessage = new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '❌ 無法下載音檔，請稍後再試',
                ]);
                $retryMessage->setQuickReply([
                    'items' => [
                        [
                            'type'   => 'action',
                            'action' => [
                                'type'        => 'postback',
                                'label'       => '🔄 重新錄製',
                                'data'        => "action=retry_audio&fish_id={$fishId}",
                                'displayText' => '重新錄製',
                            ],
                        ],
                    ],
                ]);
                $this->lineBotService->replyMessage($replyToken, [$retryMessage]);
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
                // 保留狀態，讓使用者可以重錄
                Log::warning('LINE Bot audio validation failed', [
                    'userId' => $userId,
                    'fishId' => $fishId,
                    'messageId' => $messageId,
                ]);
                
                $retryMessage = new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '❌ 音檔格式不正確，請重新錄製',
                ]);
                $retryMessage->setQuickReply([
                    'items' => [
                        [
                            'type'   => 'action',
                            'action' => [
                                'type'        => 'postback',
                                'label'       => '🔄 重新錄製',
                                'data'        => "action=retry_audio&fish_id={$fishId}",
                                'displayText' => '重新錄製',
                            ],
                        ],
                    ],
                ]);
                $this->lineBotService->replyMessage($replyToken, [$retryMessage]);
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
                // locate 從 Cache 讀取使用者選擇的部落（由 select_tribe_for_audio postback 設定）
                $tribe = \Cache::get("line_user_{$userId}_audio_tribe", 'unknown');
                FishAudio::create([
                    'fish_id'  => $fishId,
                    'name'     => $filename, // 檔案名稱（UUID.m4a）
                    'locate'   => $tribe,    // 使用者選擇的部落
                    'duration' => $duration, // 實際錄音長度（毫秒）
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
            
            // 清除狀態（含部落選擇）
            \Cache::forget("line_user_{$userId}_adding_audio");
            \Cache::forget("line_user_{$userId}_audio_tribe");
            
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

            $action = $params['action'] ?? '';

            // ==========================================
            // 圖文選單功能（Rich Menu）
            // ==========================================

            // A: 瀏覽 oyod 類魚（food_category 篩選）
            if ($action === 'browse_oyod') {
                $this->handleBrowseByFilter($replyToken, 'food_category', 'oyod', 1, 'Oyod 類魚');
                return;
            }

            // B: 瀏覽 rahet 類魚（food_category 篩選）
            if ($action === 'browse_rahet') {
                $this->handleBrowseByFilter($replyToken, 'food_category', 'rahet', 1, 'Rahet 類魚');
                return;
            }

            // C: 瀏覽資料總選單 (Flex Carousel 部落選擇)
            if ($action === 'browse_tribes_menu') {
                // 清除新增魚類流程的殘留狀態，避免污染後續操作
                \Cache::forget("line_user_{$userId}_create_fish_state");
                \Cache::forget("line_user_{$userId}_create_fish_image");

                $totalCount = Fish::count();
                $messages   = $this->lineBotService->buildBrowseTribesCarousel($totalCount);

                $this->lineBotService->replyMessage($replyToken, $messages);
                return;
            }

            // C-1: 瀏覽特定部落資料
            if ($action === 'browse_tribe_data') {
                // 清除新增魚類流程的殘留狀態
                \Cache::forget("line_user_{$userId}_create_fish_state");
                \Cache::forget("line_user_{$userId}_create_fish_image");

                $tribe = $params['tribe'] ?? 'iraraley';
                $validTribes = config('fish_options.tribes', []);
                if (!in_array($tribe, $validTribes)) {
                    $tribe = 'iraraley'; // Fallback
                }
                
                $this->handleBrowseByFilter($replyToken, 'tribe', $tribe, 1, ucfirst($tribe) . ' 部落');
                return;
            }

            // E: 隨機瀏覽魚類
            if ($action === 'random_browse') {
                // 清除新增魚類流程的殘留狀態
                \Cache::forget("line_user_{$userId}_create_fish_state");
                \Cache::forget("line_user_{$userId}_create_fish_image");

                $this->handleRandomBrowse($replyToken);
                return;
            }

            // F: 提供線索（隨機「我不知道」魚）
            if ($action === 'provide_clue') {
                // 清除新增魚類流程的殘留狀態
                \Cache::forget("line_user_{$userId}_create_fish_state");
                \Cache::forget("line_user_{$userId}_create_fish_image");

                $this->handleRandomUnknownFish($replyToken);
                return;
            }

            // ==========================================
            // 新增魚類功能
            // ==========================================

            // G: 開始新增魚類流程（Rich Menu 觸發）
            if ($action === 'start_create_fish') {
                \Cache::put("line_user_{$userId}_create_fish_state", 'waiting_image', now()->addMinutes(5));
                
                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => "🐟 新增魚類\n\n請傳送一張魚類圖片",
                        'quickReply' => [
                            'items' => [
                                [
                                    'type' => 'action',
                                    'action' => [
                                        'type' => 'postback',
                                        'label' => '❌ 取消',
                                        'data' => 'action=cancel_create_fish',
                                        'displayText' => '取消新增',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ]);
                return;
            }

            // 使用預設名稱建立魚類
            if ($action === 'create_fish_with_default_name') {
                $this->createFish($userId, $replyToken, null);
                return;
            }

            // 需要自訂名稱
            if ($action === 'create_fish_need_name') {
                \Cache::put("line_user_{$userId}_create_fish_state", 'waiting_custom_name', now()->addMinutes(5));
                
                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => "請輸入魚類名稱：",
                        'quickReply' => [
                            'items' => [
                                [
                                    'type' => 'action',
                                    'action' => [
                                        'type' => 'postback',
                                        'label' => '❌ 取消',
                                        'data' => 'action=cancel_create_fish',
                                        'displayText' => '取消新增',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ]);
                return;
            }

            // 重新上傳圖片
            if ($action === 'reupload_fish_image') {
                // 清除已上傳的圖片
                \Cache::forget("line_user_{$userId}_create_fish_image");
                \Cache::put("line_user_{$userId}_create_fish_state", 'waiting_image', now()->addMinutes(5));
                
                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => "請重新傳送魚類圖片",
                        'quickReply' => [
                            'items' => [
                                [
                                    'type' => 'action',
                                    'action' => [
                                        'type' => 'postback',
                                        'label' => '❌ 取消',
                                        'data' => 'action=cancel_create_fish',
                                        'displayText' => '取消新增',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ]);
                return;
            }

            // 取消新增魚類
            if ($action === 'cancel_create_fish') {
                // 清除所有相關狀態
                \Cache::forget("line_user_{$userId}_create_fish_state");
                \Cache::forget("line_user_{$userId}_create_fish_image");
                
                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => '✅ 已取消新增魚類',
                    ]),
                ]);
                return;
            }

            // ==========================================
            // 發音相關功能
            // ==========================================

            // 🔊 播放發音
            if ($action === 'play_audio') {
                $fishId   = (int) ($params['fish_id'] ?? 0);
                $fishName = $params['fish_name'] ?? '這條魚';
                $fish     = Fish::find($fishId);

                if ($fish && !empty($fish->audio_url)) {
                    $duration = $fish->audio_duration ?? 3000;

                    $this->lineBotService->replyMessage($replyToken, [
                        new \LINE\Clients\MessagingApi\Model\TextMessage([
                            'type' => 'text',
                            'text' => "🔊 這是「{$fishName}」的發音：",
                        ]),
                        new \LINE\Clients\MessagingApi\Model\AudioMessage([
                            'type'               => 'audio',
                            'originalContentUrl' => $fish->audio_url,
                            'duration'           => $duration,
                        ]),
                    ]);
                } else {
                    $this->lineBotService->replyMessage($replyToken, [
                        new \LINE\Clients\MessagingApi\Model\TextMessage([
                            'type' => 'text',
                            'text' => "🔇 找不到「{$fishName}」的發音檔案。",
                        ]),
                    ]);
                }
                return;
            }

            // 🔇 尚無發音
            if ($action === 'no_audio') {
                $fishName = $params['fish_name'] ?? '此魚';
                $fishId   = (int) ($params['fish_id'] ?? 0);

                $message = new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => "🔇「{$fishName}」目前尚無發音紀錄。",
                ]);

                // 提示可以新增發音
                if ($fishId) {
                    $message->setQuickReply([
                        'items' => [
                            [
                                'type'   => 'action',
                                'action' => [
                                    'type'        => 'postback',
                                    'label'       => '🎤 新增發音',
                                    'data'        => "action=start_add_audio&fish_id={$fishId}",
                                    'displayText' => '新增發音',
                                ],
                            ],
                        ],
                    ]);
                }

                $this->lineBotService->replyMessage($replyToken, [$message]);
                return;
            }

            // 分頁：下一頁瀏覽（由 Quick Reply 觸發）
            if ($action === 'browse_next') {
                $type = $params['type'] ?? '';
                $value = $params['value'] ?? '';
                $page = max(1, (int) ($params['page'] ?? 1));

                if ($type === 'food_category' || $type === 'tribe') {
                    if ($type === 'tribe') {
                        $title = ucfirst($value) . ' 部落';
                    } else {
                        $titleMap = [
                            'food_category:oyod' => 'Oyod 類魚',
                            'food_category:rahet' => 'Rahet 類魚',
                        ];
                        $title = $titleMap["{$type}:{$value}"] ?? '魚類瀏覽';
                    }
                    $this->handleBrowseByFilter($replyToken, $type, $value, $page, $title);
                } else {
                    // 隨機瀏覽的下一頁
                    $this->handleRandomBrowse($replyToken);
                }
                return;
            }

            // ==========================================
            // 原有功能
            // ==========================================

            // 處理隨機魚類請求
            if ($action === 'random_unknown_fish') {
                $this->handleRandomUnknownFish($replyToken);
                return;
            }

            // 處理開始新增發音：第一步選部落
            if ($action === 'start_add_audio') {
                $fishId = $params['fish_id'] ?? null;

                if (!$fishId) {
                    $this->lineBotService->replyMessage($replyToken, [
                        new \LINE\Clients\MessagingApi\Model\TextMessage([
                            'type' => 'text',
                            'text' => '❌ 無法取得魚類資料，請重新操作。',
                        ]),
                    ]);
                    return;
                }

                // 暫存 fishId 到 Cache 供後續步驟使用（5 分鐘過期）
                \Cache::put("line_user_{$userId}_pending_audio_fish", $fishId, now()->addMinutes(5));

                // 從設定檔讀取六個部落，組成 Quick Reply 按鈕
                $tribes = config('fish_options.tribes', []);
                $tribeItems = array_map(fn ($tribe) => [
                    'type'   => 'action',
                    'action' => [
                        'type'        => 'postback',
                        'label'       => ucfirst($tribe),
                        'data'        => "action=select_tribe_for_audio&fish_id={$fishId}&tribe={$tribe}",
                        'displayText' => "選擇部落：" . ucfirst($tribe),
                    ],
                ], $tribes);

                $message = new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => "🎤 提供發音\n\n請先選擇你來自哪個部落：",
                ]);
                $message->setQuickReply(['items' => $tribeItems]);

                $this->lineBotService->replyMessage($replyToken, [$message]);
                return;
            }

            // 處理部落選擇後進入錄音：第二步錄音
            if ($action === 'select_tribe_for_audio') {
                $fishId = $params['fish_id'] ?? \Cache::get("line_user_{$userId}_pending_audio_fish");
                $tribe  = $params['tribe'] ?? null;

                // 驗證部落是否合法
                $validTribes = config('fish_options.tribes', []);
                if (!$fishId || !$tribe || !in_array($tribe, $validTribes)) {
                    $this->lineBotService->replyMessage($replyToken, [
                        new \LINE\Clients\MessagingApi\Model\TextMessage([
                            'type' => 'text',
                            'text' => '❌ 部落資料無效，請重新操作。',
                        ]),
                    ]);
                    return;
                }

                // 儲存部落選擇與錄音狀態到 Cache（5 分鐘過期）
                \Cache::put("line_user_{$userId}_audio_tribe", $tribe, now()->addMinutes(5));
                \Cache::put("line_user_{$userId}_adding_audio", $fishId, now()->addMinutes(5));
                // 清除暫存的 pending fishId
                \Cache::forget("line_user_{$userId}_pending_audio_fish");

                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => "✅ 已選擇部落：" . ucfirst($tribe) . "\n\n🎤 請錄製魚類發音（限 5 秒以內）\n💡 不滿意可再次錄製覆蓋",
                    ]),
                ]);
                return;
            }

            // 處理重新錄製（錄音失敗後的重試）
            if ($action === 'retry_audio') {
                $fishId = $params['fish_id'] ?? null;

                if (!$fishId) {
                    $this->lineBotService->replyMessage($replyToken, [
                        new \LINE\Clients\MessagingApi\Model\TextMessage([
                            'type' => 'text',
                            'text' => '❌ 無法取得魚類資料，請重新操作。',
                        ]),
                    ]);
                    return;
                }

                // 更新（或建立）Cache TTL，再給 5 分鐘
                \Cache::put("line_user_{$userId}_adding_audio", $fishId, now()->addMinutes(5));
                // 若有部落選擇，也延長 TTL
                $existingTribe = \Cache::get("line_user_{$userId}_audio_tribe");
                if ($existingTribe) {
                    \Cache::put("line_user_{$userId}_audio_tribe", $existingTribe, now()->addMinutes(5));
                }

                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => "🎤 請重新錄製（限 5 秒以內）\n💡 不滿意可再次錄製覆蓋",
                    ]),
                ]);
                return;
            }

            // 處理開始修改名稱
            if ($action === 'start_rename') {
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
            if ($action === 'view_captures') {
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

            // 忽略 skip
            if ($action === 'skip') {
                return;
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

    /**
     * 處理圖文選單「依篩選條件瀏覽」（food_category 或 tribe）
     */
    protected function handleBrowseByFilter(
        string $replyToken,
        string $filterType,
        string $filterValue,
        int $page,
        string $title
    ): void {
        try {
            $request = Request::create('/prefix/api/fishs/filter', 'GET', [
                'filter_type' => $filterType,
                'filter_value' => $filterValue,
                'page' => $page,
            ]);

            $response = $this->apiFishController->getFishesByFilter($request);
            $data = $response->getData(true);

            $fishes = $data['data'] ?? [];
            $hasMore = $data['has_more'] ?? false;

            // 組裝「下一頁」postback data
            $nextPage = $page + 1;
            $nextPageData = "action=browse_next&type={$filterType}&value={$filterValue}&page={$nextPage}";

            // 部落篩選：卡片只顯示該部落的資料區塊
            // 食用分類篩選：顯示兩個預設部落（iraraley + imowrod）
            $contextTribes = ($filterType === 'tribe') ? [$filterValue] : null;

            $messages = $this->lineBotService->buildFishBrowseCarousel(
                $fishes,
                $hasMore,
                $nextPageData,
                $title,
                $contextTribes
            );

            $this->lineBotService->replyMessage($replyToken, $messages);

        } catch (\Exception $e) {
            Log::error('LINE Bot handleBrowseByFilter failed', [
                'filterType' => $filterType,
                'filterValue' => $filterValue,
                'page' => $page,
                'error' => $e->getMessage(),
            ]);

            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '載入魚類資料時發生錯誤，請稍後再試。',
                ]),
            ]);
        }
    }

    /**
     * 處理圖文選單「隨機瀏覽」
     */
    protected function handleRandomBrowse(string $replyToken): void
    {
        try {
            $request = Request::create('/prefix/api/fishs/random', 'GET', ['limit' => 10]);
            $response = $this->apiFishController->getRandomFishes($request);
            $data = $response->getData(true);

            $fishes = $data['data'] ?? [];

            $messages = $this->lineBotService->buildFishBrowseCarousel(
                $fishes,
                false, // 隨機瀏覽沒有「下一頁」概念，每次都重新隨機
                'action=random_browse',
                '隨機瀏覽'
            );

            // 額外加入 Quick Reply「再隨機一次」按鈕
            if (!empty($messages)) {
                $messages[0]->setQuickReply([
                    'items' => [
                        [
                            'type' => 'action',
                            'action' => [
                                'type' => 'postback',
                                'label' => '🔄 再隨機一次',
                                'data' => 'action=random_browse',
                                'displayText' => '再隨機一次',
                            ],
                        ],
                    ],
                ]);
            }

            $this->lineBotService->replyMessage($replyToken, $messages);

        } catch (\Exception $e) {
            Log::error('LINE Bot handleRandomBrowse failed', [
                'error' => $e->getMessage(),
            ]);

            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '載入隨機魚類時發生錯誤，請稍後再試。',
                ]),
            ]);
        }
    }

    /**
     * 建立魚類記錄（共用方法）
     *
     * @param string $userId LINE 用戶 ID
     * @param string $replyToken 回覆 token
     * @param string|null $customName 自訂名稱（null = 使用預設）
     */
    private function createFish(string $userId, string $replyToken, ?string $customName): void
    {
        try {
            // 取得暫存的圖片
            $filename = \Cache::get("line_user_{$userId}_create_fish_image");
            
            if (!$filename) {
                $this->lineBotService->replyMessage($replyToken, [
                    new \LINE\Clients\MessagingApi\Model\TextMessage([
                        'type' => 'text',
                        'text' => '❌ 圖片資料已過期，請重新上傳',
                    ]),
                ]);
                return;
            }
            
            // 決定名稱
            $fishName = $customName ?: '我不知道';
            
            // 建立魚類記錄
            $fish = Fish::create([
                'name'  => $fishName,
                'image' => $filename,
            ]);

            // 立即清除所有流程狀態，確保即使後續步驟失敗也不殘留
            \Cache::forget("line_user_{$userId}_create_fish_state");
            \Cache::forget("line_user_{$userId}_create_fish_image");
            
            // 建立捕獲記錄（LINE Bot 上傳的圖片無法提供完整捕獲資訊，填入佔位預設值）
            try {
                $imageUrl = $this->storageService->getUrl('images', $filename);
                \App\Models\CaptureRecord::create([
                    'fish_id'        => $fish->id,
                    'image_path'     => $imageUrl,
                    'tribe'          => 'iraraley',   // LINE Bot 預設部落，可由後台修改
                    'location'       => 'LINE Bot',
                    'capture_method' => '未知',
                    'capture_date'   => now()->toDateString(),
                ]);
            } catch (\Exception $captureEx) {
                // 捕獲記錄建立失敗不影響魚類本身的建立，記錄警告即可
                Log::warning('LINE Bot createFish: failed to create capture record', [
                    'userId'  => $userId,
                    'fishId'  => $fish->id,
                    'error'   => $captureEx->getMessage(),
                ]);
                $imageUrl = $this->storageService->getUrl('images', $filename);
            }
            
            Log::info('LINE Bot fish created successfully', [
                'userId'   => $userId,
                'fishId'   => $fish->id,
                'fishName' => $fishName,
            ]);
            
            // 組裝成功訊息
            $imageUrl = $this->storageService->getUrl('images', $filename);
            
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => "✅ 成功新增魚類「{$fishName}」",
                ]),
                new \LINE\Clients\MessagingApi\Model\ImageMessage([
                    'type'               => 'image',
                    'originalContentUrl' => $imageUrl,
                    'previewImageUrl'    => $imageUrl,
                ]),
            ]);
            
        } catch (\Exception $e) {
            // 確保即使 Fish::create 失敗，也要清除 Cache 狀態避免使用者卡住
            \Cache::forget("line_user_{$userId}_create_fish_state");
            \Cache::forget("line_user_{$userId}_create_fish_image");

            Log::error('LINE Bot create fish failed', [
                'userId' => $userId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            
            $this->lineBotService->replyMessage($replyToken, [
                new \LINE\Clients\MessagingApi\Model\TextMessage([
                    'type' => 'text',
                    'text' => '❌ 新增魚類失敗，請稍後再試',
                ]),
            ]);
        }
    }
}
