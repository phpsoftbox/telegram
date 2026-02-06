<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function trim;

final readonly class FlowEngine
{
    private LoggerInterface $logger;

    public function __construct(
        private FlowDefinitionsProviderInterface $definitions,
        private FlowGuardRegistryInterface $guards,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function decide(string $from, string $event, TransitionContext $context): ?FlowDecision
    {
        $from  = trim($from);
        $event = trim($event);
        if ($from === '' || $event === '') {
            return null;
        }

        foreach ($this->definitions->transitions() as $transition) {
            if ($transition->from !== $from || $transition->event !== $event) {
                continue;
            }

            $guardId = null;
            $matched = true;
            if ($transition->guardClass !== null) {
                $guard   = $this->guards->get($transition->guardClass);
                $guardId = trim($guard->getId());
                $matched = $guard->evaluate($context, $transition->guardArgs);
            }

            $this->logger->info('telegram.flow.guard', [
                'from'        => $from,
                'event'       => $event,
                'target_type' => $transition->target->type->value,
                'target_id'   => $transition->target->id,
                'guard_id'    => $guardId,
                'matched'     => $matched,
            ]);

            if (!$matched) {
                continue;
            }

            $this->logger->info('telegram.flow.transition', [
                'from'        => $from,
                'event'       => $event,
                'target_type' => $transition->target->type->value,
                'target_id'   => $transition->target->id,
                'guard_id'    => $guardId,
            ]);

            return new FlowDecision(
                from: $from,
                event: $event,
                target: $transition->target,
                guardId: $guardId,
                expectedScreen: $transition->expectedScreen ?? ($transition->target->isScreen() ? $transition->target->id : null),
            );
        }

        $this->logger->warning('telegram.flow.unresolved', [
            'from'  => $from,
            'event' => $event,
        ]);

        return null;
    }
}
