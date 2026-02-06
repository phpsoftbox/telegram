<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Telegram\Tests\Support\FakeUpdateHandler;
use PhpSoftBox\Telegram\Webhook\WebhookHandler;
use PHPUnit\Framework\TestCase;

final class WebhookHandlerTest extends TestCase
{
    /**
     * Проверяем обработку некорректного update.
     */
    public function testInvalidUpdate(): void
    {
        $handler = $this->makeHandler();

        $request = new ServerRequest('POST', 'https://example.com', body: 'broken');

        $response = $handler->handle($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * Проверяем успешную обработку update.
     */
    public function testValidUpdate(): void
    {
        $handler = $this->makeHandler($fakeHandler = new FakeUpdateHandler());

        $payload = '{"update_id":1,"message":{"chat":{"id":1},"text":"hi"}}';
        $request = new ServerRequest('POST', 'https://example.com', body: $payload);

        $response = $handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($fakeHandler->lastUpdate());
    }

    private function makeHandler(?FakeUpdateHandler $handler = null): WebhookHandler
    {
        $handler = $handler ?? new FakeUpdateHandler();

        return new WebhookHandler(
            $handler,
            new ResponseFactory(),
            new StreamFactory(),
        );
    }
}
