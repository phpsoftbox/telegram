# Webhook

`WebhookHandler` принимает PSR-7 запрос, парсит JSON update и возвращает JSON-ответ `{ "ok": true }`. При ошибке парсинга вернется `400` и сообщение об ошибке.

```php
use PhpSoftBox\Telegram\Webhook\WebhookHandler;

$handler = new WebhookHandler($bot, $responseFactory, $streamFactory);
$response = $handler->handle($request);
```
