<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Http\Message\RequestFactory;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Tests\Support\FakeHttpClient;
use PhpSoftBox\Telegram\Webhook\WebhookSecret;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_decode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(TelegramClient::class)]
#[CoversClass(WebhookSecret::class)]
#[CoversMethod(TelegramClient::class, 'setWebhook')]
#[CoversMethod(TelegramClient::class, 'webhookSecret')]
#[CoversMethod(WebhookSecret::class, 'fromBotToken')]
final class TelegramClientWebhookTest extends TestCase
{
    /**
     * Проверим, что setWebhook без явного secret_token регистрирует секрет, выведенный из токена бота.
     *
     * @see TelegramClient::setWebhook()
     * @see TelegramClient::webhookSecret()
     */
    #[Test]
    public function setWebhookSendsDerivedSecret(): void
    {
        [$client, $httpClient] = $this->createClient('123:bot-token');

        $client->setWebhook('https://example.com/telegram/main/webhook');

        $payload = json_decode((string) $httpClient->lastRequest()?->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('https://example.com/telegram/main/webhook', $payload['url']);
        $this->assertSame(WebhookSecret::fromBotToken('123:bot-token'), $payload['secret_token']);
        $this->assertSame($client->webhookSecret(), $payload['secret_token']);
    }

    /**
     * Проверим, что явно переданный secret_token не заменяется секретом по умолчанию.
     *
     * @see TelegramClient::setWebhook()
     */
    #[Test]
    public function explicitSecretIsKept(): void
    {
        [$client, $httpClient] = $this->createClient('123:bot-token');

        $client->setWebhook('https://example.com/hook', ['secret_token' => 'custom_secret']);

        $payload = json_decode((string) $httpClient->lastRequest()?->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('custom_secret', $payload['secret_token']);
    }

    /**
     * Проверим формат секрета: допустимые Telegram символы, стабилен для токена и различается у разных ботов.
     *
     * @see WebhookSecret::fromBotToken()
     */
    #[Test]
    public function derivedSecretIsStableAndBotSpecific(): void
    {
        $secret = WebhookSecret::fromBotToken('123:bot-token');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);
        $this->assertSame($secret, WebhookSecret::fromBotToken('123:bot-token'));
        $this->assertNotSame($secret, WebhookSecret::fromBotToken('456:other-token'));
    }

    /**
     * @return array{0: TelegramClient, 1: FakeHttpClient}
     */
    private function createClient(string $token): array
    {
        $httpClient = new FakeHttpClient(new Response(200, [], '{"ok":true,"result":true}'));

        $client = new TelegramClient(
            token: $token,
            httpClient: $httpClient,
            requestFactory: new RequestFactory(),
            streamFactory: new StreamFactory(),
        );

        return [$client, $httpClient];
    }
}
