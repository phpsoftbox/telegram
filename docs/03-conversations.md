# Диалоги и сбор данных

Диалог — это последовательность шагов (вопросов), которые заполняют данные и сохраняются в стор. Шаг задает ключ, текст вопроса и опциональные парсер/валидатор.

```php
use PhpSoftBox\Telegram\Conversation\ConversationDefinition;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Conversation\QuestionStep;

$definition = new ConversationDefinition('workspace.create', [
    new QuestionStep('name', 'Название воркспейса?'),
    new QuestionStep('description', 'Описание воркспейса?'),
]);

$definition = $definition->withCancelKeywords(['/cancel', 'cancel']);

$conversations->register($definition);
$conversations->start('workspace.create', $update);
```

Если пользователь отправляет одно из ключевых слов отмены, диалог завершится и выполнит `onCancel`. По завершению диалога можно использовать `onFinish`, чтобы сохранить собранные данные.

## Очистка сообщений

Чтобы бот удалял предыдущие сообщения (например, поддерживать «чистый экран»), передайте `MessageCleaner` в `ConversationManager` и включите `withCleanupMessages(true)` в сценарии.

```php
use PhpSoftBox\Telegram\Support\MessageCleaner;

$cleaner = new MessageCleaner($client);
$conversations = new ConversationManager($store, $client, $cleaner);

$definition = (new ConversationDefinition('workspace.clean', [
    new QuestionStep('name', 'Название?'),
]))->withCleanupMessages(true);
```
