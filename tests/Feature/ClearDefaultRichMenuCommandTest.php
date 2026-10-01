<?php

use App\Contracts\RichMenuServiceInterface;
use Illuminate\Console\Command;

it('未確認時不查詢或取消全域預設', function () {
    $service = Mockery::mock(RichMenuServiceInterface::class);
    $service->shouldNotReceive('getDefaultRichMenuId');
    $service->shouldNotReceive('clearDefault');
    $this->app->instance(RichMenuServiceInterface::class, $service);

    $this->artisan('line:clear-default-rich-menu')
        ->expectsConfirmation('確定要取消 LINE 全域預設圖文選單嗎？', 'no')
        ->expectsOutput('已取消操作，LINE 全域預設圖文選單未變更。')
        ->assertExitCode(Command::SUCCESS);
});

it('取消前後查詢並在確認已無預設後才回報成功', function () {
    $service = Mockery::mock(RichMenuServiceInterface::class);
    $service->shouldReceive('getDefaultRichMenuId')
        ->twice()
        ->andReturn('richmenu-current', null);
    $service->shouldReceive('clearDefault')->once();
    $this->app->instance(RichMenuServiceInterface::class, $service);

    $this->artisan('line:clear-default-rich-menu', ['--force' => true])
        ->expectsOutputToContain('目前的 Messaging API 全域預設：richmenu-current')
        ->expectsOutput('✅ 已取消 LINE 全域預設圖文選單，個別角色綁定不受影響。')
        ->assertExitCode(Command::SUCCESS);
});

it('本來沒有 Messaging API 預設時不假報已取消', function () {
    $service = Mockery::mock(RichMenuServiceInterface::class);
    $service->shouldReceive('getDefaultRichMenuId')->once()->andReturn(null);
    $service->shouldNotReceive('clearDefault');
    $this->app->instance(RichMenuServiceInterface::class, $service);

    $this->artisan('line:clear-default-rich-menu', ['--force' => true])
        ->expectsOutput('ℹ️  目前沒有設定 Messaging API 全域預設圖文選單，未執行取消。')
        ->assertExitCode(Command::SUCCESS);
});

it('取消後仍有預設時明確回報失敗', function () {
    $service = Mockery::mock(RichMenuServiceInterface::class);
    $service->shouldReceive('getDefaultRichMenuId')
        ->twice()
        ->andReturn('richmenu-before', 'richmenu-still-present');
    $service->shouldReceive('clearDefault')->once();
    $this->app->instance(RichMenuServiceInterface::class, $service);

    $this->artisan('line:clear-default-rich-menu', ['--force' => true])
        ->expectsOutputToContain('取消後仍查到全域預設：richmenu-still-present')
        ->assertExitCode(Command::FAILURE);
});

it('OA Manager 設定預設時提示到 OA Manager 處理', function () {
    $service = Mockery::mock(RichMenuServiceInterface::class);
    $service->shouldReceive('getDefaultRichMenuId')
        ->once()
        ->andThrow(new RuntimeException('全域預設由 LINE Official Account Manager 設定，請至 OA Manager 取消。'));
    $service->shouldNotReceive('clearDefault');
    $this->app->instance(RichMenuServiceInterface::class, $service);

    $this->artisan('line:clear-default-rich-menu', ['--force' => true])
        ->expectsOutputToContain('請至 OA Manager 取消')
        ->assertExitCode(Command::FAILURE);
});

it('LINE API 失敗時回傳失敗狀態', function () {
    $service = Mockery::mock(RichMenuServiceInterface::class);
    $service->shouldReceive('getDefaultRichMenuId')->once()->andReturn('richmenu-current');
    $service->shouldReceive('clearDefault')->once()->andThrow(new RuntimeException('LINE API error'));
    $this->app->instance(RichMenuServiceInterface::class, $service);

    $this->artisan('line:clear-default-rich-menu', ['--force' => true])
        ->expectsOutputToContain('取消 LINE 全域預設圖文選單失敗')
        ->assertExitCode(Command::FAILURE);
});
