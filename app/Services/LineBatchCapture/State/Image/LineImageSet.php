<?php

namespace App\Services\LineBatchCapture\State\Image;

class LineImageSet
{
    public function __construct(
        private readonly string $id,
        private readonly int $index,
        private readonly int $total,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function index(): int
    {
        return $this->index;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function isLast(): bool
    {
        return $this->total > 0 && $this->index === $this->total;
    }
}
