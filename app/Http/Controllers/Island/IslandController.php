<?php

namespace App\Http\Controllers\Island;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureIslandCharacter;
use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\IslandCharacter;
use App\Support\IslandSpot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IslandController extends Controller
{
    public const FISH_PER_PAGE = 20;

    /** session 裡記住的所在部落：['character_id' => 角色編號, 'tribe' => 部落代碼] */
    private const LOCATION_SESSION_KEY = 'island.location';

    public function home(Request $request): Response
    {
        $character = $this->characterOf($request);

        return Inertia::render('Island/Home', [
            'character' => $this->characterProps($character),
            'tribes' => config('fish_options.tribes'),
            'current_tribe' => $this->currentTribe($request, $character),
        ]);
    }

    /**
     * 走到某個部落＝走進這個部落的家屋（#189）：記住所在部落，帶部落進度與四個地點各自的 X/Y。
     * X＝這個地點在這個部落有結果的魚條數，Y＝這個地點未軟刪除的魚條數。
     */
    public function tribe(Request $request, string $tribe): Response
    {
        abort_unless(in_array($tribe, config('fish_options.tribes'), true), 404);

        $character = $this->characterOf($request);
        $request->session()->put(self::LOCATION_SESSION_KEY, ['character_id' => $character->id, 'tribe' => $tribe]);

        $idColumn = (new Fish)->qualifyColumn('id');
        [$hasResult, $bindings] = FishTribeResponse::islandHasResultSql($tribe, $idColumn);
        $counts = Fish::query()
            ->selectRaw("{$idColumn} % ? as remainder", [IslandSpot::COUNT])
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when {$hasResult} then 1 else 0 end) as with_result", $bindings)
            ->groupBy('remainder')
            ->get()
            ->keyBy('remainder');
        $spots = array_map(fn (array $spot) => [
            ...IslandSpot::props($spot),
            'with_result' => (int) ($counts[$spot['remainder']]->with_result ?? 0),
            'total' => (int) ($counts[$spot['remainder']]->total ?? 0),
        ], IslandSpot::all());

        return Inertia::render('Island/Tribe', [
            'character' => $this->characterProps($character),
            'tribe' => $tribe,
            'spots' => $spots,
            'progress' => $this->progress($spots),
        ]);
    }

    /**
     * 地點清單（#189）：只列這個地點的魚（編號除以 4 的餘數），每頁 20 條。
     * 排序：這個部落有結果的題數多的在前（2 題、1 題、0 題），同組回答筆數多的在前，再依編號。
     */
    public function spot(Request $request, string $tribe, string $spot): Response
    {
        abort_unless(in_array($tribe, config('fish_options.tribes'), true), 404);
        $spotDefinition = IslandSpot::find($spot);
        abort_if($spotDefinition === null, 404);

        $idColumn = (new Fish)->qualifyColumn('id');
        [$hasResult, $hasResultBindings] = FishTribeResponse::islandHasResultSql($tribe, $idColumn);
        $order = FishTribeResponse::islandSpotOrderSql($tribe, $idColumn);
        $page = Fish::with('displayCaptureRecord')
            ->select('*')
            ->selectRaw("{$hasResult} as has_island_result", $hasResultBindings)
            ->selectRaw("{$order['has_food_category'][0]} as island_has_food_category", $order['has_food_category'][1])
            ->selectRaw("{$order['has_processing_method'][0]} as island_has_processing_method", $order['has_processing_method'][1])
            ->selectRaw("{$order['answered'][0]} as island_answered", $order['answered'][1])
            ->selectRaw("{$order['response_count'][0]} as island_response_count", $order['response_count'][1])
            ->whereRaw("{$idColumn} % ? = ?", [IslandSpot::COUNT, $spotDefinition['remainder']])
            ->orderByDesc('island_answered')
            ->orderByDesc('island_response_count')
            ->orderBy('id')
            ->paginate(self::FISH_PER_PAGE);

        return Inertia::render('Island/Spot', [
            'character' => $this->characterProps($this->characterOf($request)),
            'tribe' => $tribe,
            'spot' => IslandSpot::props($spotDefinition),
            ...$this->fishCardPageProps($page),
        ]);
    }

    /** 地點清單的資料：魚卡（有結果的帶各部落結果、要問的那一題）、選項與部落清單、頁碼與空清單原因。 */
    private function fishCardPageProps(LengthAwarePaginator $page): array
    {
        $counts = FishTribeResponse::islandResultCounts($page->getCollection()->pluck('id')->all());

        return [
            'tribes' => config('fish_options.tribes'),
            'result_options' => FishTribeResponse::islandResultOptions(),
            'unknown_processing_method' => FishTribeResponse::unknownProcessingMethod(),
            'fish' => $page->getCollection()->map(fn (Fish $fish) => [
                'id' => $fish->id,
                'name' => $fish->name === FishTribeResponse::UNNAMED_FISH_NAME ? null : $fish->name,
                'image_url' => $fish->display_image_url,
                'results' => $fish->has_island_result ? $counts[$fish->id] : null,
                'ask' => FishTribeResponse::islandQuestionToAsk($fish->name, (bool) $fish->island_has_food_category, (bool) $fish->island_has_processing_method),
            ])->values(),
            'page' => $page->currentPage(),
            'has_more_pages' => $page->hasMorePages(),
            'empty_reason' => $this->emptyReason($page),
        ];
    }

    /**
     * 部落進度＝四個地點加總（每條魚剛好在一個地點）。
     *
     * @return array{with_result: int, total: int}|null 這個部落有結果的魚條數／全部魚條數；沒有魚時為 null
     */
    private function progress(array $spots): ?array
    {
        $total = array_sum(array_column($spots, 'total'));

        return $total === 0 ? null : ['with_result' => array_sum(array_column($spots, 'with_result')), 'total' => $total];
    }

    public function character(Request $request): Response|RedirectResponse
    {
        if (IslandCharacter::of($request->user()) !== null) {
            return redirect()->route('island.home');
        }

        return Inertia::render('Island/Character', [
            'tribes' => config('fish_options.tribes'),
            'name_max_length' => IslandCharacter::NAME_MAX_LENGTH,
        ]);
    }

    /**
     * 第一版不能改角色：已有角色的人送出時直接導回，不驗證、不修改。
     * 沒登入的人每次送出都建一個新的訪客帳號，不去重（連按由畫面的送出鈕停用處理）。
     */
    public function storeCharacter(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (IslandCharacter::of($user) !== null) {
            return redirect()->route('island.home');
        }

        $data = $this->validatedCharacterInput($request);
        if ($user !== null) {
            IslandCharacter::createFor($user, $data['name'], $data['tribe']);

            return redirect()->route('island.home');
        }

        Auth::login(IslandCharacter::createGuest($data['name'], $data['tribe']), remember: true);
        $request->session()->regenerate();

        return redirect()->route('island.home');
    }

    private function characterOf(Request $request): IslandCharacter
    {
        return $request->attributes->get(EnsureIslandCharacter::ATTRIBUTE);
    }

    private function characterProps(IslandCharacter $character): array
    {
        return ['name' => $character->name, 'tribe' => $character->tribe];
    }

    /** session 記的所在部落屬於目前這個角色才用；沒有或屬於別的角色（換帳號）時回到角色自己的部落。 */
    private function currentTribe(Request $request, IslandCharacter $character): string
    {
        $location = $request->session()->get(self::LOCATION_SESSION_KEY);

        return ($location['character_id'] ?? null) === $character->id ? $location['tribe'] : $character->tribe;
    }

    /** 清單為空的原因：沒有可顯示的魚（含全部軟刪除）優先於頁碼超過最後一頁。 */
    private function emptyReason(LengthAwarePaginator $page): ?string
    {
        if ($page->total() === 0) {
            return 'no_fish';
        }

        return $page->isEmpty() ? 'no_more' : null;
    }

    /** 名字去前後空白（含全形空白）後驗證，規則同「大家怎麼說」的叫法。 */
    private function validatedCharacterInput(Request $request): array
    {
        $name = $request->input('name');
        $name = is_string($name) ? preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $name) : $name;

        return validator(['name' => $name, 'tribe' => $request->input('tribe')], [
            'name' => ['required', 'string', 'max:'.IslandCharacter::NAME_MAX_LENGTH],
            'tribe' => ['required', Rule::in(config('fish_options.tribes'))],
        ], [
            'name.required' => '請填名字',
            'name.max' => '名字最多 '.IslandCharacter::NAME_MAX_LENGTH.' 個字',
            'tribe.required' => '請選部落',
            'tribe.in' => '請選部落',
        ])->validate();
    }
}
