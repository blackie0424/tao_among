<?php

use App\Contracts\RichMenuServiceInterface;
use Illuminate\Console\Command;
use Mockery\MockInterface;

beforeEach(function () {
    config(['line.channel_access_token' => 'test-token']);
});

it('建立與上傳雙選單但不設定全域預設或綁定所有使用者', function () {
    $service = Mockery::mock(RichMenuServiceInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('deleteAllMenus')->once();
        $mock->shouldReceive('create')->twice()->andReturn('viewer-menu-id', 'editor-menu-id');
        $mock->shouldReceive('uploadImage')->twice();
        $mock->shouldNotReceive('setDefault');
    });
    $this->app->instance(RichMenuServiceInterface::class, $service);

    $this->artisan('line:setup-rich-menu')
        ->expectsOutputToContain('兩種選單僅透過角色個別綁定')
        ->assertExitCode(Command::SUCCESS);
});
