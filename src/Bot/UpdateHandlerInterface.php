<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Bot;

use PhpSoftBox\Telegram\Update\Update;

interface UpdateHandlerInterface
{
    public function handle(Update $update): void;
}
