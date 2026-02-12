<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Web;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Tooling\Architecture\ArchitectureDecisionValidator;
use Celeris\Framework\Tooling\Generator\GenerationRequest;
use Celeris\Framework\Tooling\Generator\GeneratorEngine;
use Celeris\Framework\Tooling\Graph\DependencyGraphBuilder;
use Celeris\Framework\Tooling\ToolingException;

/**
 * Purpose: implement developer ui controller behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when developer ui controller functionality is required.
 */
final class DeveloperUiController
{
   private const API_VERSION = 'v1';
   private const ENV_KEY = 'APP_ENV';
   private const ENABLED_KEY = 'TOOLING_ENABLED';
   private const ALLOWED_ENVS_KEY = 'TOOLING_ALLOWED_ENVS';
   private const AUDIT_ENABLED_KEY = 'TOOLING_AUDIT_ENABLED';
   private const AUDIT_PATH_KEY = 'TOOLING_AUDIT_PATH';

   /**
    * Create a new instance.
    *
    * @param GeneratorEngine $generatorEngine
    * @param DependencyGraphBuilder $dependencyGraphBuilder
    * @param ArchitectureDecisionValidator $architectureValidator
    * @param string $projectRoot
    * @param string $routePrefix
    * @param string $namespaceRoot
    * @return mixed
    */
   public function __construct(
      private GeneratorEngine $generatorEngine,
      private DependencyGraphBuilder $dependencyGraphBuilder,
      private ArchitectureDecisionValidator $architectureValidator,
      private string $projectRoot,
      private string $routePrefix = '/__dev/tooling',
      private string $namespaceRoot = 'App',
   ) {}

   /**
    * Invoke the handler.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return Response
    */
   public function __invoke(RequestContext $ctx, Request $request): Response
   {
      return $this->handle($ctx, $request);
   }

   /**
    * Handle handle.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return Response
    */
   public function handle(RequestContext $ctx, Request $request): Response
   {
      if (!$this->isToolingEnabled()) {
         return new Response(
            404,
            ['content-type' => 'text/plain; charset=utf-8'],
            'Not Found'
         );
      }

      $subPath = $this->subPath($request->getPath());

      $apiPrefix = '/api/' . self::API_VERSION;
      if ($subPath === $apiPrefix || str_starts_with($subPath, $apiPrefix . '/')) {
         $apiSubPath = substr($subPath, strlen($apiPrefix));
         return $this->apiDispatch($ctx, $request, $apiSubPath === '' ? '/' : $apiSubPath);
      }

      if ($subPath === '/graph') {
         return $this->legacyGraphResponse();
      }

      if ($subPath === '/validate') {
         return $this->legacyValidateResponse();
      }

      if ($subPath === '/generate/preview') {
         return $this->legacyPreviewResponse($ctx, $request);
      }

      if ($subPath === '/generate/apply') {
         return $this->legacyApplyResponse($ctx, $request);
      }

      return $this->dashboardResponse($ctx, $request);
   }

