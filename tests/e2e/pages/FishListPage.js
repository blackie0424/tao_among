// Page Object Model for fish list page
// 路由：GET /fishs（web.php，FishController::getFishs）
export class FishListPage {
  constructor(page) {
    this.page = page;
  }

  async goto() {
    await this.page.goto('/fishs');
  }

  async search(keyword) {
    const searchInput = this.page.getByPlaceholder(/搜尋|search/i).first();
    await searchInput.fill(keyword);
    await searchInput.press('Enter');
  }

  async getFishCards() {
    return this.page.locator('[data-testid="fish-card"], .fish-card').all();
  }

  async clickFishCard(index = 0) {
    const cards = await this.getFishCards();
    await cards[index].click();
  }
}
