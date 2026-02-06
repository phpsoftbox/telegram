<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Cli;

use PhpSoftBox\CliApp\Response;
use PhpSoftBox\Telegram\Cli\TelegramFlowMapValidateHandler;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMap;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapBranch;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapCjm;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapEdge;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapNode;
use PhpSoftBox\Telegram\Tests\Support\FakeCliRunner;
use PhpSoftBox\Telegram\Tests\Support\FakeFlowMapRegistry;
use PhpSoftBox\Telegram\Tests\Support\FakeFlowMapRegistryResolver;
use PhpSoftBox\Telegram\Tests\Support\FakeFlowMapSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TelegramFlowMapValidateHandlerTest extends TestCase
{
    #[Test]
    public function returnsFailureWhenResolverIsNotConfigured(): void
    {
        $handler = new TelegramFlowMapValidateHandler();
        $runner  = new FakeCliRunner([
            'scope' => 'branch',
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::FAILURE, $result);
        self::assertStringContainsString('not configured', $runner->lastMessage());
    }

    #[Test]
    public function validatesBranchScopeSuccessfully(): void
    {
        $registry           = $this->makeRegistry();
        $registry->branches = [
            new ScenarioFlowMapBranch(
                id: 'start.new_user',
                entryScreen: 'start.new_user',
                exitEvents: ['support_open'],
            ),
        ];

        $handler = new TelegramFlowMapValidateHandler(
            new FakeFlowMapRegistryResolver($registry),
            new FakeFlowMapSettings(),
        );
        $runner = new FakeCliRunner([
            'scope' => 'branch',
            'id'    => 'start.new_user',
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::SUCCESS, $result);
        self::assertStringContainsString('Configured branches', $runner->messages()[0] ?? '');
        self::assertStringContainsString('validation passed', $runner->lastMessage());
    }

    #[Test]
    public function failsInStrictModeWhenWarningsExist(): void
    {
        $registry           = $this->makeRegistry();
        $registry->branches = [
            new ScenarioFlowMapBranch(
                id: 'warn.branch',
                entryScreen: 'start.new_user',
                exitScreens: ['start.new_user'],
            ),
        ];

        $handler = new TelegramFlowMapValidateHandler(
            new FakeFlowMapRegistryResolver($registry),
            new FakeFlowMapSettings(),
        );
        $runner = new FakeCliRunner([
            'scope'  => 'branch',
            'id'     => 'warn.branch',
            'strict' => true,
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::FAILURE, $result);
        self::assertStringContainsString('WARNING:', $runner->messages()[1] ?? '');
    }

    #[Test]
    public function validatesCjmScopeSuccessfully(): void
    {
        $registry           = $this->makeRegistry();
        $registry->branches = [
            new ScenarioFlowMapBranch(
                id: 'start.new_user',
                entryScreen: 'start.new_user',
                exitEvents: ['support_open'],
            ),
        ];
        $registry->cjms = [
            new ScenarioFlowMapCjm(
                id: 'onboarding',
                branches: ['start.new_user'],
            ),
        ];

        $handler = new TelegramFlowMapValidateHandler(
            new FakeFlowMapRegistryResolver($registry),
            new FakeFlowMapSettings(),
        );
        $runner = new FakeCliRunner([
            'scope' => 'cjm',
            'id'    => 'onboarding',
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::SUCCESS, $result);
        self::assertStringContainsString('Configured CJMs', $runner->messages()[0] ?? '');
        self::assertStringContainsString('validation passed', $runner->lastMessage());
    }

    private function makeRegistry(): FakeFlowMapRegistry
    {
        return new FakeFlowMapRegistry(
            new ScenarioFlowMap(
                nodes: [
                    new ScenarioFlowMapNode('screen:start.new_user', 'screen', 'Start'),
                    new ScenarioFlowMapNode('screen:support.main', 'screen', 'Support'),
                ],
                edges: [
                    new ScenarioFlowMapEdge(
                        from: 'screen:start.new_user',
                        to: 'screen:support.main',
                        kind: 'flow',
                        event: 'support_open',
                    ),
                ],
            ),
        );
    }
}