   /**
    * @param RequestContext $ctx
    */
   private function dashboardResponse(RequestContext $ctx, Request $request): Response
   {
      $summary = $this->summaryPayload();
      $report = $this->architectureValidator->validate($this->dependencyGraphBuilder->buildModuleGraph());
      $statusText = $report->isValid() ? 'VALID' : 'VIOLATIONS';
      $statusClass = $report->isValid() ? 'ok' : 'error';
      $apiBase = rtrim($this->routePrefix, '/') . '/api/' . self::API_VERSION;

      $preloadedJson = (string) json_encode(
         $summary,
         JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
      );
      $apiBaseJson = (string) json_encode($apiBase, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
      $routePrefixJson = (string) json_encode($this->routePrefix, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
      $requestId = $this->escape($ctx->getRequestId());
      $legacyQuery = $request->getQueryParams();
      $legacyGenerator = is_string($legacyQuery['generator'] ?? null) ? trim((string) $legacyQuery['generator']) : '';
      $legacyName = is_string($legacyQuery['name'] ?? null) ? trim((string) $legacyQuery['name']) : '';
      $legacyModule = is_string($legacyQuery['module'] ?? null) ? trim((string) $legacyQuery['module']) : '';

      $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Celeris Tooling</title>
<style>
:root {
  --surface: #f4eee2;
  --panel: #fffaf0;
  --line: #d8cfc1;
  --ink: #18202a;
  --muted: #596575;
  --accent: #e1573f;
  --ok: #0b8c74;
  --error: #be2f29;
  --shadow: rgba(20, 20, 20, 0.08);
}
* { box-sizing: border-box; margin: 0; }
body {
  font-family: "Space Grotesk", "Segoe UI", sans-serif;
  color: var(--ink);
  background: radial-gradient(circle at 14% 8%, #f8d9b4 0%, transparent 32%), linear-gradient(145deg, #f7f1e8 0%, #f3ede1 55%, #ece6da 100%);
}
main {
  max-width: 1200px;
  margin: 1.5rem auto;
  padding: 0 1rem 2.5rem;
}
.grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1rem;
}
@media (max-width: 950px) {
  .grid { grid-template-columns: 1fr; }
}
.card {
  background: var(--panel);
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 1rem;
  margin-bottom: 1rem;
  box-shadow: 0 8px 24px var(--shadow);
}
h1 {
  font-size: 1.6rem;
  margin-bottom: 0.4rem;
}
.badge {
  display: inline-block;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  color: #fff;
  font-weight: 700;
  letter-spacing: 0.04em;
  font-size: 0.75rem;
}
.badge.ok { background: var(--ok); }
.badge.error { background: var(--error); }
.muted {
  color: var(--muted);
  font-size: 0.86rem;
}
.row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.6rem;
  margin-top: 0.75rem;
}
@media (max-width: 920px) {
  .row { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 640px) {
  .row { grid-template-columns: 1fr; }
}
label {
  display: block;
  font-size: 0.77rem;
  color: var(--muted);
  margin-bottom: 0.25rem;
}
input, select {
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 0.45rem 0.55rem;
  font: inherit;
  background: #fff;
}
.actions {
  margin-top: 0.75rem;
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
button {
  border: 0;
  border-radius: 10px;
  background: #2c3f56;
  color: #fff;
  padding: 0.5rem 0.8rem;
  font: inherit;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
}
button.primary {
  background: var(--accent);
}
button:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
.list {
  list-style: none;
  padding-left: 0;
  margin-top: 0.6rem;
  display: grid;
  gap: 0.45rem;
}
.list li {
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 0.5rem 0.6rem;
  background: #fffef8;
  font-size: 0.84rem;
}
.list .meta {
  color: var(--muted);
  font-size: 0.75rem;
}
pre {
  margin-top: 0.7rem;
  overflow: auto;
  max-height: 18rem;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: #172133;
  color: #f4f4f4;
  padding: 0.6rem;
  white-space: pre;
  font-family: "JetBrains Mono", "Cascadia Code", monospace;
  font-size: 0.76rem;
  line-height: 1.36;
}
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
  margin-top: 0.55rem;
}
.table th, .table td {
  border-bottom: 1px solid var(--line);
  padding: 0.4rem 0.25rem;
  text-align: left;
}
.table th {
  color: var(--muted);
  font-weight: 600;
}
.status {
  padding: 0.45rem 0.55rem;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: #fff;
  font-size: 0.82rem;
}
.status.error {
  border-color: #efb4b0;
  background: #fff0ef;
  color: #7d2320;
}
.status.ok {
  border-color: #a2dcca;
  background: #edf9f4;
  color: #0d5a49;
}
</style>
</head>
<body>
<main>
  <section class="card">
    <h1>Celeris Tooling Platform</h1>
    <p class="muted">API-driven developer dashboard. Request id: {$requestId}</p>
    <div style="margin-top:0.5rem;">
      <span id="archBadge" class="badge {$statusClass}">ARCH {$statusText}</span>
      <span class="muted" style="margin-left:0.55rem;">Route prefix: {$this->escape($this->routePrefix)}</span>
    </div>
  </section>
  <div class="grid">
    <section>
      <section class="card">
        <h2>Generator Workflow</h2>
        <p class="muted" style="margin-top:0.25rem;">Preview diffs first, then apply with explicit intent.</p>

        <div class="row">
          <div>
            <label for="generator">Generator</label>
            <select id="generator"></select>
          </div>
          <div>
            <label for="name">Name</label>
            <input id="name" value="{$this->escape($legacyName !== '' ?$legacyName : 'Sample')}">
          </div>
          <div>
            <label for="module">Module</label>
            <input id="module" value="{$this->escape($legacyModule !== '' ?$legacyModule : 'Generated')}">
          </div>
          <div>
            <label for="overwrite">Overwrite</label>
            <select id="overwrite">
              <option value="false">No</option>
              <option value="true">Yes</option>
            </select>
          </div>
        </div>
        <div class="actions">
          <button id="previewBtn" class="primary">Preview</button>
          <button id="applyBtn">Apply</button>
          <button id="refreshBtn">Refresh Snapshot</button>
        </div>
        <div id="previewStatus" class="status" style="margin-top:0.7rem;">Idle</div>
        <ul id="previewFiles" class="list"></ul>
        <pre id="diffPanel">(no diff selected)</pre>
      </section>
      <section class="card">
        <h2>Architecture Validation</h2>
        <div id="validationStatus" class="status">Loading...</div>
        <table class="table">
          <thead>
            <tr><th>Severity</th><th>Rule</th><th>Message</th></tr>
          </thead>
          <tbody id="violationsBody"></tbody>
        </table>
      </section>
    </section>
    <section>
      <section class="card">
        <h2>Module Graph</h2>
        <p class="muted">Current module dependencies.</p>
        <ul id="graphList" class="list"></ul>
      </section>
      <section class="card">
        <h2>Legacy Compatibility</h2>
        <p class="muted">Legacy endpoints remain active:</p>
        <ul class="list">
          <li><code>{$this->escape($this->routePrefix)}/graph</code></li>
          <li><code>{$this->escape($this->routePrefix)}/validate</code></li>
          <li><code>{$this->escape($this->routePrefix)}/generate/preview</code></li>
          <li><code>{$this->escape($this->routePrefix)}/generate/apply</code></li>
        </ul>
      </section>
    </section>
  </div>
  <section class="card">
    <h2>Raw Snapshot</h2>
    <pre id="snapshotPanel"></pre>
  </section>
</main>
<script>
const API_BASE = {$apiBaseJson};
const ROUTE_PREFIX = {$routePrefixJson};
const PRELOADED = {$preloadedJson};

const state = {
  summary: PRELOADED,
  preview: [],
};

const elements = {
  archBadge: document.getElementById('archBadge'),
  generator: document.getElementById('generator'),
  name: document.getElementById('name'),
  module: document.getElementById('module'),
  overwrite: document.getElementById('overwrite'),
  previewBtn: document.getElementById('previewBtn'),
  applyBtn: document.getElementById('applyBtn'),
  refreshBtn: document.getElementById('refreshBtn'),
  previewStatus: document.getElementById('previewStatus'),
  previewFiles: document.getElementById('previewFiles'),
  diffPanel: document.getElementById('diffPanel'),
  validationStatus: document.getElementById('validationStatus'),
  violationsBody: document.getElementById('violationsBody'),
  graphList: document.getElementById('graphList'),
  snapshotPanel: document.getElementById('snapshotPanel'),
};

function setLoading(active) {
  elements.previewBtn.disabled = active;
  elements.applyBtn.disabled = active;
  elements.refreshBtn.disabled = active;
}

function readGenerationInput() {
  return {
    generator: elements.generator.value.trim(),
    name: elements.name.value.trim(),
    module: elements.module.value.trim() || 'Generated',
    overwrite: elements.overwrite.value === 'true',
  };
}

function request(path, options) {
  const init = Object.assign({ method: 'GET', headers: {} }, options || {});
  if (init.body && typeof init.body !== 'string') {
    init.headers['content-type'] = 'application/json';
    init.body = JSON.stringify(init.body);
  }

  return fetch(API_BASE + path, init).then((response) => {
    return response.text().then((text) => {
      let payload;
      try {
        payload = text ? JSON.parse(text) : null;
      } catch (error) {
        payload = null;
      }

      if (!response.ok) {
        const reason = payload && payload.errors && payload.errors[0] ? payload.errors[0].message : response.statusText;
        throw new Error(reason || 'Request failed');
      }
      if (!payload || payload.status !== 'ok') {
        const reason = payload && payload.errors && payload.errors[0] ? payload.errors[0].message : 'Unexpected response';
        throw new Error(reason);
      }

      return payload.data;
    });
  });
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function renderSummary(summary) {
  state.summary = summary;
  elements.snapshotPanel.textContent = JSON.stringify(summary, null, 2);

  renderGenerators(summary.generators || []);
  renderValidation(summary.architecture || { valid: true, violations: [] });
  renderGraph(summary.graph || { modules: [] });
}

function renderGenerators(rows) {
  const current = elements.generator.value || '{$this->escape($legacyGenerator !== '' ?$legacyGenerator : 'controller')}';
  elements.generator.innerHTML = '';

  rows.forEach((row) => {
    const option = document.createElement('option');
    option.value = row.name;
    option.textContent = row.name + ' - ' + row.description;
    if (row.name === current) {
      option.selected = true;
    }
    elements.generator.appendChild(option);
  });

  if (!elements.generator.value && rows[0]) {
    elements.generator.value = rows[0].name;
  }
}

function renderValidation(report) {
  const valid = report.valid === true;
  elements.archBadge.textContent = 'ARCH ' + (valid ? 'VALID' : 'VIOLATIONS');
  elements.archBadge.className = 'badge ' + (valid ? 'ok' : 'error');
  elements.validationStatus.className = 'status ' + (valid ? 'ok' : 'error');
  elements.validationStatus.textContent = valid ? 'No architecture violations detected.' : 'Architecture violations detected.';

  const violations = Array.isArray(report.violations) ? report.violations : [];
  elements.violationsBody.innerHTML = '';
  if (violations.length === 0) {
    const row = document.createElement('tr');
    row.innerHTML = '<td colspan="3" class="muted">No violations.</td>';
    elements.violationsBody.appendChild(row);
    return;
  }

  violations.forEach((violation) => {
    const row = document.createElement('tr');
    row.innerHTML =
      '<td>' + escapeHtml(violation.severity || 'info') + '</td>' +
      '<td>' + escapeHtml(violation.rule || '') + '</td>' +
      '<td>' + escapeHtml(violation.message || '') + '</td>';
    elements.violationsBody.appendChild(row);
  });
}

function renderGraph(graph) {
  elements.graphList.innerHTML = '';
  const modules = Array.isArray(graph.modules) ? graph.modules : [];
  modules.forEach((module) => {
    const li = document.createElement('li');
    li.innerHTML = '<strong>' + escapeHtml(module.name || '') + '</strong>';
    elements.graphList.appendChild(li);
  });

  const edges = Array.isArray(graph.edges) ? graph.edges : [];
  edges.forEach((edge) => {
    const li = document.createElement('li');
    li.innerHTML = escapeHtml(edge.from + ' -> ' + edge.to) + '<div class="meta">type: ' + escapeHtml(edge.type || 'unknown') + '</div>';
    elements.graphList.appendChild(li);
  });

  if (modules.length === 0 && edges.length === 0) {
    const li = document.createElement('li');
    li.textContent = 'No graph data.';
    elements.graphList.appendChild(li);
  }
}

function renderPreview(files) {
  state.preview = files;
  elements.previewFiles.innerHTML = '';
  elements.diffPanel.textContent = '(no diff selected)';

  if (!Array.isArray(files) || files.length === 0) {
    const li = document.createElement('li');
    li.textContent = 'No files produced.';
    elements.previewFiles.appendChild(li);
    return;
  }

  files.forEach((file, index) => {
    const li = document.createElement('li');
    const changed = file.diff && file.diff !== '';
    li.innerHTML =
      '<div><strong>' + escapeHtml(file.path || '') + '</strong></div>' +
      '<div class="meta">exists=' + escapeHtml(String(file.exists === true)) + ' changed=' + escapeHtml(String(changed)) + '</div>';
    li.addEventListener('click', () => {
      elements.diffPanel.textContent = file.diff && file.diff !== '' ? file.diff : '(no diff)';
      Array.from(elements.previewFiles.children).forEach((item) => {
        item.style.outline = '';
      });
      li.style.outline = '2px solid #e1573f';
    });

    elements.previewFiles.appendChild(li);
    if (index === 0) {
      li.click();
    }
  });
}

function setPreviewStatus(message, ok) {
  elements.previewStatus.textContent = message;
  elements.previewStatus.className = 'status ' + (ok ? 'ok' : 'error');
}

function refreshSummary() {
  setLoading(true);
  request('/summary')
    .then((data) => {
      renderSummary(data);
      setPreviewStatus('Snapshot refreshed.', true);
    })
    .catch((error) => {
      setPreviewStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function preview() {
  const payload = readGenerationInput();
  setLoading(true);
  request('/generate/preview', { method: 'POST', body: payload })
    .then((data) => {
      renderPreview(data.files || []);
      setPreviewStatus('Preview generated for ' + payload.generator + '.' , true);
    })
    .catch((error) => {
      setPreviewStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function apply() {
  const payload = readGenerationInput();
  if (!window.confirm('Apply generated files for ' + payload.generator + ' / ' + payload.name + '?')) {
    return;
  }

  setLoading(true);
  request('/generate/apply', { method: 'POST', body: payload })
    .then((data) => {
      const written = Array.isArray(data.written) ? data.written.length : 0;
      const skipped = Array.isArray(data.skipped) ? data.skipped.length : 0;
      setPreviewStatus('Apply complete. written=' + written + ' skipped=' + skipped, true);
      refreshSummary();
    })
    .catch((error) => {
      setPreviewStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

elements.previewBtn.addEventListener('click', preview);
elements.applyBtn.addEventListener('click', apply);
elements.refreshBtn.addEventListener('click', refreshSummary);

renderSummary(PRELOADED);
</script>
</body>
</html>
HTML;

      return new Response(200, ['content-type' => 'text/html; charset=utf-8'], $html);
   }

   /**
    * @param RequestContext $ctx
    * @param Request $request
    * @param string $apiPath
    */
   private function apiDispatch(RequestContext $ctx, Request $request, string $apiPath): Response
   {
      if ($apiPath === '/' || $apiPath === '') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         return $this->apiOk($ctx, $this->summaryPayload());
      }

      if ($apiPath === '/summary') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         return $this->apiOk($ctx, $this->summaryPayload());
      }

      if ($apiPath === '/health') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         return $this->apiOk($ctx, [
            'ok' => true,
            'version' => self::API_VERSION,
            'route_prefix' => $this->routePrefix,
         ]);
      }

      if ($apiPath === '/graph') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         return $this->apiOk($ctx, ['graph' => $this->dependencyGraphBuilder->buildModuleGraph()->toArray()]);
      }

      if ($apiPath === '/validate') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }

         $graph = $this->dependencyGraphBuilder->buildModuleGraph();
         $report = $this->architectureValidator->validate($graph);
         return $this->apiOk($ctx, ['report' => $report->toArray()]);
      }

      if ($apiPath === '/generators') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         return $this->apiOk($ctx, ['items' => $this->generatorEngine->list()]);
      }

      if ($apiPath === '/generate/preview') {
         if (!in_array($request->getMethod(), ['GET', 'POST'], true)) {
            return $this->methodNotAllowed($ctx, ['GET', 'POST']);
         }
         return $this->apiPreviewResponse($ctx, $request);
      }

      if ($apiPath === '/generate/apply') {
         if (!in_array($request->getMethod(), ['POST'], true)) {
            return $this->methodNotAllowed($ctx, ['POST']);
         }
         return $this->apiApplyResponse($ctx, $request);
      }

      return $this->apiError($ctx, 404, 'not_found', sprintf('Unknown tooling API path "%s".', $apiPath));
   }

   private function legacyGraphResponse(): Response
   {
      $graph = $this->dependencyGraphBuilder->buildModuleGraph();
      return $this->json(200, $graph->toArray());
   }

   private function legacyValidateResponse(): Response
   {
      $graph = $this->dependencyGraphBuilder->buildModuleGraph();
      $report = $this->architectureValidator->validate($graph);
      return $this->json(200, $report->toArray());
   }

   private function legacyPreviewResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $request->getQueryParams();
      $args = $this->generationArgs($input);
      if ($args instanceof Response) {
         return $args;
      }

      try {
         $rows = $this->generatorEngine->preview(
            $args['generator'],
            new GenerationRequest(
               basePath: $this->projectRoot,
               name: $args['name'],
               module: $args['module'],
               namespaceRoot: $this->namespaceRoot,
               overwrite: $args['overwrite'],
            )
         );
      } catch (ToolingException $exception) {
         return $this->json(422, ['error' => $exception->getMessage()]);
      }

      return $this->json(200, [
         'generator' => $args['generator'],
         'name' => $args['name'],
         'module' => $args['module'],
         'files' => array_map(static fn($row): array => $row->toArray(), $rows),
      ]);
   }

   private function legacyApplyResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $request->getQueryParams();
      $args = $this->generationArgs($input);
      if ($args instanceof Response) {
         return $args;
      }

      try {
         $result = $this->generatorEngine->apply(
            $args['generator'],
            new GenerationRequest(
               basePath: $this->projectRoot,
               name: $args['name'],
               module: $args['module'],
               namespaceRoot: $this->namespaceRoot,
               overwrite: $args['overwrite'],
            )
         );
         $this->auditApply($ctx, $args, $result->written(), $result->skipped(), null, $request, 'legacy');
      } catch (ToolingException $exception) {
         $this->auditApply($ctx, $args, [], [], $exception->getMessage(), $request, 'legacy');
         return $this->json(422, ['error' => $exception->getMessage()]);
      }

      return $this->json(200, [
         'generator' => $args['generator'],
         'name' => $args['name'],
         'module' => $args['module'],
         'written' => $result->written(),
         'skipped' => $result->skipped(),
      ]);
   }

   private function apiPreviewResponse(RequestContext $ctx, Request $request): Response
   {
      $args = $this->generationArgs($this->requestInput($request));
      if ($args instanceof Response) {
         return $this->apiError($ctx, 400, 'invalid_input', 'generator and name are required.');
      }

      try {
         $rows = $this->generatorEngine->preview(
            $args['generator'],
            new GenerationRequest(
               basePath: $this->projectRoot,
               name: $args['name'],
               module: $args['module'],
               namespaceRoot: $this->namespaceRoot,
               overwrite: $args['overwrite'],
            )
         );
      } catch (ToolingException $exception) {
         return $this->apiError($ctx, 422, 'tooling_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'generator' => $args['generator'],
         'name' => $args['name'],
         'module' => $args['module'],
         'files' => array_map(static fn($row): array => $row->toArray(), $rows),
      ]);
   }

   private function apiApplyResponse(RequestContext $ctx, Request $request): Response
   {
      $args = $this->generationArgs($this->requestInput($request));
      if ($args instanceof Response) {
         return $this->apiError($ctx, 400, 'invalid_input', 'generator and name are required.');
      }

      try {
         $result = $this->generatorEngine->apply(
            $args['generator'],
            new GenerationRequest(
               basePath: $this->projectRoot,
               name: $args['name'],
               module: $args['module'],
               namespaceRoot: $this->namespaceRoot,
               overwrite: $args['overwrite'],
            )
         );
         $this->auditApply($ctx, $args, $result->written(), $result->skipped(), null, $request, 'api');
      } catch (ToolingException $exception) {
         $this->auditApply($ctx, $args, [], [], $exception->getMessage(), $request, 'api');
         return $this->apiError($ctx, 422, 'tooling_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'generator' => $args['generator'],
         'name' => $args['name'],
         'module' => $args['module'],
         'written' => $result->written(),
         'skipped' => $result->skipped(),
      ]);
   }

   /**
    * @param array<string, mixed> $input
    * @return array{generator:string,name:string,module:string,overwrite:bool}|Response
    */
   private function generationArgs(array $input): array|Response
   {
      $generator = $this->stringValue($input, 'generator');
      $name = $this->stringValue($input, 'name');
      if ($generator === null || $name === null) {
         return $this->json(400, ['error' => 'generator and name query params are required']);
      }

      return [
         'generator' => $generator,
         'name' => $name,
         'module' => $this->stringValue($input, 'module') ?? 'Generated',
         'overwrite' => $this->boolValue($input, 'overwrite'),
      ];
   }

   /**
    * @return array<string, mixed>
    */
   private function requestInput(Request $request): array
   {
      $input = $request->getQueryParams();
      $parsedBody = $request->getParsedBody();
      if (!is_array($parsedBody)) {
         $parsedBody = $this->parseJsonBody($request);
      }
      if (is_array($parsedBody)) {
         foreach ($parsedBody as $key => $value) {
            if (!is_string($key)) {
               continue;
            }
            $input[$key] = $value;
         }
      }

      return $input;
   }

   /**
    * @return array<string, mixed>|null
    */
   private function parseJsonBody(Request $request): ?array
   {
      $contentType = strtolower((string) $request->getHeader('content-type', ''));
      if (!str_contains($contentType, 'application/json')) {
         return null;
      }

      $raw = $request->getBody();
      if ($raw === '') {
         return null;
      }

      $decoded = json_decode($raw, true);
      return is_array($decoded) ? $decoded : null;
   }

   /**
    * @param array<string, mixed> $input
    */
   private function stringValue(array $input, string $name): ?string
   {
      $value = $input[$name] ?? null;
      if (!is_string($value)) {
         return null;
      }

      $trimmed = trim($value);
      return $trimmed === '' ? null : $trimmed;
   }

   /**
    * @param array<string, mixed> $input
    */
   private function boolValue(array $input, string $name): bool
   {
      $value = $input[$name] ?? null;
      if (is_bool($value)) {
         return $value;
      }
      if (is_numeric($value)) {
         return (int) $value === 1;
      }
      if (!is_string($value)) {
         return false;
      }

      $normalized = strtolower(trim($value));
      return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
   }

   /**
    * @return array<string, mixed>
    */
   private function summaryPayload(): array
   {
      $graph = $this->dependencyGraphBuilder->buildModuleGraph();
      $report = $this->architectureValidator->validate($graph);

      return [
         'graph' => $graph->toArray(),
         'architecture' => $report->toArray(),
         'generators' => $this->generatorEngine->list(),
      ];
   }

   private function methodNotAllowed(RequestContext $ctx, array $allowed): Response
   {
      $response = $this->apiError(
         $ctx,
         405,
         'method_not_allowed',
         sprintf('Method not allowed. Allowed methods: %s', implode(', ', $allowed))
      );

      return $response->withHeader('allow', implode(', ', $allowed));
   }

   /**
    * @param array<string, mixed> $data
    */
   private function apiOk(RequestContext $ctx, array $data, int $status = 200): Response
   {
      return $this->json($status, [
         'status' => 'ok',
         'data' => $data,
         'errors' => [],
         'meta' => $this->apiMeta($ctx),
      ]);
   }

   /**
    * @param array<string, mixed> $details
    */
   private function apiError(
      RequestContext $ctx,
      int $status,
      string $code,
      string $message,
      array $details = [],
   ): Response {
      return $this->json($status, [
         'status' => 'error',
         'data' => null,
         'errors' => [[
            'code' => $code,
            'message' => $message,
            'details' => $details,
         ]],
         'meta' => $this->apiMeta($ctx),
      ]);
   }

   /**
    * @return array<string, mixed>
    */
   private function apiMeta(RequestContext $ctx): array
   {
      return [
         'api_version' => self::API_VERSION,
         'request_id' => $ctx->getRequestId(),
         'generated_at' => gmdate('c'),
      ];
   }

   /**
    * @param array<string, mixed> $payload
    */
   private function json(int $status, array $payload): Response
   {
      return new Response(
         $status,
         ['content-type' => 'application/json; charset=utf-8'],
         (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
      );
   }

   /**
    * Handle sub path.
    *
    * @param string $requestPath
    * @return string
    */
   private function subPath(string $requestPath): string
   {
      $prefix = rtrim($this->routePrefix, '/');
      $path = rtrim($requestPath, '/');

      if ($path === $prefix || $path === '') {
         return '/';
      }

      if (!str_starts_with($path, $prefix . '/')) {
         return '/';
      }

      $subPath = substr($path, strlen($prefix));
      return $subPath === '' ? '/' : $subPath;
   }

   /**
    * Handle escape.
    *
    * @param string $value
    * @return string
    */
   private function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }

   private function isToolingEnabled(): bool
   {
      $explicit = $this->envFlag(self::ENABLED_KEY);
      if ($explicit !== null) {
         return $explicit;
      }

      $allowed = $this->allowedEnvironments();
      if ($allowed !== []) {
         return in_array($this->environment(), $allowed, true);
      }

      return $this->environment() === 'development';
   }

   private function environment(): string
   {
      $raw = $_ENV[self::ENV_KEY] ?? $_SERVER[self::ENV_KEY] ?? getenv(self::ENV_KEY);
      if (!is_string($raw)) {
         return '';
      }

      return strtolower(trim($raw));
   }

   /**
    * @param array{generator:string,name:string,module:string,overwrite:bool} $args
    * @param array<int, string> $written
    * @param array<int, string> $skipped
    */
   private function auditApply(
      RequestContext $ctx,
      array $args,
      array $written,
      array $skipped,
      ?string $error,
      Request $request,
      string $channel,
   ): void {
      if (!$this->isAuditEnabled()) {
         return;
      }

      $path = $this->auditPath();
      if ($path === '') {
         return;
      }

      $dir = dirname($path);
      if (!is_dir($dir)) {
         @mkdir($dir, 0775, true);
      }

      $record = [
         'timestamp' => gmdate('c'),
         'env' => $this->environment(),
         'channel' => $channel,
         'method' => $request->getMethod(),
         'path' => $request->getPath(),
         'request_id' => $ctx->getRequestId(),
         'remote_addr' => $this->serverParam($ctx, 'REMOTE_ADDR'),
         'user_agent' => $request->getHeader('user-agent', ''),
         'generator' => $args['generator'],
         'name' => $args['name'],
         'module' => $args['module'],
         'overwrite' => $args['overwrite'],
         'written' => $written,
         'skipped' => $skipped,
         'status' => $error === null ? 'ok' : 'error',
         'error' => $error,
      ];

      $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if (!is_string($line)) {
         return;
      }

      @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
   }

   private function serverParam(RequestContext $ctx, string $key): string
   {
      $params = $ctx->getServerParams();
      $value = $params[$key] ?? null;
      return is_string($value) ? $value : '';
   }

   private function isAuditEnabled(): bool
   {
      $flag = $this->envFlag(self::AUDIT_ENABLED_KEY);
      return $flag ?? true;
   }

   private function auditPath(): string
   {
      $raw = $this->envString(self::AUDIT_PATH_KEY);
      if ($raw === '') {
         return $this->projectRoot . '/var/log/tooling-audit.log';
      }

      if (str_starts_with($raw, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $raw) === 1) {
         return $raw;
      }

      return rtrim($this->projectRoot, '/\\') . '/' . ltrim($raw, '/\\');
   }

   /**
    * @return array<int, string>
    */
   private function allowedEnvironments(): array
   {
      $raw = $this->envString(self::ALLOWED_ENVS_KEY);
      if ($raw === '') {
         return [];
      }

      $parts = array_map(static fn (string $item): string => strtolower(trim($item)), explode(',', $raw));
      return array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
   }

   private function envString(string $key): string
   {
      $raw = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
      return is_string($raw) ? trim($raw) : '';
   }

   private function envFlag(string $key): ?bool
   {
      $raw = $this->envString($key);
      if ($raw === '') {
         return null;
      }

      $normalized = strtolower($raw);
      if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
         return true;
      }
      if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
         return false;
      }

      return null;
   }
}
