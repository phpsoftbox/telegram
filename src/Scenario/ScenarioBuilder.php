<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use FilesystemIterator;
use PhpSoftBox\Telegram\Builder\Definitions\ActionDefinition;
use PhpSoftBox\Telegram\Builder\Definitions\ActionTypeEnum;
use PhpSoftBox\Telegram\Builder\Definitions\ButtonDefinition;
use PhpSoftBox\Telegram\Builder\Definitions\ScreenButton;
use PhpSoftBox\Telegram\Builder\Definitions\ScreenDefinition;
use PhpSoftBox\Telegram\Flow\FlowGuardInterface;
use PhpSoftBox\Telegram\Flow\FlowTarget;
use PhpSoftBox\Telegram\Flow\FlowTransition;
use PhpSoftBox\Telegram\Scenario\Id\ActionIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ButtonGroupPresetIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ButtonPresetIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\CallbackEventIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\EntryPointIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ScreenIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\TransitionIdInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function array_key_exists;
use function array_keys;
use function array_pop;
use function array_values;
use function count;
use function dirname;
use function getcwd;
use function glob;
use function in_array;
use function is_a;
use function is_array;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function ksort;
use function max;
use function preg_replace;
use function realpath;
use function rtrim;
use function sort;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function trim;

final class ScenarioBuilder
{
    /**
     * @var array<string, array{
     *   title:string,
     *   text:string,
     *   description:string,
     *   text_template:?string,
     *   image:?string,
     *   buttons:list<array{
     *     name:string,
     *     label:string,
     *     row:int,
     *     position:?int,
     *     transition_id:?string,
     *     action:array{name:string,type:ActionTypeEnum,value:string},
     *     routes:list<array{
     *       target:FlowTarget,
     *       guard_class:?string,
     *       guard_args:array<string,mixed>,
     *       expected_screen:?string
     *     }>
     *   }>
     * }>
     */
    private array $screens = [];

    /**
     * @var array<string, array{
     *   label:string,
     *   event:string,
     *   event_id:CallbackEventIdInterface,
     *   callback_data:string,
     *   description:string
     * }>
     */
    private array $buttonPresets = [];

    /**
     * @var array<string, list<array{
     *   preset:ButtonPresetIdInterface,
     *   row:int,
     *   position:?int,
     *   name:?string,
     *   label:?string,
     *   transition:?TransitionIdInterface
     * }>>
     */
    private array $buttonGroups = [];

    /**
     * @var array<string, array{
     *   description:string,
     *   routes:list<array{
     *     target:FlowTarget,
     *     guard_class:?string,
     *     guard_args:array<string,mixed>,
     *     expected_screen:?string
     *   }>
     * }>
     */
    private array $transitions = [];

    /**
     * @var array<string, ActionHandlerDefinition>
     */
    private array $actionHandlers = [];

    /**
     * @var array<string, string>
     */
    private array $actionDescriptions = [];

    /**
     * @var list<ScenarioEntryPoint>
     */
    private array $entryPoints = [];

    /**
     * @var list<string>
     */
    private array $importFileStack = [];

    /**
     * @param callable(ScenarioScreenBuilder):void|null $configure
     */
    public function screen(
        ScreenIdInterface $name,
        ?callable $configure = null,
        ?string $description = null,
    ): ScenarioScreenBuilder {
        $name = $this->normalizeScreenId($name);

        if (array_key_exists($name, $this->screens)) {
            throw new RuntimeException('Duplicate screen name: ' . $name);
        }

        $this->screens[$name] = [
            'title'         => '',
            'text'          => '',
            'description'   => trim((string) ($description ?? '')),
            'text_template' => null,
            'image'         => null,
            'buttons'       => [],
        ];

        $screenBuilder = new ScenarioScreenBuilder($this, $name);

        if ($configure !== null) {
            $configure($screenBuilder);
        }

        return $screenBuilder;
    }

    public function definitions(): ScenarioDefinitionsBuilder
    {
        return new ScenarioDefinitionsBuilder($this);
    }

