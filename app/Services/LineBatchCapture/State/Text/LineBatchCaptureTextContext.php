<?php

namespace App\Services\LineBatchCapture\State\Text;

class LineBatchCaptureTextContext
{
    public function __construct(
        private readonly string $state,
        private readonly string $userId,
        private readonly string $replyToken,
        private readonly string $text,
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

    public function text(): string
    {
        return $this->text;
    }
}
