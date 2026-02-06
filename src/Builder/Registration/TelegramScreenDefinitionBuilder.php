<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Registration;

use Closure;
use PhpSoftBox\Telegram\Builder\Definitions\ButtonDefinition;
use PhpSoftBox\Telegram\Builder\Definitions\ScreenButton;
use PhpSoftBox\Telegram\Builder\Definitions\ScreenDefinition;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use RuntimeException;

use function get_debug_type;
use function is_array;
use function is_scalar;
use function is_string;
use function preg_replace;
use function sprintf;
use function trim;

final class TelegramScreenDefinitionBuilder
{
    use DefinitionImportPathTrait;

    private string $name          = '';
    private string $title         = '';
    private string $text          = '';
    private ?string $image        = null;
    private ?string $textTemplate = null;

    /**
     * @var list<ScreenButton>
     */
    private array $buttons = [];

    public function __construct(
        private readonly TelegramBotBuilder $builder,
    ) {
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);
        $this->persist();

        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);
        $this->persist();

        return $this;
    }

    public function setImage(?string $image): self
    {
        $value       = trim((string) ($image ?? ''));
        $this->image = $value !== '' ? $value : null;
        $this->persist();

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setTextTemplate(?string $template): self
    {
        $value              = trim((string) ($template ?? ''));
        $this->textTemplate = $value !== '' ? $value : null;
        $this->persist();

        return $this;
    }

    public function getTextTemplate(): ?string
    {
        return $this->textTemplate;
    }

    /**
     * @param string|list<scalar|null> $text
     */
    public function setText(string|array $text): self
    {
        if (is_string($text)) {
            $this->text = $text;
            $this->persist();

            return $this;
        }

        $normalized = '';
        foreach ($text as $line) {
            if (!is_scalar($line) && $line !== null) {
                continue;
            }

            $value = (string) ($line ?? '');
            $value = (string) preg_replace('/\R/u', '', $value);
            $normalized .= $value . "\n";
        }

        $this->text = $normalized;
        $this->persist();

        return $this;
    }

    public function setButtons(array $buttons): self
    {
        $this->buttons = [];
        foreach ($buttons as $button) {
            $screenButton = $this->toScreenButton($button);
            if (!$screenButton instanceof ScreenButton) {
                continue;
            }

            $this->assertButtonPositionUnique($screenButton, $this->buttons);
            $this->buttons[] = $screenButton;
        }

        $this->persist();

        return $this;
    }

    public function addButton(ScreenButton|ButtonDefinition|string $button, int $row = 1, ?int $position = null): self
    {
        $screenButton = $button instanceof ScreenButton
            ? $this->normalizeScreenButton($button)
            : $this->toScreenButton([
                'button'   => $button,
                'row'      => $row,
                'position' => $position,
            ]);

        if ($screenButton instanceof ScreenButton) {
            $this->assertButtonPositionUnique($screenButton, $this->buttons);
            $this->buttons[] = $screenButton;
        }

        $this->persist();

        return $this;
    }

    public function import(string $path): self
    {
        $paths = $this->resolveImportPaths($path);

        foreach ($paths as $importPath) {
            $payload = require $importPath;

            if ($payload instanceof Closure) {
                $payload($this, $this->builder->register());

                continue;
            }

            if (!is_array($payload)) {
                throw new RuntimeException('Screen import must return array or closure.');
            }

            foreach ($payload as $key => $item) {
                if (is_string($key) && is_array($item)) {
                    $this->builder->register()->screen()
                        ->setName($key)
                        ->setTitle((string) ($item['title'] ?? ''))
                        ->setText($item['text'] ?? '')
                        ->setTextTemplate(isset($item['text_template']) ? (string) $item['text_template'] : null)
                        ->setImage(isset($item['image']) ? (string) $item['image'] : null)
                        ->setButtons(is_array($item['buttons'] ?? null) ? $item['buttons'] : []);
                    continue;
                }

                if (is_array($item)) {
                    $name = trim((string) ($item['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $this->builder->register()->screen()
                        ->setName($name)
                        ->setTitle((string) ($item['title'] ?? ''))
                        ->setText($item['text'] ?? '')
                        ->setTextTemplate(isset($item['text_template']) ? (string) $item['text_template'] : null)
                        ->setImage(isset($item['image']) ? (string) $item['image'] : null)
                        ->setButtons(is_array($item['buttons'] ?? null) ? $item['buttons'] : []);
                }
            }
        }

        return $this;
    }

    private function persist(): void
    {
        if ($this->name === '') {
            return;
        }

        $this->builder->defineScreen(new ScreenDefinition(
            name: $this->name,
            title: $this->title,
            text: $this->text,
            image: $this->image,
            buttons: $this->buttons,
            textTemplate: $this->textTemplate,
        ));
    }

    private function toScreenButton(mixed $item): ?ScreenButton
    {
        if ($item instanceof ScreenButton) {
            return $this->normalizeScreenButton($item);
        }

        if ($item instanceof ButtonDefinition) {
            return new ScreenButton($item);
        }

        if (is_string($item)) {
            $name = trim($item);
            if ($name === '') {
                return null;
            }

            return new ScreenButton($this->builder->provider()->getButton($name));
        }

        if (!is_array($item)) {
            throw new RuntimeException('Unsupported screen button type: ' . get_debug_type($item));
        }

        $button = $item['button'] ?? $item['name'] ?? null;
        if (!$button instanceof ScreenButton && !$button instanceof ButtonDefinition && !is_string($button)) {
            throw new RuntimeException('Screen button payload must contain "button" as name or ButtonDefinition.');
        }

        $resolvedButton = $button instanceof ScreenButton
            ? $button->button
            : ($button instanceof ButtonDefinition ? $button : $this->builder->provider()->getButton(trim($button)));

        $row = (int) ($item['row'] ?? 1);
        if ($row < 1) {
            $row = 1;
        }

        $position = $item['position'] ?? null;
        if ($position !== null) {
            $position = (int) $position;
            if ($position < 1) {
                throw new RuntimeException('Screen button position must be greater than zero.');
            }
        }

        return new ScreenButton(
            button: $resolvedButton,
            row: $row,
            position: $position,
        );
    }

    /**
     * @param list<ScreenButton> $current
     */
    private function assertButtonPositionUnique(ScreenButton $candidate, array $current): void
    {
        if ($candidate->position === null) {
            return;
        }

        foreach ($current as $existing) {
            if (
                $existing->row === $candidate->row
                && $existing->position !== null
                && $existing->position === $candidate->position
            ) {
                throw new RuntimeException(
                    sprintf(
                        'Duplicate screen button position %d in row %d.',
                        $candidate->position,
                        $candidate->row,
                    ),
                );
            }
        }
    }

    private function normalizeScreenButton(ScreenButton $button): ScreenButton
    {
        $row = $button->row > 0 ? $button->row : 1;

        if ($button->position !== null && $button->position < 1) {
            throw new RuntimeException('Screen button position must be greater than zero.');
        }

        return new ScreenButton(
            button: $button->button,
            row: $row,
            position: $button->position,
        );
    }
}
