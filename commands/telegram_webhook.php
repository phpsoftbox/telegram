<?php

declare(strict_types=1);

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\Telegram\Cli\TelegramWebhookHandler;

use function PhpSoftBox\CliApp\flag;
use function PhpSoftBox\CliApp\opt;

return Command::define(
    name: 'telegram:webhook',
    description: 'Регистрирует webhook URL для Telegram-бота',
    signature: [
        opt('bot', 'b', 'Имя бота из конфигурации (по умолчанию default)', default: ''),
        opt('url', 'u', 'Полный URL вебхука', default: ''),
        opt('base-url', null, 'Базовый URL, если URL не передан (например https://example.com)', default: ''),
        opt('path', null, 'Путь вебхука (по умолчанию /telegram/{bot}/webhook)', default: ''),
        flag('info', 'i', 'Показать текущую информацию о webhook'),
        flag('debug', 'd', 'Показывать подробности'),
    ],
    handler: TelegramWebhookHandler::class,
);
