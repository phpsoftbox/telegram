<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime\Template;

interface ScreenTemplateRepositoryInterface
{
    public function get(string $templateId): string;
}
