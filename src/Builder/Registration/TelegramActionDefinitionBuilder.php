<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Registration;

use Closure;
use PhpSoftBox\Telegram\Builder\Definitions\ActionDefinition;
use PhpSoftBox\Telegram\Builder\Definitions\ActionTypeEnum;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use RuntimeException;

use function is_array;
use function is_string;
use function trim;

final class TelegramActionDefinitionBuilder
{
    use DefinitionImportPathTrait;

    private string $name          = '';
    private ?ActionTypeEnum $type = null;
    private string $value         = '';

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

    public function setType(ActionTypeEnum|string $type): self
    {
        $resolved = $type instanceof ActionTypeEnum ? $type : ActionTypeEnum::tryFrom(trim($type));
        if (!$resolved instanceof ActionTypeEnum) {
            throw new RuntimeException('Unsupported action type: ' . (string) $type);
        }

        $this->type = $resolved;
        $this->persist();

        return $this;
    }

    public function setValue(string $value): self
    {
        $this->value = trim($value);
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
                throw new RuntimeException('Action import must return array or closure.');
            }

            foreach ($payload as $key => $item) {
                if (is_string($key) && is_array($item)) {
                    $this->builder->register()->action()
                        ->setName($key)
                        ->setType((string) ($item['type'] ?? ''))
                        ->setValue((string) ($item['value'] ?? ''));
                    continue;
                }

                if (is_array($item)) {
                    $name = trim((string) ($item['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $this->builder->register()->action()
                        ->setName($name)
                        ->setType((string) ($item['type'] ?? ''))
                        ->setValue((string) ($item['value'] ?? ''));
                }
            }
        }

        return $this;
    }

    private function persist(): void
    {
        if ($this->name === '' || !$this->type instanceof ActionTypeEnum || $this->value === '') {
            return;
        }

        $this->builder->defineAction(new ActionDefinition(
            name: $this->name,
            type: $this->type,
            value: $this->value,
        ));
    }
}
