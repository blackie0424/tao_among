<?php

namespace App\Services;

use App\Contracts\CaptureSessionServiceInterface;
use App\Contracts\LineMessagingClientInterface;
use App\Models\Fish;
use App\Services\LineBatchCapture\Actions\ConfirmLineBatchCaptureAction;
use App\Services\LineBatchCapture\Actions\StartLineBatchCaptureAction;
use App\Services\LineBatchCapture\LineBatchCaptureStateStore;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureDatePostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureLifecyclePostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureLocationPostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureMethodPostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureNotesPostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureSessionPostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureTribePostbackHandler;
use App\Services\LineBatchCapture\Postback\Handlers\LineBatchCaptureUploadPostbackHandler;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCapture\State\Image\Handlers\LineBatchCaptureLockedImageStateHandler;
use App\Services\LineBatchCapture\State\Image\Handlers\LineBatchCaptureWaitingImagesImageStateHandler;
use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageContext;
use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageStateHandler;
use App\Services\LineBatchCapture\State\Image\LineImageSet;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureDateSelectorTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureDateTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureLocationTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureMethodSelectorTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureNotesTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureSessionSelectorTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureSummaryTextStateHandler;
use App\Services\LineBatchCapture\State\Text\Handlers\LineBatchCaptureTribeSelectorTextStateHandler;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;

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
        private readonly LineMessagingClientInterface $lineMessagingClient,
        private readonly CaptureRecordBatchService $captureRecordBatchService,
        private readonly LineBatchCaptureMessageBuilder $lineBatchCaptureMessageBuilder,
        private readonly ?LineUploadService $lineUploadService = null,
        iterable $postbackHandlers = [],
        iterable $textStateHandlers = [],
        iterable $imageStateHandlers = [],
        private readonly ?CaptureRecordFieldValidator $captureRecordFieldValidator = null,
        private readonly ?LineBatchCaptureReplyBuilder $lineBatchCaptureReplyBuilder = null,
        private readonly ?LineBatchCaptureStateStore $lineBatchCaptureStateStore = null,
        private readonly ?StartLineBatchCaptureAction $startLineBatchCaptureAction = null,
        private readonly ?ConfirmLineBatchCaptureAction $confirmLineBatchCaptureAction = null,
        private readonly ?CaptureSessionServiceInterface $captureSessionService = null,
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
        return $this->lineBatchCaptureStateStore()->getState($userId);
    }

    public function hasActiveState(string $userId): bool
    {
        return filled($this->getState($userId));
    }

    public function clearState(string $userId): void
    {
        $this->lineBatchCaptureStateStore()->clear($userId);
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

    public function handleImageMessage(string $userId, string $replyToken, string $messageId, ?LineImageSet $imageSet = null): bool
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

        $handler->handle($this, new LineBatchCaptureImageContext($state, $userId, $replyToken, $messageId, $imageSet));
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
        $result = $this->startLineBatchCaptureAction()->execute($userId, $fishId);

        if (!$result->successful()) {
            $this->replyText($replyToken, $result->message());
            return;
        }

        $this->replySummary($replyToken, $userId, $result->fish());
    }

    public function confirmCapture(string $userId, string $replyToken): void
    {
        $result = $this->confirmLineBatchCaptureAction()->execute($userId);
        $this->replyText($replyToken, $result->message());
    }

    public function getImages(string $userId): array
    {
        return $this->lineBatchCaptureStateStore()->getImages($userId);
    }

    public function putImages(string $userId, array $images, int $minutes = 15): void
    {
        $this->lineBatchCaptureStateStore()->putImages($userId, $images, $minutes);
    }

    public function getForm(string $userId): array
    {
        return $this->lineBatchCaptureStateStore()->getForm($userId);
    }

    public function putForm(string $userId, array $form, int $minutes = 15): void
    {
        $this->lineBatchCaptureStateStore()->putForm($userId, $form, $minutes);
    }

    public function putState(string $userId, string $state, int $minutes = 15): void
    {
        $this->lineBatchCaptureStateStore()->putState($userId, $state, $minutes);
    }

    public function updateForm(string $userId, array $values): void
    {
        $this->lineBatchCaptureStateStore()->updateForm($userId, $values);
    }

    public function replySummary(string $replyToken, string $userId, ?Fish $fish = null): void
    {
        $fish ??= Fish::find($this->lineBatchCaptureStateStore()->getFishId($userId));

        if (!$fish) {
            $this->clearState($userId);
            $this->replyText($replyToken, '❌ 魚類資料已不存在，請重新操作。');
            return;
        }

        $state = $this->getState($userId) ?? 'waiting_images';
        $images = $this->getImages($userId);
        $form = $this->getForm($userId);

        $this->lineMessagingClient->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildSummaryMessage($fish, $images, $form, $state),
        ]);
    }

    public function replyTribeSelectionCard(string $replyToken, ?string $prefix = null): void
    {
        $this->lineMessagingClient->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildTribeSelectionMessage($prefix),
        ]);
    }

    /**
     * @param array<int, array{tribe:string,location:string,capture_method:string,capture_date:string}> $sessions
     */
    public function replySessionPickerCard(string $replyToken, array $sessions): void
    {
        $this->lineMessagingClient->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildSessionPickerMessage($sessions),
        ]);
    }

    public function replySessionPickerOrTribeCard(string $replyToken, string $userId): void
    {
        $sessions = array_slice($this->captureSessionService()->getRecentSessions(), 0, 3);

        if (!empty($sessions)) {
            $this->putState($userId, 'waiting_session_selection');
            $this->replySessionPickerCard($replyToken, $sessions);
        } else {
            $this->putState($userId, 'waiting_tribe_selection');
            $this->replyTribeSelectionCard($replyToken);
        }
    }

    public function replyMethodSelectionCard(string $replyToken, ?string $prefix = null): void
    {
        $this->lineMessagingClient->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildMethodSelectionMessage($prefix),
        ]);
    }

    public function replyDateSelectionCard(string $replyToken, ?string $prefix = null): void
    {
        $this->lineMessagingClient->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildDateSelectionMessage($prefix),
        ]);
    }

    public function replyText(string $replyToken, string $text): void
    {
        $this->lineMessagingClient->replyMessage($replyToken, [
            $this->lineBatchCaptureReplyBuilder()->buildTextMessage($text),
        ]);
    }

    public function uploadLineImage(string $messageId): string
    {
        $imageBlob = $this->lineMessagingClient->getMessageContent($messageId);
        $filename = $this->lineUploadService()->uploadLineImage($imageBlob);

        if (!$filename) {
            throw new \RuntimeException('Failed to upload batch capture image');
        }

        return $filename;
    }

    public function captureRecordBatchService(): CaptureRecordBatchService
    {
        return $this->captureRecordBatchService;
    }

    public function captureRecordFieldValidator(): CaptureRecordFieldValidator
    {
        return $this->captureRecordFieldValidator ?? app(CaptureRecordFieldValidator::class);
    }

    public function lineBatchCaptureReplyBuilder(): LineBatchCaptureReplyBuilder
    {
        return $this->lineBatchCaptureReplyBuilder ?? app(LineBatchCaptureReplyBuilder::class);
    }

    public function lineBatchCaptureStateStore(): LineBatchCaptureStateStore
    {
        return $this->lineBatchCaptureStateStore ?? app(LineBatchCaptureStateStore::class);
    }

    public function startLineBatchCaptureAction(): StartLineBatchCaptureAction
    {
        return $this->startLineBatchCaptureAction
            ?? new StartLineBatchCaptureAction($this->lineBatchCaptureStateStore());
    }

    public function confirmLineBatchCaptureAction(): ConfirmLineBatchCaptureAction
    {
        return $this->confirmLineBatchCaptureAction
            ?? new ConfirmLineBatchCaptureAction($this->lineBatchCaptureStateStore(), $this->captureRecordBatchService);
    }

    public function captureSessionService(): CaptureSessionServiceInterface
    {
        return $this->captureSessionService ?? app(CaptureSessionServiceInterface::class);
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
            new LineBatchCaptureSessionPostbackHandler(),
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
            new LineBatchCaptureSessionSelectorTextStateHandler(),
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
