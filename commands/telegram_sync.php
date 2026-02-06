<?php

declare(strict_types=1);

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\Telegram\Cli\TelegramSyncHandler;

use function PhpSoftBox\CliApp\flag;
use function PhpSoftBox\CliApp\opt;

return Command::define(
    name: 'telegram:sync',
    description: 'Обновляет команды Telegram-бота',
    signature: [
        opt('bot', 'b', 'Имя бота'),
        flag('webhook', 'w', 'Обновить webhook'),
        opt('webhook-ip-address', null, 'Фиксированный IP для Telegram setWebhook (ip_address)'),
    ],
    handler: TelegramSyncHandler::class,
);
