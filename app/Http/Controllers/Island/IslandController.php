<?php

namespace App\Http\Controllers\Island;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureIslandCharacter;
use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\IslandCharacter;
use App\Support\IslandNpcSpeech;
use App\Support\IslandSpot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
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
            // #232 手上的魚存在瀏覽器、依角色分開
            'character_id' => $character->id,
            'tribes' => config('fish_options.tribes'),
            'current_tribe' => $this->currentTribe($request, $character),
            'spots' => $this->householdSpotsByTribe(),
            ...$this->householdFishProps(),
            'collection' => $character->collection(),
            // #232 記錄視窗的三題：只在帶 record_fish 的 partial reload 才給
            'record_questions' => Inertia::optional(fn () => $this->recordQuestions($request, $character)),
            'name_max_length' => FishTribeResponse::NAME_MAX_LENGTH,
            'result_options' => FishTribeResponse::islandResultOptions(),
            'unknown_processing_method' => FishTribeResponse::unknownProcessingMethod(),
        ]);
    }

    /**
     * 3D 魚島的對話（#216）：打開 /island 時一次給。
     * island_fish：每條魚一次（編號、照片、魚名、各部落結果人數，給細節視窗）；
     * spot_fish：每個部落 12 個地點（順序同 spots），各列這個地點的魚（順序同地點清單）、家人說的話、有沒有可以看的說法。
     * fish_speech（#232）：同樣的家人說的話與有沒有說法，依部落、魚編號查（拿著魚問家人時用）。
     */
    private function householdFishProps(): array
    {
        $fishes = Fish::with('displayCaptureRecord')->orderBy('id')->get();
        $counts = FishTribeResponse::islandResultCounts($fishes->pluck('id')->all());
        $names = $fishes->mapWithKeys(fn (Fish $fish) => [$fish->id => $fish->name])->all();
        $spotFish = [];
        $fishSpeech = [];
        foreach (config('fish_options.tribes') as $tribe) {
            $spotFish[$tribe] = array_fill(0, IslandSpot::HOUSEHOLDS * IslandSpot::COUNT, []);
            $fishSpeech[$tribe] = [];
            foreach ($this->islandOrderQuery($tribe)->pluck('id') as $id) {
                [$categories, $methods] = $this->majorities($counts[$id] ?? null, $tribe);
                $fishSpeech[$tribe][$id] = [
                    'speech' => IslandNpcSpeech::say($this->displayName($names[$id]), $tribe, $categories, $methods, null, false),
                    'has_detail' => $categories !== [] || $methods !== [],
                ];
                $spotFish[$tribe][IslandSpot::householdIndexOf($id)][] = ['id' => $id, ...$fishSpeech[$tribe][$id]];
            }
        }

        return [
            'island_fish' => $fishes->map(fn (Fish $fish) => [
                'id' => $fish->id,
                'name' => $this->displayName($fish->name),
                'image_url' => $fish->display_image_url,
                'results' => $counts[$fish->id] ?? ['food_category' => [], 'processing_method' => []],
            ])->values(),
            'spot_fish' => $spotFish,
            'fish_speech' => $fishSpeech,
        ];
    }

    /**
     * #232 記錄視窗：角色自己部落這條魚三題的選項與自己的回答（不給結果，記錄時不看別人怎麼說）。
     * record_fish 不是數字、魚不存在或已軟刪除時為 null。
     */
    private function recordQuestions(Request $request, IslandCharacter $character): ?array
    {
        $fishId = filter_var($request->query('record_fish'), FILTER_VALIDATE_INT);
        $fish = $fishId === false ? null : Fish::find($fishId);
        if ($fish === null) {
            return null;
        }

        return array_map(
            fn (array $question) => ['key' => $question['key'], 'options' => $question['options'], 'my_answer' => $question['my_answer']],
            FishTribeResponse::islandQuestions($fish->id, $character->tribe, $fish->name, $character->user_id)
        );
    }

    /** 3D 魚島（#201）：6 個部落 × 12 個地點（3 戶 × 4 個地點）的名稱、稱呼與問好語，打開 /island 時一次給。 */
    private function householdSpotsByTribe(): array
    {
        return collect(config('fish_options.tribes'))
            ->mapWithKeys(fn (string $tribe) => [$tribe => IslandSpot::householdProps()])
            ->all();
    }

    /** 這個部落依「編號除以 4 的餘數」（地點）分組的魚條數（total）與有結果的條數（with_result），以餘數為鍵 */
    private function spotCounts(string $tribe): Collection
    {
        $idColumn = (new Fish)->qualifyColumn('id');
        [$hasResult, $bindings] = FishTribeResponse::islandHasResultSql($tribe, $idColumn);

        return Fish::query()
            ->selectRaw("{$idColumn} % ? as remainder", [IslandSpot::COUNT])
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when {$hasResult} then 1 else 0 end) as with_result", $bindings)
            ->groupBy('remainder')
            ->get()
            ->keyBy('remainder');
    }

    /**
     * 3D 魚島（#200）：角色走進另一個部落的格子時只記住所在部落，不帶任何頁面資料。
     * 不在清單裡的部落回 422，所在部落不變。
     */
    public function updateLocation(Request $request): HttpResponse
    {
        $validated = $request->validate([
            'tribe' => ['required', 'string', Rule::in(config('fish_options.tribes'))],
        ]);
        $this->rememberLocation($request, $this->characterOf($request), $validated['tribe']);

        return response()->noContent();
    }

    /**
     * 走到某個部落＝走進這個部落的家屋（#189）：記住所在部落，帶部落進度與四個地點各自的 X/Y。
     * X＝這個地點在這個部落有結果的魚條數，Y＝這個地點未軟刪除的魚條數。
     */
    public function tribe(Request $request, string $tribe): Response
    {
        abort_unless(in_array($tribe, config('fish_options.tribes'), true), 404);

        $character = $this->characterOf($request);
        $this->rememberLocation($request, $character, $tribe);

        $counts = $this->spotCounts($tribe);
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
     */
    public function spot(Request $request, string $tribe, string $spot): Response
    {
        $spotDefinition = $this->spotOrFail($tribe, $spot);
        $page = $this->spotFishQuery($tribe, $spotDefinition)->paginate(self::FISH_PER_PAGE);

        return Inertia::render('Island/Spot', [
            'character' => $this->characterProps($this->characterOf($request)),
            'tribe' => $tribe,
            'spot' => IslandSpot::props($spotDefinition),
            ...$this->fishCardPageProps($page),
        ]);
    }

    /**
     * 跟地點的 NPC 聊天（#189）：一次一條魚，第 n 條依地點清單的順序；n 不是 1～M 的整數時當成 1。
     * NPC 說這個部落的多數分類與處理方式，缺的那一題反過來問。
     */
    public function talk(Request $request, string $tribe, string $spot): Response
    {
        $spotDefinition = $this->spotOrFail($tribe, $spot);
        $query = $this->spotFishQuery($tribe, $spotDefinition);
        $total = (clone $query)->toBase()->getCountForPagination();
        $n = filter_var($request->query('n'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => max($total, 1)]]) ?: 1;
        $fish = $total === 0 ? null : $query->skip($n - 1)->first();

        return Inertia::render('Island/Talk', [
            'character' => $this->characterProps($this->characterOf($request)),
            'tribe' => $tribe,
            'spot' => IslandSpot::props($spotDefinition),
            'n' => $n,
            'total' => $total,
            'is_last' => $n === $total,
            ...($fish === null ? ['fish' => null, 'speech' => IslandNpcSpeech::NO_FISH] : $this->talkFishProps($fish, $tribe, $n === $total)),
            'tribes' => config('fish_options.tribes'),
            'result_options' => FishTribeResponse::islandResultOptions(),
            'unknown_processing_method' => FishTribeResponse::unknownProcessingMethod(),
        ]);
    }

    /** NPC 講的這條魚：照片、魚名、要問的那一題、細節視窗用的結果（至少一題有結果才帶），以及 NPC 說的話。 */
    private function talkFishProps(Fish $fish, string $tribe, bool $isLast): array
    {
        $counts = FishTribeResponse::islandResultCounts([$fish->id])[$fish->id] ?? null;
        [$categories, $methods] = $this->majorities($counts, $tribe);
        $name = $this->displayName($fish->name);
        $ask = FishTribeResponse::islandQuestionToAsk($fish->name, $categories !== [], $methods !== []);

        return [
            'fish' => [
                'id' => $fish->id,
                'name' => $name,
                'image_url' => $fish->display_image_url,
                'ask' => $ask,
                'results' => $categories === [] && $methods === [] ? null : $counts,
            ],
            'speech' => IslandNpcSpeech::say($name, $tribe, $categories, $methods, $ask, $isLast),
        ];
    }

    /**
     * NPC 要講的多數（#189）：這個部落分類、處理方式筆數最多的選項（平手全部列出）；處理方式不算「我不知道」。
     *
     * @param  array|null  $counts  islandResultCounts 裡這條魚的那一筆，沒有人答為 null
     * @return array{0: list<string>, 1: list<string>} [分類的多數, 處理方式的多數]，沒結果的那一題為空
     */
    private function majorities(?array $counts, string $tribe): array
    {
        $unknown = FishTribeResponse::unknownProcessingMethod();

        return [
            IslandNpcSpeech::leaders($counts['food_category'][$tribe] ?? []),
            IslandNpcSpeech::leaders(array_values(array_filter(
                $counts['processing_method'][$tribe] ?? [],
                fn (array $choice) => $choice['value'] !== $unknown,
            ))),
        ];
    }

    /** 畫面上的魚名：還沒有名字的魚為 null */
    private function displayName(?string $name): ?string
    {
        return $name === FishTribeResponse::UNNAMED_FISH_NAME ? null : $name;
    }

    /** @return array{code: string, name: string, npc: string, remainder: int} 部落或地點不在清單裡時 404 */
    private function spotOrFail(string $tribe, string $spot): array
    {
        abort_unless(in_array($tribe, config('fish_options.tribes'), true), 404);
        $spotDefinition = IslandSpot::find($spot);
        abort_if($spotDefinition === null, 404);

        return $spotDefinition;
    }

    /**
     * 這個地點的魚，依地點清單與 NPC 的順序：這個部落有結果的題數多的在前（2 題、1 題、0 題），
     * 同組回答筆數多的在前，再依編號。
     */
    private function spotFishQuery(string $tribe, array $spotDefinition): Builder
    {
        $idColumn = (new Fish)->qualifyColumn('id');
        [$hasResult, $hasResultBindings] = FishTribeResponse::islandHasResultSql($tribe, $idColumn);

        return $this->islandOrderQuery($tribe)
            ->with('displayCaptureRecord')
            ->selectRaw("{$hasResult} as has_island_result", $hasResultBindings)
            ->whereRaw("{$idColumn} % ? = ?", [IslandSpot::COUNT, $spotDefinition['remainder']]);
    }

    /** 全部的魚依地點清單與 NPC 的順序（見 spotFishQuery）；3D 魚島（#216）再依編號分到 12 個地點。 */
    private function islandOrderQuery(string $tribe): Builder
    {
        $idColumn = (new Fish)->qualifyColumn('id');
        $order = FishTribeResponse::islandSpotOrderSql($tribe, $idColumn);

        return Fish::query()
            ->select('*')
            ->selectRaw("{$order['has_food_category'][0]} as island_has_food_category", $order['has_food_category'][1])
            ->selectRaw("{$order['has_processing_method'][0]} as island_has_processing_method", $order['has_processing_method'][1])
            ->selectRaw("{$order['answered'][0]} as island_answered", $order['answered'][1])
            ->selectRaw("{$order['response_count'][0]} as island_response_count", $order['response_count'][1])
            ->orderByDesc('island_answered')
            ->orderByDesc('island_response_count')
            ->orderBy('id');
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
                'name' => $this->displayName($fish->name),
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
    private function rememberLocation(Request $request, IslandCharacter $character, string $tribe): void
    {
        $request->session()->put(self::LOCATION_SESSION_KEY, ['character_id' => $character->id, 'tribe' => $tribe]);
    }

    /**
     * 所在部落一定是清單裡的部落：session 記的（同一個角色）→ 角色自己的部落 → 清單的第一個。
     * 所以 3D 畫面的起點與畫面上寫的所在部落一定一致（#200）。
     */
    private function currentTribe(Request $request, IslandCharacter $character): string
    {
        $tribes = config('fish_options.tribes');
        $location = $request->session()->get(self::LOCATION_SESSION_KEY);
        $remembered = ($location['character_id'] ?? null) === $character->id ? $location['tribe'] : null;

        return collect([$remembered, $character->tribe])
            ->first(fn (?string $tribe) => in_array($tribe, $tribes, true), $tribes[0]);
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
