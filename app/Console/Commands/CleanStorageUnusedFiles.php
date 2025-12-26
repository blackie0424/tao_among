<?php

namespace App\Console\Commands;

use App\Contracts\StorageServiceInterface;
use Illuminate\Console\Command;

class CleanStorageUnusedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:cleanup-unused-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清理軟刪除的檔案';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $unusedFishes = \App\Models\Fish::onlyTrashed()->get();

        foreach ($unusedFishes as $fish) {
            if ($fish->image) {
                app(StorageServiceInterface::class)->delete($fish->image);
            }
        }

        $this->info('Storage unused files cleaned.');
    }
}
