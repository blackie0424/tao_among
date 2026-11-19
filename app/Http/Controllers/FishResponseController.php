<?php

namespace App\Http\Controllers;

use App\Models\Fish;
use App\Models\FishTribeResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FishResponseController extends Controller
{
    public function store(Request $request, int $id): RedirectResponse
    {
        $fish = Fish::findOrFail($id);
        $input = $this->normalizedInput($request);

        $validator = Validator::make($input, [
            'tribe' => ['required', Rule::in(config('fish_options.tribes'))],
            'name' => ['nullable', 'string', 'max:'.FishTribeResponse::NAME_MAX_LENGTH],
            'food_category' => ['nullable', Rule::in(FishTribeResponse::selectableFoodCategories())],
        ], [
            'name.max' => '叫法最多 '.FishTribeResponse::NAME_MAX_LENGTH.' 字',
        ]);
        $validator->after(function ($validator) use ($input) {
            if ($input['name'] === null && $input['food_category'] === null) {
                $validator->errors()->add('name', '叫法與飲食分類至少填一項');
            }
        });
        $data = $validator->validate();

        $keys = ['user_id' => $request->user()->id, 'fish_id' => $fish->id, 'tribe' => $data['tribe']];

        FishTribeResponse::submit($keys, $data['name'], $data['food_category']);

        return back();
    }

    /** 叫法去前後空白（含全形空白），空字串視為未填；飲食分類空字串視為未填。 */
    private function normalizedInput(Request $request): array
    {
        $name = $request->input('name');
        $name = is_string($name) ? preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $name) : $name;
        $category = $request->input('food_category');

        return [
            'tribe' => $request->input('tribe'),
            'name' => $name === '' ? null : $name,
            'food_category' => $category === '' ? null : $category,
        ];
    }
}
