export async function getCsrfToken(page) {
  return page.evaluate(() =>
    document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))?.split('=')[1]
  );
}

// 登入路由：POST /login（web.php 定義）
// 登入成功後跳轉 /fishs 或 /dashboard（依 role 而定）
export async function loginAs(page, role = 'editor') {
  const credentials = {
    admin: { email: 'admin@test.com', password: 'password' },
    editor: { email: 'editor@test.com', password: 'password' },
  };
  const { email, password } = credentials[role];

  await page.goto('/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: /登入|Login/i }).click();
  // 登入後可能跳轉到 /、/fishs 或 /dashboard
  await page.waitForURL(/\/(dashboard|fishs|fish|$)/, { timeout: 10000 });
}
