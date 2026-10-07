<?php

namespace App\Services;

use Illuminate\Contracts\Support\Arrayable;

class PayloadFieldFilter
{
    /** @param array<int, string> $restrictedKeys */
    public function remove(mixed $payload, array $restrictedKeys): mixed
    {
        if ($payload instanceof Arrayable) {
            $payload = $payload->toArray();
        }

        if (! is_array($payload)) {
            return $payload;
        }

        foreach ($restrictedKeys as $key) {
            unset($payload[$key]);
        }

        foreach ($payload as $key => $value) {
            $payload[$key] = $this->remove($value, $restrictedKeys);
        }

        return $payload;
    }
}
