<?php

namespace App\Services\Line;

use App\Contracts\LineMessagingClientInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Api\MessagingApiBlobApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Parser\SignatureValidator;

class LineMessagingClient implements LineMessagingClientInterface
{
    protected MessagingApiApi $client;
    protected string $channelSecret;
    protected string $channelAccessToken;

    public function __construct()
    {
        $this->channelSecret = config('line.channel_secret');
        $this->channelAccessToken = config('line.channel_access_token');

        $config = new Configuration();
        $config->setAccessToken($this->channelAccessToken);

        $this->client = new MessagingApiApi(new Client(), $config);
    }

    public function validateSignature(string $body, string $signature): bool
    {
        return SignatureValidator::validateSignature($body, $this->channelSecret, $signature);
    }

    public function getUserProfile(string $userId): array
    {
        $response = $this->client->getProfile($userId);

        return [
            'userId' => $response->getUserId(),
            'displayName' => $response->getDisplayName(),
            'pictureUrl' => $response->getPictureUrl(),
        ];
    }

    public function replyMessage(string $replyToken, array $messages): void
    {
        try {
            $request = new ReplyMessageRequest([
                'replyToken' => $replyToken,
                'messages' => $messages,
            ]);

            $this->client->replyMessage($request);
        } catch (\Exception $e) {
            Log::error('LINE Bot reply message failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function getMessageContent(string $messageId): string
    {
        try {
            $config = new Configuration();
            $config->setAccessToken(config('line.channel_access_token'));

            $blobClient = new MessagingApiBlobApi(new Client(), $config);

            Log::info('LINE Bot downloading message content', [
                'messageId' => $messageId,
            ]);

            $content = $blobClient->getMessageContent($messageId);

            if ($content instanceof \SplFileObject) {
                $content->rewind();
                $binaryData = '';

                while (!$content->eof()) {
                    $binaryData .= $content->fread(8192);
                }

                $content = $binaryData;
            } elseif (is_resource($content)) {
                rewind($content);
                $content = stream_get_contents($content);
            }

            Log::info('LINE Bot message content downloaded successfully', [
                'messageId' => $messageId,
                'size' => strlen($content),
                'first_bytes' => bin2hex(substr($content, 0, 16)),
            ]);

            return $content;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('LINE Bot failed to download message content - HTTP error', [
                'messageId' => $messageId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
                'response_body' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception(
                'Failed to download message content from LINE API: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        } catch (\Exception $e) {
            Log::error('LINE Bot failed to download message content - unexpected error', [
                'messageId' => $messageId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception(
                'Failed to download message content: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
