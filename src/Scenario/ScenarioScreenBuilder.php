<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Builder\Definitions\ActionTypeEnum;
use PhpSoftBox\Telegram\Scenario\Id\ActionIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ButtonGroupPresetIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ButtonPresetIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\CallbackEventIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ScreenIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\TransitionIdInterface;
use RuntimeException;

use function is_array;
use function is_scalar;
use function is_string;
use function preg_replace;
use function str_replace;
use function trim;

final class ScenarioScreenBuilder
{
    public function __construct(
        private readonly ScenarioBuilder $scenario,
        private readonly string $screenName,
    ) {
    }

    public function title(string $title): self
    {
        $this->scenario->setScreenTitle($this->screenName, trim($title));

        return $this;
    }

    public function description(string $description): self
    {
        $this->scenario->setScreenDescription($this->screenName, $description);

        return $this;
    }

    /**
     * @param string|list<scalar|null> $text
     */
    public function text(string|array $text): self
    {
        if (is_string($text)) {
            $this->scenario->setScreenText($this->screenName, $text);

            return $this;
        }

        if (!is_array($text)) {
            throw new RuntimeException('Screen text must be string or list of scalar lines.');
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

        $this->scenario->setScreenText($this->screenName, $normalized);

        return $this;
    }

    public function image(?string $image): self
    {
        $value = trim((string) ($image ?? ''));
        $this->scenario->setScreenImage($this->screenName, $value !== '' ? $value : null);

        return $this;
    }

    public function textTemplate(?string $templateId): self
    {
        $value = trim((string) ($templateId ?? ''));
        $this->scenario->setScreenTextTemplate($this->screenName, $value !== '' ? $value : null);

        return $this;
    }

    /**
     * @param callable(ScenarioRouteBuilder):void|null $flow
     */
    public function callbackButton(
        string $name,
        string $label,
        CallbackEventIdInterface|string $event,
        ?string $callbackData = null,
        int $row = 1,
        ?int $position = null,
        ?callable $flow = null,
        ?TransitionIdInterface $transition = null,
    ): self {
        $eventValue = is_string($event) ? trim($event) : trim($event->value);
        if ($eventValue === '') {
            throw new RuntimeException('Callback event id must not be empty.');
        }

        if ($flow !== null && $transition !== null) {
            throw new RuntimeException('Button cannot use both inline flow and transition id at the same time.');
        }

        $transitionValue = null;
        if ($transition !== null) {
            $transitionValue = trim($transition->value);
            if ($transitionValue === '') {
                throw new RuntimeException('Transition id must not be empty.');
            }
        }

        $callbackData = $callbackData !== null ? trim($callbackData) : $eventValue;
        if ($callbackData === '') {
            throw new RuntimeException('Callback data must not be empty.');
        }

        $routes = [];
        if ($flow !== null) {
            $routeBuilder = new ScenarioRouteBuilder();

            $flow($routeBuilder);
            $routes = $routeBuilder->routes();
        }

        $this->scenario->addScreenButton($this->screenName, [
            'name'          => trim($name),
            'label'         => trim($label),
            'row'           => $row,
            'position'      => $position,
            'transition_id' => $transitionValue,
            'action'        => [
                'name'  => $eventValue,
                'type'  => ActionTypeEnum::CALLBACK,
                'value' => $callbackData,
            ],
            'routes' => $routes,
        ]);

        return $this;
    }

    /**
     * @param array<string,mixed> $guardArgs
     */
    public function buttonToScreen(
        string $name,
        string $label,
        ScreenIdInterface $screenId,
        ?string $callbackData = null,
        int $row = 1,
        ?int $position = null,
        ?string $guardClass = null,
        array $guardArgs = [],
    ): self {
        return $this->callbackButton(
            name: $name,
            label: $label,
            event: $this->autoNavigationEvent($name),
            callbackData: $callbackData,
            row: $row,
            position: $position,
            flow: static function (ScenarioRouteBuilder $flow) use ($screenId, $guardClass, $guardArgs): void {
                $flow->toScreen($screenId, $guardClass, $guardArgs);
            },
        );
    }

    /**
     * @param array<string,mixed> $guardArgs
     */
    public function buttonToAction(
        string $name,
        string $label,
        ActionIdInterface $actionId,
        ?string $callbackData = null,
        int $row = 1,
        ?int $position = null,
        ?string $guardClass = null,
        array $guardArgs = [],
        ?ScreenIdInterface $expectedScreen = null,
    ): self {
        return $this->callbackButton(
            name: $name,
            label: $label,
            event: $this->autoNavigationEvent($name),
            callbackData: $callbackData,
            row: $row,
            position: $position,
            flow: static function (ScenarioRouteBuilder $flow) use (
                $actionId,
                $guardClass,
                $guardArgs,
                $expectedScreen,
            ): void {
                $flow->toAction($actionId, $guardClass, $guardArgs, $expectedScreen);
            },
        );
    }

    public function buttonToTransition(
        string $name,
        string $label,
        TransitionIdInterface $transition,
        ?string $callbackData = null,
        int $row = 1,
        ?int $position = null,
    ): self {
        $transitionValue = trim($transition->value);
        if ($transitionValue === '') {
            throw new RuntimeException('Transition id must not be empty.');
        }

        return $this->callbackButton(
            name: $name,
            label: $label,
            event: $transitionValue,
            callbackData: $callbackData,
            row: $row,
            position: $position,
            transition: $transition,
        );
    }

    /**
     * @param callable(ScenarioRouteBuilder):void|null $flow
     */
    public function presetButton(
        ButtonPresetIdInterface $preset,
        int $row = 1,
        ?int $position = null,
        ?callable $flow = null,
        ?string $name = null,
        ?string $label = null,
        ?TransitionIdInterface $transition = null,
    ): self {
        $definition = $this->scenario->getButtonPreset($preset);

        $eventValue  = trim((string) ($definition['event'] ?? ''));
        $presetValue = trim($preset->value);
        if ($eventValue === '') {
            throw new RuntimeException('Button preset "' . $presetValue . '" has empty event.');
        }
        $event = $definition['event_id'] ?? null;
        if (!$event instanceof CallbackEventIdInterface) {
            throw new RuntimeException('Button preset "' . $presetValue . '" has invalid event id.');
        }

        $buttonName = trim((string) ($name ?? ''));
        if ($buttonName === '') {
            $buttonName = $this->autoButtonName($eventValue);
        }

        $buttonLabel = trim((string) ($label ?? ($definition['label'] ?? '')));
        if ($buttonLabel === '') {
            throw new RuntimeException('Button preset "' . $presetValue . '" has empty label.');
        }

        $callbackData = trim((string) ($definition['callback_data'] ?? $eventValue));
        if ($callbackData === '') {
            throw new RuntimeException('Button preset "' . $presetValue . '" has empty callback data.');
        }

        return $this->callbackButton(
            name: $buttonName,
            label: $buttonLabel,
            event: $event,
            callbackData: $callbackData,
            row: $row,
            position: $position,
            flow: $flow,
            transition: $transition,
        );
    }

    /**
     * Alias for presetButton().
     *
     * @param callable(ScenarioRouteBuilder):void|null $flow
     */
    public function button(
        ButtonPresetIdInterface $preset,
        int $row = 1,
        ?int $position = null,
        ?callable $flow = null,
        ?string $name = null,
        ?string $label = null,
        ?TransitionIdInterface $transition = null,
    ): self {
        return $this->presetButton($preset, $row, $position, $flow, $name, $label, $transition);
    }

    public function buttonGroup(
        ButtonGroupPresetIdInterface $group,
        int $rowOffset = 0,
    ): self {
        if ($rowOffset < 0) {
            throw new RuntimeException('Button group row offset must be greater or equal to zero.');
        }

        foreach ($this->scenario->getButtonGroup($group) as $item) {
            $this->presetButton(
                preset: $item['preset'],
                row: $item['row'] + $rowOffset,
                position: $item['position'],
                name: $item['name'],
                label: $item['label'],
                transition: $item['transition'],
            );
        }

        return $this;
    }

    public function group(
        ButtonGroupPresetIdInterface $group,
        int $rowOffset = 0,
    ): self {
        return $this->buttonGroup($group, $rowOffset);
    }

    public function urlButton(
        string $name,
        string $label,
        string $actionName,
        string $url,
        int $row = 1,
        ?int $position = null,
    ): self {
        $url = trim($url);
        if ($url === '') {
            throw new RuntimeException('URL action value must not be empty.');
        }

        $this->scenario->addScreenButton($this->screenName, [
            'name'          => trim($name),
            'label'         => trim($label),
            'row'           => $row,
            'position'      => $position,
            'transition_id' => null,
            'action'        => [
                'name'  => trim($actionName),
                'type'  => ActionTypeEnum::URL,
                'value' => $url,
            ],
            'routes' => [],
        ]);

        return $this;
    }

    private function autoNavigationEvent(string $buttonName): string
    {
        return 'nav.' . $this->token($this->screenName) . '.' . $this->token($buttonName);
    }

    private function autoButtonName(string $event): string
    {
        $screen = $this->token($this->screenName);
        $event  = $this->token($event);

        return $screen . '__' . $event;
    }

    private function token(string $value): string
    {
        $value = trim($value);
        $value = str_replace('.', '_', $value);
        $value = (string) preg_replace('/[^a-zA-Z0-9_]+/', '_', $value);
        $value = trim($value, '_');

        return $value !== '' ? $value : 'button';
    }
}
