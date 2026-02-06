# Scenario Builder (экранные ветки как DSL)

`ScenarioBuilder` добавляет декларативный слой над текущими definitions + flow.

Цель:

- описывать ветки рядом с экраном (`screen -> button -> guard -> target`);
- явно описывать точки входа в сценарий (`entrypoint -> guard -> target`);
- компилировать это в стандартные артефакты:
  - `ScreenDefinition`
  - `ButtonDefinition`
  - `ActionDefinition`
  - `list<FlowTransition>`
  - `list<ScenarioEntryPoint>`
  - `actionHandlers` map

Важно: в текущем API id передаются только типизированно (enum, реализующий интерфейсы пакета), а не строками. Это дает Ctrl+Click-навигацию и уменьшает риск опечаток.

Для callback-событий можно использовать как `ActionId`, так и `TransitionId`.

Также есть секционные билдеры и импорт по файлам:

```php
$scenario = new ScenarioBuilder();

$scenario->definitions()->import(__DIR__ . '/definition.php');
$scenario->screens()->import(__DIR__ . '/screen');
$scenario->flows()->import(__DIR__ . '/flow');
```

Импорт ожидает, что `.php` вернет callable с одним аргументом:
- `ScenarioDefinitionsBuilder` для `definitions()->import(...)`
- `ScenarioScreensBuilder` для `screens()->import(...)`
- `ScenarioFlowsBuilder` для `flows()->import(...)`

## Базовый пример

```php
use App\Service\Telegram\Main\Flow\Guard\SubscriptionHasActiveGuard;
use App\Service\Telegram\Main\Flow\Guard\SubscriptionHasAnyGuard;
use App\Service\Telegram\Main\Flow\Guard\TrialCanActivateGuard;
use App\Service\Telegram\Main\Flow\Guard\TrialIsDisabledGuard;
use App\Telegram\Main\Scenario\Definition\ActionId;
use App\Telegram\Main\Scenario\Definition\ButtonPresetId;
use App\Telegram\Main\Scenario\Definition\EntryPointId;
use App\Telegram\Main\Scenario\Definition\ScreenId;
use App\Telegram\Main\ConnectCommand;
use App\Telegram\Main\SubscriptionCommand;
use PhpSoftBox\Telegram\Scenario\ScenarioBuilder;

$scenario = new ScenarioBuilder();

$scenario->screen(ScreenId::START_NEW_USER, static function ($screen): void {
    $screen
        ->title('/start — новый пользователь')
        ->textTemplate('start.new_user')
        ->callbackButton(
            name: 's02_trial_start',
            label: '🎁 Попробовать 3 дня бесплатно',
            event: ActionId::TRIAL_START,
            callbackData: 'trial_start',
            flow: static function ($flow): void {
                $flow->toAction(ActionId::SUBSCRIPTION_OPEN, SubscriptionHasActiveGuard::class);
                $flow->toAction(ActionId::TRIAL_DISABLED, TrialIsDisabledGuard::class);
                $flow->toAction(ActionId::TRIAL_ALREADY_USED, SubscriptionHasAnyGuard::class);
                $flow->toAction(ActionId::TRIAL_ACTIVATE, TrialCanActivateGuard::class);
            },
        );
});

$scenario->entryPoint(EntryPointId::COMMAND_START, static function ($entryPoint): void {
    $entryPoint
        ->toScreen(ScreenId::START_NEW_USER)
        ->guard(TrialCanActivateGuard::class)
        ->priority(10);
});

$scenario->bindAction(ActionId::TRIAL_START, ConnectCommand::class);
$scenario->bindAction(ActionId::SUBSCRIPTION_OPEN, SubscriptionCommand::class);

$compiled = $scenario->compile();
```

## Пресеты кнопок (чтобы не дублировать label/event/name)

Если одна и та же кнопка встречается в нескольких экранах, вынеси её в пресет:

```php
$scenario->button(
    preset: ButtonPresetId::COMMON_HELP,
    label: '🆘 Помощь',
    event: ActionId::SUPPORT_OPEN,
);

$scenario->screen(ScreenId::START_NEW_USER, static function ($screen): void {
    $screen->button(ButtonPresetId::COMMON_HELP, row: 3, position: 1);
});
```

Что происходит:

- `label/event/callbackData` берутся из пресета;
- `name` генерируется автоматически как `<screen>__<event>`.
  - пример: `start.new_user` + `support_open` -> `start_new_user__support_open`
- при необходимости можно переопределить `name`/`label` прямо в `presetButton(...)`.

Дальше compiled-сценарий можно применить прямо в конфигурации бота:

```php
$compiled->registerDefinitions($bot);     // screens + buttons + actions
$compiled->registerActionHandlers($bot);  // onAction(...) для callback actions
```

## Runtime-dispatch для `toAction(...)` в flow

`registerActionHandlers()` покрывает обычные callback-события action'ов.  
Для переходов flow `target = action` (через `toAction(...)`) используйте runtime-реестр:

