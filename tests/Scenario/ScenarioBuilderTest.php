<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Scenario;

use PhpSoftBox\Telegram\Flow\FlowGuardInterface;
use PhpSoftBox\Telegram\Flow\FlowTargetsEnum;
use PhpSoftBox\Telegram\Flow\TransitionContext;
use PhpSoftBox\Telegram\Scenario\Id\ActionIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ButtonGroupPresetIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ButtonPresetIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\EntryPointIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ScreenIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\TransitionIdInterface;
use PhpSoftBox\Telegram\Scenario\ScenarioBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class ScenarioBuilderTest extends TestCase
{
    /**
     * Проверяет, что сценарий компилирует экраны, кнопки, действия, flow-переходы и entrypoint.
     */
    #[Test]
    public function compilesScreenButtonsActionsAndFlowTransitions(): void
    {
        $builder = new ScenarioBuilder();

        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen
                ->title('/start')
                ->text("Hello!\nStart trial.")
                ->textTemplate('start.s02')
                ->callbackButton(
                    name: 's02_trial_start',
                    label: 'Try 3-day trial',
                    event: ScenarioTestActionId::TRIAL_START,
                    callbackData: 'trial_start',
                    row: 1,
                    position: 1,
                    flow: static function ($flow): void {
                        $flow->toAction(ScenarioTestActionId::SUBSCRIPTION_OPEN, ScenarioGuardActive::class);
                        $flow->toAction(ScenarioTestActionId::TRIAL_DISABLED, ScenarioGuardTrialDisabled::class);
                        $flow->toAction(ScenarioTestActionId::TRIAL_ALREADY_USED, ScenarioGuardHasAny::class);
                        $flow->toAction(
                            ScenarioTestActionId::TRIAL_ACTIVATE,
                            ScenarioGuardCanActivate::class,
                            expectedScreen: ScenarioTestScreenId::S08,
                        );
                    },
                );
        });

        $builder->bindAction(ScenarioTestActionId::TRIAL_START, ScenarioConnectHandler::class);
        $builder->bindAction(ScenarioTestActionId::SUBSCRIPTION_OPEN, ScenarioSubscriptionHandler::class);
        $builder->entryPoint(ScenarioTestEntryPointId::COMMAND_START, static function ($entryPoint): void {
            $entryPoint
                ->toScreen(ScenarioTestScreenId::S02)
                ->guard(ScenarioGuardCanActivate::class)
                ->priority(10);
        });

        $compiled = $builder->compile();

        $this->assertArrayHasKey('s02', $compiled->screens);
        $this->assertArrayHasKey('s02_trial_start', $compiled->buttons);
        $this->assertArrayHasKey('trial_start', $compiled->actions);
        $this->assertCount(4, $compiled->transitions);
        $this->assertCount(1, $compiled->entryPoints);
        $this->assertSame('start.s02', $compiled->screens['s02']->textTemplate);
        $this->assertSame('trial_start', $compiled->actions['trial_start']->value);
        $this->assertSame('trial_start', $compiled->buttons['s02_trial_start']->action);
        $this->assertSame(FlowTargetsEnum::ACTION, $compiled->transitions[0]->target->type);
        $this->assertSame('subscription.open', $compiled->transitions[0]->target->id);
        $this->assertSame(ScenarioGuardActive::class, $compiled->transitions[0]->guardClass);
        $this->assertSame('s08', $compiled->transitions[3]->expectedScreen);
        $this->assertSame('command.start', $compiled->entryPoints[0]->name);
        $this->assertSame('s02', $compiled->entryPoints[0]->target->id);

        $handler = $compiled->actionHandlers['trial_start'] ?? null;
        self::assertNotNull($handler);
        $this->assertSame(ScenarioConnectHandler::class, $handler->handlerClass);
        $this->assertSame('__invoke', $handler->method);
    }

    /**
     * Проверяет, что при конфликте определения action по имени compile выбрасывает исключение.
     */
    #[Test]
    public function throwsOnActionDefinitionConflict(): void
    {
        $builder = new ScenarioBuilder();

        $builder->screen(ScenarioTestScreenId::S01, static function ($screen): void {
            $screen->callbackButton('btn_1', 'One', ScenarioTestActionId::TRIAL_START, 'trial_start');
        });
        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen->callbackButton('btn_2', 'Two', ScenarioTestActionId::TRIAL_START, 'trial:start');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Action definition conflict');

        $builder->compile();
    }

    /**
     * Проверяет, что compile валидирует ссылки переходов на существующие экраны.
     */
    #[Test]
    public function throwsOnUnknownTargetScreen(): void
    {
        $builder = new ScenarioBuilder();

        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen->callbackButton(
                name: 'btn',
                label: 'Go',
                event: ScenarioTestActionId::GO,
                flow: static function ($flow): void {
                    $flow->toScreen(ScenarioTestScreenId::S404);
                },
            );
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown screen');

        $builder->compile();
    }

    /**
     * Проверяет, что guard-класс в маршруте обязан реализовывать FlowGuardInterface.
     */
    #[Test]
    public function throwsOnInvalidGuardClass(): void
    {
        $builder = new ScenarioBuilder();

        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen->callbackButton(
                name: 'btn',
                label: 'Go',
                event: ScenarioTestActionId::GO,
                flow: static function ($flow): void {
                    $flow->toAction(ScenarioTestActionId::NEXT, ScenarioNotGuard::class);
                },
            );
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must implement');

        $builder->compile();
    }

    /**
     * Проверяет, что entrypoint не может указывать на несуществующий экран.
     */
    #[Test]
    public function throwsOnUnknownEntryPointTargetScreen(): void
    {
        $builder = new ScenarioBuilder();

        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen->text('Hello');
        });
        $builder->entryPoint(ScenarioTestEntryPointId::COMMAND_START, static function ($entryPoint): void {
            $entryPoint->toScreen(ScenarioTestScreenId::S404);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown screen');

        $builder->compile();
    }

    /**
     * Проверяет, что пресет кнопки подставляет label/event и генерирует name по screen+event.
     */
    #[Test]
    public function compilesPresetButtonWithAutoGeneratedName(): void
    {
        $builder = new ScenarioBuilder();

        $builder->buttonPreset(
            ScenarioTestButtonPresetId::COMMON_HELP,
            '🆘 Help',
            ScenarioTestActionId::SUPPORT_OPEN,
        );
        $builder->screen(ScenarioTestScreenId::START_NEW_USER, static function ($screen): void {
            $screen->presetButton(ScenarioTestButtonPresetId::COMMON_HELP, row: 3, position: 1);
        });

        $compiled = $builder->compile();

        $this->assertArrayHasKey('start_new_user__support_open', $compiled->buttons);
        $this->assertArrayHasKey('support_open', $compiled->actions);
        $this->assertSame('🆘 Help', $compiled->buttons['start_new_user__support_open']->label);
        $this->assertSame('support_open', $compiled->buttons['start_new_user__support_open']->action);
        $this->assertSame('support_open', $compiled->actions['support_open']->value);
    }

    #[Test]
    public function compilesButtonGroupPresetWithRowOffset(): void
    {
        $builder = new ScenarioBuilder();

        $builder
            ->definitions()
            ->button(
                ScenarioTestButtonPresetId::COMMON_TRIAL,
                '🎁 Trial',
                ScenarioTestActionId::TRIAL_START,
            )
            ->button(
                ScenarioTestButtonPresetId::COMMON_HELP,
                '🆘 Help',
                ScenarioTestActionId::SUPPORT_OPEN,
            )
            ->buttonGroup(ScenarioTestButtonGroupPresetId::START_PRIMARY, static function ($group): void {
                $group->button(ScenarioTestButtonPresetId::COMMON_TRIAL, row: 1, position: 1);
                $group->button(ScenarioTestButtonPresetId::COMMON_HELP, row: 2, position: 1);
            });

        $builder->screen(ScenarioTestScreenId::START_NEW_USER, static function ($screen): void {
            $screen->buttonGroup(ScenarioTestButtonGroupPresetId::START_PRIMARY, rowOffset: 1);
        });

        $compiled         = $builder->compile();
        $screenButtons    = $compiled->screens['start.new_user']->buttons;
        $groupDefinitions = $compiled->buttonGroupsProviderDefinitions()['start.primary'] ?? [];

        $this->assertCount(2, $screenButtons);
        $this->assertSame('start_new_user__trial_start', $screenButtons[0]->button->name);
        $this->assertSame('start_new_user__support_open', $screenButtons[1]->button->name);
        $this->assertSame(2, $screenButtons[0]->row);
        $this->assertSame(3, $screenButtons[1]->row);
        $this->assertCount(2, $groupDefinitions);
        $this->assertSame('group_start_primary__trial_start', $groupDefinitions[0]['name']);
        $this->assertSame('trial_start', $groupDefinitions[0]['action']);
        $this->assertSame('group_start_primary__support_open', $groupDefinitions[1]['name']);
        $this->assertSame('support_open', $groupDefinitions[1]['action']);
    }

    #[Test]
    public function throwsOnUnknownButtonGroupPreset(): void
    {
        $builder = new ScenarioBuilder();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown button group preset');

        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen->buttonGroup(ScenarioTestButtonGroupPresetId::MISSING);
        });
    }

    /**
     * Проверяет, что использование неизвестного пресета выбрасывает исключение.
     */
    #[Test]
    public function throwsOnUnknownButtonPreset(): void
    {
        $builder = new ScenarioBuilder();

        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen->text('Hello');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown button preset');

        $builder->screen(ScenarioTestScreenId::S03, static function ($screen): void {
            $screen->presetButton(ScenarioTestButtonPresetId::MISSING_PRESET);
        });
    }

    #[Test]
    public function importsDefinitionsScreensAndFlowsFromPath(): void
    {
        $builder = new ScenarioBuilder();
        $baseDir = sys_get_temp_dir() . '/psb_scenario_import_' . uniqid('', true);

        mkdir($baseDir, 0777, true);
        mkdir($baseDir . '/screen', 0777, true);
        mkdir($baseDir . '/flow', 0777, true);

        file_put_contents($baseDir . '/definition.php', <<<'PHP'
<?php
declare(strict_types=1);

use PhpSoftBox\Telegram\Scenario\ScenarioDefinitionsBuilder;
use PhpSoftBox\Telegram\Tests\Scenario\ScenarioConnectHandler;
use PhpSoftBox\Telegram\Tests\Scenario\ScenarioTestActionId;
use PhpSoftBox\Telegram\Tests\Scenario\ScenarioTestButtonPresetId;

return static function (ScenarioDefinitionsBuilder $definitions): void {
    $definitions
        ->button(ScenarioTestButtonPresetId::COMMON_HELP, '🆘 Help', ScenarioTestActionId::SUPPORT_OPEN)
        ->action(ScenarioTestActionId::SUPPORT_OPEN, ScenarioConnectHandler::class);
};
PHP);

        file_put_contents($baseDir . '/screen/start.php', <<<'PHP'
<?php
declare(strict_types=1);

use PhpSoftBox\Telegram\Scenario\ScenarioScreensBuilder;
use PhpSoftBox\Telegram\Tests\Scenario\ScenarioTestButtonPresetId;
use PhpSoftBox\Telegram\Tests\Scenario\ScenarioTestScreenId;

return static function (ScenarioScreensBuilder $screens): void {
    $screens->screen(ScenarioTestScreenId::START_NEW_USER, static function ($screen): void {
        $screen->button(ScenarioTestButtonPresetId::COMMON_HELP, row: 1, position: 1);
    });
};
PHP);

        file_put_contents($baseDir . '/flow/start.php', <<<'PHP'
<?php
declare(strict_types=1);

use PhpSoftBox\Telegram\Scenario\ScenarioFlowsBuilder;
use PhpSoftBox\Telegram\Tests\Scenario\ScenarioTestEntryPointId;
use PhpSoftBox\Telegram\Tests\Scenario\ScenarioTestScreenId;

return static function (ScenarioFlowsBuilder $flows): void {
    $flows->entryPoint(ScenarioTestEntryPointId::COMMAND_START, static function ($entryPoint): void {
        $entryPoint->toScreen(ScenarioTestScreenId::START_NEW_USER);
    });
};
PHP);

        $builder->definitions()->import($baseDir . '/definition.php');
        $builder->screens()->import($baseDir . '/screen');
        $builder->flows()->import($baseDir . '/flow');

        $compiled = $builder->compile();

        $this->assertArrayHasKey('support_open', $compiled->actions);
        $this->assertArrayHasKey('start_new_user__support_open', $compiled->buttons);
        $this->assertArrayHasKey('start.new_user', $compiled->screens);
        $this->assertCount(1, $compiled->entryPoints);
        $this->assertSame('start.new_user', $compiled->entryPoints[0]->target->id);
    }

    #[Test]
    public function compilesPresetButtonUsingTransitionDefinition(): void
    {
        $builder = new ScenarioBuilder();

        $builder
            ->definitions()
            ->transition(ScenarioTestTransitionId::OPEN_SUPPORT, static function ($transition): void {
                $transition->toScreen(ScenarioTestScreenId::S08);
            })
            ->button(
                ScenarioTestButtonPresetId::COMMON_HELP,
                '🆘 Help',
                ScenarioTestTransitionId::OPEN_SUPPORT,
            );

        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen->button(ScenarioTestButtonPresetId::COMMON_HELP, row: 1, position: 1);
        });
        $builder->screen(ScenarioTestScreenId::S08, static function ($screen): void {
            $screen->text('Support');
        });

        $compiled = $builder->compile();

        $this->assertArrayHasKey('transition.open_support', $compiled->actions);
        $this->assertCount(1, $compiled->transitions);
        $this->assertSame('transition.open_support', $compiled->transitions[0]->event);
        $this->assertSame(FlowTargetsEnum::SCREEN, $compiled->transitions[0]->target->type);
        $this->assertSame('s08', $compiled->transitions[0]->target->id);
    }

    #[Test]
    public function compilesButtonToScreenWithoutExplicitActionId(): void
    {
        $builder = new ScenarioBuilder();

        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen->buttonToScreen(
                name: 'go_next',
                label: 'Next',
                screenId: ScenarioTestScreenId::S08,
                row: 1,
                position: 1,
            );
        });
        $builder->screen(ScenarioTestScreenId::S08, static function ($screen): void {
            $screen->text('Next');
        });

        $compiled = $builder->compile();

        $this->assertArrayHasKey('nav.s02.go_next', $compiled->actions);
        $this->assertCount(1, $compiled->transitions);
        $this->assertSame('nav.s02.go_next', $compiled->transitions[0]->event);
        $this->assertSame('s08', $compiled->transitions[0]->target->id);
    }

    #[Test]
    public function throwsOnUnknownTransitionReferenceFromButton(): void
    {
        $builder = new ScenarioBuilder();

        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen->buttonToTransition(
                name: 'go_support',
                label: 'Support',
                transition: ScenarioTestTransitionId::MISSING,
            );
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown transition');

        $builder->compile();
    }

    #[Test]
    public function compilesButtonToTransitionReference(): void
    {
        $builder = new ScenarioBuilder();

        $builder->transition(ScenarioTestTransitionId::OPEN_SUPPORT, static function ($transition): void {
            $transition->toScreen(ScenarioTestScreenId::S08);
        });

        $builder->screen(ScenarioTestScreenId::S02, static function ($screen): void {
            $screen->buttonToTransition(
                name: 'go_support',
                label: 'Support',
                transition: ScenarioTestTransitionId::OPEN_SUPPORT,
            );
        });
        $builder->screen(ScenarioTestScreenId::S08, static function ($screen): void {
            $screen->text('Support');
        });

        $compiled = $builder->compile();

        $this->assertArrayHasKey('transition.open_support', $compiled->actions);
        $this->assertCount(1, $compiled->transitions);
        $this->assertSame('transition.open_support', $compiled->transitions[0]->event);
        $this->assertSame('s08', $compiled->transitions[0]->target->id);
    }

    #[Test]
    public function throwsOnTransitionActionIdConflict(): void
    {
        $builder = new ScenarioBuilder();

        $builder->transition(ScenarioTestTransitionId::OPEN_SUPPORT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('conflicts');

        $builder->bindAction(ScenarioTestActionId::OPEN_SUPPORT_TRANSITION, ScenarioConnectHandler::class);
    }
}

