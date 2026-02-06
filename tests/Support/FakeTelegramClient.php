<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Support;

use PhpSoftBox\Http\Message\RequestFactory;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Api\TelegramResponse;

final class FakeTelegramClient extends TelegramClient
{
    /** @var list<array{chat_id: int|string, text: string, options: array}> */
    private array $sentMessages = [];

    /** @var list<array{chat_id: int|string, message_id: int}> */
    private array $deletedMessages = [];

    public function __construct(
        private TelegramResponse $response = new TelegramResponse(true, ['message_id' => 1]),
    ) {
        $fakeResponse = new Response(200, [], '{"ok":true,"result":[]}');

        $fakeClient = new FakeHttpClient($fakeResponse);

        parent::__construct(
            token: 'token',
            httpClient: $fakeClient,
            requestFactory: new RequestFactory(),
            streamFactory: new StreamFactory(),
        );
    }

    public function sendMessage(int|string $chatId, string $text, array $options = []): TelegramResponse
    {
        $this->sentMessages[] = ['chat_id' => $chatId, 'text' => $text, 'options' => $options];

        return $this->response;
    }

    public function deleteMessage(int|string $chatId, int $messageId): TelegramResponse
    {
        $this->deletedMessages[] = ['chat_id' => $chatId, 'message_id' => $messageId];

        return $this->response;
    }

    /**
     * @return list<array{chat_id: int|string, text: string, options: array}>
     */
    public function sentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * @return list<array{chat_id: int|string, message_id: int}>
     */
    public function deletedMessages(): array
    {
        return $this->deletedMessages;
    }
}
