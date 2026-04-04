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
                            {--image= : 圖文選單圖片路徑（預設使用 public/images/line/rich_menu.png）}
                            {--dry-run : 只顯示設定內容，不實際建立}';

    /**
     * The console command description.
     */
    protected $description = '建立 LINE 圖文選單（Rich Menu），定義 3 個功能區塊並設為預設';

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
            $imagePath = $this->option('image') ?? public_path('images/line/rich_menu.png');
            if (!file_exists($imagePath)) {
                $this->warn("⚠️  找不到圖片檔案：{$imagePath}");
                $this->warn('   請執行：php artisan line:setup-rich-menu --image=/path/to/your/image.png');
                $this->warn('   或手動上傳圖片到 LINE Developers Console');
                return Command::FAILURE;
            }
            
            $this->uploadRichMenuImage($richMenuId, $imagePath);
            $this->info('✅ 圖片上傳成功');

            // 步驟 4：設定預設圖文選單（雙重綁定確保新舊使用者均生效）
            $this->setDefaultRichMenu($richMenuId);

            // 步驟 5：驗證 LINE 實際生效的選單 ID 是否一致
            $this->verifyActiveRichMenu($richMenuId);

            $this->info('');
            $this->info('🎉 LINE 圖文選單建立完成！');
            $this->info("   Rich Menu ID: {$richMenuId}");
            $this->info('   ⚠️  user/all 綁定為非同步作業，所有使用者約 1~2 分鐘後生效');
            Log::info('SetupRichMenuCommand completed', ['richMenuId' => $richMenuId]);

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
     * 版面：1200px × 405px，三等分（每格 400px）
     *
     * 左  (A)：新增魚類  x=0~400
     * 中  (B)：瀏覽魚類  x=400~800
     * 右  (C)：提供線索  x=800~1200
     */
    protected function buildRichMenuData(): array
    {
        return [
            'size' => [
                'width' => 1200,
                'height' => 405,
            ],
            'selected' => true,
            'name' => '魚類資料與回報',
            'chatBarText' => '選單 🐟',
            'areas' => [
                // A: 左 - 新增魚類（x=0, w=400）
                [
                    'bounds' => ['x' => 0, 'y' => 0, 'width' => 400, 'height' => 405],
                    'action' => [
                        'type' => 'postback',
                        'label' => '新增魚類',
                        'data' => 'action=start_create_fish',
                        'displayText' => '新增魚類 ➕',
                    ],
                ],
                // B: 中 - 瀏覽魚類（x=400, w=400）
                [
                    'bounds' => ['x' => 400, 'y' => 0, 'width' => 400, 'height' => 405],
                    'action' => [
                        'type' => 'postback',
                        'label' => '瀏覽魚類',
                        'data' => 'action=browse_tribes_menu',
                        'displayText' => '瀏覽魚類 🔍',
                    ],
                ],
                // C: 右 - 提供線索（x=800, w=400）
                [
                    'bounds' => ['x' => 800, 'y' => 0, 'width' => 400, 'height' => 405],
                    'action' => [
                        'type' => 'postback',
                        'label' => '提供線索',
                        'data' => 'action=provide_clue',
                        'displayText' => '提供線索 💡',
                    ],
                ],
            ],
        ];
    }

    /**
     * 刪除所有現有的圖文選單（包含殘留的舊版本）
     */
    protected function deleteExistingDefaultRichMenu(): void
    {
        // 先移除 default 設定（允許 404）
        try {
            $this->httpClient->delete("{$this->apiBase}/richmenu/default");
            $this->line('  已清除預設圖文選單設定');
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }

        // 列出並刪除所有 Rich Menu 物件（避免殘留舊版本造成衝突）
        try {
            $response = $this->httpClient->get("{$this->apiBase}/richmenu/list");
            $data = json_decode($response->getBody()->getContents(), true);
            $richMenus = $data['richmenus'] ?? [];

            if (empty($richMenus)) {
                $this->line('  目前沒有任何圖文選單，無需刪除');
                return;
            }

            foreach ($richMenus as $menu) {
                $menuId   = $menu['richMenuId'];
                $menuName = $menu['name'] ?? $menuId;
                try {
                    $this->httpClient->delete("{$this->apiBase}/richmenu/{$menuId}");
                    $this->line("  已刪除舊圖文選單：{$menuName} ($menuId)");
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    $this->warn("  無法刪除 {$menuId}：" . $e->getMessage());
                }
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
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
     *
     * 1. 嘗試設定 LINE 官方 default（對未曾互動的新使用者自動生效）
     * 2. 批量綁定 user/all（對所有已有紀錄的使用者立即覆蓋舊綁定）
     */
    protected function setDefaultRichMenu(string $richMenuId): void
    {
        // 嘗試設定 LINE 官方 default rich menu（允許 404，部分 Channel 不支援）
        try {
            $this->httpClient->post("{$this->apiBase}/richmenu/default", [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => json_encode(['richMenuId' => $richMenuId]),
            ]);
            $this->info('✅ 已設定官方 default 圖文選單（新使用者自動生效）');
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            $this->warn("  官方 default 端點回傳 {$status}，跳過（此 Channel 不支援）");
            $this->warn('  → 新使用者需至 LINE Official Account Manager 手動設定預設選單');
            Log::warning('SetupRichMenuCommand: POST /richmenu/default failed', [
                'status'     => $status,
                'richMenuId' => $richMenuId,
            ]);
        }

        // 批量覆蓋所有現有使用者的個別綁定
        $this->httpClient->post("{$this->apiBase}/user/all/richmenu/{$richMenuId}");
        $this->info('✅ 已批量綁定所有現有使用者（非同步，約 1~2 分鐘生效）');
    }

    /**
     * 驗證 LINE 上實際記錄的選單 ID 是否與剛建立的一致
     */
    protected function verifyActiveRichMenu(string $expectedId): void
    {
        try {
            $response = $this->httpClient->get("{$this->apiBase}/richmenu/list");
            $data     = json_decode($response->getBody()->getContents(), true);
            $menus    = $data['richmenus'] ?? [];

            if (count($menus) === 1 && $menus[0]['richMenuId'] === $expectedId) {
                $this->info("✅ 驗證通過：LINE 上只有一個圖文選單且 ID 吻合");
            } elseif (count($menus) > 1) {
                $ids = implode(', ', array_column($menus, 'richMenuId'));
                $this->warn("⚠️  LINE 上存在多個圖文選單：{$ids}");
            } else {
                $this->warn('⚠️  無法驗證圖文選單狀態，請至 LINE Developers Console 確認');
            }
        } catch (\Exception $e) {
            $this->warn('  驗證步驟失敗（不影響選單運作）：' . $e->getMessage());
        }
    }
}
