<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

use PhpSoftBox\Telegram\Scenario\CompiledScenario;

final readonly class ScenarioFlowMapService
{
    public function __construct(
        private ScenarioFlowMapFactory $factory = new ScenarioFlowMapFactory(),
        private ScenarioFlowMapScopeResolver $scopeResolver = new ScenarioFlowMapScopeResolver(),
        private ScenarioFlowMapDotRenderer $dotRenderer = new ScenarioFlowMapDotRenderer(),
        private ScenarioFlowMapHtmlRenderer $htmlRenderer = new ScenarioFlowMapHtmlRenderer(),
    ) {
    }

    public function build(
        CompiledScenario $scenario,
        bool $includeButtons = true,
    ): ScenarioFlowMap {
        return $this->factory->build($scenario, $includeButtons);
    }

    /**
     * @param list<ScenarioFlowMapBranch> $branches
     * @param list<ScenarioFlowMapCjm> $cjms
     */
    public function scope(
        ScenarioFlowMap $map,
        string $scope = 'all',
        array $branches = [],
        array $cjms = [],
    ): ScenarioFlowMap {
        return $this->scopeResolver->resolve($map, $scope, $branches, $cjms);
    }

    /**
     * @param list<ScenarioFlowMapBranch> $branches
     * @param list<ScenarioFlowMapCjm> $cjms
     */
    public function dot(
        CompiledScenario $scenario,
        bool $includeButtons = true,
        string $scope = 'all',
        array $branches = [],
        string $rankdir = 'TB',
        array $cjms = [],
    ): string {
        $map    = $this->build($scenario, $includeButtons);
        $scoped = $this->scope($map, $scope, $branches, $cjms);

        return $this->dotRenderer->render($scoped, $rankdir);
    }

    /**
     * @param list<ScenarioFlowMapBranch> $branches
     * @param list<ScenarioFlowMapCjm> $cjms
     */
    public function html(
        CompiledScenario $scenario,
        bool $includeButtons = true,
        string $scope = 'all',
        array $branches = [],
        string $rankdir = 'TB',
        ?string $vizJsCode = null,
        ?string $vizRenderCode = null,
        array $cjms = [],
    ): string {
        $dot = $this->dot($scenario, $includeButtons, $scope, $branches, $rankdir, $cjms);

        return $this->htmlRenderer->render($dot, $scope, $rankdir, $vizJsCode, $vizRenderCode);
    }
}
