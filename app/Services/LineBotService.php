<?php

namespace App\Services;

use App\Contracts\LineMessagingClientInterface;
use App\Services\Line\LineFishMessageBuilder;
use App\Services\Line\LineMenuMessageBuilder;
use App\Services\Line\LineMessagingClient;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\TextMessage;

/**
 * @deprecated 過渡 façade，請改依賴更明確的 LineMessagingClient / LineFishMessageBuilder / LineMenuMessageBuilder。
 */
class LineBotService implements LineMessagingClientInterface
{
    public function __construct(
        private readonly ?LineMessagingClientInterface $lineMessagingClient = null,
        private readonly ?LineFishMessageBuilder $lineFishMessageBuilder = null,
        private readonly ?LineMenuMessageBuilder $lineMenuMessageBuilder = null,
    ) {
    }

    public function validateSignature(string $body, string $signature): bool
    {
        return $this->lineMessagingClient()->validateSignature($body, $signature);
    }

    public function getUserProfile(string $userId): array
    {
        return $this->lineMessagingClient()->getUserProfile($userId);
    }

    public function replyMessage(string $replyToken, array $messages): void
    {
        $this->lineMessagingClient()->replyMessage($replyToken, $messages);
    }

    public function buildFishCard(array $fish, ?array $contextTribes = null, bool $isEditor = false): FlexMessage
    {
        return $this->lineFishMessageBuilder()->buildFishCard($fish, $contextTribes, $isEditor);
    }

    public function buildFishListMessage(array $fishes, bool $isEditor = false): array
    {
        return $this->lineFishMessageBuilder()->buildFishListMessage($fishes, $isEditor);
    }

    public function buildCaptureRecordsCarousel(array $captureRecords, string $fishName): FlexMessage
    {
        return $this->lineFishMessageBuilder()->buildCaptureRecordsCarousel($captureRecords, $fishName);
    }

    public function buildFishCardWithQuickReply(array $fish, bool $isEditor = false): FlexMessage
    {
        return $this->lineFishMessageBuilder()->buildFishCardWithQuickReply($fish, $isEditor);
    }

    public function buildBrowseTribesCarousel(): array
    {
        return $this->lineMenuMessageBuilder()->buildBrowseTribesMenu();
    }

    public function buildFishBrowseCarousel(array $fishes, bool $hasMore, string $nextPageData, string $title, ?array $contextTribes = null, bool $isEditor = false): array
    {
        return $this->lineFishMessageBuilder()->buildFishBrowseCarousel(
            $fishes,
            $hasMore,
            $nextPageData,
            $title,
            $contextTribes,
            $isEditor
        );
    }

    public function buildHelpMessage(): TextMessage
    {
        return $this->lineMenuMessageBuilder()->buildHelpMessage();
    }

    public function getMessageContent(string $messageId): string
    {
        return $this->lineMessagingClient()->getMessageContent($messageId);
    }

    private function lineMessagingClient(): LineMessagingClientInterface
    {
        return $this->lineMessagingClient ?? new LineMessagingClient();
    }

    private function lineFishMessageBuilder(): LineFishMessageBuilder
    {
        return $this->lineFishMessageBuilder ?? new LineFishMessageBuilder();
    }

    private function lineMenuMessageBuilder(): LineMenuMessageBuilder
    {
        return $this->lineMenuMessageBuilder ?? new LineMenuMessageBuilder();
    }
}
