<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Cli;

use PhpSoftBox\CliApp\Response;
use PhpSoftBox\Telegram\Cli\TelegramFlowMapHandler;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMap;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapBranch;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapCjm;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapNode;
use PhpSoftBox\Telegram\Tests\Support\FakeCliRunner;
use PhpSoftBox\Telegram\Tests\Support\FakeFlowMapRegistry;
use PhpSoftBox\Telegram\Tests\Support\FakeFlowMapRegistryResolver;
use PhpSoftBox\Telegram\Tests\Support\FakeFlowMapSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function is_file;
use function rtrim;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class TelegramFlowMapHandlerTest extends TestCase
{
    #[Test]
    public function returnsFailureWhenResolverIsNotConfigured(): void
    {
        $handler = new TelegramFlowMapHandler();
        $runner  = new FakeCliRunner([
            'scope' => 'branch',
            'id'    => 'start.new_user',
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::FAILURE, $result);
        self::assertStringContainsString('not configured', $runner->lastMessage());
    }

    #[Test]
    public function returnsFailureForUnknownBranchScopeId(): void
    {
        $registry = $this->makeRegistry();
        $resolver = new FakeFlowMapRegistryResolver($registry);

        $handler = new TelegramFlowMapHandler($resolver, new FakeFlowMapSettings());
        $runner  = new FakeCliRunner([
            'scope' => 'branch',
            'id'    => 'missing.branch',
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::FAILURE, $result);
        self::assertStringContainsString('не найден', $runner->lastMessage());
    }

    #[Test]
    public function writesHtmlToDefaultOutputPathUsingSettings(): void
    {
        $tempDir = $this->tempDir();

        $registry           = $this->makeRegistry();
        $registry->branches = [
            new ScenarioFlowMapBranch(
                id: 'start.new_user',
                entryScreen: 'start.new_user',
            ),
        ];
        $registry->html = '<html><body>flow map html</body></html>';

        $resolver = new FakeFlowMapRegistryResolver($registry);
        $settings = new FakeFlowMapSettings(
            defaultBot: 'main',
            defaultOutputDir: $tempDir,
            defaultFormat: 'html',
        );

        $handler = new TelegramFlowMapHandler($resolver, $settings);
        $runner  = new FakeCliRunner([
            'scope' => 'branch',
            'id'    => 'start.new_user',
            'bot'   => '',
        ]);

        $result = $handler->run($runner);

        $expectedFile = $tempDir . '/main/start.new_user.html';
        self::assertSame(Response::SUCCESS, $result);
        self::assertSame(['main'], $resolver->resolvedBots);
        self::assertSame(['branch:start.new_user'], $registry->scopes);
        self::assertTrue(is_file($expectedFile));
        self::assertSame($registry->html . "\n", (string) file_get_contents($expectedFile));
        self::assertStringContainsString('Flow map сохранена', $runner->lastMessage());

        unlink($expectedFile);
    }

    #[Test]
    public function writesJsonToExplicitOutputPath(): void
    {
        $outPath = sprintf('%s/tg-flow-map-%s.json', sys_get_temp_dir(), uniqid('', true));

        $registry       = $this->makeRegistry();
        $registry->cjms = [
            new ScenarioFlowMapCjm(
                id: 'onboarding',
                branches: ['start.new_user'],
            ),
        ];
        $registry->scopedMap = [
            'nodes' => [
                ['id' => 'screen:start.new_user', 'type' => 'screen', 'label' => 'Start'],
            ],
            'edges' => [],
        ];

        $resolver = new FakeFlowMapRegistryResolver($registry);

        $handler = new TelegramFlowMapHandler($resolver, new FakeFlowMapSettings());
        $runner  = new FakeCliRunner([
            'scope'  => 'cjm',
            'id'     => 'onboarding',
            'format' => 'json',
            'out'    => $outPath,
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::SUCCESS, $result);
        self::assertSame(['cjm:onboarding'], $registry->scopes);
        self::assertTrue(is_file($outPath));
        self::assertStringContainsString('"nodes"', (string) file_get_contents($outPath));

        unlink($outPath);
    }

    private function makeRegistry(): FakeFlowMapRegistry
    {
        return new FakeFlowMapRegistry(
            new ScenarioFlowMap(
                nodes: [
                    new ScenarioFlowMapNode('screen:start.new_user', 'screen', 'Start'),
                ],
                edges: [],
            ),
        );
    }

    private function tempDir(): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/tg-flow-map-' . uniqid('', true);
    }
}
