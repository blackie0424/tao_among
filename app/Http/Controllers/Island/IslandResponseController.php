<?php

namespace App\Http\Controllers\Island;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureIslandCharacter;
use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\IslandCharacter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** 魚島作答：一次送出一題，寫進 among 魚頁「大家怎麼說」的同一張表。 */
class IslandResponseController extends Controller
{
    private const BLANK_NAME_MESSAGE = '請填叫法，或按跳過';

    private const UNKNOWN_NAME_MESSAGE = '不知道的話請按跳過';

    /** 回答的部落一律取網址上的部落，不由表單傳。 */
    public function store(Request $request, string $tribe, int $id): RedirectResponse
    {
        abort_unless(in_array($tribe, config('fish_options.tribes'), true), 404);
        $fish = Fish::findOrFail($id);
        /** @var IslandCharacter $character */
        $character = $request->attributes->get(EnsureIslandCharacter::ATTRIBUTE);

        $data = $this->validatedAnswer($request);
        FishTribeResponse::submitQuestion(
            ['user_id' => $character->user_id, 'fish_id' => $fish->id, 'tribe' => $tribe],
            $data['question'],
            $data['value'],
            $character->tribe,
        );

        return back();
    }

    /** 叫法去前後空白（含全形空白，規則同 among 魚頁）後驗證；飲食分類與處理方式要在回答用的選項裡。 */
    private function validatedAnswer(Request $request): array
    {
        $question = $request->input('question');
        $value = $request->input('value');
        if ($question === 'name' && is_string($value)) {
            $value = preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $value);
        }

        $validator = validator(['question' => $question, 'value' => $value], [
            'question' => ['required', Rule::in(FishTribeResponse::ISLAND_QUESTIONS)],
            'value' => ['required', 'string', ...$this->valueRules($question)],
        ], [
            'question.required' => '請選題目',
            'question.in' => '請選題目',
            'value.required' => $question === 'name' ? self::BLANK_NAME_MESSAGE : '請選一個選項',
            'value.in' => '請選一個選項',
            'value.max' => '叫法最多 '.FishTribeResponse::NAME_MAX_LENGTH.' 字',
        ]);
        $validator->after(function ($validator) use ($question, $value) {
            if ($question === 'name' && is_string($value) && FishTribeResponse::sameSpelling($value, FishTribeResponse::UNNAMED_FISH_NAME)) {
                $validator->errors()->add('value', self::UNKNOWN_NAME_MESSAGE);
            }
        });

        return $validator->validate();
    }

    private function valueRules(mixed $question): array
    {
        return match ($question) {
            'name' => ['max:'.FishTribeResponse::NAME_MAX_LENGTH],
            'food_category' => [Rule::in(FishTribeResponse::selectableFoodCategories())],
            'processing_method' => [Rule::in(config('fish_options.response_processing_methods'))],
            default => [],
        };
    }
}
