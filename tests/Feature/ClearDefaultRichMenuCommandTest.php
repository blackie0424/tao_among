<?php

use App\Contracts\RichMenuServiceInterface;
use Illuminate\Console\Command;

it('未確認時不取消全域預設', function () {
    $service = Mockery::mock(RichMenuServiceInterface::class);
    $service->shouldNotReceive('clearDefault');
    $this->app->instance(RichMenuServiceInterface::class, $service);

    $this->artisan('line:clear-default-rich-menu')
        ->expectsConfirmation('確定要取消 LINE 全域預設圖文選單嗎？', 'no')
        ->expectsOutput('已取消操作，LINE 全域預設圖文選單未變更。')
        ->assertExitCode(Command::SUCCESS);
});

it('可用 force 明確取消全域預設', function () {
    $service = Mockery::mock(RichMenuServiceInterface::class);
    $service->shouldReceive('clearDefault')->once();
    $this->app->instance(RichMenuServiceInterface::class, $service);

    $this->artisan('line:clear-default-rich-menu', ['--force' => true])
        ->expectsOutput('✅ 已取消 LINE 全域預設圖文選單，個別角色綁定不受影響。')
        ->assertExitCode(Command::SUCCESS);
});

it('取消全域預設失敗時回傳失敗狀態', function () {
    $service = Mockery::mock(RichMenuServiceInterface::class);
    $service->shouldReceive('clearDefault')->once()->andThrow(new RuntimeException('LINE API error'));
    $this->app->instance(RichMenuServiceInterface::class, $service);

    $this->artisan('line:clear-default-rich-menu', ['--force' => true])
        ->expectsOutputToContain('取消 LINE 全域預設圖文選單失敗')
        ->assertExitCode(Command::FAILURE);
});
