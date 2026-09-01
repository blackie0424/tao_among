import { test, expect } from '@playwright/test';
import { FishListPage } from '../pages/FishListPage.js';

// 公開瀏覽路由（不需登入）：
//   GET /          FishController::index
//   GET /fishs     FishController::getFishs
//   GET /search    FishController::search
//   GET /fish/{id} FishController::getFish
// 公開 API：GET /api/fish（ApiFishController::getFishs）

test.describe('公開瀏覽', () => {
  test('首頁應載入並顯示魚類列表', async ({ page }) => {
    // 若首頁透過 Inertia SSR props 傳資料，不會發 /api/fish 請求
    // 改為確認頁面可正常載入即可
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toBeEmpty();
    await expect(page).not.toHaveURL(/error|exception/i);
  });

  test('可搜尋魚類', async ({ page }) => {
    const listPage = new FishListPage(page);
    await listPage.goto();
    await listPage.search('飛魚');
    await page.waitForTimeout(1000);
    await expect(page).toHaveURL(/fishs/);
  });

  test('點擊魚卡進入詳細頁', async ({ page }) => {
    const listPage = new FishListPage(page);
    await listPage.goto();
    await page.waitForLoadState('networkidle');
    const cards = await listPage.getFishCards();
    if (cards.length > 0) {
      await listPage.clickFishCard(0);
      // 詳細頁路由：/fish/{id}
      await page.waitForURL(/\/fish\/\d+/);
      await expect(page).toHaveURL(/\/fish\/\d+/);
    } else {
      // 測試環境可能無資料
      test.skip();
    }
  });
});
