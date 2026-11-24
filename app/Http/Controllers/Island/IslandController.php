<?php

namespace App\Http\Controllers\Island;

use App\Http\Controllers\Controller;
use App\Models\IslandCharacter;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IslandController extends Controller
{
    private const FORM_TOKEN_TTL_MINUTES = 30;

    /** 還沒有人用這個表單代碼建過帳號 */
    private const FORM_TOKEN_UNUSED = 'unused';

    private const FORM_TOKEN_LOCK_SECONDS = 10;

    private const FORM_TOKEN_WAIT_SECONDS = 5;

    private const FORM_TOKEN_MESSAGE = '請再送出一次';

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
            'form_token' => $this->issueFormToken(),
        ]);
    }

    /** 第一版不能改角色：已有角色的人送出時直接導回，不驗證、不修改。 */
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

        Auth::login($this->guestForFormToken($request->input('form_token'), $data), remember: true);
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

    private function issueFormToken(): string
    {
        $token = Str::uuid()->toString();
        Cache::put($this->formTokenKey($token), self::FORM_TOKEN_UNUSED, now()->addMinutes(self::FORM_TOKEN_TTL_MINUTES));

        return $token;
    }

    /**
     * 同一個表單代碼只建一個訪客帳號：第一次送出建立帳號與角色並記下帳號，
     * 之後（含同時送出、等鎖的那一個）拿到同一個帳號。
     *
     * @throws ValidationException 代碼不存在、已過期，或等不到鎖
     */
    private function guestForFormToken(mixed $token, array $data): User
    {
        $key = $this->formTokenKey(is_string($token) ? $token : '');

        try {
            return Cache::lock($key.':lock', self::FORM_TOKEN_LOCK_SECONDS)
                ->block(self::FORM_TOKEN_WAIT_SECONDS, function () use ($key, $data) {
                    $state = Cache::get($key);
                    if ($state === null) {
                        throw $this->formTokenError();
                    }
                    if ($state !== self::FORM_TOKEN_UNUSED) {
                        return User::findOrFail($state);
                    }

                    $user = DB::transaction(function () use ($data) {
                        $user = IslandCharacter::createGuestUser($data['name']);
                        IslandCharacter::createFor($user, $data['name'], $data['tribe']);

                        return $user;
                    });
                    Cache::put($key, $user->id, now()->addMinutes(self::FORM_TOKEN_TTL_MINUTES));

                    return $user;
                });
        } catch (LockTimeoutException) {
            throw $this->formTokenError();
        }
    }

    private function formTokenKey(string $token): string
    {
        return 'island_form_token:'.$token;
    }

    private function formTokenError(): ValidationException
    {
        return ValidationException::withMessages(['form_token' => self::FORM_TOKEN_MESSAGE]);
    }
}