    public function screens(): ScenarioScreensBuilder
    {
        return new ScenarioScreensBuilder($this);
    }

    public function flows(): ScenarioFlowsBuilder
    {
        return new ScenarioFlowsBuilder($this);
    }

    /**
     * @param callable(ScenarioTransitionBuilder):void|null $configure
     */
    public function transition(
        TransitionIdInterface $name,
        ?callable $configure = null,
        ?string $description = null,
    ): ScenarioTransitionBuilder {
        $name = $this->normalizeTransitionId($name);

        if (array_key_exists($name, $this->transitions)) {
            throw new RuntimeException('Duplicate transition name: ' . $name);
        }

        if (array_key_exists($name, $this->actionHandlers)) {
            throw new RuntimeException('Transition id conflicts with bound action: ' . $name);
        }

        $this->transitions[$name] = [
            'description' => trim((string) ($description ?? '')),
            'routes'      => [],
        ];

        $transitionBuilder = new ScenarioTransitionBuilder($this, $name);

        if ($configure !== null) {
            $configure($transitionBuilder);
        }

        return $transitionBuilder;
    }

    public function bindAction(
        ActionIdInterface $action,
        string $handlerClass,
        ?string $method = null,
        ?string $description = null,
    ): self {
        $action      = $this->normalizeActionId($action);
        $description = trim((string) ($description ?? ''));

        if (array_key_exists($action, $this->transitions)) {
            throw new RuntimeException('Action id conflicts with transition id: ' . $action);
        }

        $handlerClass = trim($handlerClass);
        if ($handlerClass === '') {
            throw new RuntimeException('Action handler class must not be empty.');
        }

        $method = trim((string) ($method ?? '__invoke'));
        if ($method === '') {
            throw new RuntimeException('Action handler method must not be empty.');
        }

        $definition = new ActionHandlerDefinition($action, $handlerClass, $method);
        $existing   = $this->actionHandlers[$action] ?? null;
        if ($existing instanceof ActionHandlerDefinition) {
            if ($existing->handlerClass !== $definition->handlerClass || $existing->method !== $definition->method) {
                throw new RuntimeException('Action handler conflict for action "' . $action . '".');
            }

            if (($this->actionDescriptions[$action] ?? '') !== $description) {
                throw new RuntimeException('Action description conflict for action "' . $action . '".');
            }

            return $this;
        }

        $this->actionHandlers[$action]     = $definition;
        $this->actionDescriptions[$action] = $description;

        return $this;
    }

    public function button(
        ButtonPresetIdInterface $preset,
        string $label,
        CallbackEventIdInterface $event,
        ?string $callbackData = null,
        ?string $description = null,
    ): self {
        $preset  = $this->normalizePresetId($preset);
        $eventId = $event;

        $label = trim($label);
        if ($label === '') {
            throw new RuntimeException('Button preset label must not be empty.');
        }

        $event = $this->normalizeEventId($eventId);

        $callbackData = $callbackData !== null ? trim($callbackData) : $event;
        if ($callbackData === '') {
            throw new RuntimeException('Button preset callback data must not be empty.');
        }

        $existing = $this->buttonPresets[$preset] ?? null;
        if (is_array($existing)) {
            if (
                ($existing['label'] ?? null) !== $label
                || ($existing['event'] ?? null) !== $event
                || ($existing['callback_data'] ?? null) !== $callbackData
                || ($existing['description'] ?? null) !== trim((string) ($description ?? ''))
            ) {
                throw new RuntimeException('Button preset conflict for "' . $preset . '".');
            }

            return $this;
        }

        $this->buttonPresets[$preset] = [
            'label'         => $label,
            'event'         => $event,
            'event_id'      => $eventId,
            'callback_data' => $callbackData,
            'description'   => trim((string) ($description ?? '')),
        ];

        return $this;
    }

    public function buttonPreset(
        ButtonPresetIdInterface $preset,
        string $label,
        CallbackEventIdInterface $event,
        ?string $callbackData = null,
        ?string $description = null,
    ): self {
        return $this->button($preset, $label, $event, $callbackData, $description);
    }

