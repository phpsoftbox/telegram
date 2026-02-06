<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Http\Message\RequestFactory;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Telegram\Api\TelegramApiException;
use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class TelegramClientTest extends TestCase
{
    /**
     * Проверяем успешный запрос и тело отправки.
     */
    public function testSendMessage(): void
    {
        $responseBody = '{"ok":true,"result":{"message_id":10}}';
        $response     = new Response(200, [], $responseBody);

        $httpClient = new FakeHttpClient($response);

        $client = new TelegramClient(
            token: 'token',
            httpClient: $httpClient,
            requestFactory: new RequestFactory(),
            streamFactory: new StreamFactory(),
        );

        $client->sendMessage(123, 'Hello');

        $request = $httpClient->lastRequest();
        $this->assertNotNull($request);

        $payload = (string) $request->getBody();
        $this->assertStringContainsString('"chat_id":123', $payload);
        $this->assertStringContainsString('"text":"Hello"', $payload);
    }

    /**
     * Проверяем обработку ошибки Telegram API.
     */
    public function testErrorResponse(): void
    {
        $responseBody = '{"ok":false,"error_code":400,"description":"Bad"}';
        $response     = new Response(200, [], $responseBody);

        $httpClient = new FakeHttpClient($response);

        $client = new TelegramClient(
            token: 'token',
            httpClient: $httpClient,
            requestFactory: new RequestFactory(),
            streamFactory: new StreamFactory(),
        );

        $this->expectException(TelegramApiException::class);
        $client->sendMessage(1, 'Test');
    }

    /**
     * Проверяем обработку некорректного JSON.
     */
    public function testInvalidJson(): void
    {
        $response = new Response(200, [], 'broken');

        $httpClient = new FakeHttpClient($response);

        $client = new TelegramClient(
            token: 'token',
            httpClient: $httpClient,
            requestFactory: new RequestFactory(),
            streamFactory: new StreamFactory(),
        );

        $this->expectException(TelegramApiException::class);
        $client->sendMessage(1, 'Test');
    }

    /**
     * Проверяем скачивание файла по file_id.
     */
    public function testDownloadFile(): void
    {
        $responses = [
            new Response(200, [], '{"ok":true,"result":{"file_id":"f1","file_path":"files/a.txt"}}'),
            new Response(200, [], 'file-content'),
        ];

        $httpClient = new FakeHttpClient($responses);

        $client = new TelegramClient(
            token: 'token',
            httpClient: $httpClient,
            requestFactory: new RequestFactory(),
            streamFactory: new StreamFactory(),
        );

        $tmp = tempnam(sys_get_temp_dir(), 'psb');
        if ($tmp === false) {
            $this->fail('Не удалось создать временный файл.');
        }

        $client->downloadFile('f1', $tmp);

        $this->assertSame('file-content', file_get_contents($tmp));

        unlink($tmp);
    }
}
