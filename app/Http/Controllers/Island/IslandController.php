<?php

namespace App\Http\Controllers\Island;

use App\Http\Controllers\Controller;
use App\Models\IslandCharacter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IslandController extends Controller
{
    public function home(Request $request): Response|RedirectResponse
    {
        $character = IslandCharacter::of($request->user());
        if ($character === null) {
            return redirect()->route('island.character');
        }

        return Inertia::render('Island/Home', [
            'character' => ['name' => $character->name, 'tribe' => $character->tribe],
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
