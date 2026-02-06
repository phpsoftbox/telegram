# Entry Points (точки входа в сценарии)

`FlowTransition` описывает переходы внутри уже открытого экрана.  
`ScenarioEntryPoint` описывает, с какого узла начинается сценарий для внешнего события:

- `/start`
- callback от платежа
- fallback-событие
- кастомные entrypoint-сигналы

`ScenarioBuilder` принимает id entrypoint/action/screen в виде enum (интерфейсы `*IdInterface`), не строк.

Для пофайловой организации entrypoint-веток:

```php
$scenario->flows()->import(__DIR__ . '/flow');
```

Каждый файл в `flow/*.php` должен вернуть callable, который принимает `ScenarioFlowsBuilder`.

## Модель

- `ScenarioEntryPoint`
  - `name` — id точки входа (`command.start`, `payment.success`, `fallback.default`)
  - `target` — `FlowTarget::screen(...)` или `FlowTarget::action(...)`
  - `guardClass` + `guardArgs` — условие
  - `priority` — порядок проверки (меньше = раньше)

## DSL в ScenarioBuilder

```php
$scenario->entryPoint(EntryPointId::COMMAND_START, static function ($entryPoint): void {
    $entryPoint
        ->toScreen(ScreenId::START_NEW_USER)
        ->guard(StartAllowedGuard::class)
        ->priority(10);
});

$scenario->entryPoint(EntryPointId::PAYMENT_SUCCESS, static function ($entryPoint): void {
    $entryPoint
        ->toAction(ActionId::PAYMENT_SUCCESS_OPEN)
        ->priority(20);
});
```

## Разрешение entrypoint

Используй `ScenarioEntryPointEngine`:

```php
$engine = new ScenarioEntryPointEngine(
    $compiledScenario->entryPointsProvider(),
    $flowGuardRegistry,
);

$decision = $engine->decide('command.start', $transitionContext);
```

`$decision` вернет `target` (screen/action) и `guardId` для логирования.
