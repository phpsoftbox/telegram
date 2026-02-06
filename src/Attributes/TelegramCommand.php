<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class TelegramCommand
{
    public function __construct(
        public string $name,
        public ?string $method = null,
    ) {
    }
}
