<?php

namespace App\Services;

class LineBatchCaptureSummaryPresenter
{
    /**
     * @param string[] $images
     * @param array{tribe?:?string,location?:?string,capture_method?:?string,capture_date?:?string,notes?:?string} $form
     * @return array{
     *   notice:?string,
     *   actions:array<int, array{label:string,data:string,display_text?:?string,style?:?string,color?:?string}>
     * }
     */
    public function present(string $state, array $images, array $form): array
    {
        return [
            'notice' => $this->noticeFor($state, $images),
            'actions' => $this->actionsFor($state, $images),
        ];
    }

    /**
     * @param string[] $images
     */
    private function noticeFor(string $state, array $images): ?string
    {
        return match ($state) {
            'waiting_images' => count($images) > 0
                ? '請確認照片數量；全部圖片都上傳完成後，按「圖片上傳完成」進入下一步。'
                : '請先上傳至少 1 張捕獲照片，全部上傳完成後再進入下一步。',
            'awaiting_location_prompt' => '已完成部落選擇，請輸入捕獲地點。',
            'awaiting_method_prompt' => '地點已填寫，請選擇捕獲方式。',
            'awaiting_date_prompt' => '捕獲方式已選擇，請選擇捕獲日期。',
            'awaiting_notes_prompt' => '日期已選擇，請輸入備註或略過。',
            'waiting_confirm' => '請確認資料無誤後再送出。',
            default => null,
        };
    }

    /**
     * @param string[] $images
     * @return array<int, array{label:string,data:string,display_text?:?string,style?:?string,color?:?string}>
     */
    private function actionsFor(string $state, array $images): array
    {
        return match ($state) {
            'waiting_images' => count($images) > 0 ? [
                ['label' => '➕ 繼續上傳', 'data' => 'action=continue_batch_capture_upload', 'display_text' => '繼續上傳照片'],
                ['label' => '✅ 圖片上傳完成', 'data' => 'action=finish_batch_capture_upload', 'display_text' => '圖片上傳完成', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ] : [
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄'],
            ],
            'awaiting_location_prompt', 'awaiting_location_input' => [
                ['label' => '輸入捕獲地點', 'data' => 'action=prompt_batch_capture_location', 'display_text' => '輸入捕獲地點'],
                ['label' => '修改部落', 'data' => 'action=open_batch_capture_tribe_selector', 'display_text' => '修改捕獲部落', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
            'awaiting_method_prompt', 'waiting_method_selection' => [
                ['label' => '選擇捕獲方式', 'data' => 'action=open_batch_capture_method_selector', 'display_text' => '選擇捕獲方式'],
                ['label' => '修改地點', 'data' => 'action=prompt_batch_capture_location', 'display_text' => '修改捕獲地點', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
            'awaiting_date_prompt', 'waiting_date_selection', 'awaiting_date_manual_input' => [
                ['label' => '選擇捕獲日期', 'data' => 'action=open_batch_capture_date_selector', 'display_text' => '選擇捕獲日期'],
                ['label' => '修改捕獲方式', 'data' => 'action=open_batch_capture_method_selector', 'display_text' => '修改捕獲方式', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
            'awaiting_notes_prompt', 'awaiting_notes_input' => [
                ['label' => '輸入備註', 'data' => 'action=prompt_batch_capture_notes', 'display_text' => '輸入備註'],
                ['label' => '略過備註', 'data' => 'action=skip_batch_capture_notes', 'display_text' => '略過備註', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
            'waiting_confirm' => [
                ['label' => '✅ 確認送出', 'data' => 'action=confirm_batch_capture_record', 'display_text' => '確認送出批次捕獲紀錄'],
                ['label' => '🔁 重新填寫', 'data' => 'action=reset_batch_capture_form', 'display_text' => '重新填寫捕獲資料', 'style' => 'secondary'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
            default => [
                ['label' => '➕ 繼續上傳', 'data' => 'action=continue_batch_capture_upload', 'display_text' => '繼續上傳照片'],
                ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄', 'style' => 'secondary'],
            ],
        };
    }
}