    /**
     * @param callable(ScenarioButtonGroupBuilder):void|null $configure
     */
    public function buttonGroup(
        ButtonGroupPresetIdInterface $group,
        ?callable $configure = null,
    ): ScenarioButtonGroupBuilder {
        $groupName = $this->normalizeButtonGroupId($group);

        if (array_key_exists($groupName, $this->buttonGroups)) {
            throw new RuntimeException('Duplicate button group preset: ' . $groupName);
        }

        $this->buttonGroups[$groupName] = [];

        $groupBuilder = new ScenarioButtonGroupBuilder($this, $groupName);

        if ($configure !== null) {
            $configure($groupBuilder);
        }

        return $groupBuilder;
    }

    /**
     * @param callable(ScenarioButtonGroupBuilder):void|null $configure
     */
    public function buttonGroupPreset(
        ButtonGroupPresetIdInterface $group,
        ?callable $configure = null,
    ): ScenarioButtonGroupBuilder {
        return $this->buttonGroup($group, $configure);
    }

    /**
     * @param callable(ScenarioEntryPointBuilder):void|null $configure
     */
    public function entryPoint(
        EntryPointIdInterface $name,
        ?callable $configure = null,
        ?string $description = null,
    ): ScenarioEntryPointBuilder {
        $name = $this->normalizeEntryPointId($name);

        $entryPointBuilder = new ScenarioEntryPointBuilder($this, $name, $description);

        if ($configure !== null) {
            $configure($entryPointBuilder);
        }

        if (!$entryPointBuilder->isRegistered()) {
            $entryPointBuilder->register();
        }

        return $entryPointBuilder;
    }

