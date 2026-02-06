<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Scenario\FlowMap;

use PhpSoftBox\Telegram\Flow\FlowGuardInterface;
use PhpSoftBox\Telegram\Flow\TransitionContext;
use PhpSoftBox\Telegram\Scenario\CompiledScenario;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapBranch;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapCjm;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapService;
use PhpSoftBox\Telegram\Scenario\Id\ActionIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\EntryPointIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ScreenIdInterface;
use PhpSoftBox\Telegram\Scenario\ScenarioBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_filter;
use function array_values;

final class ScenarioFlowMapServiceTest extends TestCase
{
    #[Test]
    public function scopesBranchByPlainScopeIdAndKeepsExitBoundaries(): void
    {
        $service  = new ScenarioFlowMapService();
        $compiled = $this->buildScenario();

        $branch = new ScenarioFlowMapBranch(
            id: 'start.new_user',
            entryScreen: 'start.new_user',
            internalEvents: ['trial_start', 'subscription.open'],
            exitEvents: ['support_open', 'start_open'],
            exitScreens: ['subscription.choose'],
            description: 'New user branch',
        );

        $map    = $service->build($compiled, includeButtons: true);
        $scoped = $service->scope($map, 'start.new_user', [$branch]);
        $array  = $scoped->toArray();

        $nodeIds = array_values(array_column($array['nodes'], 'id'));
        $this->assertContains('screen:start.new_user', $nodeIds);
        $this->assertContains('exit:support_open', $nodeIds);
        $this->assertContains('exit:start_open', $nodeIds);
        $this->assertContains('exit:screen:subscription.choose', $nodeIds);
        $this->assertNotContains('screen:support.main', $nodeIds);
        $this->assertNotContains('screen:subscription.choose', $nodeIds);
    }

    #[Test]
    public function allowsExplicitScreenScopeWithoutBranchBoundaries(): void
    {
        $service  = new ScenarioFlowMapService();
        $compiled = $this->buildScenario();

        $branch = new ScenarioFlowMapBranch(
            id: 'start.new_user',
            entryScreen: 'start.new_user',
            internalEvents: ['trial_start', 'subscription.open'],
            exitEvents: ['support_open', 'start_open'],
            exitScreens: ['subscription.choose'],
        );

        $map    = $service->build($compiled, includeButtons: true);
        $scoped = $service->scope($map, 'screen:start.new_user', [$branch]);
        $array  = $scoped->toArray();

        $nodeIds = array_values(array_column($array['nodes'], 'id'));
        $this->assertContains('screen:support.main', $nodeIds);
        $this->assertContains('screen:subscription.choose', $nodeIds);
        $this->assertNotContains('exit:support_open', $nodeIds);
    }

    #[Test]
    public function rendersDotAndHtmlForScopedBranch(): void
    {
        $service  = new ScenarioFlowMapService();
        $compiled = $this->buildScenario();

        $branch = new ScenarioFlowMapBranch(
            id: 'start.new_user',
            entryScreen: 'start.new_user',
            internalEvents: ['trial_start', 'subscription.open'],
            exitEvents: ['support_open', 'start_open'],
            exitScreens: ['subscription.choose'],
        );

        $dot = $service->dot(
            scenario: $compiled,
            includeButtons: true,
            scope: 'start.new_user',
            branches: [$branch],
            rankdir: 'TB',
        );

        $this->assertStringContainsString('rankdir=TB;', $dot);
        $this->assertStringContainsString('switch: trial_start', $dot);
        $this->assertStringContainsString('EXIT: support_open', $dot);
        $this->assertStringContainsString('EXIT: screen:subscription.choose', $dot);

        $html = $service->html(
            scenario: $compiled,
            includeButtons: true,
            scope: 'start.new_user',
            branches: [$branch],
            rankdir: 'TB',
            vizJsCode: 'window.Viz = function () {};',
            vizRenderCode: 'window.render = function () {};',
        );

        $this->assertStringContainsString('<h1>Telegram Main Flow Map</h1>', $html);
        $this->assertStringContainsString('<strong>Scope:</strong> start.new_user', $html);
        $this->assertStringContainsString('<span><span class="dot handler"></span>Handler</span>', $html);
        $this->assertStringContainsString('<span><span class="dot switch"></span>Switch</span>', $html);
        $this->assertStringNotContainsString('Terminal screens', $html);
        $this->assertStringNotContainsString('Loop screens', $html);
    }

    #[Test]
    public function scopesCjmByMergingSelectedBranches(): void
    {
        $service  = new ScenarioFlowMapService();
        $compiled = $this->buildScenario();

        $branches = [
            new ScenarioFlowMapBranch(
                id: 'start.new_user',
                entryScreen: 'start.new_user',
                internalEvents: ['trial_start', 'subscription.open'],
                exitEvents: ['support_open', 'start_open'],
                exitScreens: ['subscription.choose'],
            ),
            new ScenarioFlowMapBranch(
                id: 'trial.activated',
                entryScreen: 'trial.activated',
                internalEvents: [],
                exitEvents: ['start_open'],
            ),
        ];

        $cjms = [
            new ScenarioFlowMapCjm(
                id: 'cjm.trial',
                branches: ['start.new_user', 'trial.activated'],
            ),
        ];

        $map    = $service->build($compiled, includeButtons: true);
        $scoped = $service->scope($map, 'cjm:cjm.trial', $branches, $cjms);
        $array  = $scoped->toArray();

        $nodeIds = array_values(array_column($array['nodes'], 'id'));
        $this->assertContains('screen:start.new_user', $nodeIds);
        $this->assertContains('screen:trial.activated', $nodeIds);
        $this->assertContains('exit:start_open', $nodeIds);
    }

