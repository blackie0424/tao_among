<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Fish;
use Illuminate\Support\Facades\Http;

class CheckFishWebp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fish:check-webp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '定期檢查所有 fish 物件的 webp 檔案是否存在，並更新 has_webp 欄位';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $storageUrl = env('SUPABASE_STORAGE_URL');
        $bucket = env('SUPABASE_BUCKET');
        $apiKey = env('SUPABASE_SERVICE_ROLE_KEY');

        Fish::chunk(50, function ($fishes) use ($storageUrl, $bucket, $apiKey) {
            foreach ($fishes as $fish) {
                if (!$fish->image) {
                    continue;
                }
                $baseName = pathinfo($fish->image, PATHINFO_FILENAME);
                $webpPath = "webp/{$baseName}.webp";
                $url = "{$storageUrl}/object/{$bucket}/{$webpPath}";
                $response = Http::withHeaders([
                    'apikey' => $apiKey,
                    'Authorization' => "Bearer {$apiKey}",
                ])->head($url);

                $hasWebp = $response->status() === 200;
                if ($fish->has_webp !== $hasWebp) {
                    $fish->has_webp = $hasWebp;
                    $fish->save();
                }
            }
        });

        $this->info('webp 狀態檢查完成');
    }
}
