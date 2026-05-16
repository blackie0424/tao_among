<?php

namespace App\Services\LineBatchCapture\Results;

use App\Models\Fish;

class StartLineBatchCaptureResult
{
    public function __construct(
        private readonly bool $successful,
        private readonly ?Fish $fish = null,
        private readonly string $message = '',
    ) {
    }

    public static function success(Fish $fish): self
    {
        return new self(true, $fish);
    }

    public static function failure(string $message): self
    {
        return new self(false, null, $message);
    }

    public function successful(): bool
    {
        return $this->successful;
    }

    public function fish(): ?Fish
    {
        return $this->fish;
    }

    public function message(): string
    {
        return $this->message;
    }
}
