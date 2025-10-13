<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiErrorHandler
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Check if the request is too large
            if ($request->server('CONTENT_LENGTH') > $this->getMaxPostSize()) {
                Log::warning('Request too large', [
                    'content_length' => $request->server('CONTENT_LENGTH'),
                    'max_size' => $this->getMaxPostSize(),
                    'url' => $request->url()
                ]);

                return response()->json([
                    'message' => '請求資料過大，請減少上傳檔案大小',
                    'error' => 'payload_too_large'
                ], 413);
            }

            // Check for required headers for API requests
            if ($request->is('prefix/api/*')) {
                if (!$request->hasHeader('Accept') || !str_contains($request->header('Accept'), 'application/json')) {
                    return response()->json([
                        'message' => '請設置正確的 Accept header',
                        'error' => 'invalid_accept_header'
                    ], 400);
                }
            }

            $response = $next($request);

            // Log successful API requests for monitoring
            if ($request->is('prefix/api/*') && $response->getStatusCode() < 400) {
                Log::info('API request successful', [
                    'method' => $request->method(),
                    'url' => $request->url(),
                    'status' => $response->getStatusCode(),
                    'response_time' => microtime(true) - LARAVEL_START
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Middleware error: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);

            return response()->json([
                'message' => '請求處理失敗',
                'error' => 'middleware_error'
            ], 500);
        }
    }

    /**
     * Get the maximum POST size allowed
     */
    private function getMaxPostSize(): int
    {
        $postMaxSize = ini_get('post_max_size');
        $uploadMaxFilesize = ini_get('upload_max_filesize');

        return min(
            $this->parseSize($postMaxSize),
            $this->parseSize($uploadMaxFilesize)
        );
    }

    /**
     * Parse size string to bytes
     */
    private function parseSize(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;

        switch ($last) {
            case 'g':
                $size *= 1024;
                // no break
            case 'm':
                $size *= 1024;
                // no break
            case 'k':
                $size *= 1024;
        }

        return $size;
    }
}
