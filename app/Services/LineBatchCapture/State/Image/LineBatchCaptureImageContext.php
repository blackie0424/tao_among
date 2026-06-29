<?php

namespace App\Services\LineBatchCapture\State\Image;

class LineBatchCaptureImageContext
{
    public function __construct(
        private readonly string $state,
        private readonly string $userId,
        private readonly string $replyToken,
        private readonly string $messageId,
        private readonly ?LineImageSet $imageSet = null,
    ) {
    }

    public function state(): string
    {
        return $this->state;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function replyToken(): string
    {
        return $this->replyToken;
    }

    public function messageId(): string
    {
        return $this->messageId;
    }

    public function imageSet(): ?LineImageSet
    {
        return $this->imageSet;
    }
}
