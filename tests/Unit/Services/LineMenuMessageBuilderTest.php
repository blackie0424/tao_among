<?php

use App\Services\Line\LineMenuMessageBuilder;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\TextMessage;
use Tests\TestCase;

class LineMenuMessageBuilderTest extends TestCase
{
    private LineMenuMessageBuilder $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LineMenuMessageBuilder();
    }

    public function test_build_help_message_returns_text_message(): void
    {
        $message = $this->service->buildHelpMessage();

        $this->assertInstanceOf(TextMessage::class, $message);
        $this->assertStringContainsString('歡迎使用', $message->getText());
    }

    public function test_build_browse_tribes_menu_lists_all_tribes_and_instruction(): void
    {
        config(['fish_options.tribes' => ['iraraley', 'imowrod', 'ivalino']]);

        [$message] = $this->service->buildBrowseTribesMenu();

        $this->assertInstanceOf(FlexMessage::class, $message);

        $json = json_decode(json_encode($message->jsonSerialize()), true);
        $this->assertSame('bubble', $json['contents']['type'] ?? null);

        $bodyContents = $json['contents']['body']['contents'] ?? [];
        $texts = array_values(array_filter(array_map(
            fn ($item) => ($item['type'] ?? null) === 'text' ? ($item['text'] ?? null) : null,
            $bodyContents
        )));
        $actions = array_values(array_filter(array_map(
            fn ($item) => ($item['type'] ?? null) === 'button' ? ($item['action'] ?? null) : null,
            $bodyContents
        )));

        $this->assertContains('點選下列部落，觀看該部落的魚類資訊', $texts);
        $this->assertCount(3, $actions);
        $this->assertSame('action=browse_tribe_data&tribe=iraraley', $actions[0]['data'] ?? null);
    }
}
