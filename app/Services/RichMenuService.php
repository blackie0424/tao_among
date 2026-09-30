<?php

namespace App\Services;

use App\Contracts\RichMenuServiceInterface;
use App\Exceptions\DefaultRichMenuManagedExternallyException;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class RichMenuService implements RichMenuServiceInterface
{
    protected Client $httpClient;
    protected string $apiBase = 'https://api.line.me/v2/bot';

    public function __construct()
    {
        $accessToken = config('line.channel_access_token');
        $this->httpClient = new Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function create(array $data): string
    {
        $response = $this->httpClient->post("{$this->apiBase}/richmenu", [
            'json' => $data,
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (empty($result['richMenuId'])) {
            throw new \Exception('LINE API 未回傳 richMenuId：' . json_encode($result));
        }

        return $result['richMenuId'];
    }

    public function uploadImage(string $richMenuId, string $imagePath): void
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/jpeg',
        };

        $accessToken = config('line.channel_access_token');
        $imageClient = new Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => $contentType,
            ],
        ]);

        $imageClient->post("https://api-data.line.me/v2/bot/richmenu/{$richMenuId}/content", [
            'body' => fopen($imagePath, 'r'),
        ]);
    }

    public function setDefault(string $richMenuId): void
    {
        $this->httpClient->post("{$this->apiBase}/user/all/richmenu/{$richMenuId}");
    }

    public function getDefaultRichMenuId(): ?string
    {
        try {
            $response = $this->httpClient->get("{$this->apiBase}/user/all/richmenu");
            $result = json_decode($response->getBody()->getContents(), true);

            return $result['richMenuId'] ?? null;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            if ($status === 404) {
                return null;
            }
            if ($status === 403) {
                throw new DefaultRichMenuManagedExternallyException(
                    '全域預設由 LINE Official Account Manager 設定，請至 OA Manager 取消。',
                    previous: $e
                );
            }

            throw $e;
        }
    }

    public function linkToUser(string $lineUserId, string $richMenuId): void
    {
        $this->httpClient->post("{$this->apiBase}/user/{$lineUserId}/richmenu/{$richMenuId}");
        Log::info('RichMenuService: linked user to rich menu', [
            'lineUserId' => $lineUserId,
            'richMenuId' => $richMenuId,
        ]);
    }

    public function unlinkFromUser(string $lineUserId): void
    {
        try {
            $this->httpClient->delete("{$this->apiBase}/user/{$lineUserId}/richmenu");
            Log::info('RichMenuService: unlinked user from rich menu', [
                'lineUserId' => $lineUserId,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }
    }

    public function clearDefault(): void
    {
        $this->httpClient->delete("{$this->apiBase}/user/all/richmenu");
    }

    public function deleteAllMenus(): void
    {
        try {
            if ($this->getDefaultRichMenuId() !== null) {
                $this->clearDefault();
            }
        } catch (DefaultRichMenuManagedExternallyException $e) {
            Log::warning(
                'RichMenuService: default rich menu is managed by OA Manager; continuing menu cleanup',
                ['error' => $e->getMessage()]
            );
        }

        $menus = $this->list();
        foreach ($menus as $menu) {
            try {
                $this->httpClient->delete("{$this->apiBase}/richmenu/{$menu['richMenuId']}");
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                Log::warning('RichMenuService: failed to delete menu', ['id' => $menu['richMenuId']]);
            }
        }
    }

    public function list(): array
    {
        try {
            $response = $this->httpClient->get("{$this->apiBase}/richmenu/list");
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['richmenus'] ?? [];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return [];
            }
            throw $e;
        }
    }
}
