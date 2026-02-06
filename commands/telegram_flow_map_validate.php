<?php

declare(strict_types=1);

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\Telegram\Cli\TelegramFlowMapValidateHandler;

use function PhpSoftBox\CliApp\flag;
use function PhpSoftBox\CliApp\opt;

return Command::define(
    name: 'telegram:flow-map:validate',
    description: 'Валидация flow-map definitions для scope branch/cjm',
    signature: [
        opt('scope', 's', 'Scope type: branch|cjm', required: true),
        opt('id', 'i', 'Branch/CJM id (если не указан — валидировать все в scope)', required: false),
        opt('bot', 'b', 'Имя бота (по умолчанию из DI)', required: false),
        flag('strict', null, 'Считать warning как ошибку'),
    ],
    handler: TelegramFlowMapValidateHandler::class,
);

