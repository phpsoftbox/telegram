<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime\Template;

use PhpSoftBox\Telegram\Runtime\Text\TextFormatter;

final readonly class MarkdownScreenTemplateRenderer implements ScreenTemplateRendererInterface
{
    private TextFormatter $formatter;

    public function __construct(?TextFormatter $formatter = null)
    {
        $this->formatter = $formatter ?? new TextFormatter();
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function render(string $template, array $context = []): string
    {
        return $this->formatter->format($template, $context);
    }
}
