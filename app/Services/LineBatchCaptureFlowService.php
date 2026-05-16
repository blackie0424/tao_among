<?php

namespace App\Services;

use App\Models\Fish;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureDatePostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureLifecyclePostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureLocationPostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureMethodPostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureNotesPostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureTribePostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureUploadPostbackHandler;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LINE\Clients\MessagingApi\Model\TextMessage;

class LineBatchCaptureFlowService
{
    /**
     * @var array<string, LineBatchCapturePostbackHandler>
     */
    private array $postbackHandlers;

    public function __construct(
        private readonly LineBotService $lineBotService,
        private readonly CaptureRecordBatchService $captureRecordBatchService,
        private readonly LineBatchCaptureCardService $lineBatchCaptureCardService,
        private readonly ?LineUploadService $lineUploadService = null,
        iterable $postbackHandlers = [],
    ) {
        $resolvedHandlers = is_array($postbackHandlers)
            ? $postbackHandlers
            : iterator_to_array($postbackHandlers, false);

        $this->postbackHandlers = $this->indexPostbackHandlers(
            $resolvedHandlers !== [] ? $resolvedHandlers : $this->defaultPostbackHandlers()
        );
    }

    /**
     * @return string[]
     */
    public function protectedActions(): array
    {
        $actions = [];

        foreach ($this->postbackHandlers as $handler) {
            foreach ($handler->protectedActions() as $action) {
                if (!in_array($action, $actions, true)) {
                    $actions[] = $action;
                }
            }
        }

        return $actions;
    }

    public function handlesPostback(string $action): bool
    {
        return array_key_exists($action, $this->postbackHandlers);
    }

    public function getState(string $userId): ?string
    {
        return Cache::get($this->batchCaptureKey($userId, 'state'));
    }

    public function hasActiveState(string $userId): bool
    {
        return filled($this->getState($userId));
    }

    public function clearState(string $userId): void
    {
        Cache::forget($this->batchCaptureKey($userId, 'state'));
        Cache::forget($this->batchCaptureKey($userId, 'fish'));
        Cache::forget($this->batchCaptureKey($userId, 'images'));
        Cache::forget($this->batchCaptureKey($userId, 'form'));
    }

    public function handleUnauthorizedAccess(string $userId, string $replyToken): void
    {
        $this->clearState($userId);
        $this->replyText($replyToken, '⚠️ 您沒有此功能的使用權限。');
    }

    public function handleTextMessage(string $userId, string $text, string $replyToken): bool
    {
        $state = $this->getState($userId);
        if (!$state) {
            return false;
        }

        if ($state === 'waiting_images') {
            $this->replySummary($replyToken, $userId);
            return true;
        }

        if ($state === 'waiting_tribe_selection') {
            $this->replyTribeSelectionCard($replyToken);
            return true;
        }

        if (in_array($state, ['awaiting_location_prompt', 'awaiting_location_input'], true)) {
            [$validated, $error] = $this->validateLocation($text);
            if (!$validated) {
                $this->replyText($replyToken, $error);
                return true;
            }

            $this->updateForm($userId, ['location' => $validated['location']]);
            $this->putState($userId, 'awaiting_method_prompt');
            $this->replySummary($replyToken, $userId);
            return true;
        }

        if ($state === 'waiting_method_selection') {
            $this->replyMethodSelectionCard($replyToken);
            return true;
        }

        if ($state === 'waiting_date_selection') {
            $this->replyDateSelectionCard($replyToken);
            return true;
        }

        if (in_array($state, ['awaiting_date_prompt', 'awaiting_date_manual_input'], true)) {
            [$validated, $error] = $this->validateCaptureDate($text);
            if (!$validated) {
                $this->replyText($replyToken, $error);
                return true;
            }

            $this->updateForm($userId, ['capture_date' => $validated['capture_date']]);
            $this->putState($userId, 'awaiting_notes_prompt');
            $this->replySummary($replyToken, $userId);
            return true;
        }

        if (in_array($state, ['awaiting_notes_prompt', 'awaiting_notes_input'], true)) {
            [$validated, $error] = $this->validateNotes($text);
            if ($validated === null) {
                $this->replyText($replyToken, $error);
                return true;
            }

            $this->updateForm($userId, ['notes' => $validated['notes']]);
            $this->putState($userId, 'waiting_confirm');
            $this->replySummary($replyToken, $userId);
            return true;
        }

        $this->replySummary($replyToken, $userId);
        return true;
    }

