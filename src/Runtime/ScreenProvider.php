<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime;

use PhpSoftBox\Telegram\Runtime\Text\TextFormatter;
use RuntimeException;

use function is_array;
use function is_string;
use function trim;

final class ScreenProvider
{
    /**
     * @var array<string, BotScreen>|null
     */
    private ?array $screens = null;
    private TextFormatter $formatter;

    public function __construct(
        private readonly array $definitions = [],
        ?TextFormatter $formatter = null,
    ) {
        $this->formatter = $formatter ?? new TextFormatter();
    }

    public function getByName(string $name): BotScreen
    {
        $screen = $this->findByName($name);
        if ($screen instanceof BotScreen) {
            return $screen;
        }

        throw new RuntimeException('Telegram screen not found: ' . $name);
    }

    public function findByName(string $name): ?BotScreen
    {
        $normalized = $this->normalizeName($name);
        if ($normalized === null) {
            return null;
        }

        return $this->screens()[$normalized] ?? null;
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function render(string $name, array $context = []): string
    {
        $screen = $this->getByName($name);

        return $this->replaceContext($screen->getText(), $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function renderImage(string $name, array $context = []): ?string
    {
        $screen = $this->getByName($name);
        $image  = $screen->getImage();
        if ($image === null) {
            return null;
        }

        $image = $this->replaceContext($image, $context);
        $image = trim($image);

        return $image !== '' ? $image : null;
    }

    /**
     * @return array<string, BotScreen>
     */
    private function screens(): array
    {
        if (is_array($this->screens)) {
            return $this->screens;
        }

        $screens = [];
        foreach ($this->definitions as $name => $definition) {
            if (!is_string($name) || !is_array($definition)) {
                continue;
            }

            $normalized = $this->normalizeName($name);
            if ($normalized === null) {
                continue;
            }

            $title = trim((string) ($definition['title'] ?? ''));
            $text  = trim((string) ($definition['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $image = trim((string) ($definition['image'] ?? ''));
            if ($image === '') {
                $image = null;
            }

            $screen = new BotScreen(
                name: $normalized,
                title: $title,
                text: $text,
                image: $image,
            );

            $screens[$normalized] = $screen;
        }

        $this->screens = $screens;

        return $this->screens;
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function replaceContext(string $template, array $context = []): string
    {
        return $this->formatter->format($template, $context);
    }

    private function normalizeName(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return $name;
    }
}
