<?php

declare(strict_types=1);

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\Telegram\Cli\TelegramSyncResetHandler;

use function PhpSoftBox\CliApp\opt;

return Command::define(
    name: 'telegram:sync:reset',
    description: 'Сбрасывает команды Telegram-бота',
    signature: [
        opt('bot', 'b', 'Имя бота'),
    ],
    handler: TelegramSyncResetHandler::class,
);

