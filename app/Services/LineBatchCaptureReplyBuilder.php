<?php

namespace App\Services;

use App\Models\Fish;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\TextMessage;

class LineBatchCaptureReplyBuilder
{
    public function __construct(
        private readonly LineBatchCaptureCardService $lineBatchCaptureCardService,
        private readonly LineBatchCaptureSummaryPresenter $lineBatchCaptureSummaryPresenter,
    ) {
    }

    /**
     * @param string[] $images
     * @param array{tribe?:?string,location?:?string,capture_method?:?string,capture_date?:?string,notes?:?string} $form
     */
    public function buildSummaryMessage(Fish $fish, array $images, array $form, string $state): FlexMessage
    {
        $view = $this->lineBatchCaptureSummaryPresenter->present($state, $images, $form);

        return $this->lineBatchCaptureCardService->buildSummaryCard(
            $fish,
            $images,
            $form,
            $view['actions'],
            $view['notice']
        );
    }

    public function buildTribeSelectionMessage(?string $prefix = null): FlexMessage
    {
        $actions = array_map(fn ($tribe) => [
            'label' => ucfirst($tribe),
            'data' => "action=select_batch_capture_tribe&tribe={$tribe}",
            'display_text' => $tribe,
            'style' => 'secondary',
        ], config('fish_options.tribes', []));

        return $this->lineBatchCaptureCardService->buildOptionSelectorCard(
            '請選擇捕獲部落',
            trim(($prefix ? "{$prefix}\n" : '') . '點選後會回到摘要卡片繼續填寫。'),
            $actions
        );
    }

    public function buildMethodSelectionMessage(?string $prefix = null): FlexMessage
    {
        $actions = [];
        foreach (config('fish_options.capture_methods', []) as $value => $label) {
            $actions[] = [
                'label' => $label,
                'data' => "action=select_batch_capture_method&capture_method={$value}",
                'display_text' => $label,
                'style' => 'secondary',
            ];
        }

        return $this->lineBatchCaptureCardService->buildOptionSelectorCard(
            '請選擇捕獲方式',
            trim(($prefix ? "{$prefix}\n" : '') . '點選後會回到摘要卡片繼續填寫。'),
            $actions
        );
    }

    public function buildDateSelectionMessage(?string $prefix = null): FlexMessage
    {
        return $this->lineBatchCaptureCardService->buildOptionSelectorCard(
            '請選擇捕獲日期',
            trim(($prefix ? "{$prefix}\n" : '') . '點選後會回到摘要卡片繼續填寫。'),
            [
                ['label' => '今天', 'data' => 'action=set_batch_capture_date&value=today', 'display_text' => '今天'],
                ['label' => '昨天', 'data' => 'action=set_batch_capture_date&value=yesterday', 'display_text' => '昨天', 'style' => 'secondary'],
                ['label' => '手動輸入', 'data' => 'action=request_manual_batch_capture_date', 'display_text' => '手動輸入日期', 'style' => 'secondary'],
            ]
        );
    }

    public function buildTextMessage(string $text): TextMessage
    {
        return new TextMessage([
            'type' => 'text',
            'text' => $text,
        ]);
    }
}
