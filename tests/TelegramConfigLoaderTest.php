<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use PhpSoftBox\Telegram\Conversation\ArrayConversationStore;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Loader\TelegramConfigLoader;
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

#[CoversClass(TelegramConfigLoader::class)]
final class TelegramConfigLoaderTest extends TestCase
{
    /**
     * Проверяет загрузку конфигурации из файла и регистрацию команд.
     */
    #[Test]
    public function testConfigFileRegistersCommand(): void
    {
        $dir = sys_get_temp_dir() . '/psb-telegram-config-' . uniqid('', true);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            $this->fail('Failed to create temp dir.');
        }

        $file = $dir . '/bot.php';
        file_put_contents($file, <<<'PHP'
<?php

use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;

return static function (TelegramBotBuilder $bot): void {
    $bot->command('hello', function ($update, $context): void {
        $context->sendMessage($update->chatId() ?? 0, 'hi');
    });
};
PHP);

        $router = new UpdateRouter();
        $client = new FakeTelegramClient();

        $conversations = new ConversationManager(new ArrayConversationStore(), $client);

        $builder = new TelegramBotBuilder($router, $conversations);

        new TelegramConfigLoader($dir)->load($builder);

        $update = Update::fromArray([
            'message' => [
                'text' => '/hello',
                'chat' => ['id' => 1],
                'from' => ['id' => 1],
            ],
        ]);

        $router->dispatch($update, new BotContext($client, $conversations));
        $messages = $client->sentMessages();

        $this->assertCount(1, $messages);
        $this->assertSame('hi', $messages[0]['text']);
    }
}
