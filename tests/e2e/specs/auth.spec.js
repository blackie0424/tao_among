import { test, expect } from '@playwright/test';
import { LoginPage } from '../pages/LoginPage.js';

// 認證路由（web.php）：
//   GET  /login   AuthController::create
//   POST /login   AuthController::store
//   POST /logout  AuthController::destroy
//   GET  /dashboard 需要 auth + admin 中介層

test.describe('認證流程', () => {
  test('未登入訪問 /dashboard 應被導向登入頁', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/login/);
  });

  test('登入成功後跳轉', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.login('admin@test.com', 'password');
    // admin 登入後預期跳轉到 / 或 /dashboard
    await page.waitForURL(/\/(dashboard|fishs|fish|$)/, { timeout: 10000 }).catch(() => {});
    const url = page.url();
    expect(url).not.toContain('/login');
  });

  test('登出後無法訪問 /dashboard', async ({ page }) => {
    // 先登入
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.login('admin@test.com', 'password');
    await page.waitForURL(/\/(dashboard|fishs|fish|$)/, { timeout: 10000 }).catch(() => {});

    // 使用 XSRF-TOKEN cookie 呼叫 POST /logout（Inertia.js 用 cookie-based CSRF）
    const csrfToken = await page.evaluate(() =>
      document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))?.split('=')[1]
    );
    await page.request.post('/logout', {
      headers: {
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        Accept: 'application/json',
      },
    }).catch(() => {});

    // 重新載入後訪問 /dashboard 應被導向 /login
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/login/);
  });
});
