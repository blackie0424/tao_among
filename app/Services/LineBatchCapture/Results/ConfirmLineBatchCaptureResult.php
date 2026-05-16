<?php

namespace App\Services\LineBatchCapture\Results;

class ConfirmLineBatchCaptureResult
{
    public function __construct(
        private readonly bool $successful,
        private readonly ?int $recordsCount = null,
        private readonly string $message = '',
    ) {
    }

    public static function success(int $recordsCount): self
    {
        return new self(true, $recordsCount, '✅ 已成功新增 ' . $recordsCount . ' 筆捕獲紀錄');
    }

    public static function failure(string $message): self
    {
        return new self(false, null, $message);
    }

    public function successful(): bool
    {
        return $this->successful;
    }

    public function recordsCount(): ?int
    {
        return $this->recordsCount;
    }

    public function message(): string
    {
        return $this->message;
    }
}
