<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAudioRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
                    'audio' => 'required|file|mimes:mp3,wav|max:10240',
                ];
    }

    public function messages()
    {
        return [
            'audio.required' => '請選擇要上傳的音訊檔案。',
            'audio.file' => '只能上傳單一音訊檔案。',
            'audio.mimes' => '音訊格式僅限 mp3, wav',
            'audio.max' => '音訊大小不可超過 10MB。',
        ];
    }
}
