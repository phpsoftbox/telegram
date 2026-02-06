<?php

declare(strict_types=1);

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\Telegram\Cli\TelegramFlowMapHandler;

use function PhpSoftBox\CliApp\opt;

return Command::define(
    name: 'telegram:flow-map',
    description: 'Генерация flow map Telegram-бота для выбранного branch/cjm',
    signature: [
        opt('scope', 's', 'Scope type: branch|cjm', required: true),
        opt('id', 'i', 'Branch/CJM id', required: true),
        opt('bot', 'b', 'Имя бота (по умолчанию из DI)', required: false),
        opt('format', 'f', 'Формат вывода: html|json', required: false),
        opt('out', 'o', 'Путь сохранения файла (по умолчанию из DI)', required: false),
    ],
    handler: TelegramFlowMapHandler::class,
);

