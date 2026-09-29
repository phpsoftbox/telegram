<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use InvalidArgumentException;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Telegram\Tests\Support\FakeUpdateHandler;
use PhpSoftBox\Telegram\Webhook\WebhookHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookHandler::class)]
#[CoversMethod(WebhookHandler::class, '__construct')]
#[CoversMethod(WebhookHandler::class, 'handle')]
final class WebhookHandlerTest extends TestCase
{
    private const string SECRET = 'webhook-secret';
    private const string UPDATE = '{"update_id":1,"message":{"chat":{"id":1},"text":"hi"}}';

    /**
     * Проверим, что update с верным секретом передаётся в обработчик и подтверждается 200.
     *
     * @see WebhookHandler::handle()
     */
    #[Test]
    public function validUpdateIsHandled(): void
    {
        $fakeHandler = new FakeUpdateHandler();

        $handler = $this->makeHandler($fakeHandler);

        $response = $handler->handle($this->request(self::UPDATE, self::SECRET));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $fakeHandler->lastUpdate()?->updateId());
    }

    /**
     * Проверим, что некорректный JSON с верным секретом отклоняется с 400.
     *
     * @see WebhookHandler::handle()
     */
    #[Test]
    public function invalidUpdateReturns400(): void
    {
        $handler = $this->makeHandler(new FakeUpdateHandler());

        $response = $handler->handle($this->request('broken', self::SECRET));

        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * Проверим, что запрос без заголовка секрета отклоняется с 401 и не доходит до обработчика.
     *
     * @see WebhookHandler::handle()
     */
    #[Test]
    public function missingSecretIsRejected(): void
    {
        $fakeHandler = new FakeUpdateHandler();

        $handler = $this->makeHandler($fakeHandler);

        $response = $handler->handle($this->request(self::UPDATE, null));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertNull($fakeHandler->lastUpdate());
    }

    /**
     * Проверим, что запрос с чужим секретом отклоняется с 401 и не доходит до обработчика.
     *
     * @see WebhookHandler::handle()
     */
    #[Test]
    public function wrongSecretIsRejected(): void
    {
        $fakeHandler = new FakeUpdateHandler();

        $handler = $this->makeHandler($fakeHandler);

        $response = $handler->handle($this->request(self::UPDATE, 'other-secret'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertNull($fakeHandler->lastUpdate());
    }

    /**
     * Проверим, что обработчик нельзя создать с пустым секретом (незащищённый webhook).
     *
     * @see WebhookHandler::__construct()
     */
    #[Test]
    public function emptySecretIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WebhookHandler(new FakeUpdateHandler(), '', new ResponseFactory(), new StreamFactory());
    }

    private function makeHandler(FakeUpdateHandler $handler): WebhookHandler
    {
        return new WebhookHandler($handler, self::SECRET, new ResponseFactory(), new StreamFactory());
    }

    private function request(string $body, ?string $secret): ServerRequest
    {
        $headers = $secret === null ? [] : [WebhookHandler::SECRET_HEADER => $secret];

        return new ServerRequest('POST', 'https://example.com/telegram/main/webhook', $headers, $body);
    }
}
