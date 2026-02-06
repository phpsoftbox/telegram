<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Bot;

use PhpSoftBox\Telegram\Update\Update;

final class NullUpdateHandler implements UpdateHandlerInterface
{
    public function handle(Update $update): void
    {
    }
}
