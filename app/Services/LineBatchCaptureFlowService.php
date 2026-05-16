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
use App\Services\LineBatchCapture\State\Image\Handlers\LineBatchCaptureLockedImageStateHandler;
use App\Services\LineBatchCapture\State\Image\Handlers\LineBatchCaptureWaitingImagesImageStateHandler;
use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageContext;
use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureDateSelectorTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureDateTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureLocationTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureMethodSelectorTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureNotesTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureSummaryTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureTribeSelectorTextStateHandler;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class LineBatchCaptureFlowService
{
    /**
     * @var array<string, LineBatchCapturePostbackHandler>
     */
    private array $postbackHandlers;

    /**
     * @var array<string, LineBatchCaptureTextStateHandler>
     */
    private array $textStateHandlers;

    /**
     * @var array<string, LineBatchCaptureImageStateHandler>
     */
    private array $imageStateHandlers;

    public function __construct(
        private readonly LineBotService $lineBotService,
        private readonly CaptureRecordBatchService $captureRecordBatchService,
        private readonly LineBatchCaptureCardService $lineBatchCaptureCardService,
        private readonly ?LineUploadService $lineUploadService = null,
        iterable $postbackHandlers = [],
        iterable $textStateHandlers = [],
        iterable $imageStateHandlers = [],
        private readonly ?CaptureRecordFieldValidator $captureRecordFieldValidator = null,
        private readonly ?LineBatchCaptureReplyBuilder $lineBatchCaptureReplyBuilder = null,
    ) {
        $resolvedHandlers = is_array($postbackHandlers)
            ? $postbackHandlers
            : iterator_to_array($postbackHandlers, false);
        $resolvedTextHandlers = is_array($textStateHandlers)
            ? $textStateHandlers
            : iterator_to_array($textStateHandlers, false);
        $resolvedImageHandlers = is_array($imageStateHandlers)
            ? $imageStateHandlers
            : iterator_to_array($imageStateHandlers, false);

        $this->postbackHandlers = $this->indexPostbackHandlers(
            $resolvedHandlers !== [] ? $resolvedHandlers : $this->defaultPostbackHandlers()
        );
        $this->textStateHandlers = $this->indexTextStateHandlers(
            $resolvedTextHandlers !== [] ? $resolvedTextHandlers : $this->defaultTextStateHandlers()
        );
        $this->imageStateHandlers = $this->indexImageStateHandlers(
            $resolvedImageHandlers !== [] ? $resolvedImageHandlers : $this->defaultImageStateHandlers()
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

        $handler = $this->textStateHandlers[$state] ?? null;
        if (!$handler) {
            $this->replySummary($replyToken, $userId);
            return true;
        }

        $handler->handle($this, new LineBatchCaptureTextContext($state, $userId, $replyToken, $text));
        return true;
    }

    public function handleImageMessage(string $userId, string $replyToken, string $messageId): bool
    {
        $state = $this->getState($userId);
        if (!$state) {
            return false;
        }

        $handler = $this->imageStateHandlers[$state] ?? null;
        if (!$handler) {
            $this->replyText($replyToken, '目前已進入欄位填寫流程，請先完成部落、地點與捕獲方式等資料。');
            return true;
        }

        $handler->handle($this, new LineBatchCaptureImageContext($state, $userId, $replyToken, $messageId));
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

    public function putImages(string $userId, array $images, int $minutes = 15): void
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

        $this->lineBotService->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildSummaryMessage($fish, $images, $form, $state),
        ]);
    }

    public function replyTribeSelectionCard(string $replyToken, ?string $prefix = null): void
    {
        $this->lineBotService->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildTribeSelectionMessage($prefix),
        ]);
    }

    public function replyMethodSelectionCard(string $replyToken, ?string $prefix = null): void
    {
        $this->lineBotService->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildMethodSelectionMessage($prefix),
        ]);
    }

    public function replyDateSelectionCard(string $replyToken, ?string $prefix = null): void
    {
        $this->lineBotService->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildDateSelectionMessage($prefix),
        ]);
    }

    public function replyText(string $replyToken, string $text): void
    {
        $this->lineBotService->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildTextMessage($text),
        ]);
    }

    public function uploadLineImage(string $messageId): string
    {
        $imageBlob = $this->lineBotService->getMessageContent($messageId);
        $filename = $this->lineUploadService()->uploadLineImage($imageBlob);

        if (!$filename) {
            throw new \RuntimeException('Failed to upload batch capture image');
        }

        return $filename;
    }

    public function captureRecordFieldValidator(): CaptureRecordFieldValidator
    {
        return $this->captureRecordFieldValidator ?? app(CaptureRecordFieldValidator::class);
    }

    public function lineBatchCaptureReplyBuilder(): LineBatchCaptureReplyBuilder
    {
        return $this->lineBatchCaptureReplyBuilder ?? app(LineBatchCaptureReplyBuilder::class);
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
     * @return LineBatchCaptureTextStateHandler[]
     */
    private function defaultTextStateHandlers(): array
    {
        return [
            new LineBatchCaptureSummaryTextStateHandler(),
            new LineBatchCaptureTribeSelectorTextStateHandler(),
            new LineBatchCaptureLocationTextStateHandler(),
            new LineBatchCaptureMethodSelectorTextStateHandler(),
            new LineBatchCaptureDateSelectorTextStateHandler(),
            new LineBatchCaptureDateTextStateHandler(),
            new LineBatchCaptureNotesTextStateHandler(),
        ];
    }

    /**
     * @return LineBatchCaptureImageStateHandler[]
     */
    private function defaultImageStateHandlers(): array
    {
        return [
            new LineBatchCaptureWaitingImagesImageStateHandler(),
            new LineBatchCaptureLockedImageStateHandler(),
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

    /**
     * @param iterable<LineBatchCaptureTextStateHandler> $handlers
     * @return array<string, LineBatchCaptureTextStateHandler>
     */
    private function indexTextStateHandlers(iterable $handlers): array
    {
        $indexed = [];

        foreach ($handlers as $handler) {
            foreach ($handler->states() as $state) {
                $indexed[$state] = $handler;
            }
        }

        return $indexed;
    }

    /**
     * @param iterable<LineBatchCaptureImageStateHandler> $handlers
     * @return array<string, LineBatchCaptureImageStateHandler>
     */
    private function indexImageStateHandlers(iterable $handlers): array
    {
        $indexed = [];

        foreach ($handlers as $handler) {
            foreach ($handler->states() as $state) {
                $indexed[$state] = $handler;
            }
        }

        return $indexed;
    }
}
