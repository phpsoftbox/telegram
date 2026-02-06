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
        opt('bot', 'b', 'Имя бота', default: 'account'),
        flag('webhook', 'w', 'Обновить webhook'),
    ],
    handler: TelegramSyncHandler::class,
);
