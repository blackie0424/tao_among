// Page Object Model for fish detail page
// 路由：GET /fish/{id}（web.php，FishController::getFish）
export class FishDetailPage {
  constructor(page) {
    this.page = page;
  }

  async goto(fishId) {
    await this.page.goto(`/fish/${fishId}`);
  }

  async getFishName() {
    return this.page.locator('h1, [data-testid="fish-name"]').first().textContent();
  }
}
