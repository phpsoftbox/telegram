<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Support;

use PhpSoftBox\Telegram\Bot\UpdateHandlerInterface;
use PhpSoftBox\Telegram\Update\Update;

final class FakeUpdateHandler implements UpdateHandlerInterface
{
    private ?Update $lastUpdate = null;

    public function handle(Update $update): void
    {
        $this->lastUpdate = $update;
    }

    public function lastUpdate(): ?Update
    {
        return $this->lastUpdate;
    }
}
