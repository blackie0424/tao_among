<?php

namespace App\Services\LineBatchCapture\Results;

class ConfirmLineBatchCaptureResult
{
    private const REASON_SESSION_GONE = 'session_gone';

    private const REASON_SESSION_CHANGED = 'session_changed';

    public function __construct(
        private readonly bool $successful,
        private readonly ?int $recordsCount = null,
        private readonly string $message = '',
        private readonly ?string $reason = null,
    ) {}

    public static function success(int $recordsCount): self
    {
        return new self(true, $recordsCount, '✅ 已成功新增 '.$recordsCount.' 筆捕獲紀錄');
    }

    public static function failure(string $message): self
    {
        return new self(false, null, $message);
    }

    /** 選好的情境在確認前被刪除 */
    public static function sessionGone(): self
    {
        return new self(false, null, '這個情境已不存在，請重新選擇', self::REASON_SESSION_GONE);
    }

    /** 選好的情境在確認前被修改，摘要需要重新確認 */
    public static function sessionChanged(): self
    {
        return new self(false, null, '情境已更新，請重新確認', self::REASON_SESSION_CHANGED);
    }

    public function successful(): bool
    {
        return $this->successful;
    }

    public function isSessionGone(): bool
    {
        return $this->reason === self::REASON_SESSION_GONE;
    }

    public function isSessionChanged(): bool
    {
        return $this->reason === self::REASON_SESSION_CHANGED;
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
