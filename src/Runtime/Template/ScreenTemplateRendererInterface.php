<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime\Template;

interface ScreenTemplateRendererInterface
{
    /**
     * @param array<string, scalar|null> $context
     */
    public function render(string $template, array $context = []): string;
}
