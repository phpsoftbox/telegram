<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use PhpSoftBox\Telegram\Conversation\ArrayConversationStore;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Loader\TelegramAttributeLoader;
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Tests\Support\FakeTelegramClient;
use PhpSoftBox\Telegram\Update\Update;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(TelegramAttributeLoader::class)]
final class TelegramAttributeLoaderTest extends TestCase
{
    /**
     * Проверяет регистрацию команд и диалогов по атрибутам.
     */
    #[Test]
    public function testAttributeLoaderRegistersHandlers(): void
    {
        $dir = sys_get_temp_dir() . '/psb-telegram-attrs-' . uniqid('', true);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            $this->fail('Failed to create temp dir.');
        }

        $file = $dir . '/BotHandlers.php';
        file_put_contents($file, <<<'PHP'
<?php

namespace TempTelegram;

use PhpSoftBox\Telegram\Attributes\TelegramCommand;
use PhpSoftBox\Telegram\Attributes\TelegramConversation;
use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Conversation\ConversationDefinition;
use PhpSoftBox\Telegram\Conversation\QuestionStep;
use PhpSoftBox\Telegram\Update\Update;

#[TelegramCommand('hi')]
final class HiCommand
{
    public function __invoke(Update $update, BotContext $context): void
    {
        $context->sendMessage($update->chatId() ?? 0, 'hello');
    }
}

#[TelegramConversation('demo.flow')]
final class DemoConversation
{
    public function __invoke(string $name): ConversationDefinition
    {
        return new ConversationDefinition($name, [
            new QuestionStep('name', 'Введите имя:'),
        ]);
    }
}
PHP);

        $router = new UpdateRouter();
        $client = new FakeTelegramClient();

        $conversations = new ConversationManager(new ArrayConversationStore(), $client);

        $builder = new TelegramBotBuilder($router, $conversations);

        new TelegramAttributeLoader($dir)->load($builder);

        $update = Update::fromArray([
            'message' => [
                'text' => '/hi',
                'chat' => ['id' => 5],
                'from' => ['id' => 5],
            ],
        ]);

        $router->dispatch($update, new BotContext($client, $conversations));
        $this->assertCount(1, $client->sentMessages());

        $startUpdate = Update::fromArray([
            'message' => [
                'chat' => ['id' => 6],
                'from' => ['id' => 6],
            ],
        ]);

        $this->assertTrue($builder->startConversation('demo.flow', $startUpdate));
        $this->assertCount(2, $client->sentMessages());
    }
}
