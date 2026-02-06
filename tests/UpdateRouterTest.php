<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Tests\Support\FakeTelegramClient;
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

    /**
     * Проверяем вызов обработчика по callback_data.
     */
    public function testCallbackDataHandler(): void
    {
        $router = new UpdateRouter();
        $called = 0;

        $router->onCallbackData('trial:start', static function () use (&$called): void {
            $called++;
        });

        $update = Update::fromArray([
            'callback_query' => [
                'id'      => 'cbq-1',
                'from'    => ['id' => 1001],
                'data'    => 'trial:start',
                'message' => [
                    'chat' => ['id' => 1],
                ],
            ],
        ]);

        $router->dispatch($update, $this->makeContext());

        $this->assertSame(1, $called);
    }

    /**
     * Проверяем вызов общего обработчика callback_query.
     */
    public function testCallbackQueryHandler(): void
    {
        $router = new UpdateRouter();
        $called = 0;

        $router->onCallbackQuery(static function () use (&$called): void {
            $called++;
        });

        $update = Update::fromArray([
            'callback_query' => [
                'id'      => 'cbq-2',
                'from'    => ['id' => 1002],
                'data'    => 'unknown:data',
                'message' => [
                    'chat' => ['id' => 2],
                ],
            ],
        ]);

        $router->dispatch($update, $this->makeContext());

        $this->assertSame(1, $called);
    }

    /**
     * Проверяем, что callback_data имеет приоритет над onCallbackQuery.
     */
    public function testCallbackDataPriorityOverGenericHandler(): void
    {
        $router         = new UpdateRouter();
        $specificCalled = 0;
        $genericCalled  = 0;

        $router->onCallbackData('trial:start', static function () use (&$specificCalled): void {
            $specificCalled++;
        });
        $router->onCallbackQuery(static function () use (&$genericCalled): void {
            $genericCalled++;
        });

        $update = Update::fromArray([
            'callback_query' => [
                'id'      => 'cbq-3',
                'from'    => ['id' => 1003],
                'data'    => 'trial:start',
                'message' => [
                    'chat' => ['id' => 3],
                ],
            ],
        ]);

        $router->dispatch($update, $this->makeContext());

        $this->assertSame(1, $specificCalled);
        $this->assertSame(0, $genericCalled);
    }

    /**
     * Проверяем, что callback_query не проваливается в текстовые обработчики.
     */
    public function testCallbackQueryDoesNotTriggerTextHandlers(): void
    {
        $router         = new UpdateRouter();
        $textCalled     = 0;
        $fallbackCalled = 0;

        $router->onText(static function () use (&$textCalled): void {
            $textCalled++;
        });
        $router->fallback(static function () use (&$fallbackCalled): void {
            $fallbackCalled++;
        });

        $update = Update::fromArray([
            'callback_query' => [
                'id'      => 'cbq-4',
                'from'    => ['id' => 1004],
                'data'    => 'unknown',
                'message' => [
                    'chat' => ['id' => 4],
                    'text' => '/start',
                ],
            ],
        ]);

        $router->dispatch($update, $this->makeContext());

        $this->assertSame(0, $textCalled);
        $this->assertSame(1, $fallbackCalled);
    }

    private function makeContext(): BotContext
    {
        $client = new FakeTelegramClient();

        return new BotContext($client);
    }
}
