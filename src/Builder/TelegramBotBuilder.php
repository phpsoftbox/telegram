<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder;

use Closure;
use PhpSoftBox\Telegram\Conversation\ConversationDefinition;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Update\MessageTypeEnum;
use PhpSoftBox\Telegram\Update\Update;
use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use RuntimeException;

use function class_exists;
use function get_debug_type;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function str_starts_with;
use function trim;

final class TelegramBotBuilder
{
    private ?string $conversationPrefix = null;
    private ?string $commandPrefix      = null;

    public function __construct(
        private readonly UpdateRouter $router,
        private readonly ?ConversationManager $conversations = null,
        private readonly ?ContainerInterface $container = null,
    ) {
    }

    public function router(): UpdateRouter
    {
        return $this->router;
    }

    public function conversations(): ?ConversationManager
    {
        return $this->conversations;
    }

    public function container(): ?ContainerInterface
    {
        return $this->container;
    }

    public function command(string $name, callable|string $handler, ?string $method = null): self
    {
        $name     = $this->applyCommandPrefix($name);
        $callable = $this->resolveHandler($handler, $method);
        $this->router->command($name, $callable);

        return $this;
    }

    public function onText(callable|string $handler, ?string $method = null): self
    {
        $callable = $this->resolveHandler($handler, $method);
        $this->router->onText($callable);

        return $this;
    }

    public function onType(MessageTypeEnum $type, callable|string $handler, ?string $method = null): self
    {
        $callable = $this->resolveHandler($handler, $method);
        $this->router->onType($type, $callable);

        return $this;
    }

    public function fallback(callable|string $handler, ?string $method = null): self
    {
        $callable = $this->resolveHandler($handler, $method);
        $this->router->fallback($callable);

        return $this;
    }

    public function conversation(string $name, ConversationDefinition|callable|string $definition, ?string $method = null): self
    {
        $name       = $this->applyConversationPrefix($name);
        $definition = $this->resolveConversationDefinition($name, $definition, $method);

        if ($this->conversations === null) {
            throw new RuntimeException('ConversationManager is not configured.');
        }

        $this->conversations->register($definition);

        return $this;
    }

    public function startConversation(string $name, Update $update): bool
    {
        if ($this->conversations === null) {
            return false;
        }

        return $this->conversations->start($name, $update);
    }

    public function group(string $prefix, callable $callback, bool $prefixCommands = false): self
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            $callback($this);

            return $this;
        }

        $prevConversationPrefix = $this->conversationPrefix;
        $prevCommandPrefix      = $this->commandPrefix;

        $this->conversationPrefix = $this->appendPrefix($prevConversationPrefix, $prefix, '.');
        if ($prefixCommands) {
            $this->commandPrefix = $this->appendPrefix($prevCommandPrefix, $prefix, '_');
        }

        $callback($this);

        $this->conversationPrefix = $prevConversationPrefix;
        $this->commandPrefix      = $prevCommandPrefix;

        return $this;
    }

    private function applyConversationPrefix(string $name): string
    {
        $prefix = $this->conversationPrefix;
        if ($prefix === null || $prefix === '') {
            return $name;
        }

        if (str_starts_with($name, $prefix . '.')) {
            return $name;
        }

        return $prefix . '.' . $name;
    }

    private function applyCommandPrefix(string $name): string
    {
        $prefix = $this->commandPrefix;
        if ($prefix === null || $prefix === '') {
            return $name;
        }

        if (str_starts_with($name, $prefix . '_')) {
            return $name;
        }

        return $prefix . '_' . $name;
    }

    private function appendPrefix(?string $current, string $prefix, string $separator): string
    {
        $prefix = trim($prefix, $separator);
        if ($current === null || $current === '') {
            return $prefix;
        }

        return $current . $separator . $prefix;
    }

    private function resolveHandler(callable|string $handler, ?string $method): callable
    {
        if (!is_string($handler)) {
            if (is_callable($handler)) {
                return $handler;
            }

            $type = get_debug_type($handler);

            throw new RuntimeException("Unsupported handler type: {$type}");
        }

        if (is_callable($handler) && !class_exists($handler)) {
            return $handler;
        }

        if (!class_exists($handler)) {
            throw new RuntimeException('Handler class not found: ' . $handler);
        }

        $instance = $this->resolveClass($handler);

        if ($method !== null) {
            if (is_callable([$instance, $method])) {
                return [$instance, $method];
            }

            throw new RuntimeException("Handler method not found: {$handler}::{$method}");
        }

        if (is_callable($instance)) {
            return $instance;
        }

        if (is_callable([$instance, 'handle'])) {
            return [$instance, 'handle'];
        }

        throw new RuntimeException('Handler is not callable: ' . $handler);
    }

    private function resolveConversationDefinition(
        string $name,
        ConversationDefinition|callable|string $definition,
        ?string $method,
    ): ConversationDefinition {
        if ($definition instanceof ConversationDefinition) {
            if ($definition->name() !== $name) {
                throw new RuntimeException(
                    'Conversation name mismatch: expected ' . $name . ', got ' . $definition->name(),
                );
            }

            return $definition;
        }

        if (is_callable($definition) && !is_string($definition)) {
            return $this->callDefinitionFactory($definition, $name);
        }

        if (is_string($definition)) {
            if (is_callable($definition) && !class_exists($definition)) {
                return $this->callDefinitionFactory($definition, $name);
            }

            if (!class_exists($definition)) {
                throw new RuntimeException('Conversation class not found: ' . $definition);
            }

            $factory = $this->resolveConversationFactory($definition, $method);

            return $this->callDefinitionFactory($factory, $name);
        }

        $type = get_debug_type($definition);

        throw new RuntimeException("Unsupported conversation definition: {$type}");
    }

    private function resolveConversationFactory(string $class, ?string $method): callable
    {
        if ($method !== null) {
            if (is_callable([$class, $method])) {
                return [$class, $method];
            }

            $instance = $this->resolveClass($class);
            if (is_callable([$instance, $method])) {
                return [$instance, $method];
            }

            throw new RuntimeException("Conversation factory not found: {$class}::{$method}");
        }

        if (is_callable([$class, 'build'])) {
            return [$class, 'build'];
        }

        $instance = $this->resolveClass($class);
        if (is_callable([$instance, 'build'])) {
            return [$instance, 'build'];
        }

        if (is_callable($instance)) {
            return $instance;
        }

        throw new RuntimeException('Conversation factory is not callable: ' . $class);
    }

    private function callDefinitionFactory(callable $factory, string $name): ConversationDefinition
    {
        $ref        = $this->reflectCallable($factory);
        $definition = $ref->getNumberOfParameters() > 0 ? $factory($name) : $factory();

        if (!$definition instanceof ConversationDefinition) {
            $type = get_debug_type($definition);

            throw new RuntimeException("Conversation factory must return ConversationDefinition, got {$type}.");
        }

        return $definition;
    }

    private function reflectCallable(callable $factory): ReflectionFunctionAbstract
    {
        if (is_array($factory)) {
            $target = $factory[0] ?? null;
            $method = $factory[1] ?? null;
            if (is_string($target) && is_string($method)) {
                return new ReflectionMethod($target, $method);
            }
            if (is_object($target) && is_string($method)) {
                return new ReflectionMethod($target, $method);
            }
        }

        if (is_object($factory) && !($factory instanceof Closure)) {
            return new ReflectionMethod($factory, '__invoke');
        }

        return new ReflectionFunction($factory);
    }

    private function resolveClass(string $class): object
    {
        if ($this->container !== null && $this->container->has($class)) {
            return $this->container->get($class);
        }

        return new $class();
    }
}
