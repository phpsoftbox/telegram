<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use PhpSoftBox\Telegram\Conversation\ArrayConversationStore;
use PhpSoftBox\Telegram\Conversation\ConversationDefinition;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Conversation\QuestionStep;
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Tests\Support\FakeTelegramClient;
use PhpSoftBox\Telegram\Update\Update;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TelegramBotBuilder::class)]
final class TelegramBotBuilderTest extends TestCase
{
    /**
     * Проверяет, что builder регистрирует команду и вызывает обработчик.
     */
    #[Test]
    public function testCommandHandlerInvoked(): void
    {
        $router = new UpdateRouter();

        $builder = new TelegramBotBuilder($router);

        $builder->command('ping', TelegramBotBuilderTestCommand::class);

        $client = new FakeTelegramClient();

        $context = new BotContext($client);
        $update  = Update::fromArray([
            'message' => [
                'text' => '/ping',
                'chat' => ['id' => 10],
                'from' => ['id' => 10],
            ],
        ]);

        $router->dispatch($update, $context);

        $messages = $client->sentMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('pong', $messages[0]['text']);
    }

    /**
     * Проверяет регистрацию диалога через builder и запуск по имени.
     */
    #[Test]
    public function testConversationRegistrationAndStart(): void
    {
        $client = new FakeTelegramClient();

        $conversations = new ConversationManager(new ArrayConversationStore(), $client);

        $router = new UpdateRouter();

        $builder = new TelegramBotBuilder($router, $conversations);

        $definition = new ConversationDefinition('demo.flow', [
            new QuestionStep('name', 'Введите имя:'),
        ]);

        $builder->conversation('demo.flow', $definition);

        $update = Update::fromArray([
            'message' => [
                'chat' => ['id' => 22],
                'from' => ['id' => 22],
            ],
        ]);

        $this->assertTrue($builder->startConversation('demo.flow', $update));
        $this->assertCount(1, $client->sentMessages());
    }
}

final class TelegramBotBuilderTestCommand
{
    public function __invoke(Update $update, BotContext $context): void
    {
        $context->sendMessage($update->chatId() ?? 0, 'pong');
    }
}
