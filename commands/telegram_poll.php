<?php

declare(strict_types=1);

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\Telegram\Cli\TelegramPollHandler;

use function PhpSoftBox\CliApp\flag;
use function PhpSoftBox\CliApp\opt;

return Command::define(
    name: 'telegram:poll',
    description: 'Запускает long-polling Telegram вместо вебхука',
    signature: [
        opt('bot', 'b', 'Имя бота из конфигурации', default: ''),
        opt('timeout', 't', 'Таймаут long polling (сек)', default: 25, type: 'int'),
        opt('sleep', 's', 'Пауза между запросами (сек)', default: 1, type: 'int'),
        opt('offset', 'o', 'Начальный offset', default: 0, type: 'int'),
        flag('debug', 'd', 'Показывать ход выполнения'),
        flag('all', 'a', 'Опросить всех ботов'),
        flag('once', null, 'Выполнить один запрос и завершиться'),
    ],
    handler: TelegramPollHandler::class,
);
