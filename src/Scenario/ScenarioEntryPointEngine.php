<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Flow\FlowGuardRegistryInterface;
use PhpSoftBox\Telegram\Flow\TransitionContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function trim;
use function usort;

final readonly class ScenarioEntryPointEngine
{
    private LoggerInterface $logger;

    public function __construct(
        private ScenarioEntryPointDefinitionsProviderInterface $definitions,
        private FlowGuardRegistryInterface $guards,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function decide(string $entryPoint, TransitionContext $context): ?ScenarioEntryPointDecision
    {
        $entryPoint = trim($entryPoint);
        if ($entryPoint === '') {
            return null;
        }

        $candidates = [];
        $index      = 0;
        foreach ($this->definitions->entryPoints() as $definition) {
            if ($definition->name !== $entryPoint) {
                continue;
            }

            $candidates[] = [
                'index'       => $index,
                'entry_point' => $definition,
            ];
            $index++;
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => $left['entry_point']->priority <=> $right['entry_point']->priority
                ?: ($left['index'] <=> $right['index']),
        );

        foreach ($candidates as $candidate) {
            $definition = $candidate['entry_point'];
            $guardId    = null;
            $matched    = true;
            if ($definition->guardClass !== null) {
                $guard   = $this->guards->get($definition->guardClass);
                $guardId = trim($guard->getId());
                $matched = $guard->evaluate($context, $definition->guardArgs);
            }

            $this->logger->info('telegram.entrypoint.guard', [
                'entry_point' => $entryPoint,
                'target_type' => $definition->target->type->value,
                'target_id'   => $definition->target->id,
                'guard_id'    => $guardId,
                'matched'     => $matched,
            ]);

            if (!$matched) {
                continue;
            }

            $this->logger->info('telegram.entrypoint.transition', [
                'entry_point' => $entryPoint,
                'target_type' => $definition->target->type->value,
                'target_id'   => $definition->target->id,
                'guard_id'    => $guardId,
            ]);

            return new ScenarioEntryPointDecision(
                name: $entryPoint,
                target: $definition->target,
                guardId: $guardId,
            );
        }

        $this->logger->warning('telegram.entrypoint.unresolved', [
            'entry_point' => $entryPoint,
        ]);

        return null;
    }
}