    #[Test]
    public function scopesBranchFromEntryEventWithoutEntryScreen(): void
    {
        $service  = new ScenarioFlowMapService();
        $compiled = $this->buildScenario();

        $branch = new ScenarioFlowMapBranch(
            id: 'subscription.open.from_event',
            entryScreen: '',
            entryEvents: ['subscription.open'],
            exitScreens: ['subscription.choose'],
        );

        $map    = $service->build($compiled, includeButtons: true);
        $scoped = $service->scope($map, 'subscription.open.from_event', [$branch]);
        $array  = $scoped->toArray();

        $nodeIds = array_values(array_column($array['nodes'], 'id'));
        $this->assertContains('button:buy', $nodeIds);
        $this->assertContains('exit:screen:subscription.choose', $nodeIds);
        $this->assertNotContains('screen:subscription.choose', $nodeIds);
        $this->assertNotContains('screen:start.new_user', $nodeIds);
    }

    #[Test]
    public function doesNotRenderRedundantButtonActionWhenTransitionExistsForSameEvent(): void
    {
        $service  = new ScenarioFlowMapService();
        $compiled = $this->buildScenario();

        $branch = new ScenarioFlowMapBranch(
            id: 'subscription.open.from_event',
            entryScreen: '',
            entryEvents: ['subscription.open'],
            exitScreens: ['subscription.choose'],
        );

        $map    = $service->build($compiled, includeButtons: true);
        $scoped = $service->scope($map, 'subscription.open.from_event', [$branch]);
        $array  = $scoped->toArray();

        $duplicateEdges = array_values(array_filter(
            $array['edges'],
            static fn (array $edge): bool => ($edge['kind'] ?? '') === 'button_action'
                && ($edge['event'] ?? null) === 'subscription.open',
        ));

        $this->assertSame([], $duplicateEdges);
    }

    private function buildScenario(): CompiledScenario
    {
        $builder = new ScenarioBuilder();

        $builder->screen(TestScreenId::START_NEW_USER, static function ($screen): void {
            $screen->text('Welcome');
            $screen->callbackButton(
                name: 'start_trial',
                label: '🎁 Trial',
                event: TestActionId::TRIAL_START,
                flow: static function ($flow): void {
                    $flow->toScreen(TestScreenId::TRIAL_DISABLED, TestGuardTrialDisabled::class);
                    $flow->toScreen(TestScreenId::TRIAL_ACTIVATED, TestGuardCanActivate::class);
                },
            );
            $screen->callbackButton(
                name: 'buy',
                label: '💳 Buy',
                event: TestActionId::SUBSCRIPTION_OPEN,
                flow: static function ($flow): void {
                    $flow->toScreen(TestScreenId::SUBSCRIPTION_CHOOSE);
                },
            );
            $screen->callbackButton(
                name: 'help',
                label: '🆘 Help',
                event: TestActionId::SUPPORT_OPEN,
                flow: static function ($flow): void {
                    $flow->toScreen(TestScreenId::SUPPORT_MAIN);
                },
            );
        });

        $builder->screen(TestScreenId::TRIAL_ACTIVATED, static function ($screen): void {
            $screen->text('Activated');
            $screen->callbackButton(
                name: 'my_subscription',
                label: '🔑 Моя подписка',
                event: TestActionId::START_OPEN,
                flow: static function ($flow): void {
                    $flow->toScreen(TestScreenId::START_ACTIVE);
                },
            );
        });
        $builder->screen(TestScreenId::TRIAL_DISABLED, static function ($screen): void {
            $screen->text('Disabled');
        });
        $builder->screen(TestScreenId::SUBSCRIPTION_CHOOSE, static function ($screen): void {
            $screen->text('Choose subscription');
        });
        $builder->screen(TestScreenId::SUPPORT_MAIN, static function ($screen): void {
            $screen->text('Support');
        });
        $builder->screen(TestScreenId::START_ACTIVE, static function ($screen): void {
            $screen->text('Active');
        });

        $builder->bindAction(TestActionId::TRIAL_START, TestTrialStartHandler::class);
        $builder->bindAction(TestActionId::TRIAL_ACTIVATE, TestTrialActivateHandler::class);

        $builder->entryPoint(TestEntryPointId::COMMAND_START, static function ($entryPoint): void {
            $entryPoint->toScreen(TestScreenId::START_NEW_USER)->priority(10);
        });

        return $builder->compile();
    }
}

enum TestScreenId: string implements ScreenIdInterface
{
    case START_NEW_USER      = 'start.new_user';
    case TRIAL_ACTIVATED     = 'trial.activated';
    case TRIAL_DISABLED      = 'trial.disabled';
    case SUBSCRIPTION_CHOOSE = 'subscription.choose';
    case SUPPORT_MAIN        = 'support.main';
    case START_ACTIVE        = 'start.with_active_subscription';
}

enum TestActionId: string implements ActionIdInterface
{
    case TRIAL_START       = 'trial_start';
    case TRIAL_ACTIVATE    = 'trial.activate';
    case TRIAL_DISABLED    = 'trial.disabled';
    case SUBSCRIPTION_OPEN = 'subscription.open';
    case SUPPORT_OPEN      = 'support_open';
    case START_OPEN        = 'start_open';
}

enum TestEntryPointId: string implements EntryPointIdInterface
{
    case COMMAND_START = 'command.start';
}

final class TestGuardTrialDisabled implements FlowGuardInterface
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

final class TestGuardCanActivate implements FlowGuardInterface
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

final class TestTrialStartHandler
{
    public function __invoke(): void
    {
    }
}

final class TestTrialActivateHandler
{
    public function __invoke(): void
    {
    }
}

final class TestTrialDisabledHandler
{
    public function __invoke(): void
    {
    }
}