    public function compile(): CompiledScenario
    {
        $screens      = [];
        $buttons      = [];
        $actions      = [];
        $transitions  = [];
        $buttonGroups = [];

        foreach ($this->screens as $screenName => $screen) {
            $screenButtons  = [];
            $positionsByRow = [];
            foreach ($screen['buttons'] as $button) {
                $buttonName  = trim((string) ($button['name'] ?? ''));
                $buttonLabel = trim((string) ($button['label'] ?? ''));
                if ($buttonName === '' || $buttonLabel === '') {
                    throw new RuntimeException('Screen "' . $screenName . '" contains button with empty name or label.');
                }

                if (array_key_exists($buttonName, $buttons)) {
                    throw new RuntimeException('Duplicate button name: ' . $buttonName);
                }

                $row = $button['row'] ?? 1;
                if (!is_int($row) || $row < 1) {
                    throw new RuntimeException('Screen "' . $screenName . '" button "' . $buttonName . '" has invalid row.');
                }

                $position = $button['position'] ?? null;
                if ($position !== null) {
                    if (!is_int($position) || $position < 1) {
                        throw new RuntimeException(
                            'Screen "' . $screenName . '" button "' . $buttonName . '" has invalid position.',
                        );
                    }

                    if (array_key_exists($position, $positionsByRow[$row] ?? [])) {
                        throw new RuntimeException(
                            'Screen "' . $screenName . '" has duplicate button position '
                            . $position
                            . ' in row '
                            . $row
                            . '.',
                        );
                    }

                    $positionsByRow[$row][$position] = true;
                }

                $actionData = $button['action'] ?? null;
                if (!is_array($actionData)) {
                    throw new RuntimeException('Screen "' . $screenName . '" button "' . $buttonName . '" has invalid action.');
                }

                $actionName  = trim((string) ($actionData['name'] ?? ''));
                $actionType  = $actionData['type'] ?? null;
                $actionValue = trim((string) ($actionData['value'] ?? ''));
                if ($actionName === '' || $actionValue === '' || !$actionType instanceof ActionTypeEnum) {
                    throw new RuntimeException(
                        'Screen "' . $screenName . '" button "' . $buttonName . '" has invalid action definition.',
                    );
                }

                $this->ensureActionDefinition($actions, $actionName, $actionType, $actionValue);

                $buttons[$buttonName] = new ButtonDefinition(
                    name: $buttonName,
                    label: $buttonLabel,
                    action: $actionName,
                );

                $screenButtons[] = new ScreenButton($buttons[$buttonName], $row, $position);

                foreach ($this->resolveButtonRoutes($screenName, $buttonName, $actionName, $actionType, $button) as $route) {
                    $transitions[] = $this->compileFlowTransition($screenName, $buttonName, $actionName, $route);
                }
            }

            $screens[$screenName] = new ScreenDefinition(
                name: $screenName,
                title: $screen['title'],
                text: $screen['text'],
                image: $screen['image'],
                buttons: $screenButtons,
                textTemplate: $screen['text_template'],
            );
        }

        foreach ($this->buttonGroups as $groupName => $groupItems) {
            if ($groupItems === []) {
                $buttonGroups[$groupName] = [];

                continue;
            }

            $positionsByRow = [];
            $names          = [];
            $compiledGroup  = [];

            foreach ($groupItems as $index => $groupItem) {
                $preset = $groupItem['preset'] ?? null;
                if (!$preset instanceof ButtonPresetIdInterface) {
                    throw new RuntimeException('Button group "' . $groupName . '" item has invalid preset.');
                }

                $presetDefinition = $this->getButtonPreset($preset);

                $actionName  = trim((string) ($presetDefinition['event'] ?? ''));
                $actionValue = trim((string) ($presetDefinition['callback_data'] ?? ''));
                if ($actionName === '' || $actionValue === '') {
                    throw new RuntimeException('Button group "' . $groupName . '" references invalid button preset action.');
                }

                $this->ensureActionDefinition($actions, $actionName, ActionTypeEnum::CALLBACK, $actionValue);

                $label = trim((string) ($groupItem['label'] ?? ''));
                if ($label === '') {
                    $label = trim((string) ($presetDefinition['label'] ?? ''));
                }
                if ($label === '') {
                    throw new RuntimeException('Button group "' . $groupName . '" item has empty label.');
                }

                $name = trim((string) ($groupItem['name'] ?? ''));
                if ($name === '') {
                    $name = $this->autoGroupButtonName($groupName, $actionName);
                }
                if (array_key_exists($name, $names)) {
                    throw new RuntimeException('Button group "' . $groupName . '" has duplicate button name "' . $name . '".');
                }
                $names[$name] = true;

                $row = $groupItem['row'] ?? 1;
                if (!is_int($row) || $row < 1) {
                    throw new RuntimeException('Button group "' . $groupName . '" item has invalid row.');
                }

                $position = $groupItem['position'] ?? null;
                if ($position !== null) {
                    if (!is_int($position) || $position < 1) {
                        throw new RuntimeException('Button group "' . $groupName . '" item has invalid position.');
                    }

                    if (array_key_exists($position, $positionsByRow[$row] ?? [])) {
                        throw new RuntimeException(
                            'Button group "' . $groupName . '" has duplicate button position '
                            . $position
                            . ' in row '
                            . $row
                            . '.',
                        );
                    }
                } else {
                    $position = ($positionsByRow[$row] ?? []) !== []
                        ? (max(array_keys($positionsByRow[$row])) + 1)
                        : 1;
                }

                $positionsByRow[$row][$position] = true;

                $compiledGroup[] = [
                    'name'     => $name,
                    'label'    => $label,
                    'action'   => $actionName,
                    'row'      => $row,
                    'position' => $position,
                ];
            }

            $buttonGroups[$groupName] = $compiledGroup;
        }

        foreach ($this->entryPoints as $entryPoint) {
            if ($entryPoint->target->isScreen() && !array_key_exists($entryPoint->target->id, $screens)) {
                throw new RuntimeException(
                    'Entry point "' . $entryPoint->name . '" points to unknown screen "' . $entryPoint->target->id . '".',
                );
            }
        }

        ksort($screens);
        ksort($buttons);
        ksort($actions);
        ksort($this->actionHandlers);

        return new CompiledScenario(
            screens: $screens,
            buttons: $buttons,
            actions: $actions,
            transitions: $transitions,
            actionHandlers: $this->actionHandlers,
            entryPoints: $this->entryPoints,
            buttonGroups: $buttonGroups,
        );
    }

