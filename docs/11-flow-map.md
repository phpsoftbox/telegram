# Scenario Flow Map (CJM/Graph)

`ScenarioFlowMap` в компоненте дает единый API для:

- JSON-графа (для Inertia/React в админке),
- scoped CJM (ветки с hard-boundary),
- DOT/HTML-рендера (для CLI-дампа и локальной диагностики).

Классы:

- `ScenarioFlowMapService`
- `ScenarioFlowMapBranch`
- `ScenarioFlowMapCjm`

## Быстрый старт

```php
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapBranch;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapCjm;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapService;

$compiled = $scenario->compile();
$flowMap = new ScenarioFlowMapService();

$branch = new ScenarioFlowMapBranch(
    id: 'start.new_user',
    entryScreen: 'start.new_user',
    internalEvents: ['trial_start', 'subscription.open'],
    exitEvents: ['support_open', 'start_open'],
    exitScreens: ['subscription.choose'],
    description: 'CJM new user',
);

$cjm = new ScenarioFlowMapCjm(
    id: 'onboarding',
    branches: ['start.new_user'],
);
```

## JSON для React/Inertia

```php
$map = $flowMap->build($compiled, includeButtons: true);
$scoped = $flowMap->scope($map, 'branch:start.new_user', [$branch], [$cjm]);

return [
    'graph' => $scoped->toArray(),
];
```

Поведение `scope`:

- `all` -> полный граф,
- `branch:<id>` -> branch scope,
- `cjm:<id>` -> объединение branch scope внутри CJM,
- `<id>` -> branch scope, если branch с таким id существует; иначе cjm scope; иначе screen scope,
- `screen:<screen_id>` -> принудительный screen scope.

## DOT/HTML для консольного dump

```php
$dot = $flowMap->dot(
    scenario: $compiled,
    includeButtons: true,
    scope: 'cjm:onboarding',
    branches: [$branch],
    cjms: [$cjm],
    rankdir: 'TB',
);

$html = $flowMap->html(
    scenario: $compiled,
    includeButtons: true,
    scope: 'cjm:onboarding',
    branches: [$branch],
    cjms: [$cjm],
    rankdir: 'TB',
);
```

`html()` возвращает готовую страницу с pan/zoom и легендой.  
Если нужно использовать локальный runtime `Viz.js`, передай код скриптов аргументами `vizJsCode` и `vizRenderCode`.

## CLI команды

В компоненте есть команды:

- `telegram:flow-map`
- `telegram:flow-map:validate`

Они не читают app-конфиг напрямую. Для работы в DI нужно связать:

- `PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapRegistryResolverInterface`
- `PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapSettingsInterface`
