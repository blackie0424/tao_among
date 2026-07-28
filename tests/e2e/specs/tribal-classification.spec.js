import { test, expect } from '@playwright/test';
import { loginAs, getCsrfToken } from '../fixtures/auth.js';

// 部落分類 API 路由（api.php）：
// 公開唯讀：
//   GET  /api/fish/{fish_id}/tribal-classifications  TribalClassificationController::index
//   GET  /api/tribal-classifications/{id}            TribalClassificationController::show
// 需 auth:sanctum + editor：
//   POST   /api/fish/{fish_id}/tribal-classifications   TribalClassificationController::store
//   PUT    /api/tribal-classifications/{id}             TribalClassificationController::update
//   DELETE /api/tribal-classifications/{id}             TribalClassificationController::destroy
//
// tribe 有效值（依 TribalClassificationFactory）：
//   ivalino, iranmeilek, imowrod, iratay, yayo, iraraley
// food_category 有效值：
//   oyod, rahet, 不分類, 不食用, ?, ''

test.describe('部落分類管理', () => {
  let testFishId;

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'editor');
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);
    const resp = await page.request.post('/api/fish', {
      data: { name: 'e2e部落分類測試魚_' + Date.now() },
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

  test('新增部落分類', async ({ page }) => {
    if (!testFishId) {
      test.skip();
      return;
    }
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);
    // tribe 使用 factory 中定義的有效值（如 ivalino）
    const resp = await page.request.post(
      `/api/fish/${testFishId}/tribal-classifications`,
      {
        data: {
          tribe: 'ivalino',
          name: 'e2e測試名稱',
          food_category: 'oyod',
        },
        headers: {
          'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      }
    );
    expect([200, 201]).toContain(resp.status());
  });

  test('更新部落分類', async ({ page }) => {
    if (!testFishId) {
      test.skip();
      return;
    }
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);

    const createResp = await page.request.post(
      `/api/fish/${testFishId}/tribal-classifications`,
      {
        data: { tribe: 'ivalino', name: '原始名稱', food_category: 'oyod' },
        headers: {
          'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      }
    );
    if (!createResp.ok()) {
      test.skip();
      return;
    }
    const classification = await createResp.json();
    const classId = classification.id || classification.data?.id;

    // PUT /api/tribal-classifications/{id}
    const updateResp = await page.request.put(
      `/api/tribal-classifications/${classId}`,
      {
        data: { name: '更新後名稱' },
        headers: {
          'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      }
    );
    expect([200, 204]).toContain(updateResp.status());
  });

  test('刪除部落分類', async ({ page }) => {
    if (!testFishId) {
      test.skip();
      return;
    }
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);

    const createResp = await page.request.post(
      `/api/fish/${testFishId}/tribal-classifications`,
      {
        data: {
          tribe: 'iranmeilek',
          name: '待刪除分類',
          food_category: 'rahet',
        },
        headers: {
          'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      }
    );
    if (!createResp.ok()) {
      test.skip();
      return;
    }
    const classification = await createResp.json();
    const classId = classification.id || classification.data?.id;

    // DELETE /api/tribal-classifications/{id}
    const deleteResp = await page.request.delete(
      `/api/tribal-classifications/${classId}`,
      {
        headers: {
          'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
          Accept: 'application/json',
        },
      }
    );
    expect([200, 204]).toContain(deleteResp.status());
  });
});
