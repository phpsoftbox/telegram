<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Scenario\FlowMap;

use PhpSoftBox\Telegram\Flow\FlowGuardInterface;
use PhpSoftBox\Telegram\Flow\TransitionContext;
use PhpSoftBox\Telegram\Scenario\CompiledScenario;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapBranch;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapCjm;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapDefinitionsValidator;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapService;
use PhpSoftBox\Telegram\Scenario\Id\ActionIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\EntryPointIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ScreenIdInterface;
use PhpSoftBox\Telegram\Scenario\ScenarioBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScenarioFlowMapDefinitionsValidatorTest extends TestCase
{
    #[Test]
    public function validatesCorrectBranchAndCjmDefinitions(): void
    {
        $service   = new ScenarioFlowMapService();
        $validator = new ScenarioFlowMapDefinitionsValidator();
        $map       = $service->build($this->buildScenario(), includeButtons: true);

        $report = $validator->validate(
            $map,
            branches: [
                new ScenarioFlowMapBranch(
                    id: 'start.new_user',
                    entryScreen: '',
                    entryEvents: ['trial_start'],
                    internalEvents: ['trial_start'],
                    exitEvents: ['support_open'],
                ),
            ],
            cjms: [
                new ScenarioFlowMapCjm(
                    id: 'cjm.onboarding',
                    branches: ['start.new_user'],
                ),
            ],
        );

        $this->assertSame([], $report['errors']);
    }

    #[Test]
    public function reportsErrorsForUnknownBranchReferences(): void
    {
        $service   = new ScenarioFlowMapService();
        $validator = new ScenarioFlowMapDefinitionsValidator();
        $map       = $service->build($this->buildScenario(), includeButtons: true);

        $report = $validator->validate(
            $map,
            branches: [
                new ScenarioFlowMapBranch(
                    id: 'broken.branch',
                    entryScreen: 'missing.screen',
                    entryEvents: ['missing_entry_event'],
                    internalEvents: ['missing_event'],
                    exitEvents: ['missing_exit_event'],
                    exitScreens: ['missing.exit.screen'],
                ),
            ],
            cjms: [
                new ScenarioFlowMapCjm(
                    id: 'cjm.invalid',
                    branches: ['missing.branch'],
                ),
            ],
        );

        $this->assertNotSame([], $report['errors']);
        $this->assertContains(
            'Branch "broken.branch" references unknown entry event "missing_entry_event".',
            $report['errors'],
        );
    }

    private function buildScenario(): CompiledScenario
    {
        $builder = new ScenarioBuilder();

        $builder->screen(ValidatorTestScreenId::START_NEW_USER, static function ($screen): void {
            $screen->text('Welcome');
            $screen->callbackButton(
                name: 'start_trial',
                label: '🎁 Trial',
                event: ValidatorTestActionId::TRIAL_START,
                flow: static function ($flow): void {
                    $flow->toScreen(ValidatorTestScreenId::TRIAL_ACTIVATED, ValidatorTestGuardCanActivate::class);
                },
            );
            $screen->callbackButton(
                name: 'help',
                label: '🆘 Help',
                event: ValidatorTestActionId::SUPPORT_OPEN,
                flow: static function ($flow): void {
                    $flow->toScreen(ValidatorTestScreenId::SUPPORT_MAIN);
                },
            );
        });

        $builder->screen(ValidatorTestScreenId::TRIAL_ACTIVATED, static function ($screen): void {
            $screen->text('Activated');
        });
        $builder->screen(ValidatorTestScreenId::SUPPORT_MAIN, static function ($screen): void {
            $screen->text('Support');
        });

        $builder->entryPoint(ValidatorTestEntryPointId::COMMAND_START, static function ($entryPoint): void {
            $entryPoint->toScreen(ValidatorTestScreenId::START_NEW_USER)->priority(10);
        });

        return $builder->compile();
    }
}

enum ValidatorTestScreenId: string implements ScreenIdInterface
{
    case START_NEW_USER  = 'start.new_user';
    case TRIAL_ACTIVATED = 'trial.activated';
    case SUPPORT_MAIN    = 'support.main';
}

enum ValidatorTestActionId: string implements ActionIdInterface
{
    case TRIAL_START  = 'trial_start';
    case SUPPORT_OPEN = 'support_open';
}

enum ValidatorTestEntryPointId: string implements EntryPointIdInterface
{
    case COMMAND_START = 'command.start';
}

final class ValidatorTestGuardCanActivate implements FlowGuardInterface
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
