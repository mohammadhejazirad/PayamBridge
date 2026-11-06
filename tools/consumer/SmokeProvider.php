<?php

declare(strict_types=1);

namespace App\Providers;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\ServiceProvider;
use PayamBridge\Http\HttpTransport;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class SmokeProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(HttpTransport::class, static fn () => new HttpTransport(new class implements ClientInterface
        {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                if ($request->getUri()->getHost() !== 'tapi.bale.ai' || $request->getMethod() !== 'POST' || ! str_ends_with($request->getUri()->getPath(), '/sendMessage')) {
                    throw new RuntimeException('Unexpected consumer HTTP request.');
                }
                $capture = [
                    'uri_sha256' => hash('sha256', (string) $request->getUri()),
                    'body' => json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR),
                ];
                file_put_contents(storage_path('logs/payambridge-smoke.jsonl'), json_encode($capture, JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);

                return new Response(200, [], '{"ok":true,"result":{"message_id":7,"chat":{"id":123}}}');
            }
        }));
    }
}