    /**
     * @internal
     */
    public function setScreenTitle(string $name, string $title): void
    {
        $screen               = $this->getScreen($name);
        $screen['title']      = $title;
        $this->screens[$name] = $screen;
    }

    /**
     * @internal
     */
    public function setScreenText(string $name, string $text): void
    {
        $screen               = $this->getScreen($name);
        $screen['text']       = $text;
        $this->screens[$name] = $screen;
    }

    /**
     * @internal
     */
    public function setScreenDescription(string $name, string $description): void
    {
        $screen                = $this->getScreen($name);
        $screen['description'] = trim($description);
        $this->screens[$name]  = $screen;
    }

    /**
     * @internal
     */
    public function setScreenTextTemplate(string $name, ?string $templateId): void
    {
        $screen                  = $this->getScreen($name);
        $screen['text_template'] = $templateId;
        $this->screens[$name]    = $screen;
    }

    /**
     * @internal
     */
    public function setScreenImage(string $name, ?string $image): void
    {
        $screen               = $this->getScreen($name);
        $screen['image']      = $image;
        $this->screens[$name] = $screen;
    }

    /**
     * @internal
     */
    public function setTransitionDescription(string $name, string $description): void
    {
        $transition                = $this->getTransition($name);
        $transition['description'] = trim($description);
        $this->transitions[$name]  = $transition;
    }

    /**
     * @param array{
     *   target:FlowTarget,
     *   guard_class:?string,
     *   guard_args:array<string,mixed>,
     *   expected_screen:?string
     * } $route
     * @internal
     */
    public function addTransitionRoute(string $name, array $route): void
    {
        $transition               = $this->getTransition($name);
        $transition['routes'][]   = $route;
        $this->transitions[$name] = $transition;
    }

    /**
     * @internal
     */
    public function addEntryPoint(ScenarioEntryPoint $entryPoint): void
    {
        $this->entryPoints[] = $entryPoint;
    }

    /**
     * @param array{
     *   name:string,
     *   label:string,
     *   row:int,
     *   position:?int,
     *   transition_id:?string,
     *   action:array{name:string,type:ActionTypeEnum,value:string},
     *   routes:list<array{
     *     target:FlowTarget,
     *     guard_class:?string,
     *     guard_args:array<string,mixed>,
     *     expected_screen:?string
     *   }>
     * } $button
     * @internal
     */
    public function addScreenButton(string $name, array $button): void
    {
        $screen               = $this->getScreen($name);
        $screen['buttons'][]  = $button;
        $this->screens[$name] = $screen;
    }