    public function handleImageMessage(string $userId, string $replyToken, string $messageId): bool
    {
        $state = $this->getState($userId);
        if (!$state) {
            return false;
        }

        if ($state !== 'waiting_images') {
            $this->replyText($replyToken, '目前已進入欄位填寫流程，請先完成部落、地點與捕獲方式等資料。');
            return true;
        }

        $maxImages = config('fish_options.batch_upload.max_files_mobile', 5);
        $images = $this->getImages($userId);

        if (count($images) >= $maxImages) {
            $this->replySummary($replyToken, $userId);
            return true;
        }

        try {
            $imageBlob = $this->lineBotService->getMessageContent($messageId);
            $filename = $this->lineUploadService()->uploadLineImage($imageBlob);

            if (!$filename) {
                throw new \RuntimeException('Failed to upload batch capture image');
            }

            $images[] = $filename;
            $this->putImages($userId, $images);
            $this->putState($userId, 'waiting_images');
            $this->replySummary($replyToken, $userId);
        } catch (\Exception $e) {
            Log::error('LINE Bot batch capture image upload failed', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);

            $this->replyText($replyToken, '❌ 捕獲照片處理失敗，請稍後再試。');
        }

        return true;
    }

    public function handlePostback(string $userId, string $replyToken, string $action, array $params): bool
    {
        $handler = $this->postbackHandlers[$action] ?? null;
        if (!$handler) {
            return false;
        }

        $handler->handle($this, new LineBatchCapturePostbackContext($action, $userId, $replyToken, $params));
        return true;
    }

    public function startCapture(string $userId, string $replyToken, int $fishId): void
    {
        $fish = Fish::find($fishId);

        if (!$fish) {
            $this->replyText($replyToken, '❌ 找不到魚類資料，請重新操作。');
            return;
        }

        Cache::forget("line_user_{$userId}_create_fish_state");
        Cache::forget("line_user_{$userId}_create_fish_images");
        $this->clearState($userId);

        Cache::put($this->batchCaptureKey($userId, 'fish'), $fish->id, now()->addMinutes(15));
        $this->putImages($userId, []);
        $this->putForm($userId, []);
        $this->putState($userId, 'waiting_images');

        $this->replySummary($replyToken, $userId, $fish);
    }

    public function confirmCapture(string $userId, string $replyToken): void
    {
        $fish = Fish::find(Cache::get($this->batchCaptureKey($userId, 'fish')));

        if (!$fish) {
            $this->clearState($userId);
            $this->replyText($replyToken, '❌ 魚類資料已不存在，請重新操作。');
            return;
        }

        try {
            $records = $this->captureRecordBatchService->createForFish($fish, $this->getImages($userId), $this->getForm($userId));
            $this->clearState($userId);
            $this->replyText($replyToken, '✅ 已成功新增 ' . count($records) . ' 筆捕獲紀錄');
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? '❌ 捕獲紀錄資料有誤，請重新確認。';
            $this->replyText($replyToken, $message);
        }
    }

    private function batchCaptureKey(string $userId, string $suffix): string
    {
        return "line_user_{$userId}_batch_capture_{$suffix}";
    }

    public function getImages(string $userId): array
    {
        return Cache::get($this->batchCaptureKey($userId, 'images'), []);
    }

    private function putImages(string $userId, array $images, int $minutes = 15): void
    {
        Cache::put($this->batchCaptureKey($userId, 'images'), $images, now()->addMinutes($minutes));
    }

    public function getForm(string $userId): array
    {
        return Cache::get($this->batchCaptureKey($userId, 'form'), []);
    }

    public function putForm(string $userId, array $form, int $minutes = 15): void
    {
        Cache::put($this->batchCaptureKey($userId, 'form'), $form, now()->addMinutes($minutes));
    }

    public function putState(string $userId, string $state, int $minutes = 15): void
    {
        Cache::put($this->batchCaptureKey($userId, 'state'), $state, now()->addMinutes($minutes));
    }

    public function updateForm(string $userId, array $values): void
    {
        $this->putForm($userId, array_merge($this->getForm($userId), $values));
    }

