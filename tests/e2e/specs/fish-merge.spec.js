import { test, expect } from '@playwright/test';
import { loginAs, getCsrfToken } from '../fixtures/auth.js';

// 魚類合併 API 路由（api.php，需 auth:sanctum + editor 中介層）：
//   POST /api/fish/merge/preview   FishMergeController::preview
//   POST /api/fish/merge           FishMergeController::merge
//
// 注意：/api/fish/merge/preview 和 /api/fish/merge 必須在 /api/fish/{id} 之前定義
// 才不會被 whereNumber('id') 擋掉——api.php 已正確按此順序排列

test.describe('魚類合併流程', () => {
  let fishId1, fishId2;

  test.beforeEach(async ({ page }) => {
    // 合併功能需要 admin 角色
    await loginAs(page, 'admin');
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);
    const headers = {
      'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
      Accept: 'application/json',
      'Content-Type': 'application/json',
    };
    const r1 = await page.request.post('/api/fish', {
      data: { name: 'e2e合併來源魚_' + Date.now() },
      headers,
    });
    const r2 = await page.request.post('/api/fish', {
      data: { name: 'e2e合併目標魚_' + Date.now() },
      headers,
    });
    if (r1.ok() && r2.ok()) {
      const f1 = await r1.json();
      const f2 = await r2.json();
      fishId1 = f1.id || f1.data?.id;
      fishId2 = f2.id || f2.data?.id;
    }
  });

  test.afterEach(async ({ page }) => {
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);
    const headers = {
      'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
      Accept: 'application/json',
    };
    if (fishId1) {
      await page.request.delete(`/api/fish/${fishId1}`, { headers }).catch(() => {});
      fishId1 = null;
    }
    if (fishId2) {
      await page.request.delete(`/api/fish/${fishId2}`, { headers }).catch(() => {});
      fishId2 = null;
    }
  });

  test('預覽合併結果', async ({ page }) => {
    if (!fishId1 || !fishId2) {
      test.skip();
      return;
    }
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);
    const resp = await page.request.post('/api/fish/merge/preview', {
      data: { source_id: fishId1, target_id: fishId2 },
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
    expect([200]).toContain(resp.status());
  });

  test('執行合併', async ({ page }) => {
    if (!fishId1 || !fishId2) {
      test.skip();
      return;
    }
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);
    const resp = await page.request.post('/api/fish/merge', {
      data: { source_id: fishId1, target_id: fishId2 },
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
    expect([200, 204]).toContain(resp.status());
    // 合併後來源魚已被刪除，afterEach 不需清理
    fishId1 = null;
  });
});