enum ScenarioTestScreenId: string implements ScreenIdInterface
{
    case S01            = 's01';
    case S02            = 's02';
    case S03            = 's03';
    case S08            = 's08';
    case S404           = 's404';
    case START_NEW_USER = 'start.new_user';
}

enum ScenarioTestActionId: string implements ActionIdInterface
{
    case TRIAL_START             = 'trial_start';
    case SUBSCRIPTION_OPEN       = 'subscription.open';
    case TRIAL_DISABLED          = 'trial.disabled';
    case TRIAL_ALREADY_USED      = 'trial.already_used';
    case TRIAL_ACTIVATE          = 'trial.activate';
    case GO                      = 'go';
    case NEXT                    = 'next';
    case SUPPORT_OPEN            = 'support_open';
    case OPEN_SUPPORT_TRANSITION = 'transition.open_support';
}

enum ScenarioTestEntryPointId: string implements EntryPointIdInterface
{
    case COMMAND_START = 'command.start';
}

enum ScenarioTestButtonPresetId: string implements ButtonPresetIdInterface
{
    case COMMON_HELP    = 'common.help';
    case COMMON_TRIAL   = 'common.trial';
    case MISSING_PRESET = 'missing.preset';
}

enum ScenarioTestButtonGroupPresetId: string implements ButtonGroupPresetIdInterface
{
    case START_PRIMARY = 'start.primary';
    case MISSING       = 'group.missing';
}

