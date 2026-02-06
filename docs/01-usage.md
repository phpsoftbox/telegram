# Быстрый старт

Компонент состоит из клиента Telegram API, роутера обновлений и обработчика webhook. Клиент отвечает за вызовы API, роутер — за обработку входящих обновлений, а `WebhookHandler` принимает HTTP-запрос и передает его в бота.

```php
use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Bot\Bot;
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Webhook\WebhookHandler;

$client = new TelegramClient($token, $httpClient, $requestFactory, $streamFactory);
$router = new UpdateRouter();
$bot = new Bot($client, $router);
$handler = new WebhookHandler($bot, $responseFactory, $streamFactory);
```

Алгоритм работы:

1. Telegram отправляет webhook-запрос с update.
2. `WebhookHandler` парсит update и передает его в `Bot`.
3. `Bot` сначала проверяет активные диалоги, затем роутит в зарегистрированные команды и обработчики.
