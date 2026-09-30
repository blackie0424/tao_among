<?php

namespace App\Console\Commands;

use App\Contracts\RichMenuServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClearDefaultRichMenuCommand extends Command
{
    protected $signature = 'line:clear-default-rich-menu
                            {--force : 不顯示確認提示，直接取消全域預設}';

    protected $description = '取消 LINE 全域預設圖文選單，保留使用者的個別角色綁定';

    public function __construct(
        protected RichMenuServiceInterface $richMenuService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->option('force')
            && ! $this->confirm('確定要取消 LINE 全域預設圖文選單嗎？')) {
            $this->info('已取消操作，LINE 全域預設圖文選單未變更。');

            return Command::SUCCESS;
        }

        try {
            $currentMenuId = $this->richMenuService->getDefaultRichMenuId();

            if ($currentMenuId === null) {
                $this->info('ℹ️  目前沒有設定 Messaging API 全域預設圖文選單，未執行取消。');

                return Command::SUCCESS;
            }

            $this->info("目前的 Messaging API 全域預設：{$currentMenuId}");
            $this->richMenuService->clearDefault();

            $remainingMenuId = $this->richMenuService->getDefaultRichMenuId();
            if ($remainingMenuId !== null) {
                $this->error("❌ 取消後仍查到全域預設：{$remainingMenuId}");

                return Command::FAILURE;
            }

            $this->info('✅ 已取消 LINE 全域預設圖文選單，個別角色綁定不受影響。');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ 取消 LINE 全域預設圖文選單失敗：'.$e->getMessage());
            Log::error('ClearDefaultRichMenuCommand failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }
}
