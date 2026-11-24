<?php

namespace App\Http\Controllers\Island;

use App\Http\Controllers\Controller;
use App\Models\IslandCharacter;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    /**
     * 表單代碼的鎖最長保留秒數：要比一個請求能活的時間長（PHP 預設最長執行 30 秒），
     * 這樣鎖自己過期時，原本拿鎖的請求一定已經結束，不會有兩個請求同時建帳號。
     */
    private const FORM_TOKEN_LOCK_SECONDS = 60;

    /** 等另一個用同一代碼的請求結束的最長秒數；超過就請使用者再送一次，不建任何資料 */
    private const FORM_TOKEN_WAIT_SECONDS = 25;

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
        $expiresAt = now()->addMinutes(self::FORM_TOKEN_TTL_MINUTES);
        Cache::put($this->formTokenKey($token), ['expires_at' => $expiresAt->getTimestamp(), 'user_id' => null], $expiresAt);

        return $token;
    }

    /**
     * 同一個表單代碼只建一個訪客帳號：第一次送出建立帳號與角色並記下帳號，
     * 之後（含同時送出、等鎖的那一個）拿到同一個帳號。代碼的到期時間從開表單起算，用過也不延長。
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
                    if ($state['user_id'] !== null) {
                        return User::findOrFail($state['user_id']);
                    }

                    $user = DB::transaction(function () use ($data) {
                        $user = IslandCharacter::createGuestUser($data['name']);
                        IslandCharacter::createFor($user, $data['name'], $data['tribe']);

                        return $user;
                    });
                    Cache::put($key, [...$state, 'user_id' => $user->id], Carbon::createFromTimestamp($state['expires_at']));

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
