<?php

use App\Services\PayloadFieldFilter;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

it('recursively removes restricted keys across supported payload shapes', function (mixed $payload, mixed $expected) {
    expect(app(PayloadFieldFilter::class)->remove($payload, ['location']))->toBe($expected);
})->with([
    'collection' => [
        new Collection(['location' => 'secret', 'name' => 'fish']),
        ['name' => 'fish'],
    ],
    'custom Arrayable' => [
        new class implements Arrayable
        {
            public function toArray(): array
            {
                return ['location' => 'secret', 'nested' => ['location' => 'hidden', 'name' => 'fish']];
            }
        },
        ['nested' => ['name' => 'fish']],
    ],
    'nested array' => [
        ['location' => 'secret', 'nested' => [['location' => 'hidden', 'name' => 'fish']]],
        ['nested' => [['name' => 'fish']]],
    ],
    'scalar' => ['plain text', 'plain text'],
    'null' => [null, null],
    'empty array' => [[], []],
]);