```php
use PhpSoftBox\Telegram\Runtime\ActionHandlerRegistry;

$handlers = new ActionHandlerRegistry(
    definitions: $compiled->actionHandlersMap(),
    container: $container, // optional
);

// ...
if ($decision->target->isAction()) {
    $handlers->dispatch($decision->target->id, $update, $context);
}
```

Реестр резолвит callables один раз при создании и дальше только диспетчит.

## Группы кнопок (ButtonGroupPreset)

Когда один и тот же набор кнопок повторяется между экранами, можно собрать его в группу:

```php
use App\Telegram\Main\Scenario\Definition\ButtonGroupPresetId;
use App\Telegram\Main\Scenario\Definition\ButtonPresetId;

$scenario->definitions()->buttonGroup(
    ButtonGroupPresetId::TRIAL_PLATFORMS,
    static function ($group): void {
        $group
            ->button(ButtonPresetId::PLATFORM_IPHONE, row: 1, position: 1)
            ->button(ButtonPresetId::PLATFORM_ANDROID, row: 1, position: 2)
            ->button(ButtonPresetId::PLATFORM_WINDOWS, row: 2, position: 1)
            ->button(ButtonPresetId::PLATFORM_MACOS, row: 2, position: 2);
    },
);

$scenario->screen(ScreenId::TRIAL_ACTIVATED, static function ($screen): void {
    $screen
        ->textTemplate('trial.activated')
        ->buttonGroup(ButtonGroupPresetId::TRIAL_PLATFORMS, rowOffset: 0);
});
```

Что это даёт:

- единое место для состава набора кнопок;
- меньше риска перепутать event/label/position на каждом экране;
- `rowOffset` позволяет переиспользовать группу с сдвигом по строкам.

### Runtime-композиция меню из группы

`compile()` теперь отдает `buttonGroupsProviderDefinitions()`, которые можно передать в runtime и собрать меню с модификаторами:

```php
use PhpSoftBox\Telegram\Runtime\ActionRegistry;
use PhpSoftBox\Telegram\Runtime\ButtonGroupMenuComposer;
use PhpSoftBox\Telegram\Runtime\ButtonGroupProvider;

$groups = new ButtonGroupProvider($compiled->buttonGroupsProviderDefinitions());
$actions = new ActionRegistry($registry->actionProviderDefinitions());
$composer = new ButtonGroupMenuComposer($groups, $actions);

$menu = $composer
    ->forGroup('instructions.platform')
    ->withoutNames('help', 'back')
    ->appendCallback('platform_ios', '📱 iOS', 'instruction.platform.ios', row: 1, position: 1)
    ->appendCallback('platform_android', '🤖 Android', 'instruction.platform.android', row: 1, position: 2)
    ->appendCallback('skip', '⏭ Пропустить инструкции', 'start_open', row: 2, position: 1)
    ->build();
```

Поддерживаемые модификаторы:

- `withRowOffset(int $offset)`
- `withoutNames(string ...$names)`
- `withoutActions(string ...$actions)`
- `appendCallback(...)`
- `appendUrl(...)`

## Навигация без action-handler (рекомендуемый путь для простых экранов)

Для простой навигации больше не нужно заводить отдельный handler/action на каждый переход.

Есть два варианта:

1) Кнопка сразу в экран:

```php
$scenario->screen(ScreenId::START_NEW_USER, static function ($screen): void {
    $screen->buttonToScreen(
        name: 'start_to_help',
        label: '🆘 Помощь',
        screenId: ScreenId::SUPPORT_MAIN,
        row: 1,
        position: 1,
    );
});
```

2) Переиспользуемый transition в definitions:

```php
$scenario->definitions()
    ->transition(TransitionId::OPEN_SUPPORT, static function ($transition): void {
        $transition->toScreen(ScreenId::SUPPORT_MAIN);
    })
    ->button(
        ButtonPresetId::COMMON_HELP,
        '🆘 Помощь',
        TransitionId::OPEN_SUPPORT, // event = transition id
    );
```

```php
$scenario->screen(ScreenId::START_NEW_USER, static function ($screen): void {
    $screen->button(ButtonPresetId::COMMON_HELP, row: 1, position: 1);
});
```

`bindAction(...)` нужен только когда действительно есть бизнес-логика (side-effects), а не просто переход между экранами.

## Что проверяется при compile

- дубли screen/button;
- конфликт action-определений (одно имя action, но разные callback/url значения);
- невалидные `row/position` в кнопках;
- route на неизвестный экран;
- guard-класс, не реализующий `FlowGuardInterface`.
- `expectedScreen` должен быть строкой (для `toScreen` всегда фиксируется как target screen).
- entrypoint с `toScreen(...)` обязан ссылаться на существующий экран.

## Экранные шаблоны (Markdown)

`ScreenDefinition` теперь поддерживает `textTemplate` (id шаблона).  
Сам шаблон хранится в `.md` файлах и рендерится отдельно.

```php
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;

$bot->useMarkdownScreenTemplates(__DIR__ . '/screens');

$bot->register()->screen()
    ->setName('s02')
    ->setTitle('/start')
    ->setTextTemplate('start.new_user');
```

Правила поиска шаблона `start.new_user`:

- `<dir>/start/new_user.md`
- `<dir>/start/new_user/index.md`