enum ScenarioTestTransitionId: string implements TransitionIdInterface
{
    case OPEN_SUPPORT = 'transition.open_support';
    case MISSING      = 'transition.missing';
}

final class ScenarioGuardActive implements FlowGuardInterface
{
    public function getId(): string
    {
        return 'subscription.has_active';
    }

    public function evaluate(TransitionContext $context, array $args = []): bool
    {
        return true;
    }
}

final class ScenarioGuardTrialDisabled implements FlowGuardInterface
{
    public function getId(): string
    {
        return 'trial.is_disabled';
    }

    public function evaluate(TransitionContext $context, array $args = []): bool
    {
        return false;
    }
}

final class ScenarioGuardHasAny implements FlowGuardInterface
{
    public function getId(): string
    {
        return 'subscription.has_any';
    }

    public function evaluate(TransitionContext $context, array $args = []): bool
    {
        return false;
    }
}

final class ScenarioGuardCanActivate implements FlowGuardInterface
{
    public function getId(): string
    {
        return 'trial.can_activate';
    }

    public function evaluate(TransitionContext $context, array $args = []): bool
    {
        return true;
    }
}

final class ScenarioNotGuard
{
}

final class ScenarioConnectHandler
{
    public function __invoke(): void
    {
    }
}

final class ScenarioSubscriptionHandler
{
    public function __invoke(): void
    {
    }
}
