# Команды и обработчики

`UpdateRouter` умеет:

- роутить команды вида `/start` или `/workspace`;
- обрабатывать текстовые сообщения, когда команда не распознана;
- обрабатывать сообщения по типу (фото, видео, аудио и т.д.);
- отправлять нераспознанные события в `fallback`.

```php
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Update\MessageTypeEnum;

$router = new UpdateRouter();

$router->command('start', static function ($update, $context): void {
    $context->sendMessage($update->chatId(), 'Привет!');
});

$router->onText(static function ($update, $context): void {
    $context->sendMessage($update->chatId(), 'Текст получен.');
});

$router->onType(MessageTypeEnum::PHOTO, static function ($update, $context): void {
    $context->sendMessage($update->chatId(), 'Фото получено.');
});

$router->fallback(static function ($update, $context): void {
    $context->sendMessage($update->chatId(), 'Команда не найдена.');
});
```