    /**
     * @param array{
     *   preset:ButtonPresetIdInterface,
     *   row:int,
     *   position:?int,
     *   name:?string,
     *   label:?string,
     *   transition:?TransitionIdInterface
     * } $item
     * @internal
     */
    public function addButtonGroupButton(string $groupName, array $item): void
    {
        if (!array_key_exists($groupName, $this->buttonGroups)) {
            throw new RuntimeException('Unknown button group preset "' . $groupName . '".');
        }

        $row = $item['row'] ?? 1;
        if (!is_int($row) || $row < 1) {
            throw new RuntimeException('Button group "' . $groupName . '" item has invalid row.');
        }

        $position = $item['position'] ?? null;
        if ($position !== null && (!is_int($position) || $position < 1)) {
            throw new RuntimeException('Button group "' . $groupName . '" item has invalid position.');
        }

        $name  = trim((string) ($item['name'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));

        $this->buttonGroups[$groupName][] = [
            'preset'     => $item['preset'],
            'row'        => $row,
            'position'   => $position,
            'name'       => $name !== '' ? $name : null,
            'label'      => $label !== '' ? $label : null,
            'transition' => $item['transition'] ?? null,
        ];
    }

    /**
     * @return array{label:string,event:string,event_id:CallbackEventIdInterface,callback_data:string,description:string}
     * @internal
     */
    public function getButtonPreset(ButtonPresetIdInterface $preset): array
    {
        $preset = $this->normalizePresetId($preset);

        $definition = $this->buttonPresets[$preset] ?? null;
        if (!is_array($definition)) {
            throw new RuntimeException('Unknown button preset "' . $preset . '".');
        }

        return $definition;
    }

    /**
     * @return list<array{
     *   preset:ButtonPresetIdInterface,
     *   row:int,
     *   position:?int,
     *   name:?string,
     *   label:?string,
     *   transition:?TransitionIdInterface
     * }>
     * @internal
     */
    public function getButtonGroup(ButtonGroupPresetIdInterface $group): array
    {
        $groupName  = $this->normalizeButtonGroupId($group);
        $definition = $this->buttonGroups[$groupName] ?? null;
        if (!is_array($definition)) {
            throw new RuntimeException('Unknown button group preset "' . $groupName . '".');
        }

        return $definition;
    }

    /**
     * @return list<string>
     * @internal
     */
    public function resolveImportPaths(string $path): array
    {
        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('Scenario import path must be non-empty.');
        }

        $resolvedPath = $this->resolveImportPath($path);

        if (is_file($resolvedPath)) {
            return [$this->resolveImportFilePath($resolvedPath)];
        }

        if (is_dir($resolvedPath)) {
            $files = $this->collectPhpFiles($resolvedPath);
            if ($files !== []) {
                return $files;
            }

            throw new ScenarioImportPathNotFoundException('Scenario import files not found: ' . $path);
        }

        if (!str_ends_with($resolvedPath, '.php') && is_file($resolvedPath . '.php')) {
            return [$this->resolveImportFilePath($resolvedPath . '.php')];
        }

        $globbed = glob($resolvedPath, 0) ?: [];
        $files   = [];
        foreach ($globbed as $file) {
            if (!is_string($file) || !is_file($file) || !str_ends_with($file, '.php')) {
                continue;
            }

            $files[] = $this->resolveImportFilePath($file);
        }

        $files = array_values($files);
        sort($files);

        if ($files !== []) {
            return $files;
        }

        throw new ScenarioImportPathNotFoundException('Scenario import files not found: ' . $path);
    }

    /**
     * @param callable(string):void $register
     * @internal
     */
    public function executeImportFile(string $file, callable $register): void
    {
        $resolvedFile = $this->resolveImportFilePath($file);
        if (in_array($resolvedFile, $this->importFileStack, true)) {
            throw new RuntimeException('Circular scenario import detected: ' . $resolvedFile);
        }

        $this->importFileStack[] = $resolvedFile;

        try {
            $register($resolvedFile);
        } finally {
            array_pop($this->importFileStack);
        }
    }

    private function normalizeScreenId(ScreenIdInterface $id): string
    {
        $value = trim($id->value);
        if ($value === '') {
            throw new RuntimeException('Screen id must not be empty.');
        }

        return $value;
    }

    private function normalizeActionId(ActionIdInterface $id): string
    {
        $value = trim($id->value);
        if ($value === '') {
            throw new RuntimeException('Action id must not be empty.');
        }

        return $value;
    }

    private function normalizeEventId(CallbackEventIdInterface $id): string
    {
        $value = trim($id->value);
        if ($value === '') {
            throw new RuntimeException('Event id must not be empty.');
        }

        return $value;
    }

    private function normalizeTransitionId(TransitionIdInterface $id): string
    {
        $value = trim($id->value);
        if ($value === '') {
            throw new RuntimeException('Transition id must not be empty.');
        }

        return $value;
    }

    private function normalizePresetId(ButtonPresetIdInterface $id): string
    {
        $value = trim($id->value);
        if ($value === '') {
            throw new RuntimeException('Button preset id must not be empty.');
        }

        return $value;
    }

