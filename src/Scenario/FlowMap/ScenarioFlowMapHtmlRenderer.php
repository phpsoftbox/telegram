<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

use function htmlspecialchars;
use function json_encode;

use const ENT_QUOTES;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class ScenarioFlowMapHtmlRenderer
{
    public function render(
        string $dotSource,
        string $scope = 'all',
        string $rankdir = 'TB',
        ?string $vizJsCode = null,
        ?string $vizRenderCode = null,
    ): string {
        $scopeHtml   = htmlspecialchars($scope, ENT_QUOTES, 'UTF-8');
        $rankdirHtml = htmlspecialchars($rankdir, ENT_QUOTES, 'UTF-8');
        $dotJson     = json_encode($dotSource, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';

        $runtime = '';
        if ($vizJsCode !== null && $vizJsCode !== '') {
            $runtime .= "<script>\n" . $vizJsCode . "\n</script>\n";
        }
        if ($vizRenderCode !== null && $vizRenderCode !== '') {
            $runtime .= "<script>\n" . $vizRenderCode . "\n</script>\n";
        }
        if ($runtime === '') {
            $runtime = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/viz.js@2.1.2/viz.js"></script>
<script src="https://cdn.jsdelivr.net/npm/viz.js@2.1.2/full.render.js"></script>
HTML;
        }

        return <<<HTML
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Telegram Main Flow Map</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --panel: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #d1d5db;
            --accent: #2563eb;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 14px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            overflow: hidden;
        }
        .layout {
            width: 100%;
            height: 100vh;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            gap: 8px;
            padding: 10px;
        }
        .header {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 14px;
        }
        .header h1 { margin: 0 0 4px; font-size: 16px; }
        .header p { margin: 0; color: var(--muted); }
        .legend {
            display: flex;
            gap: 14px;
            color: var(--muted);
            margin-top: 8px;
            font-size: 13px;
            flex-wrap: wrap;
        }
        .dot {
            width: 10px;
            height: 10px;
            display: inline-block;
            border-radius: 999px;
            margin-right: 6px;
            vertical-align: middle;
        }
        .dot.screen { background: #60a5fa; }
        .dot.button { background: #22c55e; }
        .dot.action { background: #f59e0b; }
        .dot.entry { background: #ef4444; }
        .dot.handler { background: #9ca3af; }
        .dot.switch { background: #8b5cf6; }
        .diagram {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 8px;
            overflow: auto;
            overscroll-behavior: contain;
            min-height: 0;
            height: 100%;
            cursor: grab;
            user-select: none;
            touch-action: none;
            position: relative;
        }
        .diagram-tools {
            position: sticky;
            top: 0;
            left: 0;
            z-index: 3;
            display: inline-flex;
            gap: 6px;
            align-items: center;
            padding: 6px;
            margin: 0 0 6px 0;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        .diagram-tools button {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            border-radius: 6px;
            min-width: 36px;
            height: 28px;
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
        }
        .diagram-tools button:hover { border-color: var(--accent); }
        #flow-diagram-zoom-value {
            color: var(--muted);
            min-width: 56px;
            text-align: right;
            font-size: 12px;
        }
        #flow-diagram-status {
            color: var(--muted);
            margin-left: 6px;
            font-size: 12px;
        }
        .diagram.is-panning { cursor: grabbing; }
        .diagram-canvas {
            width: max-content;
            height: max-content;
        }
        #flow-diagram {
            width: max-content;
            transform-origin: top left;
        }
        #flow-diagram svg {
            max-width: none !important;
            width: auto !important;
            height: auto !important;
            display: block;
        }
    </style>
</head>
<body>
<main class="layout">
    <section class="header">
        <h1>Telegram Main Flow Map</h1>
        <p><strong>Scope:</strong> {$scopeHtml}</p>
        <p><strong>Direction:</strong> {$rankdirHtml}</p>
        <div class="legend">
            <span><span class="dot screen"></span>Screen</span>
            <span><span class="dot button"></span>Button</span>
            <span><span class="dot action"></span>Action</span>
            <span><span class="dot entry"></span>Entry point</span>
            <span><span class="dot handler"></span>Handler</span>
            <span><span class="dot switch"></span>Switch</span>
            <span>ЛКМ + drag — панорамирование, колесо — масштаб, double click — reset</span>
        </div>
    </section>
    <section class="diagram" id="flow-diagram-viewport">
        <div class="diagram-tools" id="flow-diagram-tools">
            <button type="button" data-zoom="in">+</button>
            <button type="button" data-zoom="out">−</button>
            <button type="button" data-zoom="reset">100%</button>
            <button type="button" data-zoom="fit">Fit</button>
            <span id="flow-diagram-zoom-value">100%</span>
            <span id="flow-diagram-status">Loading…</span>
        </div>
        <div class="diagram-canvas" id="flow-diagram-canvas">
            <div id="flow-diagram"></div>
        </div>
    </section>
</main>

{$runtime}
<script>
    const dotSource = {$dotJson};
    const viewport = document.getElementById("flow-diagram-viewport");
    const canvas = document.getElementById("flow-diagram-canvas");
    const graph = document.getElementById("flow-diagram");
    const tools = document.getElementById("flow-diagram-tools");
    const zoomValue = document.getElementById("flow-diagram-zoom-value");
    const status = document.getElementById("flow-diagram-status");

    if (viewport && canvas && graph) {
        let zoom = 1;
        const minZoom = 0.2;
        const maxZoom = 3.5;
        const zoomStep = 0.12;
        let baseWidth = 0;
        let baseHeight = 0;
        let panX = 0;
        let panY = 0;
        let panActive = false;
        let panStartX = 0;
        let panStartY = 0;
        let panOriginX = 0;
        let panOriginY = 0;

        const setStatus = (message) => {
            if (status) {
                status.textContent = message;
            }
        };

        const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

        const applyTransform = () => {
            graph.style.transform = "translate(" + panX + "px, " + panY + "px) scale(" + zoom + ")";
            if (zoomValue) {
                zoomValue.textContent = Math.round(zoom * 100) + "%";
            }
        };

        const center = () => {
            const viewportWidth = viewport.clientWidth;
            const viewportHeight = viewport.clientHeight;
            const graphWidth = baseWidth * zoom;
            const graphHeight = baseHeight * zoom;
            panX = Math.max(0, (viewportWidth - graphWidth) / 2);
            panY = Math.max(0, (viewportHeight - graphHeight) / 2);
            applyTransform();
        };

        const zoomAtPoint = (delta, clientX, clientY) => {
            const nextZoom = clamp(zoom + delta, minZoom, maxZoom);
            if (nextZoom === zoom) {
                return;
            }

            const rect = viewport.getBoundingClientRect();
            const x = clientX - rect.left;
            const y = clientY - rect.top;
            const scale = nextZoom / zoom;
            panX = x - (x - panX) * scale;
            panY = y - (y - panY) * scale;
            zoom = nextZoom;
            applyTransform();
        };

        const zoomIn = () => zoomAtPoint(zoomStep, viewport.clientWidth / 2, viewport.clientHeight / 2);
        const zoomOut = () => zoomAtPoint(-zoomStep, viewport.clientWidth / 2, viewport.clientHeight / 2);
        const zoomReset = () => {
            zoom = 1;
            center();
        };
        const zoomFit = () => {
            if (baseWidth <= 0 || baseHeight <= 0) {
                return;
            }
            const scaleX = (viewport.clientWidth - 20) / baseWidth;
            const scaleY = (viewport.clientHeight - 20) / baseHeight;
            zoom = clamp(Math.min(scaleX, scaleY), minZoom, maxZoom);
            center();
        };

        viewport.addEventListener("wheel", (event) => {
            event.preventDefault();
            const delta = event.deltaY < 0 ? zoomStep : -zoomStep;
            zoomAtPoint(delta, event.clientX, event.clientY);
        }, { passive: false });

        viewport.addEventListener("pointerdown", (event) => {
            if (event.button !== 0) {
                return;
            }
            panActive = true;
            panStartX = event.clientX;
            panStartY = event.clientY;
            panOriginX = panX;
            panOriginY = panY;
            viewport.classList.add("is-panning");
            viewport.setPointerCapture(event.pointerId);
        });

        viewport.addEventListener("pointermove", (event) => {
            if (!panActive) {
                return;
            }
            panX = panOriginX + (event.clientX - panStartX);
            panY = panOriginY + (event.clientY - panStartY);
            applyTransform();
        });

        const stopPan = (event) => {
            if (!panActive) {
                return;
            }
            panActive = false;
            viewport.classList.remove("is-panning");
            if (event && viewport.hasPointerCapture(event.pointerId)) {
                viewport.releasePointerCapture(event.pointerId);
            }
        };

        viewport.addEventListener("pointerup", stopPan);
        viewport.addEventListener("pointercancel", stopPan);
        viewport.addEventListener("dblclick", () => zoomReset());

        if (tools) {
            tools.addEventListener("click", (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }
                const control = target.getAttribute("data-zoom");
                if (control === "in") {
                    zoomIn();
                } else if (control === "out") {
                    zoomOut();
                } else if (control === "reset") {
                    zoomReset();
                } else if (control === "fit") {
                    zoomFit();
                }
            });
        }

        const setGraph = (svgMarkup) => {
            graph.innerHTML = svgMarkup;
            const svg = graph.querySelector("svg");
            if (!svg) {
                setStatus("Render failed: SVG is empty");
                return;
            }

            svg.removeAttribute("width");
            svg.removeAttribute("height");
            const box = svg.getBBox();
            baseWidth = Math.max(1, Math.ceil(box.width));
            baseHeight = Math.max(1, Math.ceil(box.height));
            graph.style.width = baseWidth + "px";
            graph.style.height = baseHeight + "px";
            zoom = 1;
            center();
            setStatus("Ready");
        };

        const render = () => {
            const hasVizConstructor = typeof window.Viz === "function";
            if (!hasVizConstructor) {
                setStatus("Viz.js runtime is not available");
                return;
            }

            setStatus("Rendering…");
            try {
                const hasGlobalRuntime = typeof window.Module !== "undefined" && typeof window.render !== "undefined";
                const hasVizRuntime = typeof window.Viz.Module !== "undefined" && typeof window.Viz.render === "function";
                const viz = hasGlobalRuntime
                    ? new window.Viz({ Module: window.Module, render: window.render })
                    : (hasVizRuntime
                        ? new window.Viz({ Module: window.Viz.Module, render: window.Viz.render })
                        : new window.Viz());
                viz.renderSVGElement(dotSource)
                    .then((element) => {
                        setGraph(element.outerHTML);
                    })
                    .catch((error) => {
                        setStatus("Render failed: " + error.message);
                    });
            } catch (error) {
                const message = error instanceof Error ? error.message : String(error);
                setStatus("Render failed: " + message);
            }
        };

        render();
        window.addEventListener("resize", () => {
            if (baseWidth > 0 && baseHeight > 0) {
                center();
            }
        });
    }
</script>
</body>
</html>
HTML;
    }
}
