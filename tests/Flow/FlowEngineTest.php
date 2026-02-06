<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Flow;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Flow\ArrayFlowDefinitionsProvider;
use PhpSoftBox\Telegram\Flow\ArrayFlowGuardRegistry;
use PhpSoftBox\Telegram\Flow\FlowEngine;
use PhpSoftBox\Telegram\Flow\FlowGuardInterface;
use PhpSoftBox\Telegram\Flow\FlowTargetsEnum;
use PhpSoftBox\Telegram\Flow\FlowTransition;
use PhpSoftBox\Telegram\Flow\TransitionContext;
use PhpSoftBox\Telegram\Tests\Support\FakeTelegramClient;
use PhpSoftBox\Telegram\Update\Update;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FlowEngineTest extends TestCase
{
    /**
     * Проверяет, что движок выбирает первый transition, для которого guard вернул true.
     */
    #[Test]
    public function selectsFirstMatchedTransition(): void
    {
        $provider = new ArrayFlowDefinitionsProvider([
            FlowTransition::action('s02', 'trial_start', 'trial.already_used', AlwaysFalseGuard::class),
            FlowTransition::action(
                's02',
                'trial_start',
                'trial.activate',
                AlwaysTrueGuard::class,
                expectedScreen: 's08',
            ),
        ]);

        $registry = new ArrayFlowGuardRegistry([
            new AlwaysFalseGuard(),
            new AlwaysTrueGuard(),
        ]);

        $engine = new FlowEngine($provider, $registry);

        $decision = $engine->decide('s02', 'trial_start', $this->context());

        $this->assertNotNull($decision);
        $this->assertSame('s02', $decision->from);
        $this->assertSame('trial_start', $decision->event);
        $this->assertSame(FlowTargetsEnum::ACTION, $decision->target->type);
        $this->assertSame('trial.activate', $decision->target->id);
        $this->assertSame('always_true', $decision->guardId);
        $this->assertSame('s08', $decision->expectedScreen);
    }

    /**
     * Проверяет, что при отсутствии подходящих transition движок возвращает null.
     */
    #[Test]
    public function returnsNullWhenNothingMatched(): void
    {
        $provider = new ArrayFlowDefinitionsProvider([
            FlowTransition::action('s02', 'trial_start', 'trial.activate', AlwaysFalseGuard::class),
        ]);
        $registry = new ArrayFlowGuardRegistry([
            new AlwaysFalseGuard(),
        ]);

        $engine = new FlowEngine($provider, $registry);

        $this->assertNull($engine->decide('s02', 'trial_start', $this->context()));
    }

    /**
     * Проверяет, что для screen-target expectedScreen заполняется автоматически.
     */
    #[Test]
    public function fillsExpectedScreensForScreenTargetByDefault(): void
    {
        $provider = new ArrayFlowDefinitionsProvider([
            FlowTransition::screen('s02', 'trial_start', 's16', AlwaysTrueGuard::class),
        ]);
        $registry = new ArrayFlowGuardRegistry([
            new AlwaysTrueGuard(),
        ]);

        $engine = new FlowEngine($provider, $registry);

        $decision = $engine->decide('s02', 'trial_start', $this->context());

        $this->assertNotNull($decision);
        $this->assertSame(FlowTargetsEnum::SCREEN, $decision->target->type);
        $this->assertSame('s16', $decision->target->id);
        $this->assertSame('s16', $decision->expectedScreen);
    }

    /**
     * Проверяет, что guard получает статические аргументы transition (`guardArgs`).
     */
    #[Test]
    public function passesGuardArgsToEvaluation(): void
    {
        $provider = new ArrayFlowDefinitionsProvider([
            FlowTransition::action('s02', 'trial_start', 'trial.activate', MinDaysGuard::class, ['min' => 1]),
        ]);
        $registry = new ArrayFlowGuardRegistry([
            new MinDaysGuard(),
        ]);

        $engine = new FlowEngine($provider, $registry);

        $decision = $engine->decide('s02', 'trial_start', $this->context(['trial_days' => 3]));

        $this->assertNotNull($decision);
        $this->assertSame('trial.activate', $decision->target->id);
        $this->assertSame('trial.min_days', $decision->guardId);
    }

    /**
     * Проверяет, что при неизвестном классе guard в реестре выбрасывается исключение.
     */
    #[Test]
    public function throwsForUnknownGuardClassInRegistry(): void
    {
        $provider = new ArrayFlowDefinitionsProvider([
            FlowTransition::action('s02', 'trial_start', 'trial.activate', UnknownGuard::class),
        ]);
        $registry = new ArrayFlowGuardRegistry([]);

        $engine = new FlowEngine($provider, $registry);

        $this->expectException(RuntimeException::class);

        $engine->decide('s02', 'trial_start', $this->context());
    }

    /**
     * Проверяет валидацию провайдера: в transitions допускаются только FlowTransition объекты.
     */
    #[Test]
    public function throwsForInvalidTransitionDefinition(): void
    {
        $provider = new ArrayFlowDefinitionsProvider([
            ['invalid' => 'transition'],
        ]);
        $registry = new ArrayFlowGuardRegistry([
            new AlwaysTrueGuard(),
        ]);

        $engine = new FlowEngine($provider, $registry);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transition definition must be FlowTransition');

        $engine->decide('s02', 'trial_start', $this->context());
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function context(array $payload = []): TransitionContext
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
        $botContext = new BotContext(new FakeTelegramClient());

        return new TransitionContext($update, $botContext, $payload);
    }
}

final class AlwaysFalseGuard implements FlowGuardInterface
{
    public function getId(): string
    {
        return 'always_false';
    }

    public function evaluate(TransitionContext $context, array $args = []): bool
    {
        return false;
    }
}

final class AlwaysTrueGuard implements FlowGuardInterface
{
    public function getId(): string
    {
        return 'always_true';
    }

    public function evaluate(TransitionContext $context, array $args = []): bool
    {
        return true;
    }
}

final class MinDaysGuard implements FlowGuardInterface
{
    public function getId(): string
    {
        return 'trial.min_days';
    }

    public function evaluate(TransitionContext $context, array $args = []): bool
    {
        return (int) $context->payload('trial_days', 0) >= (int) ($args['min'] ?? 0);
    }
}

final class UnknownGuard implements FlowGuardInterface
{
    public function getId(): string
    {
        return 'unknown';
    }

    public function evaluate(TransitionContext $context, array $args = []): bool
    {
        return false;
    }
}
