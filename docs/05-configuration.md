# Конфигурация и сборка бота

Если команд много, удобнее описывать их по файлам и собирать через `TelegramBotBuilder`.

## Конфигурация по файлам

Создайте папку `config/telegram` и разбейте команды по смыслу.

`config/telegram/auth.php`:

```php
use App\Telegram\Auth\StartCommand;
use App\Telegram\Auth\ResetCommand;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use PhpSoftBox\Telegram\Update\MessageTypeEnum;

return static function (TelegramBotBuilder $bot): void {
    $bot->command('start', StartCommand::class);
    $bot->command('reset', ResetCommand::class);
    $bot->onType(MessageTypeEnum::CONTACT, AuthContactHandler::class);
};
```

Можно группировать команды:

```php
$bot->group('admin', static function (TelegramBotBuilder $bot): void {
    $bot->command('stats', AdminStats::class);
    $bot->command('users', AdminUsers::class);
}, prefixCommands: true);
```

## Атрибуты

Если удобнее держать команды рядом с кодом, используйте атрибуты:

```php
use PhpSoftBox\Telegram\Attributes\TelegramCommand;
use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Update\Update;

#[TelegramCommand('start')]
final class StartCommand
{
    public function __invoke(Update $update, BotContext $context): void
    {
        $context->sendMessage($update->chatId(), 'Привет!');
    }
}
```

## Подключение в приложении

В приложении обычно подключают оба варианта:

```php
$builder = new TelegramBotBuilder($router, $conversations, $container);
(new TelegramConfigLoader($configDir))->load($builder);
(new TelegramAttributeLoader($sourceDir))->load($builder);
```

## Несколько ботов

Если нужно несколько ботов (например, `auth`, `main`, `news`), удобно хранить конфиг по именам.

```php
return [
    'telegram' => [
        'default' => 'auth',
        'bots' => [
            'auth' => [
                'token' => env('TELEGRAM_AUTH_BOT_TOKEN', ''),
                'config_path' => 'config/telegram/auth.php',
                'source_path' => 'src/Telegram/Auth',
            ],
            'main' => [
                'token' => env('TELEGRAM_MAIN_BOT_TOKEN', ''),
                'config_path' => 'config/telegram/main.php',
                'source_path' => 'src/Telegram/Main',
            ],
        ],
    ],
];
```
