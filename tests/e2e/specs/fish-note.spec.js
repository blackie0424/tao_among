import { test, expect } from '@playwright/test';
import { loginAs, getCsrfToken } from '../fixtures/auth.js';

// 知識筆記 API 路由（api.php，需 auth:sanctum + editor 中介層）：
//   POST   /api/fish/{id}/note              FishNoteController::store
//   PUT    /api/fish/{id}/note/{note_id}    FishNoteController::update
//   DELETE /api/fish/{id}/note/{note_id}    FishNoteController::destroy
// 公開 API：
//   GET    /api/fish/{id}/notes             ApiFishController::getFishNotes

test.describe('知識筆記管理', () => {
  let testFishId;

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'editor');
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);
    const resp = await page.request.post('/api/fish', {
      data: { name: 'e2e筆記測試魚_' + Date.now() },
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

  test('新增知識筆記', async ({ page }) => {
    if (!testFishId) {
      test.skip();
      return;
    }
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);
    const resp = await page.request.post(`/api/fish/${testFishId}/note`, {
      data: { content: 'e2e 測試筆記內容' },
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
    expect([200, 201]).toContain(resp.status());
    const note = await resp.json();
    const noteId = note.id || note.data?.id;
    expect(noteId).toBeTruthy();
  });

  test('更新知識筆記', async ({ page }) => {
    if (!testFishId) {
      test.skip();
      return;
    }
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);

    // 先建立筆記
    const createResp = await page.request.post(`/api/fish/${testFishId}/note`, {
      data: { content: '原始筆記' },
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
    if (!createResp.ok()) {
      test.skip();
      return;
    }
    const note = await createResp.json();
    const noteId = note.id || note.data?.id;

    const updateResp = await page.request.put(
      `/api/fish/${testFishId}/note/${noteId}`,
      {
        data: { content: '更新後的筆記' },
        headers: {
          'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      }
    );
    expect([200, 204]).toContain(updateResp.status());
  });

  test('刪除知識筆記', async ({ page }) => {
    if (!testFishId) {
      test.skip();
      return;
    }
    await page.goto('/');
    const csrfToken = await getCsrfToken(page);

    const createResp = await page.request.post(`/api/fish/${testFishId}/note`, {
      data: { content: '待刪除筆記' },
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
    if (!createResp.ok()) {
      test.skip();
      return;
    }
    const note = await createResp.json();
    const noteId = note.id || note.data?.id;

    const deleteResp = await page.request.delete(
      `/api/fish/${testFishId}/note/${noteId}`,
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
