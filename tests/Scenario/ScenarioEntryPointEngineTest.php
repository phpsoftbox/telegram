<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Scenario;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Flow\ArrayFlowGuardRegistry;
use PhpSoftBox\Telegram\Flow\FlowGuardInterface;
use PhpSoftBox\Telegram\Flow\FlowTarget;
use PhpSoftBox\Telegram\Flow\FlowTargetsEnum;
use PhpSoftBox\Telegram\Flow\TransitionContext;
use PhpSoftBox\Telegram\Scenario\ArrayScenarioEntryPointDefinitionsProvider;
use PhpSoftBox\Telegram\Scenario\ScenarioEntryPoint;
use PhpSoftBox\Telegram\Scenario\ScenarioEntryPointEngine;
use PhpSoftBox\Telegram\Tests\Support\FakeTelegramClient;
use PhpSoftBox\Telegram\Update\Update;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScenarioEntryPointEngineTest extends TestCase
{
    /**
     * Проверяет, что движок entrypoint выбирает первый переход с matched guard по приоритету.
     */
    #[Test]
    public function selectsFirstMatchedByPriority(): void
    {
        $provider = new ArrayScenarioEntryPointDefinitionsProvider([
            new ScenarioEntryPoint(
                name: 'command.start',
                target: FlowTarget::screen('s02'),
                guardClass: EntryPointFalseGuard::class,
                priority: 10,
            ),
            new ScenarioEntryPoint(
                name: 'command.start',
                target: FlowTarget::screen('s16'),
                guardClass: EntryPointTrueGuard::class,
                priority: 20,
            ),
        ]);

        $engine = new ScenarioEntryPointEngine(
            $provider,
            new ArrayFlowGuardRegistry([
                new EntryPointFalseGuard(),
                new EntryPointTrueGuard(),
            ]),
        );

        $decision = $engine->decide('command.start', $this->context());

        $this->assertNotNull($decision);
        $this->assertSame('command.start', $decision->name);
        $this->assertSame(FlowTargetsEnum::SCREEN, $decision->target->type);
        $this->assertSame('s16', $decision->target->id);
        $this->assertSame('entry.true', $decision->guardId);
    }

    /**
     * Проверяет, что при отсутствии matched guard для entrypoint движок возвращает null.
     */
    #[Test]
    public function returnsNullWhenNoGuardMatched(): void
    {
        $provider = new ArrayScenarioEntryPointDefinitionsProvider([
            new ScenarioEntryPoint(
                name: 'command.start',
                target: FlowTarget::screen('s02'),
                guardClass: EntryPointFalseGuard::class,
            ),
        ]);

        $engine = new ScenarioEntryPointEngine(
            $provider,
            new ArrayFlowGuardRegistry([
                new EntryPointFalseGuard(),
            ]),
        );

        $this->assertNull($engine->decide('command.start', $this->context()));
    }

    private function context(): TransitionContext
    {
        $update = Update::fromArray([
            'update_id' => 1,
            'message'   => [
                'message_id' => 10,
                'text'       => '/start',
                'chat'       => ['id' => 100],
                'from'       => ['id' => 200],
            ],
        ]);

        return new TransitionContext($update, new BotContext(new FakeTelegramClient()));
    }
}

final class EntryPointFalseGuard implements FlowGuardInterface
{
    public function getId(): string
    {
        return 'entry.false';
    }

    public function evaluate(TransitionContext $context, array $args = []): bool
    {
        return false;
    }
}

final class EntryPointTrueGuard implements FlowGuardInterface
{
    public function getId(): string
    {
        return 'entry.true';
    }

    public function evaluate(TransitionContext $context, array $args = []): bool
    {
        return true;
    }
}
