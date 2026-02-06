<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Builder\Definitions\ActionTypeEnum;
use PhpSoftBox\Telegram\Builder\Definitions\ScreenButton;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use PhpSoftBox\Telegram\Conversation\ArrayConversationStore;
use PhpSoftBox\Telegram\Conversation\ConversationDefinition;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Conversation\QuestionStep;
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Support\MessageCleaner;
use PhpSoftBox\Telegram\Tests\Support\FakeTelegramClient;
use PhpSoftBox\Telegram\Update\Update;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function glob;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

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

        $cleaner = new MessageCleaner($client);

        $conversations = new ConversationManager(new ArrayConversationStore(), $client, $cleaner);

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

    /**
     * Проверяет, что builder регистрирует обработчик callback_data.
     */
    #[Test]
    public function testCallbackDataHandlerInvoked(): void
    {
        $router = new UpdateRouter();

        $builder = new TelegramBotBuilder($router);
        $called  = 0;

        $builder->onCallbackData('trial:start', static function () use (&$called): void {
            $called++;
        });

        $update = Update::fromArray([
            'callback_query' => [
                'id'      => 'cbq-1',
                'from'    => ['id' => 10],
                'data'    => 'trial:start',
                'message' => [
                    'chat' => ['id' => 10],
                ],
            ],
        ]);

        $router->dispatch($update, new BotContext(new FakeTelegramClient()));

        $this->assertSame(1, $called);
    }

    #[Test]
    public function testOnActionHandlerInvoked(): void
    {
        $router = new UpdateRouter();

        $builder = new TelegramBotBuilder($router);

        $builder->register()->action()
            ->setName('open_trial')
            ->setType(ActionTypeEnum::CALLBACK)
            ->setValue('main.open.trial');

        $called = 0;
        $builder->onAction('open_trial', static function () use (&$called): void {
            $called++;
        });

        $update = Update::fromArray([
            'callback_query' => [
                'id'      => 'cbq-a1',
                'from'    => ['id' => 100],
                'data'    => 'main.open.trial',
                'message' => [
                    'chat' => ['id' => 100],
                ],
            ],
        ]);

        $router->dispatch($update, new BotContext(new FakeTelegramClient()));

        $this->assertSame(1, $called);
    }

    #[Test]
    public function testRegisterDefinitionsAndBuildInlineKeyboard(): void
    {
        $builder = new TelegramBotBuilder(new UpdateRouter());

        $builder->register()->action()
            ->setName('help_open')
            ->setType('callback')
            ->setValue('main.help.open');
        $builder->register()->action()
            ->setName('site_open')
            ->setType('url')
            ->setValue('https://example.com');

        $builder->register()->button()
            ->setName('help_button')
            ->setLabel('🆘 Помощь')
            ->setAction('help_open');
        $builder->register()->button()
            ->setName('site_button')
            ->setLabel('🌐 Сайт')
            ->setAction('site_open');

        $builder->register()->screen()
            ->setName('s04')
            ->setTitle('/start - активен триал')
            ->setText([
                '{first_name}, доступ уже активен.',
                '',
                'Подключите устройство.',
            ])
            ->setButtons([
                new ScreenButton($builder->provider()->getButton('help_button'), row: 1, position: 2),
                new ScreenButton($builder->provider()->getButton('site_button'), row: 1, position: 1),
            ]);

        $this->assertSame(
            "{first_name}, доступ уже активен.\n\nПодключите устройство.\n",
            $builder->renderScreenText('s04'),
        );

        $keyboard = $builder->inlineKeyboardForScreen('s04');
        $this->assertSame('🌐 Сайт', $keyboard['reply_markup']['inline_keyboard'][0][0]['text']);
        $this->assertSame('https://example.com', $keyboard['reply_markup']['inline_keyboard'][0][0]['url']);
        $this->assertSame('🆘 Помощь', $keyboard['reply_markup']['inline_keyboard'][0][1]['text']);
        $this->assertSame('main.help.open', $keyboard['reply_markup']['inline_keyboard'][0][1]['callback_data']);
    }

    #[Test]
    public function testScreenButtonsAutoPositionByAddOrder(): void
    {
        $builder = new TelegramBotBuilder(new UpdateRouter());

        $builder->register()->action()
            ->setName('first_action')
            ->setType('callback')
            ->setValue('main.first');
        $builder->register()->action()
            ->setName('second_action')
            ->setType('callback')
            ->setValue('main.second');

        $builder->register()->button()
            ->setName('first_button')
            ->setLabel('Первая')
            ->setAction('first_action');
        $builder->register()->button()
            ->setName('second_button')
            ->setLabel('Вторая')
            ->setAction('second_action');

        $builder->register()->screen()
            ->setName('s20')
            ->setTitle('Auto positions')
            ->setText('...')
            ->setButtons(['first_button', 'second_button']);

        $keyboard = $builder->inlineKeyboardForScreen('s20');

        $this->assertSame('Первая', $keyboard['reply_markup']['inline_keyboard'][0][0]['text']);
        $this->assertSame('Вторая', $keyboard['reply_markup']['inline_keyboard'][0][1]['text']);
    }

    #[Test]
    public function testScreenButtonsThrowsOnDuplicateExplicitPosition(): void
    {
        $builder = new TelegramBotBuilder(new UpdateRouter());

        $builder->register()->action()
            ->setName('action_1')
            ->setType('callback')
            ->setValue('main.a1');
        $builder->register()->action()
            ->setName('action_2')
            ->setType('callback')
            ->setValue('main.a2');

        $builder->register()->button()
            ->setName('button_1')
            ->setLabel('One')
            ->setAction('action_1');
        $builder->register()->button()
            ->setName('button_2')
            ->setLabel('Two')
            ->setAction('action_2');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate screen button position 1 in row 1.');

        $builder->register()->screen()
            ->setName('s30')
            ->setTitle('Duplicate positions')
            ->setText('...')
            ->setButtons([
                new ScreenButton($builder->provider()->getButton('button_1'), row: 1, position: 1),
                new ScreenButton($builder->provider()->getButton('button_2'), row: 1, position: 1),
            ]);
    }

    #[Test]
    public function testScreenImportFromPhpFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'psb_tg_screen_');
        self::assertIsString($tmp);
        $path = $tmp . '.php';
        file_put_contents(
            $path,
            <<<'PHP'
<?php
return [
    's10' => [
        'title' => 'Imported screen',
        'text' => [
            'Line 1',
            '',
            'Line 3',
        ],
    ],
];
PHP,
        );

        $builder = new TelegramBotBuilder(new UpdateRouter());

        $builder->register()->screen()->import($path);

        $this->assertSame("Line 1\n\nLine 3\n", $builder->renderScreenText('s10'));
        $this->assertNull($builder->renderScreenImage('s10'));

        @unlink($tmp);
        @unlink($path);
    }

    #[Test]
    public function testRenderScreenImageWithContext(): void
    {
        $builder = new TelegramBotBuilder(new UpdateRouter());

        $builder->register()->screen()
            ->setName('s11')
            ->setTitle('Image test')
            ->setText('...')
            ->setImage('https://cdn.example.com/covers/{cover}.jpg');

        $this->assertSame(
            'https://cdn.example.com/covers/main.jpg',
            $builder->renderScreenImage('s11', ['cover' => 'main']),
        );
    }

    #[Test]
    public function testScreenImportSupportsImage(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'psb_tg_screen_img_');
        self::assertIsString($tmp);
        $path = $tmp . '.php';
        file_put_contents(
            $path,
            <<<'PHP'
<?php
return [
    's12' => [
        'title' => 'Imported image screen',
        'text' => 'Hello',
        'image' => 'https://cdn.example.com/{name}.jpg',
    ],
];
PHP,
        );

        $builder = new TelegramBotBuilder(new UpdateRouter());

        $builder->register()->screen()->import($path);

        $this->assertSame(
            'https://cdn.example.com/banner.jpg',
            $builder->renderScreenImage('s12', ['name' => 'banner']),
        );

        @unlink($tmp);
        @unlink($path);
    }

    /**
     * Проверяет рендер текста экрана через markdown-шаблон с подстановкой контекста.
     */
    #[Test]
    public function testRenderScreenTextFromMarkdownTemplate(): void
    {
        $dir = sys_get_temp_dir() . '/psb_tg_templates_' . uniqid('', true);
        mkdir($dir, 0777, true);
        mkdir($dir . '/start', 0777, true);
        file_put_contents(
            $dir . '/start/new_user.md',
            <<<'MD'
Hello, {name}!

Trial days: {days}
MD,
        );

        $builder = new TelegramBotBuilder(new UpdateRouter());

        $builder->useMarkdownScreenTemplates($dir);
        $builder->register()->screen()
            ->setName('s40')
            ->setTitle('Template')
            ->setTextTemplate('start.new_user')
            ->setText('fallback');

        $this->assertSame(
            "Hello, Anton!\n\nTrial days: 3",
            $builder->renderScreenText('s40', ['name' => 'Anton', 'days' => 3]),
        );

        @unlink($dir . '/start/new_user.md');
        @rmdir($dir . '/start');
        @rmdir($dir);
    }

    /**
     * Проверяет, что import screen-definition поддерживает ключ `text_template`.
     */
    #[Test]
    public function testScreenImportSupportsTextTemplate(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'psb_tg_screen_tpl_');
        self::assertIsString($tmp);
        $path = $tmp . '.php';
        file_put_contents(
            $path,
            <<<'PHP'
<?php
return [
    's41' => [
        'title' => 'Imported template screen',
        'text' => 'unused',
        'text_template' => 'start.imported',
    ],
];
PHP,
        );

        $builder = new TelegramBotBuilder(new UpdateRouter());

        $builder->register()->screen()->import($path);

        $screen = $builder->screen('s41');
        self::assertNotNull($screen);
        $this->assertSame('start.imported', $screen->textTemplate);

        @unlink($tmp);
        @unlink($path);
    }

    #[Test]
    public function testScreenImportFromDirectoryLoadsPhpFiles(): void
    {
        $dir = sys_get_temp_dir() . '/psb_tg_screen_dir_' . uniqid('', true);
        mkdir($dir, 0777, true);

        file_put_contents(
            $dir . '/a.php',
            <<<'PHP'
<?php
return [
    's21' => [
        'title' => 'Screen A',
        'text' => 'A',
    ],
];
PHP,
        );
        file_put_contents(
            $dir . '/b.php',
            <<<'PHP'
<?php
return [
    's22' => [
        'title' => 'Screen B',
        'text' => 'B',
    ],
];
PHP,
        );

        $builder = new TelegramBotBuilder(new UpdateRouter());

        $builder->register()->screen()->import($dir);

        $this->assertSame('A', $builder->renderScreenText('s21'));
        $this->assertSame('B', $builder->renderScreenText('s22'));

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    #[Test]
    public function testActionAndButtonImportFromGlobPattern(): void
    {
        $dir = sys_get_temp_dir() . '/psb_tg_defs_' . uniqid('', true);
        mkdir($dir, 0777, true);

        file_put_contents(
            $dir . '/actions_1.php',
            <<<'PHP'
<?php
return [
    'a_first' => ['type' => 'callback', 'value' => 'main.first'],
];
PHP,
        );
        file_put_contents(
            $dir . '/actions_2.php',
            <<<'PHP'
<?php
return [
    'a_second' => ['type' => 'url', 'value' => 'https://example.com'],
];
PHP,
        );
        file_put_contents(
            $dir . '/buttons_1.php',
            <<<'PHP'
<?php
return [
    'b_first' => ['label' => 'Первая', 'action' => 'a_first'],
];
PHP,
        );
        file_put_contents(
            $dir . '/buttons_2.php',
            <<<'PHP'
<?php
return [
    'b_second' => ['label' => 'Вторая', 'action' => 'a_second'],
];
PHP,
        );

        $builder = new TelegramBotBuilder(new UpdateRouter());

        $builder->register()->action()->import($dir . '/actions_*.php');
        $builder->register()->button()->import($dir . '/buttons_*.php');
        $builder->register()->screen()
            ->setName('s23')
            ->setTitle('Imported defs')
            ->setText('...')
            ->setButtons(['b_first', 'b_second']);

        $keyboard = $builder->inlineKeyboardForScreen('s23');
        $this->assertSame('Первая', $keyboard['reply_markup']['inline_keyboard'][0][0]['text']);
        $this->assertSame('main.first', $keyboard['reply_markup']['inline_keyboard'][0][0]['callback_data']);
        $this->assertSame('Вторая', $keyboard['reply_markup']['inline_keyboard'][0][1]['text']);
        $this->assertSame('https://example.com', $keyboard['reply_markup']['inline_keyboard'][0][1]['url']);

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}

final class TelegramBotBuilderTestCommand
{
    public function __invoke(Update $update, BotContext $context): void
    {
        $context->sendMessage($update->chatId() ?? 0, 'pong');
    }
}
