<?php

use App\Services\GoogleDocsService;

it('does not expose Docs client injection on constructor', function () {
    $constructor = new ReflectionMethod(GoogleDocsService::class, '__construct');

    $parameterTypes = collect($constructor->getParameters())
        ->map(fn (ReflectionParameter $parameter) => $parameter->getType()?->getName())
        ->filter()
        ->values()
        ->all();

    expect($parameterTypes)->toBe([
        App\Contracts\StorageServiceInterface::class,
        App\Services\GoogleDocs\FishCatalogLayoutBuilder::class,
    ]);
});
