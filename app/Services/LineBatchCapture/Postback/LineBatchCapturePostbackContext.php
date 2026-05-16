<?php

namespace App\Services\LineBatchCapture\Postback;

class LineBatchCapturePostbackContext
{
    public function __construct(
        private readonly string $action,
        private readonly string $userId,
        private readonly string $replyToken,
        private readonly array $params = [],
    ) {
    }

    public function action(): string
    {
        return $this->action;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function replyToken(): string
    {
        return $this->replyToken;
    }

    public function params(): array
    {
        return $this->params;
    }
}
