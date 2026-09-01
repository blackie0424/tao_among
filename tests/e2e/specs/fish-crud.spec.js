import { test, expect } from '@playwright/test';
import { loginAs } from '../fixtures/auth.js';

// 魚類 API 路由（api.php，需 auth:sanctum + editor 中介層）：
//   POST   /api/fish        ApiFishController::store
//   PUT    /api/fish/{id}   ApiFishController::update
//   DELETE /api/fish/{id}   ApiFishController::destroy

test.describe('魚類 CRUD', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'editor');
  });

  test('透過 API 建立魚類', async ({ page }) => {
    // 先取得 XSRF-TOKEN cookie（Laravel Sanctum SPA 模式）
    await page.goto('/');
    const csrfToken = await page.evaluate(() =>
      document.cookie
        .split('; ')
        .find((r) => r.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
    );

    const response = await page.request.post('/api/fish', {
      data: { name: 'e2e測試魚_' + Date.now() },
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
    expect([200, 201]).toContain(response.status());
    const body = await response.json();
    const createdFishId = body.id || body.data?.id;
    expect(createdFishId).toBeTruthy();

    // 清理建立的魚
    await page.request.delete(`/api/fish/${createdFishId}`, {
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
      },
    });
  });

  test('透過 API 更新魚類', async ({ page }) => {
    await page.goto('/');
    const csrfToken = await page.evaluate(() =>
      document.cookie
        .split('; ')
        .find((r) => r.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
    );

    const createResp = await page.request.post('/api/fish', {
      data: { name: 'e2e更新測試魚_' + Date.now() },
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
    if (![200, 201].includes(createResp.status())) {
      test.skip();
      return;
    }
    const fish = await createResp.json();
    const fishId = fish.id || fish.data?.id;

    const updateResp = await page.request.put(`/api/fish/${fishId}`, {
      data: { name: 'e2e更新後_' + Date.now() },
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
    expect([200, 204]).toContain(updateResp.status());

    // 清理
    await page.request.delete(`/api/fish/${fishId}`, {
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
      },
    });
  });

  test('透過 API 刪除魚類', async ({ page }) => {
    await page.goto('/');
    const csrfToken = await page.evaluate(() =>
      document.cookie
        .split('; ')
        .find((r) => r.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
    );

    const createResp = await page.request.post('/api/fish', {
      data: { name: 'e2e刪除測試魚_' + Date.now() },
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
    if (![200, 201].includes(createResp.status())) {
      test.skip();
      return;
    }
    const fish = await createResp.json();
    const fishId = fish.id || fish.data?.id;

    const deleteResp = await page.request.delete(`/api/fish/${fishId}`, {
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
      },
    });
    expect([200, 204]).toContain(deleteResp.status());
  });
});
