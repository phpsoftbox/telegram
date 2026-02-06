<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Update\Update;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function class_exists;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function method_exists;
use function trim;

final class ActionHandlerRegistry
{
    /**
     * @var array<string, callable>
     */
    private array $handlers = [];

    /**
     * @param array<string, array{class:string,method:string}> $definitions
     */
    public function __construct(
        array $definitions = [],
        ?ContainerInterface $container = null,
    ) {
        foreach ($definitions as $action => $definition) {
            if (!is_string($action) || !is_array($definition)) {
                continue;
            }

            $action = trim($action);
            if ($action === '') {
                continue;
            }

            $class = trim((string) ($definition['class'] ?? ''));
            if ($class === '') {
                throw new RuntimeException('Action handler class must not be empty for action: ' . $action);
            }

            $method = trim((string) ($definition['method'] ?? '__invoke'));
            if ($method === '') {
                $method = '__invoke';
            }

            $this->handlers[$action] = $this->resolveCallable($class, $method, $container);
        }
    }

    public function has(string $action): bool
    {
        $action = trim($action);
        if ($action === '') {
            return false;
        }

        return array_key_exists($action, $this->handlers);
    }

    public function dispatch(string $action, Update $update, BotContext $context): bool
    {
        $action = trim($action);
        if ($action === '') {
            return false;
        }

        $handler = $this->handlers[$action] ?? null;
        if (!is_callable($handler)) {
            return false;
        }

        $handler($update, $context);

        return true;
    }

    private function resolveCallable(string $class, string $method, ?ContainerInterface $container): callable
    {
        $instance = $this->resolveClass($class, $container);
        if (!is_object($instance)) {
            throw new RuntimeException('Action handler instance is invalid for class: ' . $class);
        }

        if (!method_exists($instance, $method)) {
            throw new RuntimeException('Action handler method not found: ' . $class . '::' . $method);
        }

        return [$instance, $method];
    }

    private function resolveClass(string $class, ?ContainerInterface $container): object
    {
        if ($container !== null) {
            try {
                $resolved = $container->get($class);
                if (is_object($resolved)) {
                    return $resolved;
                }
            } catch (Throwable) {
                // fallback to direct instantiation below
            }
        }

        if (!class_exists($class)) {
            throw new RuntimeException('Action handler class not found: ' . $class);
        }

        return new $class();
    }
}
