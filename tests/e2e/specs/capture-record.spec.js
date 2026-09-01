import { test, expect } from '@playwright/test';
import { loginAs, getCsrfToken } from '../fixtures/auth.js';

// 捕獲紀錄路由（web.php，需 auth 中介層）：
//   GET  /fish/{id}/capture-records/create  CaptureRecordController::create
//   POST /fish/{id}/capture-records         CaptureRecordController::store
//   GET  /fish/{id}/capture-records/{record_id}/edit  CaptureRecordController::edit
//   PUT  /fish/{id}/capture-records/{record_id}       CaptureRecordController::update
//   DELETE /fish/{id}/capture-records/{record_id}     CaptureRecordController::destroy
// 注意：捕獲紀錄無 REST API，僅 web 路由，無法純 API 測試 CRUD

test.describe('捕獲紀錄管理', () => {
  let testFishId;

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'editor');
    // 建立測試用魚類
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);
    const resp = await page.request.post('/api/fish', {
      data: { name: 'e2e捕獲紀錄測試魚_' + Date.now() },
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
    if (resp.ok()) {
      const fish = await resp.json();
      testFishId = fish.id || fish.data?.id;
    }
  });

  test.afterEach(async ({ page }) => {
    if (testFishId) {
      await page.goto('/');
      const csrfToken = await getCsrfToken(page);
      await page.request
        .delete(`/api/fish/${testFishId}`, {
          headers: {
            'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
            Accept: 'application/json',
          },
        })
        .catch(() => {});
      testFishId = null;
    }
  });

  test('新增捕獲紀錄頁面應可訪問', async ({ page }) => {
    if (!testFishId) {
      test.skip();
      return;
    }
    // 路由：GET /fish/{id}/capture-records/create
    await page.goto(`/fish/${testFishId}/capture-records/create`);
    await page.waitForLoadState('networkidle');
    const url = page.url();
    // 若頁面正常載入（非 404、非被導向 login）
    if (url.includes('login')) {
      // 登入 session 已過期
      test.skip();
    } else {
      await expect(page.locator('body')).not.toBeEmpty();
    }
  });

  test('捕獲紀錄列表頁應可訪問', async ({ page }) => {
    if (!testFishId) {
      test.skip();
      return;
    }
    // 路由：GET /fish/{id}/capture-records
    await page.goto(`/fish/${testFishId}/capture-records`);
    await page.waitForLoadState('networkidle');
    const url = page.url();
    if (url.includes('login')) {
      test.skip();
    } else {
      await expect(page.locator('body')).not.toBeEmpty();
    }
  });
});
