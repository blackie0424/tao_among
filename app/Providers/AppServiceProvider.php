<?php

namespace App\Providers;

use App\Contracts\StorageServiceInterface;
use App\Services\SupabaseStorageService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 註冊儲存服務介面綁定
        $this->app->bind(StorageServiceInterface::class, SupabaseStorageService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
