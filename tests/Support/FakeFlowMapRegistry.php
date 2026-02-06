<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Support;

use PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapRegistryInterface;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMap;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapBranch;
use PhpSoftBox\Telegram\Scenario\FlowMap\ScenarioFlowMapCjm;

use function array_values;

final class FakeFlowMapRegistry implements TelegramFlowMapRegistryInterface
{
    /**
     * @var list<ScenarioFlowMapBranch>
     */
    public array $branches = [];

    /**
     * @var list<ScenarioFlowMapCjm>
     */
    public array $cjms = [];

    /**
     * @var array{
     *   nodes:list<array{id:string,type:string,label:string}>,
     *   edges:list<array{
     *     from:string,
     *     to:string,
     *     kind:string,
     *     label:string,
     *     event:?string,
     *     target_type:?string,
     *     target_id:?string,
     *     guard_class:?string,
     *     guard_args:array<string,mixed>,
     *     expected_screen:?string
     *   }>
     * }
     */
    public array $scopedMap;

    public string $html = '<html>stub</html>';

    /**
     * @var list<string>
     */
    public array $branchLookup = [];

    /**
     * @var list<string>
     */
    public array $cjmLookup = [];

    /**
     * @var list<string>
     */
    public array $scopes = [];

    public function __construct(
        private readonly ScenarioFlowMap $componentMap,
    ) {
        $this->scopedMap = $componentMap->toArray();
    }

    public function flowMapComponent(bool $includeButtons = true): ScenarioFlowMap
    {
        return $this->componentMap;
    }

    public function flowMapScoped(bool $includeButtons = true, string $chainScope = 'all'): array
    {
        $this->scopes[] = $chainScope;

        return $this->scopedMap;
    }

    public function flowMapHtml(bool $includeButtons = true, string $chainScope = 'all', string $rankdir = 'TB'): string
    {
        $this->scopes[] = $chainScope;

        return $this->html;
    }

    public function flowMapBranchDefinitions(?string $branchId = null): array
    {
        if ($branchId !== null) {
            $this->branchLookup[] = $branchId;
        }

        if ($branchId === null || $branchId === '') {
            return $this->branches;
        }

        $filtered = [];
        foreach ($this->branches as $branch) {
            if ($branch->id !== $branchId) {
                continue;
            }

            $filtered[] = $branch;
        }

        return array_values($filtered);
    }

    public function flowMapCjmDefinitions(?string $cjmId = null): array
    {
        if ($cjmId !== null) {
            $this->cjmLookup[] = $cjmId;
        }

        if ($cjmId === null || $cjmId === '') {
            return $this->cjms;
        }

        $filtered = [];
        foreach ($this->cjms as $cjm) {
            if ($cjm->id !== $cjmId) {
                continue;
            }

            $filtered[] = $cjm;
        }

        return array_values($filtered);
    }
}
