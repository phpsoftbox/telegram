<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder;

use Closure;
use PhpSoftBox\Telegram\Builder\Definitions\ActionDefinition;
use PhpSoftBox\Telegram\Builder\Definitions\ActionTypeEnum;
use PhpSoftBox\Telegram\Builder\Definitions\ButtonDefinition;
use PhpSoftBox\Telegram\Builder\Definitions\ScreenButton;
use PhpSoftBox\Telegram\Builder\Definitions\ScreenDefinition;
use PhpSoftBox\Telegram\Builder\Provider\TelegramDefinitionsProvider;
use PhpSoftBox\Telegram\Builder\Registration\TelegramDefinitionsRegisterBuilder;
use PhpSoftBox\Telegram\Conversation\ConversationDefinition;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Router\UpdateRouter;
use PhpSoftBox\Telegram\Runtime\Template\MarkdownScreenTemplateRenderer;
use PhpSoftBox\Telegram\Runtime\Template\MarkdownScreenTemplateRepository;
use PhpSoftBox\Telegram\Runtime\Template\ScreenTemplateRendererInterface;
use PhpSoftBox\Telegram\Runtime\Template\ScreenTemplateRepositoryInterface;
use PhpSoftBox\Telegram\Update\MessageTypeEnum;
use PhpSoftBox\Telegram\Update\Update;
use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use RuntimeException;

use function array_keys;
use function class_exists;
use function get_debug_type;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function ksort;
use function sprintf;
use function str_starts_with;
use function strtr;
use function trim;

final class TelegramBotBuilder
{
    private ?string $conversationPrefix                                  = null;
    private ?string $commandPrefix                                       = null;
    private ?ScreenTemplateRepositoryInterface $screenTemplateRepository = null;
    private ?ScreenTemplateRendererInterface $screenTemplateRenderer     = null;

    /**
     * @var array<string, ScreenDefinition>
     */
    private array $screenDefinitions = [];

    /**
     * @var array<string, ButtonDefinition>
     */
    private array $buttonDefinitions = [];

    /**
     * @var array<string, ActionDefinition>
     */
    private array $actionDefinitions                          = [];
    private ?TelegramDefinitionsProvider $definitionsProvider = null;

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

    public function setScreenTemplateRepository(ScreenTemplateRepositoryInterface $repository): self
    {
        $this->screenTemplateRepository = $repository;

        return $this;
    }

    public function setScreenTemplateRenderer(ScreenTemplateRendererInterface $renderer): self
    {
        $this->screenTemplateRenderer = $renderer;

        return $this;
    }

    public function useMarkdownScreenTemplates(string ...$directories): self
    {
        if ($directories === []) {
            throw new RuntimeException('At least one markdown templates directory is required.');
        }

        $this->screenTemplateRepository = new MarkdownScreenTemplateRepository($directories);

        $this->screenTemplateRenderer ??= new MarkdownScreenTemplateRenderer();

        return $this;
    }

    public function register(): TelegramDefinitionsRegisterBuilder
    {
        return new TelegramDefinitionsRegisterBuilder($this);
    }

