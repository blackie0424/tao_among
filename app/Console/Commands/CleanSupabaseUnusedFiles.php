<?php

namespace App\Console\Commands;

use App\Contracts\StorageServiceInterface;
use Illuminate\Console\Command;

class CleanSupabaseUnusedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-supabase-unused-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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

        $this->info('Supabase unused files cleaned.');
    }
}
