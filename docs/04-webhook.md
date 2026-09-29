# Webhook

`WebhookHandler` принимает PSR-7 запрос, проверяет секрет, парсит JSON update и возвращает JSON-ответ `{ "ok": true }`.
При ошибке парсинга вернется `400` и сообщение об ошибке.

```php
use PhpSoftBox\Telegram\Webhook\WebhookHandler;

$handler = new WebhookHandler($bot, $telegramBot->webhookSecret(), $responseFactory, $streamFactory);
$response = $handler->handle($request);
```

## Секрет webhook

Telegram передаёт в каждом запросе заголовок `X-Telegram-Bot-Api-Secret-Token` со значением `secret_token`, заданным
в `setWebhook`. `WebhookHandler` сравнивает его с секретом из конструктора (`hash_equals`) и при отсутствии или
несовпадении отвечает `401`, не вызывая обработчик: без проверки любой, кто знает URL, может прислать поддельный update
от имени произвольного пользователя. Пустой секрет запрещён (`InvalidArgumentException`).

По умолчанию секрет выводится из токена бота (`WebhookSecret::fromBotToken()`, HMAC-SHA256, 64 hex-символа), отдельно
его хранить не нужно:

- `TelegramClient::webhookSecret()`, `TelegramBot::webhookSecret()`, `TelegramBotRegistry::webhookSecret($name)` —
  секрет для `WebhookHandler`;
- `TelegramClient::setWebhook()` (а значит, команды `telegram:webhook` и `telegram:sync --webhook`) добавляет его
  в `secret_token`, если в `$options` не передан свой.

Свой секрет: передайте его в `setWebhook($url, ['secret_token' => $secret])` и в конструктор `WebhookHandler`.
После смены токена бота секрет меняется — webhook нужно зарегистрировать заново.

## Регистрация webhook URL

Webhook URL нужно зарегистрировать через Telegram API один раз на каждый бот.
Для этого есть CLI-команда:

```bash
php psb telegram:webhook --bot=auth --base-url=https://example.com
```

По умолчанию путь берётся как `/telegram/{bot}/webhook`.
Это можно переопределить через `--path` или явно передать `--url`.
