<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateFishNoteRequest extends FormRequest
{
    public function rules()
    {
        return [
            'note' => 'sometimes|required|string',
            'note_type' => 'sometimes|required|string|max:50',
            'locate' => 'sometimes|required|string|max:20',
        ];
    }

    public function messages()
    {
        return [
            'note.required' => '請填寫筆記內容。',
            'note_type.required' => '請填寫筆記類型。',
            'locate.required' => '請填寫地區。',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => '驗證失敗',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}