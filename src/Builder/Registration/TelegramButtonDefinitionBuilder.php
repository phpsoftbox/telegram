<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Registration;

use Closure;
use PhpSoftBox\Telegram\Builder\Definitions\ButtonDefinition;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use RuntimeException;

use function is_array;
use function is_string;
use function trim;

final class TelegramButtonDefinitionBuilder
{
    use DefinitionImportPathTrait;

    private string $name   = '';
    private string $label  = '';
    private string $action = '';

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

    public function setLabel(string $label): self
    {
        $this->label = trim($label);
        $this->persist();

        return $this;
    }

    public function setAction(string $action): self
    {
        $this->action = trim($action);
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
                throw new RuntimeException('Button import must return array or closure.');
            }

            foreach ($payload as $key => $item) {
                if (is_string($key) && is_array($item)) {
                    $this->builder->register()->button()
                        ->setName($key)
                        ->setLabel((string) ($item['label'] ?? ''))
                        ->setAction((string) ($item['action'] ?? ''));
                    continue;
                }

                if (is_array($item)) {
                    $name = trim((string) ($item['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $this->builder->register()->button()
                        ->setName($name)
                        ->setLabel((string) ($item['label'] ?? ''))
                        ->setAction((string) ($item['action'] ?? ''));
                }
            }
        }

        return $this;
    }

    private function persist(): void
    {
        if ($this->name === '' || $this->label === '' || $this->action === '') {
            return;
        }

        $this->builder->defineButton(new ButtonDefinition(
            name: $this->name,
            label: $this->label,
            action: $this->action,
        ));
    }
}