    private function validateLocation(string $text): array
    {
        $validator = Validator::make(
            ['location' => $text],
            ['location' => 'required|string|max:255'],
            [
                'location.required' => '請輸入捕獲地點',
                'location.max' => '捕獲地點不能超過 255 個字元',
            ]
        );

        if ($validator->fails()) {
            return [null, $validator->errors()->first()];
        }

        return [$validator->validated(), null];
    }

    private function validateCaptureDate(string $text): array
    {
        $validator = Validator::make(
            ['capture_date' => $text],
            ['capture_date' => 'required|date|before_or_equal:today'],
            [
                'capture_date.required' => '請輸入捕獲日期',
                'capture_date.date' => '請輸入 YYYY-MM-DD 格式的有效日期',
                'capture_date.before_or_equal' => '捕獲日期不能是未來日期',
            ]
        );

        if ($validator->fails()) {
            return [null, $validator->errors()->first()];
        }

        return [$validator->validated(), null];
    }

    private function validateNotes(string $text): array
    {
        $validator = Validator::make(
            ['notes' => $text],
            ['notes' => 'nullable|string|max:65535'],
            [
                'notes.max' => '備註內容過長，請縮短至65535字元以內',
            ]
        );

        if ($validator->fails()) {
            return [null, $validator->errors()->first()];
        }

        return [$validator->validated(), null];
    }