    private function normalizeButtonGroupId(ButtonGroupPresetIdInterface $id): string
    {
        $value = trim($id->value);
        if ($value === '') {
            throw new RuntimeException('Button group preset id must not be empty.');
        }

        return $value;
    }

    private function normalizeEntryPointId(EntryPointIdInterface $id): string
    {
        $value = trim($id->value);
        if ($value === '') {
            throw new RuntimeException('Entry point id must not be empty.');
        }

        return $value;
    }

    private function resolveImportPath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        $currentFile = $this->importFileStack !== [] ? $this->importFileStack[count($this->importFileStack) - 1] : null;
        $baseDir     = is_string($currentFile) && $currentFile !== ''
            ? dirname($currentFile)
            : ((string) (getcwd() ?: ''));

        if ($baseDir === '') {
            throw new RuntimeException('Cannot resolve relative scenario import path: ' . $path);
        }

        return rtrim($baseDir, '/') . '/' . $path;
    }

    private function resolveImportFilePath(string $file): string
    {
        $realPath = realpath($file);
        if (is_string($realPath) && $realPath !== '' && is_file($realPath)) {
            return $realPath;
        }

        if (is_file($file)) {
            return $file;
        }

        throw new ScenarioImportPathNotFoundException('Scenario import file not found: ' . $file);
    }

    /**
     * @return list<string>
     */
    private function collectPhpFiles(string $dir): array
    {
        $files    = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $pathname = $item->getPathname();
            if (!str_ends_with($pathname, '.php')) {
                continue;
            }

            $files[] = $this->resolveImportFilePath($pathname);
        }

        sort($files);

        return array_values($files);
    }

    /**
     * @param array{
     *   name:string,
     *   label:string,
     *   row:int,
     *   position:?int,
     *   transition_id:?string,
     *   action:array{name:string,type:ActionTypeEnum,value:string},
     *   routes:list<array{
     *     target:FlowTarget,
     *     guard_class:?string,
     *     guard_args:array<string,mixed>,
     *     expected_screen:?string
     *   }>
     * } $button
     * @return list<array{
     *   target:FlowTarget,
     *   guard_class:?string,
     *   guard_args:array<string,mixed>,
     *   expected_screen:?string
     * }>
     */
    private function resolveButtonRoutes(
        string $screenName,
        string $buttonName,
        string $actionName,
        ActionTypeEnum $actionType,
        array $button,
    ): array {
        $routes       = $button['routes'] ?? [];
        $transitionId = trim((string) ($button['transition_id'] ?? ''));

        if ($transitionId === '' && array_key_exists($actionName, $this->transitions)) {
            $transitionId = $actionName;
        }

        if ($transitionId !== '' && $routes !== []) {
            throw new RuntimeException(
                'Screen "' . $screenName . '" button "' . $buttonName . '" mixes inline routes and transition id.',
            );
        }

        if ($transitionId !== '') {
            $transition = $this->transitions[$transitionId] ?? null;
            if (!is_array($transition)) {
                throw new RuntimeException(
                    'Screen "' . $screenName . '" button "' . $buttonName . '" references unknown transition "'
                    . $transitionId
                    . '".',
                );
            }

            $routes = $transition['routes'] ?? [];
            if (!is_array($routes)) {
                throw new RuntimeException('Transition "' . $transitionId . '" has invalid routes.');
            }
        }

        if ($routes !== [] && $actionType->value !== 'callback') {
            throw new RuntimeException(
                'Screen "' . $screenName . '" button "' . $buttonName . '" has flow routes but action is not callback.',
            );
        }

        return $routes;
    }

    /**
     * @param array{
     *   target:FlowTarget,
     *   guard_class:?string,
     *   guard_args:array<string,mixed>,
     *   expected_screen:?string
     * } $route
     */
    private function compileFlowTransition(string $screenName, string $buttonName, string $actionName, array $route): FlowTransition
    {
        $target = $route['target'] ?? null;
        if (!$target instanceof FlowTarget) {
            throw new RuntimeException(
                'Screen "' . $screenName . '" button "' . $buttonName . '" has route with invalid target.',
            );
        }

        if ($target->isScreen() && !array_key_exists($target->id, $this->screens)) {
            throw new RuntimeException(
                'Screen "' . $screenName . '" button "' . $buttonName . '" points to unknown screen "'
                . $target->id
                . '".',
            );
        }

        $guardClass = $route['guard_class'] ?? null;
        if ($guardClass !== null) {
            if (!is_string($guardClass) || trim($guardClass) === '') {
                throw new RuntimeException(
                    'Screen "' . $screenName . '" button "' . $buttonName . '" has invalid guard class.',
                );
            }

            if (!is_a($guardClass, FlowGuardInterface::class, true)) {
                throw new RuntimeException(
                    'Guard class "' . $guardClass . '" must implement ' . FlowGuardInterface::class . '.',
                );
            }
        }

        $guardArgs = $route['guard_args'] ?? [];
        if (!is_array($guardArgs)) {
            throw new RuntimeException(
                'Screen "' . $screenName . '" button "' . $buttonName . '" has invalid guard args.',
            );
        }

        $expectedScreen = $route['expected_screen'] ?? null;
        if ($expectedScreen !== null && !is_string($expectedScreen)) {
            throw new RuntimeException(
                'Screen "' . $screenName . '" button "' . $buttonName . '" has invalid expected screen.',
            );
        }

        return new FlowTransition(
            from: $screenName,
            event: $actionName,
            target: $target,
            guardClass: $guardClass,
            guardArgs: $guardArgs,
            expectedScreen: $expectedScreen ?? ($target->isScreen() ? $target->id : null),
        );
    }

    /**
     * @param array<string, ActionDefinition> $actions
     */
    private function ensureActionDefinition(
        array &$actions,
        string $actionName,
        ActionTypeEnum $actionType,
        string $actionValue,
    ): void {
        $existingAction = $actions[$actionName] ?? null;
        if ($existingAction instanceof ActionDefinition) {
            if ($existingAction->type !== $actionType || $existingAction->value !== $actionValue) {
                throw new RuntimeException('Action definition conflict for action "' . $actionName . '".');
            }

            return;
        }

        $actions[$actionName] = new ActionDefinition(
            name: $actionName,
            type: $actionType,
            value: $actionValue,
        );
    }

    private function autoGroupButtonName(string $groupName, string $event): string
    {
        return 'group_' . $this->token($groupName) . '__' . $this->token($event);
    }

    private function token(string $value): string
    {
        $value = trim($value);
        $value = str_replace('.', '_', $value);
        $value = (string) preg_replace('/[^a-zA-Z0-9_]+/', '_', $value);
        $value = trim($value, '_');

        return $value !== '' ? $value : 'button';
    }

    /**
     * @return array{
     *   title:string,
     *   text:string,
     *   description:string,
     *   text_template:?string,
     *   image:?string,
     *   buttons:list<array{
     *     name:string,
     *     label:string,
     *     row:int,
     *     position:?int,
     *     transition_id:?string,
     *     action:array{name:string,type:ActionTypeEnum,value:string},
     *     routes:list<array{
     *       target:FlowTarget,
     *       guard_class:?string,
     *       guard_args:array<string,mixed>,
     *       expected_screen:?string
     *     }>
     *   }>
     * }
     */
    private function getScreen(string $name): array
    {
        if (!array_key_exists($name, $this->screens)) {
            throw new RuntimeException('Unknown screen "' . $name . '".');
        }

        return $this->screens[$name];
    }

    /**
     * @return array{
     *   description:string,
     *   routes:list<array{
     *     target:FlowTarget,
     *     guard_class:?string,
     *     guard_args:array<string,mixed>,
     *     expected_screen:?string
     *   }>
     * }
     */
    private function getTransition(string $name): array
    {
        if (!array_key_exists($name, $this->transitions)) {
            throw new RuntimeException('Unknown transition "' . $name . '".');
        }

        return $this->transitions[$name];
    }
}