    public function provider(): TelegramDefinitionsProvider
    {
        return $this->definitionsProvider ??= new TelegramDefinitionsProvider($this);
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

    public function onCallbackQuery(callable|string $handler, ?string $method = null): self
    {
        $callable = $this->resolveHandler($handler, $method);
        $this->router->onCallbackQuery($callable);

        return $this;
    }

    public function onCallbackData(string $data, callable|string $handler, ?string $method = null): self
    {
        $callable = $this->resolveHandler($handler, $method);
        $this->router->onCallbackData($data, $callable);

        return $this;
    }

    public function onAction(string $name, callable|string $handler, ?string $method = null): self
    {
        $definition = $this->action($name);
        if (!$definition instanceof ActionDefinition) {
            throw new RuntimeException('Action definition not found: ' . $name);
        }

        if ($definition->type !== ActionTypeEnum::CALLBACK) {
            throw new RuntimeException('Action must be callback for onAction(): ' . $name);
        }

        return $this->onCallbackData($definition->value, $handler, $method);
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

    public function defineScreen(ScreenDefinition $definition): self
    {
        $name = trim($definition->name);
        if ($name === '') {
            throw new RuntimeException('Screen name must be non-empty.');
        }

        $this->screenDefinitions[$name] = $definition;

        return $this;
    }

    public function defineButton(ButtonDefinition $definition): self
    {
        $name = trim($definition->name);
        if ($name === '') {
            throw new RuntimeException('Button name must be non-empty.');
        }

        $this->buttonDefinitions[$name] = $definition;

        return $this;
    }

    public function defineAction(ActionDefinition $definition): self
    {
        $name = trim($definition->name);
        if ($name === '') {
            throw new RuntimeException('Action name must be non-empty.');
        }

        $this->actionDefinitions[$name] = $definition;

        return $this;
    }

    public function screen(string $name): ?ScreenDefinition
    {
        $name = trim($name);

        return $name !== '' ? ($this->screenDefinitions[$name] ?? null) : null;
    }

    public function button(string $name): ?ButtonDefinition
    {
        $name = trim($name);

        return $name !== '' ? ($this->buttonDefinitions[$name] ?? null) : null;
    }

    public function action(string $name): ?ActionDefinition
    {
        $name = trim($name);

        return $name !== '' ? ($this->actionDefinitions[$name] ?? null) : null;
    }

    /**
     * @return array<string, ScreenDefinition>
     */
    public function screens(): array
    {
        $items = $this->screenDefinitions;
        ksort($items);

        return $items;
    }

    /**
     * @return array<string, ButtonDefinition>
     */
    public function buttons(): array
    {
        $items = $this->buttonDefinitions;
        ksort($items);

        return $items;
    }

    /**
     * @return array<string, ActionDefinition>
     */
    public function actions(): array
    {
        $items = $this->actionDefinitions;
        ksort($items);

        return $items;
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function renderScreenText(string $screenName, array $context = []): string
    {
        $screen = $this->screen($screenName);
        if (!$screen instanceof ScreenDefinition) {
            throw new RuntimeException('Screen definition not found: ' . $screenName);
        }

        $templateId = trim((string) ($screen->textTemplate ?? ''));
        if ($templateId !== '') {
            if (!$this->screenTemplateRepository instanceof ScreenTemplateRepositoryInterface) {
                throw new RuntimeException('Screen template repository is not configured.');
            }

            $template = $this->screenTemplateRepository->get($templateId);
            $renderer = $this->screenTemplateRenderer ?? new MarkdownScreenTemplateRenderer();

            return $renderer->render($template, $context);
        }

        return $this->replaceContext($screen->text, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function renderScreenImage(string $screenName, array $context = []): ?string
    {
        $screen = $this->screen($screenName);
        if (!$screen instanceof ScreenDefinition) {
            throw new RuntimeException('Screen definition not found: ' . $screenName);
        }

        $image = trim((string) ($screen->image ?? ''));
        if ($image === '') {
            return null;
        }

        $image = $this->replaceContext($image, $context);
        $image = trim($image);

        return $image !== '' ? $image : null;
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function replaceContext(string $template, array $context = []): string
    {
        if ($context === []) {
            return $template;
        }

        $replace = [];
        foreach ($context as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $replace['{' . $key . '}'] = trim((string) ($value ?? ''));
        }

        return strtr($template, $replace);
    }

    /**
     * @return array<string, mixed>
     */
    public function inlineKeyboardForScreen(string $screenName): array
    {
        $screen = $this->screen($screenName);
        if (!$screen instanceof ScreenDefinition || $screen->buttons === []) {
            return [];
        }

        $rows            = [];
        $rowNextPosition = [];
        foreach ($screen->buttons as $item) {
            if ($item instanceof ScreenButton) {
                $screenButton = $item;
            } elseif ($item instanceof ButtonDefinition) {
                $screenButton = new ScreenButton($item);
            } elseif (is_string($item)) {
                $button = $this->button($item);
                if (!$button instanceof ButtonDefinition) {
                    continue;
                }

                $screenButton = new ScreenButton($button);
            } else {
                continue;
            }

            $row = $screenButton->row > 0 ? $screenButton->row : 1;
            if (!isset($rows[$row])) {
                $rows[$row]            = [];
                $rowNextPosition[$row] = 1;
            }

            $action = $this->action($screenButton->button->action);
            if (!$action instanceof ActionDefinition) {
                throw new RuntimeException('Action definition not found: ' . $screenButton->button->action);
            }

            $position = $screenButton->position;
            if ($position !== null) {
                if ($position < 1) {
                    throw new RuntimeException(
                        sprintf('Screen "%s" has invalid button position %d.', $screenName, $position),
                    );
                }

                if (isset($rows[$row][$position])) {
                    throw new RuntimeException(
                        sprintf(
                            'Screen "%s" has duplicate button position %d in row %d.',
                            $screenName,
                            $position,
                            $row,
                        ),
                    );
                }

                $rows[$row][$position] = [$screenButton->button, $action];
                if ($position >= $rowNextPosition[$row]) {
                    $rowNextPosition[$row] = $position + 1;
                }

                continue;
            }

            while (isset($rows[$row][$rowNextPosition[$row]])) {
                $rowNextPosition[$row]++;
            }

            $rows[$row][$rowNextPosition[$row]] = [$screenButton->button, $action];
            $rowNextPosition[$row]++;
        }

        if ($rows === []) {
            return [];
        }

        ksort($rows);

        $inlineRows = [];
        foreach ($rows as $row) {
            ksort($row);

            $inlineRow = [];
            foreach ($row as [$button, $action]) {
                if ($action->type === ActionTypeEnum::URL) {
                    $inlineRow[] = [
                        'text' => $button->label,
                        'url'  => $action->value,
                    ];
                    continue;
                }

                $inlineRow[] = [
                    'text'          => $button->label,
                    'callback_data' => $action->value,
                ];
            }

            $inlineRows[] = $inlineRow;
        }

        return [
            'reply_markup' => [
                'inline_keyboard' => $inlineRows,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function screenNames(): array
    {
        return array_keys($this->screens());
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