    public function replySummary(string $replyToken, string $userId, ?Fish $fish = null): void
    {
        $fish ??= Fish::find(Cache::get($this->batchCaptureKey($userId, 'fish')));

        if (!$fish) {
            $this->clearState($userId);
            $this->replyText($replyToken, '❌ 魚類資料已不存在，請重新操作。');
            return;
        }

        $state = $this->getState($userId) ?? 'waiting_images';
        $images = $this->getImages($userId);
        $form = $this->getForm($userId);

        $notice = match ($state) {
            'waiting_images' => count($images) > 0
                ? '請確認照片數量；全部圖片都上傳完成後，按「圖片上傳完成」進入下一步。'
                : '請先上傳至少 1 張捕獲照片，全部上傳完成後再進入下一步。',
            'awaiting_location_prompt' => '已完成部落選擇，請輸入捕獲地點。',
            'awaiting_method_prompt' => '地點已填寫，請選擇捕獲方式。',
            'awaiting_date_prompt' => '捕獲方式已選擇，請選擇捕獲日期。',
            'awaiting_notes_prompt' => '日期已選擇，請輸入備註或略過。',
            'waiting_confirm' => '請確認資料無誤後再送出。',
            default => null,
        };

        $actions = match ($state) {
            'waiting_images' => count($images) > 0 ? [
                ['label' => '➕ 繼續上傳', 'data' => 'action=continue_batch_capture_upload', 'display_text' => '繼續上傳照片'],
                ['label' => '✅ 圖片上傳完成', 'data' => 'action=finish_batch_capture_upload', 'display_text' => '圖片上傳完成', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ] : [
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄'],
            ],
            'awaiting_location_prompt', 'awaiting_location_input' => [
                ['label' => '輸入捕獲地點', 'data' => 'action=prompt_batch_capture_location', 'display_text' => '輸入捕獲地點'],
                ['label' => '修改部落', 'data' => 'action=open_batch_capture_tribe_selector', 'display_text' => '修改捕獲部落', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
            'awaiting_method_prompt', 'waiting_method_selection' => [
                ['label' => '選擇捕獲方式', 'data' => 'action=open_batch_capture_method_selector', 'display_text' => '選擇捕獲方式'],
                ['label' => '修改地點', 'data' => 'action=prompt_batch_capture_location', 'display_text' => '修改捕獲地點', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
            'awaiting_date_prompt', 'waiting_date_selection', 'awaiting_date_manual_input' => [
                ['label' => '選擇捕獲日期', 'data' => 'action=open_batch_capture_date_selector', 'display_text' => '選擇捕獲日期'],
                ['label' => '修改捕獲方式', 'data' => 'action=open_batch_capture_method_selector', 'display_text' => '修改捕獲方式', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
            'awaiting_notes_prompt', 'awaiting_notes_input' => [
                ['label' => '輸入備註', 'data' => 'action=prompt_batch_capture_notes', 'display_text' => '輸入備註'],
                ['label' => '略過備註', 'data' => 'action=skip_batch_capture_notes', 'display_text' => '略過備註', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
            'waiting_confirm' => [
                ['label' => '✅ 確認送出', 'data' => 'action=confirm_batch_capture_record', 'display_text' => '確認送出批次捕獲紀錄'],
                ['label' => '🔁 重新填寫', 'data' => 'action=reset_batch_capture_form', 'display_text' => '重新填寫捕獲資料', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
            default => [
                ['label' => '➕ 繼續上傳', 'data' => 'action=continue_batch_capture_upload', 'display_text' => '繼續上傳照片'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
        };

        $this->lineBotService->replyMessage($replyToken, [
            $this->lineBatchCaptureCardService->buildSummaryCard($fish, $images, $form, $actions, $notice),
        ]);
    }

    public function replyTribeSelectionCard(string $replyToken, ?string $prefix = null): void
    {
        $actions = array_map(fn ($tribe) => [
            'label' => ucfirst($tribe),
            'data' => "action=select_batch_capture_tribe&tribe={$tribe}",
            'display_text' => $tribe,
            'style' => 'secondary',
        ], config('fish_options.tribes', []));

        $this->lineBotService->replyMessage($replyToken, [
            $this->lineBatchCaptureCardService->buildOptionSelectorCard(
                '請選擇捕獲部落',
                trim(($prefix ? "{$prefix}\n" : '') . '點選後會回到摘要卡片繼續填寫。'),
                $actions
            ),
        ]);
    }

    public function replyMethodSelectionCard(string $replyToken, ?string $prefix = null): void
    {
        $actions = [];
        foreach (config('fish_options.capture_methods', []) as $value => $label) {
            $actions[] = [
                'label' => $label,
                'data' => "action=select_batch_capture_method&capture_method={$value}",
                'display_text' => $label,
                'style' => 'secondary',
            ];
        }

        $this->lineBotService->replyMessage($replyToken, [
            $this->lineBatchCaptureCardService->buildOptionSelectorCard(
                '請選擇捕獲方式',
                trim(($prefix ? "{$prefix}\n" : '') . '點選後會回到摘要卡片繼續填寫。'),
                $actions
            ),
        ]);
    }

    public function replyDateSelectionCard(string $replyToken, ?string $prefix = null): void
    {
        $actions = [
            ['label' => '今天', 'data' => 'action=set_batch_capture_date&value=today', 'display_text' => '今天'],
            ['label' => '昨天', 'data' => 'action=set_batch_capture_date&value=yesterday', 'display_text' => '昨天', 'style' => 'secondary'],
            ['label' => '手動輸入', 'data' => 'action=request_manual_batch_capture_date', 'display_text' => '手動輸入日期', 'style' => 'secondary'],
        ];

        $this->lineBotService->replyMessage($replyToken, [
            $this->lineBatchCaptureCardService->buildOptionSelectorCard(
                '請選擇捕獲日期',
                trim(($prefix ? "{$prefix}\n" : '') . '點選後會回到摘要卡片繼續填寫。'),
                $actions
            ),
        ]);
    }

    public function replyText(string $replyToken, string $text): void
    {
        $this->lineBotService->replyMessage($replyToken, [
            new TextMessage([
                'type' => 'text',
                'text' => $text,
            ]),
        ]);
    }

    private function lineUploadService(): LineUploadService
    {
        return $this->lineUploadService ?? app(LineUploadService::class);
    }

    /**
     * @return LineBatchCapturePostbackHandler[]
     */
    private function defaultPostbackHandlers(): array
    {
        return [
            new LineBatchCaptureLifecyclePostbackHandler(),
            new LineBatchCaptureUploadPostbackHandler(),
            new LineBatchCaptureTribePostbackHandler(),
            new LineBatchCaptureLocationPostbackHandler(),
            new LineBatchCaptureMethodPostbackHandler(),
            new LineBatchCaptureDatePostbackHandler(),
            new LineBatchCaptureNotesPostbackHandler(),
        ];
    }

    /**
     * @param iterable<LineBatchCapturePostbackHandler> $handlers
     * @return array<string, LineBatchCapturePostbackHandler>
     */
    private function indexPostbackHandlers(iterable $handlers): array
    {
        $indexed = [];

        foreach ($handlers as $handler) {
            foreach ($handler->actions() as $action) {
                $indexed[$action] = $handler;
            }
        }

        return $indexed;
    }
}
