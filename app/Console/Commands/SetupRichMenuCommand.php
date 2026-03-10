<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class SetupRichMenuCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'line:setup-rich-menu
                            {--image= : 圖文選單圖片路徑（預設使用 storage/app/rich_menu.jpg）}
                            {--dry-run : 只顯示設定內容，不實際建立}';

    /**
     * The console command description.
     */
    protected $description = '建立 LINE 圖文選單（Rich Menu），定義 6 個功能區塊並設為預設';

    protected Client $httpClient;
    protected string $accessToken;
    protected string $apiBase = 'https://api.line.me/v2/bot';

    public function handle(): int
    {
        $this->accessToken = config('line.channel_access_token');

        if (empty($this->accessToken)) {
            $this->error('LINE Channel Access Token 未設定，請檢查 .env 的 LINE_CHANNEL_ACCESS_TOKEN');
            return Command::FAILURE;
        }

        $this->httpClient = new Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
        ]);

        // 定義圖文選單結構
        $richMenuData = $this->buildRichMenuData();

        if ($this->option('dry-run')) {
            $this->info('=== Dry Run 模式：以下是圖文選單設定 ===');
            $this->line(json_encode($richMenuData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $this->info('🚀 開始建立 LINE 圖文選單...');

        try {
            // 步驟 1：刪除現有的預設圖文選單（避免衝突）
            $this->deleteExistingDefaultRichMenu();

            // 步驟 2：建立圖文選單，取得 richMenuId
            $richMenuId = $this->createRichMenu($richMenuData);
            $this->info("✅ 建立圖文選單成功，ID: {$richMenuId}");

            // 步驟 3：上傳圖文選單圖片
            $imagePath = $this->option('image') ?? storage_path('app/rich_menu.jpg');
            if (!file_exists($imagePath)) {
                $this->warn("⚠️  找不到圖片檔案：{$imagePath}");
                $this->warn('   請執行：php artisan line:setup-rich-menu --image=/path/to/your/image.jpg');
                $this->warn('   或手動上傳圖片到 LINE Developers Console');
                return Command::FAILURE;
            } 
            
            $this->uploadRichMenuImage($richMenuId, $imagePath);
            $this->info('✅ 圖片上傳成功');

            // 步驟 4：設定為預設圖文選單
            $this->setDefaultRichMenu($richMenuId);
            $this->info('✅ 已設定為預設圖文選單');

            $this->info('');
            $this->info('🎉 LINE 圖文選單建立完成！');
            $this->info("   Rich Menu ID: {$richMenuId}");
            $this->info('   現在開啟 LINE Bot 對話應該可以看到圖文選單');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ 建立圖文選單失敗：' . $e->getMessage());
            Log::error('SetupRichMenuCommand failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return Command::FAILURE;
        }
    }

    /**
     * 定義圖文選單資料結構
     * 
     * 版面：1200px × 810px，3 欄 × 2 列
     * A(oyod) | B(rahet)   | C(iraraley)
     * D(imowrod) | E(random) | F(clue)
     */
    protected function buildRichMenuData(): array
    {
        return [
            'size' => [
                'width' => 1200,
                'height' => 810,
            ],
            'selected' => true,
            'name' => '魚類資料瀏覽',
            'chatBarText' => '瀏覽魚類資料 🐟',
            'areas' => [
                // A: 左上 - Oyod 類魚（food_category 篩選）
                [
                    'bounds' => ['x' => 0, 'y' => 0, 'width' => 400, 'height' => 405],
                    'action' => [
                        'type' => 'postback',
                        'label' => 'Oyod 類魚',
                        'data' => 'action=browse_oyod',
                        'displayText' => '瀏覽 Oyod 類魚 🐟',
                    ],
                ],
                // B: 中上 - Rahet 類魚（food_category 篩選）
                [
                    'bounds' => ['x' => 400, 'y' => 0, 'width' => 400, 'height' => 405],
                    'action' => [
                        'type' => 'postback',
                        'label' => 'Rahet 類魚',
                        'data' => 'action=browse_rahet',
                        'displayText' => '瀏覽 Rahet 類魚 🐠',
                    ],
                ],
                // C: 右上 - Iraraley 部落（tribe 篩選）
                [
                    'bounds' => ['x' => 800, 'y' => 0, 'width' => 400, 'height' => 405],
                    'action' => [
                        'type' => 'postback',
                        'label' => 'Iraraley 部落',
                        'data' => 'action=browse_iraraley',
                        'displayText' => '瀏覽 Iraraley 部落魚類 🏘️',
                    ],
                ],
                // D: 左下 - Imowrod 部落（tribe 篩選）
                [
                    'bounds' => ['x' => 0, 'y' => 405, 'width' => 400, 'height' => 405],
                    'action' => [
                        'type' => 'postback',
                        'label' => 'Imowrod 部落',
                        'data' => 'action=browse_imowrod',
                        'displayText' => '瀏覽 Imowrod 部落魚類 🏡',
                    ],
                ],
                // E: 中下 - 隨機瀏覽
                [
                    'bounds' => ['x' => 400, 'y' => 405, 'width' => 400, 'height' => 405],
                    'action' => [
                        'type' => 'postback',
                        'label' => '隨機瀏覽',
                        'data' => 'action=random_browse',
                        'displayText' => '隨機探索魚類 🎲',
                    ],
                ],
                // F: 右下 - 提供線索（隨機「我不知道」魚）
                [
                    'bounds' => ['x' => 800, 'y' => 405, 'width' => 400, 'height' => 405],
                    'action' => [
                        'type' => 'postback',
                        'label' => '提供線索',
                        'data' => 'action=provide_clue',
                        'displayText' => '協助命名未知魚類 💡',
                    ],
                ],
            ],
        ];
    }

    /**
     * 刪除現有的預設圖文選單
     */
    protected function deleteExistingDefaultRichMenu(): void
    {
        try {
            $response = $this->httpClient->get("{$this->apiBase}/richmenu/default");
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!empty($data['richMenuId'])) {
                $existingId = $data['richMenuId'];
                $this->line("  找到現有預設圖文選單：{$existingId}，正在移除...");
                
                $this->httpClient->delete("{$this->apiBase}/richmenu/default");
                $this->httpClient->delete("{$this->apiBase}/richmenu/{$existingId}");
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // 404 表示沒有預設圖文選單，忽略
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * 建立圖文選單，回傳 richMenuId
     */
    protected function createRichMenu(array $data): string
    {
        $response = $this->httpClient->post("{$this->apiBase}/richmenu", [
            'json' => $data,
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (empty($result['richMenuId'])) {
            throw new \Exception('LINE API 未回傳 richMenuId：' . json_encode($result));
        }

        return $result['richMenuId'];
    }

    /**
     * 上傳圖文選單圖片
     */
    protected function uploadRichMenuImage(string $richMenuId, string $imagePath): void
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/jpeg',
        };

        $imageClient = new Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => $contentType,
            ],
        ]);

        $imageClient->post("https://api-data.line.me/v2/bot/richmenu/{$richMenuId}/content", [
            'body' => fopen($imagePath, 'r'),
        ]);
    }

    /**
     * 設定為預設圖文選單
     */
    protected function setDefaultRichMenu(string $richMenuId): void
    {
        $this->httpClient->post("{$this->apiBase}/user/all/richmenu/{$richMenuId}");
    }
}
