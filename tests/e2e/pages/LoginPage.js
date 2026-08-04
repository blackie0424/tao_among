// Page Object Model for login page
// 路由：GET /login（web.php 定義，AuthController::create）
export class LoginPage {
  constructor(page) {
    this.page = page;
  }

  async goto() {
    await this.page.goto('/login');
  }

  async login(email, password) {
    await this.page.getByLabel('Email').fill(email);
    await this.page.getByLabel('Password').fill(password);
    await this.page.getByRole('button', { name: /登入|Login/i }).click();
  }

  async expectLoginSuccess() {
    // 登入後可能跳轉到 /、/fishs 或 /dashboard（視角色而定）
    await this.page.waitForURL(/\/(dashboard|fishs|fish|$)/, { timeout: 10000 });
  }
}
