<?php

namespace App\Console\Commands;

use App\Models\Fish;
use App\Services\GoogleDocsService;
use Illuminate\Console\Command;

class ExportFishToGoogleDocs extends Command
{
    protected $signature = 'fish:export-to-docs';

    protected $description = '將所有魚類資料匯出至 Google Docs 文件（每次覆蓋）';

    public function handle(): int
    {
        $docId = config('services.google.docs_document_id');
        if (empty($docId)) {
            $this->error('請在 .env 設定 GOOGLE_DOCS_DOCUMENT_ID');
            return self::FAILURE;
        }

        $credentialsPath = storage_path(
            config('services.google.credentials_path', 'app/google-credentials.json')
        );
        if (!file_exists($credentialsPath)) {
            $this->error("找不到 Google 憑證檔案：{$credentialsPath}");
            return self::FAILURE;
        }

        $fishes = Fish::with([
            'tribalClassifications' => fn ($q) => $q->whereIn('tribe', ['imowrod', 'iraraley']),
            'captureRecords' => fn ($q) => $q->latest('capture_date'),
        ])->orderBy('name')->get();

        $this->info("正在匯出 {$fishes->count()} 筆魚類資料...");

        try {
            $service = new GoogleDocsService();
            $service->exportFishes($docId, $fishes);
        } catch (\Exception $e) {
            $this->error('匯出失敗：' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('匯出完成！');
        return self::SUCCESS;
    }
}
