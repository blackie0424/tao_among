# Quickstart — Refactor FishController (TDD, web.php routes)

1. Run Feature tests for SSR routes (expect failures first time)

- ./vendor/bin/pest --filter FishControllerTest
  - Ignore `/prefix/api` tests (deprecated for this plan)

2. Add/Update Unit & Feature tests (Pest)

- tests/Unit/FishServiceTest.php — media URL rules (image default, audio null)
- tests/Feature/FishControllerTest.php — `/`, `/fishs`, `/search`, `/fish/{id}` basic props

3. Implement service extraction

- Move URL logic into `App/Services/FishService`
- Ensure callers only call `SupabaseStorageService::getUrl` with non-empty strings

4. Avoid N+1

- Use `with([...])` eager loading where needed, e.g. `audios` latest(1)

5. Logging in prod

- `.env`: `LOG_CHANNEL=errorlog`

6. Rerun tests until green

- ./vendor/bin/pest
