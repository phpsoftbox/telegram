# Flow Engine (переходы и guard-условия)

Если у бота много экранов и ветвлений, удобно выносить переходы в единый реестр.

`FlowEngine` в `phpsoftbox/telegram` решает переходы по состоянию:

1. Берет список переходов из `FlowDefinitionsProviderInterface`.
2. Фильтрует по `from + event`.
3. Для каждого кандидата проверяет guard через `FlowGuardRegistryInterface` (по классу guard-а).
4. Возвращает первый подходящий `FlowDecision`.

## Базовые классы

- `FlowEngine`
- `FlowDecision`
- `TransitionContext`
- `FlowDefinitionsProviderInterface`
- `FlowGuardInterface`
- `FlowGuardRegistryInterface`
- `FlowTransition`
- `FlowTarget`
- `ArrayFlowDefinitionsProvider`
- `ArrayFlowGuardRegistry`

## Формат transition

```php
FlowTransition::screen(
    from: 's02',
    event: 'trial_start',
    screenId: 's16',
    guardClass: TrialCanActivateGuard::class,
    guardArgs: ['min_days' => 1], // optional
    expectedScreen: 's16', // optional, ожидаемый финальный экран после выполнения action/screen
)
```

- `ArrayFlowDefinitionsProvider` принимает `list<FlowTransition>`.
- `target` внутри transition — `FlowTarget`, тип задается enum `FlowTargetsEnum`.
- `guardClass` — класс guard-а (`FlowGuardInterface`).
- `guard.getId()` — стабильный id для логирования и визуализации.
- `guardArgs` — статический payload из схемы переходов.
- `expectedScreen` — ожидаемый экран на выходе transition (для screen-target по умолчанию = `target.id`).
- Множественные ожидаемые экраны больше не поддерживаются: один transition описывает один ожидаемый экран.
- динамический payload передается через `TransitionContext::$payload`.

## Пример использования

```php
use PhpSoftBox\Telegram\Flow\ArrayFlowDefinitionsProvider;
use PhpSoftBox\Telegram\Flow\ArrayFlowGuardRegistry;
use PhpSoftBox\Telegram\Flow\FlowEngine;
use PhpSoftBox\Telegram\Flow\FlowGuardInterface;
use PhpSoftBox\Telegram\Flow\FlowTransition;
use PhpSoftBox\Telegram\Flow\TransitionContext;

$definitions = new ArrayFlowDefinitionsProvider([
    FlowTransition::action('s02', 'trial_start', 'subscription.open', SubscriptionHasActiveGuard::class),
    FlowTransition::action('s02', 'trial_start', 'trial.activate', TrialCanActivateGuard::class),
]);

$guards = new ArrayFlowGuardRegistry([
    new class implements FlowGuardInterface {
        public function getId(): string
        {
            return 'subscription.has_active';
        }

        public function evaluate(TransitionContext $context, array $args = []): bool
        {
            return (bool) $context->payload('has_active_subscription', false);
        }
    },
    new class implements FlowGuardInterface {
        public function getId(): string
        {
            return 'trial.can_activate';
        }

        public function evaluate(TransitionContext $context, array $args = []): bool
        {
            return (int) $context->payload('trial_days', 0) > 0;
        }
    },
]);

$engine = new FlowEngine($definitions, $guards);

$decision = $engine->decide('s02', 'trial_start', new TransitionContext($update, $botContext, [
    'has_active_subscription' => false,
    'trial_days' => 3,
]));
```

## Зачем это нужно

- Централизует условия переходов.
- Делает ветвления наблюдаемыми (`from`, `event`, `target`, `guard.getId()` легко логировать).
- Позволяет строить визуальную карту переходов из конфигурации.
