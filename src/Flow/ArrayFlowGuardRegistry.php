<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

use RuntimeException;

use function array_key_exists;
use function is_string;
use function trim;

final readonly class ArrayFlowGuardRegistry implements FlowGuardRegistryInterface
{
    /**
     * @param list<FlowGuardInterface>|array<string, FlowGuardInterface> $guards
     */
    public function __construct(array $guards)
    {
        $resolved = [];
        foreach ($guards as $key => $guard) {
            if (!$guard instanceof FlowGuardInterface) {
                throw new RuntimeException('Guard registry accepts only FlowGuardInterface instances.');
            }

            $guardClass = is_string($key) ? trim($key) : '';
            if ($guardClass === '') {
                $guardClass = $guard::class;
            }

            $resolved[$guardClass] = $guard;
        }

        $this->guards = $resolved;
    }

    /**
     * @var array<string, FlowGuardInterface>
     */
    private array $guards;

    public function get(string $guardClass): FlowGuardInterface
    {
        $guardClass = trim($guardClass);
        if ($guardClass === '') {
            throw new RuntimeException('Guard class must not be empty.');
        }

        if (!array_key_exists($guardClass, $this->guards)) {
            throw new RuntimeException('Unknown flow guard class: ' . $guardClass);
        }

        return $this->guards[$guardClass];
    }
}
