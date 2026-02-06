# Webhook

`WebhookHandler` принимает PSR-7 запрос, парсит JSON update и возвращает JSON-ответ `{ "ok": true }`. При ошибке парсинга вернется `400` и сообщение об ошибке.

```php
use PhpSoftBox\Telegram\Webhook\WebhookHandler;

$handler = new WebhookHandler($bot, $responseFactory, $streamFactory);
$response = $handler->handle($request);
```

## Регистрация webhook URL

Webhook URL нужно зарегистрировать через Telegram API один раз на каждый бот.
Для этого есть CLI-команда:

```bash
php psb telegram:webhook --bot=auth --base-url=https://example.com
```

По умолчанию путь берётся как `/telegram/{bot}/webhook`.
Это можно переопределить через `--path` или явно передать `--url`.
