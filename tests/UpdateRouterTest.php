<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Update\MessageTypeEnum;
use PhpSoftBox\Telegram\Update\Update;
use PHPUnit\Framework\TestCase;

final class UpdateRouterTest extends TestCase
{
    /**
     * Проверяем вызов обработчика команды.
     */
    public function testCommandHandler(): void
    {
        $router = new UpdateRouter();
        $called = 0;

        $router->command('start', static function () use (&$called): void {
            $called++;
        });

        $update = Update::fromArray([
            'message' => [
                'text' => '/start',
                'chat' => ['id' => 1],
            ],
        ]);

        $router->dispatch($update, $this->makeContext());

        $this->assertSame(1, $called);
    }

    /**
     * Проверяем вызов текстового обработчика.
     */
    public function testTextHandler(): void
    {
        $router = new UpdateRouter();
        $called = 0;

        $router->onText(static function () use (&$called): void {
            $called++;
        });

        $update = Update::fromArray([
            'message' => [
                'text' => 'hello',
                'chat' => ['id' => 1],
            ],
        ]);

        $router->dispatch($update, $this->makeContext());

        $this->assertSame(1, $called);
    }

    /**
     * Проверяем вызов обработчика по типу сообщения.
     */
    public function testTypeHandler(): void
    {
        $router = new UpdateRouter();
        $called = 0;

        $router->onType(MessageTypeEnum::PHOTO, static function () use (&$called): void {
            $called++;
        });

        $update = Update::fromArray([
            'message' => [
                'chat'  => ['id' => 1],
                'photo' => [
                    ['file_id' => 'x1'],
                ],
            ],
        ]);

        $router->dispatch($update, $this->makeContext());

        $this->assertSame(1, $called);
    }

    /**
     * Проверяем fallback для неизвестных сообщений.
     */
    public function testFallbackHandler(): void
    {
        $router = new UpdateRouter();
        $called = 0;

        $router->fallback(static function () use (&$called): void {
            $called++;
        });

        $update = Update::fromArray([
            'message' => [
                'chat' => ['id' => 1],
            ],
        ]);

        $router->dispatch($update, $this->makeContext());

        $this->assertSame(1, $called);
    }

    private function makeContext(): BotContext
    {
        $client = new \PhpSoftBox\Telegram\Tests\Support\FakeTelegramClient();

        return new BotContext($client);
    }
}
