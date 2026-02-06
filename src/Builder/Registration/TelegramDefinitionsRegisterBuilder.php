<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Registration;

use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;

final readonly class TelegramDefinitionsRegisterBuilder
{
    public function __construct(
        private TelegramBotBuilder $builder,
    ) {
    }

    public function screen(): TelegramScreenDefinitionBuilder
    {
        return new TelegramScreenDefinitionBuilder($this->builder);
    }

    public function button(): TelegramButtonDefinitionBuilder
    {
        return new TelegramButtonDefinitionBuilder($this->builder);
    }

    public function action(): TelegramActionDefinitionBuilder
    {
        return new TelegramActionDefinitionBuilder($this->builder);
    }
}
