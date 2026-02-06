<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Api;

use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function file_put_contents;
use function is_array;
use function json_decode;
use function json_encode;
use function ltrim;
use function rtrim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class TelegramClient
{
    private string $baseUrl;
    private string $fileBaseUrl;

    public function __construct(
        private readonly string $token,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        string $apiBase = 'https://api.telegram.org',
    ) {
        $base              = rtrim($apiBase, '/');
        $this->baseUrl     = $base . '/bot' . $token . '/';
        $this->fileBaseUrl = $base . '/file/bot' . $token . '/';
    }

    public function request(string $method, array $payload = []): TelegramResponse
    {
        $body = '';
        if ($payload !== []) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new TelegramApiException('Failed to encode Telegram request.', null, $payload);
            }
        }

        $request = $this->requestFactory
            ->createRequest('POST', $this->baseUrl . $method)
            ->withHeader('Content-Type', 'application/json');

        $request = $request->withBody($this->streamFactory->createStream($body));

        $response = $this->httpClient->sendRequest($request);
        $raw      = (string) $response->getBody();

        try {
            $decoded = $raw !== '' ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException $exception) {
            throw new TelegramApiException('Invalid Telegram response.', $response->getStatusCode(), ['body' => $raw]);
        }

        if (!is_array($decoded)) {
            throw new TelegramApiException('Invalid Telegram response.', $response->getStatusCode(), ['body' => $raw]);
        }

        $telegramResponse = TelegramResponse::fromArray($decoded);
        if (!$telegramResponse->ok()) {
            $message = $telegramResponse->description() ?? 'Telegram API error.';

            throw new TelegramApiException($message, $telegramResponse->errorCode(), $decoded);
        }

        return $telegramResponse;
    }

    public function sendMessage(int|string $chatId, string $text, array $options = []): TelegramResponse
    {
        return $this->request('sendMessage', ['chat_id' => $chatId, 'text' => $text] + $options);
    }

    public function deleteMessage(int|string $chatId, int $messageId): TelegramResponse
    {
        return $this->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    public function getFile(string $fileId): TelegramFile
    {
        $response = $this->request('getFile', ['file_id' => $fileId]);
        $result   = $response->result();

        if (!is_array($result)) {
            throw new TelegramApiException('Invalid getFile response.');
        }

        return TelegramFile::fromArray($result);
    }

    public function fileUrl(string $filePath): string
    {
        return $this->fileBaseUrl . ltrim($filePath, '/');
    }

    public function downloadFile(string $fileId, string $targetPath): void
    {
        $file     = $this->getFile($fileId);
        $filePath = $file->filePath();

        if ($filePath === null || $filePath === '') {
            throw new TelegramApiException('File path is missing in getFile response.');
        }

        $this->downloadFileByPath($filePath, $targetPath);
    }

    public function downloadFileByPath(string $filePath, string $targetPath): void
    {
        $request  = $this->requestFactory->createRequest('GET', $this->fileUrl($filePath));
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw new TelegramApiException('Failed to download file.', $response->getStatusCode());
        }

        $result = file_put_contents($targetPath, (string) $response->getBody());
        if ($result === false) {
            throw new TelegramApiException('Failed to save file to disk.');
        }
    }

    public function setWebhook(string $url, array $options = []): TelegramResponse
    {
        return $this->request('setWebhook', ['url' => $url] + $options);
    }

    /**
     * @param array<int, array{command:string,description:string}> $commands
     */
    public function setMyCommands(array $commands, array $options = []): TelegramResponse
    {
        return $this->request('setMyCommands', ['commands' => $commands] + $options);
    }

    public function getWebhookInfo(): TelegramResponse
    {
        return $this->request('getWebhookInfo');
    }
}
