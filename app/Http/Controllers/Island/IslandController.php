<?php

namespace App\Http\Controllers\Island;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureIslandCharacter;
use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\IslandCharacter;
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

    /** 走到某個部落：記住所在部落，列出全部的魚與這個部落的回答人數。 */
    public function tribe(Request $request, string $tribe): Response
    {
        abort_unless(in_array($tribe, config('fish_options.tribes'), true), 404);

        $character = $this->characterOf($request);
        $request->session()->put(self::LOCATION_SESSION_KEY, ['character_id' => $character->id, 'tribe' => $tribe]);

        $page = Fish::with('displayCaptureRecord')->orderBy('id')->paginate(self::FISH_PER_PAGE);
        $counts = FishTribeResponse::responderCountsInTribe($page->getCollection()->pluck('id')->all(), $tribe);

        return Inertia::render('Island/Tribe', [
            'character' => $this->characterProps($character),
            'tribe' => $tribe,
            'fish' => $page->getCollection()->map(fn (Fish $fish) => [
                'id' => $fish->id,
                'name' => $fish->name === FishTribeResponse::UNNAMED_FISH_NAME ? null : $fish->name,
                'image_url' => $fish->display_image_url,
                'responder_count' => $counts[$fish->id] ?? 0,
            ])->values(),
            'page' => $page->currentPage(),
            'has_more_pages' => $page->hasMorePages(),
            'empty_reason' => $this->emptyReason($page),
        ]);
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
