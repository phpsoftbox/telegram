<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime\Text;

use Closure;

use function is_callable;
use function is_scalar;
use function trim;

final class ContentVariableRegistry
{
    /**
     * @var array<string, callable|scalar|null>
     */
    private array $variables = [];

    /**
     * @param callable|scalar|null $resolver
     */
    public function register(string $name, mixed $resolver): self
    {
        $name = trim($name);
        if ($name === '') {
            return $this;
        }

        if (!is_callable($resolver) && !is_scalar($resolver) && $resolver !== null) {
            return $this;
        }

        $this->variables[$name] = $resolver;

        return $this;
    }

    /**
     * @param array<string, callable|scalar|null> $variables
     */
    public function registerMany(array $variables): self
    {
        foreach ($variables as $name => $resolver) {
            $this->register((string) $name, $resolver);
        }

        return $this;
    }

    /**
     * @param array<string, scalar|null> $context
     * @return array<string, scalar|null>
     */
    public function resolve(array $context = []): array
    {
        $resolved = $context;

        foreach ($this->variables as $name => $resolver) {
            if (isset($resolved[$name])) {
                continue;
            }

            if (is_callable($resolver)) {
                $value = $this->invokeResolver($resolver, $context);
            } else {
                $value = $resolver;
            }

            if (is_scalar($value) || $value === null) {
                $resolved[$name] = $value;
            }
        }

        return $resolved;
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function invokeResolver(callable $resolver, array $context): mixed
    {
        if ($resolver instanceof Closure) {
            return $resolver($context);
        }

        return $resolver($context);
    }
}
