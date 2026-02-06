# Telegram

## About

`phpsoftbox/telegram` — компонент для построения Telegram-ботов: команды, обработчики, диалоги (Q&A) и веб-хук.

Ключевые возможности:

- подключение к Telegram API и работа с webhook;
- роутинг команд и сообщений по типу;
- диалоги с вопросами, отменой и сбором данных;
- авторизация через Telegram Login Widget;
- очистка сообщений для «чистого экрана».

## Quick Start

Основная идея: `Bot` принимает update, сначала пытается продолжить активный диалог, затем отдает управление `UpdateRouter`.

```php
use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Bot\Bot;
use PhpSoftBox\Telegram\Conversation\ArrayConversationStore;
use PhpSoftBox\Telegram\Conversation\ConversationDefinition;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Conversation\QuestionStep;
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Webhook\WebhookHandler;

$client = new TelegramClient(
    token: $_ENV['TELEGRAM_BOT_TOKEN'],
    httpClient: $httpClient,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory,
);

$router = new UpdateRouter();
$router->command('start', static function ($update, $context): void {
    $context->sendMessage($update->chatId(), 'Привет!');
});

$store = new ArrayConversationStore();
$conversations = new ConversationManager($store, $client);
$conversations->register(new ConversationDefinition('workspace.create', [
    new QuestionStep('name', 'Название воркспейса?'),
    new QuestionStep('description', 'Описание воркспейса?'),
]));

$router->command('workspace', static function ($update, $context) use ($conversations): void {
    $conversations->start('workspace.create', $update);
});

$bot = new Bot($client, $router, $conversations);
$handler = new WebhookHandler($bot, $responseFactory, $streamFactory);

$response = $handler->handle($request);
```

## Команды и обработчики

Команды и хендлеры регистрируются в `UpdateRouter`. Можно слушать текстовые сообщения, типы сообщений и fallback.

```php
use PhpSoftBox\Telegram\Update\MessageTypeEnum;

$router->command('start', fn ($update, $context) => ...);
$router->onText(fn ($update, $context) => ...);
$router->onType(MessageTypeEnum::PHOTO, fn ($update, $context) => ...);
$router->fallback(fn ($update, $context) => ...);
```

## Конфигурация бота

Если команд много, используйте `TelegramBotBuilder` и описывайте команды по файлам в `config/telegram` или через атрибуты.

```php
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;

return static function (TelegramBotBuilder $bot): void {
    $bot->command('start', StartCommand::class);
    $bot->command('reset', ResetCommand::class);
};
```

### Несколько ботов

Можно держать несколько ботов, разделяя их по имени в конфиге (`auth`, `main`, `news`) и указывая `default`.

## Диалоги (Q&A)

Диалоги строятся из последовательности шагов. Шаг может парсить и валидировать ответ пользователя.

```php
$definition = new ConversationDefinition('workspace.edit', [
    new QuestionStep('name', 'Новое название?'),
    new QuestionStep('description', 'Новое описание?'),
]);
$definition = $definition->withCancelKeywords(['/cancel', 'cancel']);

$conversations->register($definition);
$conversations->start('workspace.edit', $update);
```

## Авторизация через Telegram

Авторизация вынесена в отдельный пакет `phpsoftbox/telegram-auth`.

## Файлы

Если в update приходит `file_id` (фото, видео, документ), файл можно скачать через `downloadFile`:

```php
$fileId = $update->message()?->value();
if ($fileId !== null) {
    $client->downloadFile($fileId, __DIR__ . '/storage/file.bin');
}
```

## Оглавление

- [Документация](docs/index.md)
- [CLI (long polling)](docs/07-cli.md)

## Дополнения

- `phpsoftbox/telegram-auth` — авторизация через Telegram Login Widget.
- `phpsoftbox/telegram-mongo` — MongoDB-хранилище диалогов.
