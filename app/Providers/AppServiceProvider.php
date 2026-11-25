<?php

namespace App\Providers;

use App\Contracts\CaptureSessionServiceInterface;
use App\Contracts\FishSearchServiceInterface;
use App\Contracts\FishServiceInterface;
use App\Contracts\FishStatisticsServiceInterface;
use App\Contracts\LineMessagingClientInterface;
use App\Contracts\LineUserServiceInterface;
use App\Contracts\RichMenuServiceInterface;
use App\Contracts\StorageServiceInterface;
use App\Models\IslandCharacter;
use App\Services\CaptureSessionService;
use App\Services\FishSearchService;
use App\Services\FishService;
use App\Services\FishStatisticsService;
use App\Services\Line\LineMessagingClient;
use App\Services\LineUserService;
use App\Services\RichMenuService;
use App\Services\S3StorageService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\IpUtils;

class AppServiceProvider extends ServiceProvider
{
    /** 魚島建角：同一個帳號或同一個訪客來源每分鐘可送出的次數 */
    private const ISLAND_CHARACTER_PER_MINUTE = 5;

    /** 魚島建角：所有沒登入的人合計每分鐘可送出的次數（擋偽造來源位址的灌水） */
    private const ISLAND_CHARACTER_GUESTS_PER_MINUTE = 60;

    /** 魚島作答：同一個帳號每分鐘可送出的次數（作答一定已有角色、已登入） */
    private const ISLAND_RESPONSE_PER_MINUTE = 30;

    /** Cloudflare 放真正訪客位址的標頭 */
    private const CLOUDFLARE_VISITOR_HEADER = 'CF-Connecting-IP';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StorageServiceInterface::class, S3StorageService::class);

        // LINE 角色機制服務綁定
        $this->app->bind(RichMenuServiceInterface::class, RichMenuService::class);
        $this->app->bind(LineUserServiceInterface::class, LineUserService::class);
        $this->app->bind(LineMessagingClientInterface::class, LineMessagingClient::class);

        // 魚類統計報告服務綁定
        $this->app->bind(FishStatisticsServiceInterface::class, FishStatisticsService::class);

        // 魚類服務 DIP 綁定
        $this->app->bind(FishServiceInterface::class, FishService::class);
        $this->app->bind(FishSearchServiceInterface::class, FishSearchService::class);

        // 捕獲資訊組合服務綁定
        $this->app->bind(CaptureSessionServiceInterface::class, CaptureSessionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $request = $this->app['request'];

        if ($this->app->environment(['local', 'testing']) && $request->getHost()) {
            $currentUrl = $request->getSchemeAndHttpHost();

            if ($currentUrl !== config('app.url')) {
                URL::useOrigin($currentUrl);
                URL::useAssetOrigin($currentUrl);
                config()->set('app.url', $currentUrl);
                config()->set('app.asset_url', $currentUrl);
            }
        }

        // 生產環境強制使用 HTTPS 協定
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureIslandRateLimiting();
    }

    /**
     * 魚島建角的限流：已登入以帳號計；沒登入以訪客來源位址計，另有全站合計上限。
     * 只用在這一條路由，不改全站對來源位址的判斷。
     * 魚島作答的限流：一律以帳號計。
     */
    private function configureIslandRateLimiting(): void
    {
        RateLimiter::for('island-character', function (Request $request) {
            if ($request->user() !== null) {
                return Limit::perMinute(self::ISLAND_CHARACTER_PER_MINUTE)->by('user:'.$request->user()->id);
            }

            return [
                Limit::perMinute(self::ISLAND_CHARACTER_GUESTS_PER_MINUTE)->by('guests'),
                Limit::perMinute(self::ISLAND_CHARACTER_PER_MINUTE)->by('visitor:'.$this->visitorAddress($request)),
            ];
        });

        // 限流在路由的角色檢查之前執行；沒有角色（含沒登入）的人不限流，接著會被導到角色設定頁
        RateLimiter::for('island-response', fn (Request $request) => IslandCharacter::of($request->user()) === null
            ? Limit::none()
            : Limit::perMinute(self::ISLAND_RESPONSE_PER_MINUTE)->by('user:'.$request->user()->id));
    }

    /**
     * 訪客的來源位址。網站在 Cloudflare 與主機轉送層後面，連線來源永遠是同一個內部位址，
     * 這時改取 Cloudflare 提供的訪客位址；連線不是來自內部位址（有人繞過 Cloudflare 直連）時不採信標頭。
     */
    private function visitorAddress(Request $request): string
    {
        $connection = (string) $request->server('REMOTE_ADDR');
        $viaCloudflare = $request->headers->get(self::CLOUDFLARE_VISITOR_HEADER);

        return IpUtils::isPrivateIp($connection) && filled($viaCloudflare) ? $viaCloudflare : $connection;
    }
}
