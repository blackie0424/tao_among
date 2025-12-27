<?php

namespace App\Providers;

use App\Contracts\StorageServiceInterface;
use App\Services\S3StorageService;
use App\Services\SupabaseStorageService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 根據設定檔動態綁定儲存服務
        $this->app->bind(StorageServiceInterface::class, function ($app) {
            $driver = config('storage.default', 'supabase');

            return match ($driver) {
                's3' => new S3StorageService(),
                'supabase' => new SupabaseStorageService(),
                default => throw new \InvalidArgumentException("Unsupported storage driver: {$driver}")
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
