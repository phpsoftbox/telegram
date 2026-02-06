# Работа с файлами

Telegram присылает `file_id` для медиа. Чтобы скачать файл на сервер, сначала вызовите `getFile`, затем используйте `downloadFile`.

```php
$fileId = $update->message()?->value();
if ($fileId !== null) {
    $client->downloadFile($fileId, __DIR__ . '/storage/file.bin');
}
```

`downloadFile()` делает два запроса: `getFile` для получения `file_path` и затем загрузку по адресу `https://api.telegram.org/file/bot<TOKEN>/<file_path>`.
