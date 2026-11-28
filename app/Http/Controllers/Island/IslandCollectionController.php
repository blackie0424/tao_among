<?php

namespace App\Http\Controllers\Island;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureIslandCharacter;
use App\Models\Fish;
use App\Models\IslandCharacter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** #232 魚島圖鑑：把海邊拿到、自己記過的魚收進圖鑑。「從海邊拿到」由畫面保證，伺服器只檢查自己部落有記錄。 */
class IslandCollectionController extends Controller
{
    private const NO_RECORD_MESSAGE = '先記下至少一題';

    public function store(Request $request, int $fish): RedirectResponse
    {
        $collected = Fish::findOrFail($fish);
        /** @var IslandCharacter $character */
        $character = $request->attributes->get(EnsureIslandCharacter::ATTRIBUTE);

        if (! $character->collect($collected)) {
            throw ValidationException::withMessages(['fish' => self::NO_RECORD_MESSAGE]);
        }

        return back();
    }
}
