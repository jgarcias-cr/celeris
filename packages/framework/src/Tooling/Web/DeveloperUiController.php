<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Web;

use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\DatabaseBootstrap;
use Celeris\Framework\Database\DatabaseConfig;
use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Tooling\Architecture\ArchitectureDecisionValidator;
use Celeris\Framework\Tooling\Diff\UnifiedDiffBuilder;
use Celeris\Framework\Tooling\Generator\GenerationRequest;
use Celeris\Framework\Tooling\Generator\GeneratorEngine;
use Celeris\Framework\Tooling\Graph\DependencyGraphBuilder;
use Celeris\Framework\Tooling\ToolingException;
use Throwable;

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
   private ?ConfigRepository $config = null;

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
      $apiBase = rtrim($this->routePrefix, '/') . '/api/' . self::API_VERSION;
      $apiBaseJson = (string) json_encode($apiBase, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
      $requestId = $this->escape($ctx->getRequestId());

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
#artifactChecks {
  margin-top: 0.7rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.35rem 0.7rem;
}
#artifactChecks label {
  display: flex;
  align-items: flex-start;
  gap: 0.45rem;
  color: var(--ink);
  font-size: 0.82rem;
}
#artifactChecks input[type="checkbox"] {
  width: auto;
  margin-top: 0.15rem;
}
.hint {
  display: block;
  color: var(--muted);
  font-size: 0.73rem;
  line-height: 1.3;
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
    <p class="muted">DB-first scaffolding dashboard. Request id: {$requestId}</p>
    <div style="margin-top:0.5rem;">
      <span class="badge ok">DB-FIRST</span>
    </div>
  </section>
  <section class="card">
    <h2>DB Scaffolding (MVP)</h2>
    <p class="muted" style="margin-top:0.25rem;">Generate code from database tables and preview per file.</p>
    <div class="row">
      <div>
        <label for="dbConnection">Connection</label>
        <select id="dbConnection"></select>
      </div>
      <div>
        <label for="dbTable">Table</label>
        <select id="dbTable"></select>
      </div>
      <div>
        <label>&nbsp;</label>
        <button id="dbReloadBtn">Reload Tables</button>
      </div>
    </div>
    <div id="artifactChecks">
      <label><input type="checkbox" value="model" checked><span>Model<span class="hint">Entity constants and table schema metadata.</span></span></label>
      <label><input type="checkbox" value="repository" checked><span>Repository<span class="hint">Data-access abstraction for table operations.</span></span></label>
      <label><input type="checkbox" value="service" checked><span>Service<span class="hint">Business logic with required/default validation.</span></span></label>
      <label><input type="checkbox" value="controller" checked><span>Controller<span class="hint">HTTP endpoints for the generated resource.</span></span></label>
      <label><input type="checkbox" value="dto.request" checked><span>DTO Request<span class="hint">Typed input contract for create/update payloads.</span></span></label>
      <label><input type="checkbox" value="dto.response" checked><span>DTO Response<span class="hint">Typed output contract returned to clients.</span></span></label>
      <label><input type="checkbox" value="factory"><span>Factory<span class="hint">Realistic test-data builder for this table.</span></span></label>
      <label><input type="checkbox" value="seed"><span>Seeder<span class="hint">Seed records for local/dev environments.</span></span></label>
      <label><input type="checkbox" value="test.unit.repository"><span>Repo Test<span class="hint">Unit scaffold for repository behavior.</span></span></label>
      <label><input type="checkbox" value="test.unit.service"><span>Service Test<span class="hint">Unit scaffold for service rules/defaults.</span></span></label>
      <label><input type="checkbox" value="test.integration.controller"><span>Controller Test<span class="hint">Integration scaffold for HTTP contract checks.</span></span></label>
    </div>
    <div class="actions">
      <button id="dbPreviewBtn" class="primary">DB Preview</button>
      <button id="dbApplyBtn">DB Apply</button>
    </div>
    <div id="previewStatus" class="status" style="margin-top:0.7rem;">Idle</div>
    <ul id="previewFiles" class="list"></ul>
    <pre id="diffPanel">(no diff selected)</pre>
  </section>
  <section class="card">
    <h2>Compatibility Guard</h2>
    <p class="muted" style="margin-top:0.25rem;">Detect schema/API breaking changes against a saved baseline.</p>
    <div class="actions">
      <button id="compatCheckBtn">Check Breaking Changes</button>
      <button id="compatSaveBtn">Save Baseline</button>
    </div>
    <pre id="compatPanel">(no compatibility run yet)</pre>
  </section>
</main>
<script>
const API_BASE = {$apiBaseJson};

const elements = {
  dbConnection: document.getElementById('dbConnection'),
  dbTable: document.getElementById('dbTable'),
  dbReloadBtn: document.getElementById('dbReloadBtn'),
  dbPreviewBtn: document.getElementById('dbPreviewBtn'),
  dbApplyBtn: document.getElementById('dbApplyBtn'),
  compatCheckBtn: document.getElementById('compatCheckBtn'),
  compatSaveBtn: document.getElementById('compatSaveBtn'),
  compatPanel: document.getElementById('compatPanel'),
  artifactChecks: document.getElementById('artifactChecks'),
  previewStatus: document.getElementById('previewStatus'),
  previewFiles: document.getElementById('previewFiles'),
  diffPanel: document.getElementById('diffPanel'),
};

function setLoading(active) {
  if (elements.dbReloadBtn) elements.dbReloadBtn.disabled = active;
  if (elements.dbPreviewBtn) elements.dbPreviewBtn.disabled = active;
  if (elements.dbApplyBtn) elements.dbApplyBtn.disabled = active;
  if (elements.compatCheckBtn) elements.compatCheckBtn.disabled = active;
  if (elements.compatSaveBtn) elements.compatSaveBtn.disabled = active;
}

function readScaffoldInput() {
  const selected = [];
  if (elements.artifactChecks) {
    elements.artifactChecks.querySelectorAll('input[type="checkbox"]').forEach((el) => {
      if (el.checked) selected.push(el.value);
    });
  }

  return {
    connection: elements.dbConnection ? elements.dbConnection.value : '',
    table: elements.dbTable ? elements.dbTable.value : '',
    artifacts: selected,
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

function renderPreview(files) {
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

function renderConnectionOptions(items) {
  if (!elements.dbConnection) return;
  elements.dbConnection.innerHTML = '';
  const placeholder = document.createElement('option');
  placeholder.value = '';
  placeholder.textContent = 'Select a connection';
  placeholder.selected = true;
  elements.dbConnection.appendChild(placeholder);
  (Array.isArray(items) ? items : []).forEach((row) => {
    const option = document.createElement('option');
    option.value = row.name;
    const suffix = row.default ? ' (default)' : '';
    option.textContent = row.name + ' [' + (row.driver || 'unknown') + ']' + suffix;
    elements.dbConnection.appendChild(option);
  });
}

function renderTableOptions(items) {
  if (!elements.dbTable) return;
  elements.dbTable.innerHTML = '';
  (Array.isArray(items) ? items : []).forEach((name) => {
    const option = document.createElement('option');
    option.value = name;
    option.textContent = name;
    elements.dbTable.appendChild(option);
  });
}

function loadTables() {
  const connection = elements.dbConnection && elements.dbConnection.value ? elements.dbConnection.value : '';
  if (!connection) {
    renderTableOptions([]);
    return Promise.resolve();
  }
  const query = connection ? ('?connection=' + encodeURIComponent(connection)) : '';
  return request('/schema/tables' + query).then((data) => {
    renderTableOptions(data.items || []);
  });
}

function initScaffoldPanel() {
  if (!elements.dbConnection) return;

  request('/schema/connections')
    .then((data) => {
      renderConnectionOptions(data.items || []);
    })
    .catch((error) => {
      setPreviewStatus('Schema init failed: ' + error.message, false);
    });
}

function scaffoldPreview() {
  const payload = readScaffoldInput();
  if (!payload.connection) {
    setPreviewStatus('Select a connection first.', false);
    return;
  }
  if (!payload.table) {
    setPreviewStatus('Select a table first.', false);
    return;
  }
  if (!Array.isArray(payload.artifacts) || payload.artifacts.length === 0) {
    setPreviewStatus('Select at least one artifact.', false);
    return;
  }

  setLoading(true);
  request('/scaffold/preview', { method: 'POST', body: payload })
    .then((data) => {
      renderPreview(data.files || []);
      setPreviewStatus('DB preview generated for table ' + payload.table + '.', true);
    })
    .catch((error) => {
      setPreviewStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function scaffoldApply() {
  const payload = readScaffoldInput();
  if (!payload.connection) {
    setPreviewStatus('Select a connection first.', false);
    return;
  }
  if (!payload.table) {
    setPreviewStatus('Select a table first.', false);
    return;
  }
  if (!Array.isArray(payload.artifacts) || payload.artifacts.length === 0) {
    setPreviewStatus('Select at least one artifact.', false);
    return;
  }
  if (!window.confirm('Apply DB scaffold for table ' + payload.table + '?')) {
    return;
  }

  setLoading(true);
  request('/scaffold/apply', { method: 'POST', body: payload })
    .then((data) => {
      const written = Array.isArray(data.written) ? data.written.length : 0;
      const skipped = Array.isArray(data.skipped) ? data.skipped.length : 0;
      setPreviewStatus('DB apply complete. written=' + written + ' skipped=' + skipped, true);
    })
    .catch((error) => {
      setPreviewStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function compatibilityCheck() {
  const payload = readScaffoldInput();
  if (!payload.connection) {
    setPreviewStatus('Select a connection first.', false);
    return;
  }
  if (!payload.table) {
    setPreviewStatus('Select a table first.', false);
    return;
  }

  setLoading(true);
  const query = '?connection=' + encodeURIComponent(payload.connection) + '&table=' + encodeURIComponent(payload.table);
  request('/compat/breaking-changes' + query)
    .then((data) => {
      if (elements.compatPanel) {
        elements.compatPanel.textContent = JSON.stringify(data, null, 2);
      }
      const count = Array.isArray(data.breaking_changes) ? data.breaking_changes.length : 0;
      setPreviewStatus('Compatibility check complete. breaking=' + count, count === 0);
    })
    .catch((error) => {
      setPreviewStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function compatibilitySaveBaseline() {
  const payload = readScaffoldInput();
  if (!payload.connection) {
    setPreviewStatus('Select a connection first.', false);
    return;
  }
  if (!payload.table) {
    setPreviewStatus('Select a table first.', false);
    return;
  }

  setLoading(true);
  request('/compat/baseline/save', {
    method: 'POST',
    body: { connection: payload.connection, table: payload.table },
  })
    .then((data) => {
      if (elements.compatPanel) {
        elements.compatPanel.textContent = JSON.stringify(data, null, 2);
      }
      setPreviewStatus('Baseline saved.', true);
    })
    .catch((error) => {
      setPreviewStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}
if (elements.dbConnection) elements.dbConnection.addEventListener('change', loadTables);
if (elements.dbReloadBtn) elements.dbReloadBtn.addEventListener('click', loadTables);
if (elements.dbPreviewBtn) elements.dbPreviewBtn.addEventListener('click', scaffoldPreview);
if (elements.dbApplyBtn) elements.dbApplyBtn.addEventListener('click', scaffoldApply);
if (elements.compatCheckBtn) elements.compatCheckBtn.addEventListener('click', compatibilityCheck);
if (elements.compatSaveBtn) elements.compatSaveBtn.addEventListener('click', compatibilitySaveBaseline);

initScaffoldPanel();
</script>
</body>
</html>
HTML;

      return new Response(
         200,
         [
            'content-type' => 'text/html; charset=utf-8',
            'content-security-policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'",
         ],
         $html
      );
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

      if ($apiPath === '/schema/connections') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         return $this->apiSchemaConnectionsResponse($ctx);
      }

      if ($apiPath === '/schema/tables') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         return $this->apiSchemaTablesResponse($ctx, $request);
      }

      if (str_starts_with($apiPath, '/schema/tables/')) {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         $tableName = urldecode(substr($apiPath, strlen('/schema/tables/')));
         return $this->apiSchemaTableDescribeResponse($ctx, $request, $tableName);
      }

      if ($apiPath === '/scaffold/preview') {
         if ($request->getMethod() !== 'POST') {
            return $this->methodNotAllowed($ctx, ['POST']);
         }
         return $this->apiScaffoldPreviewResponse($ctx, $request);
      }

      if ($apiPath === '/scaffold/apply') {
         if ($request->getMethod() !== 'POST') {
            return $this->methodNotAllowed($ctx, ['POST']);
         }
         return $this->apiScaffoldApplyResponse($ctx, $request);
      }

      if ($apiPath === '/compat/breaking-changes') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         return $this->apiCompatibilityBreakingChangesResponse($ctx, $request);
      }

      if ($apiPath === '/compat/baseline/save') {
         if ($request->getMethod() !== 'POST') {
            return $this->methodNotAllowed($ctx, ['POST']);
         }
         return $this->apiCompatibilitySaveBaselineResponse($ctx, $request);
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

   private function apiSchemaConnectionsResponse(RequestContext $ctx): Response
   {
      try {
         $connections = $this->databaseConfigs();
         $pool = DatabaseBootstrap::poolFromConfig($this->config());

         $items = [];
         foreach ($connections as $name => $connection) {
            try {
               $pool->get($name);
            } catch (Throwable) {
               continue;
            }

            $items[] = [
               'name' => $name,
               'driver' => $connection->driver()->value,
               'database' => $connection->database(),
               'host' => $connection->host(),
               'port' => $connection->port(),
               'path' => $connection->path(),
               'dsn' => $connection->dsn(),
            ];
         }

         $default = $this->defaultConnectionName();
         $names = array_map(static fn (array $item): string => (string) ($item['name'] ?? ''), $items);
         if (!in_array($default, $names, true)) {
            $default = $names[0] ?? '';
         }
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 500, 'schema_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'default' => $default,
         'items' => $items,
      ]);
   }

   private function apiSchemaTablesResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $connectionName = $this->stringValue($input, 'connection') ?? $this->defaultConnectionName();

      try {
         [$connection, $driver] = $this->databaseConnectionAndDriver($connectionName);
         $tables = $this->listTables($connection, $driver);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'schema_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         'items' => $tables,
      ]);
   }

   private function apiSchemaTableDescribeResponse(RequestContext $ctx, Request $request, string $table): Response
   {
      $input = $this->requestInput($request);
      $connectionName = $this->stringValue($input, 'connection') ?? $this->defaultConnectionName();
      $tableName = trim($table);
      if ($tableName === '') {
         return $this->apiError($ctx, 400, 'invalid_input', 'table path segment is required.');
      }

      try {
         [$connection, $driver] = $this->databaseConnectionAndDriver($connectionName);
         $schema = $this->describeTable($connection, $driver, $tableName);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'schema_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         'table' => $tableName,
         'schema' => $schema,
      ]);
   }

   private function apiScaffoldPreviewResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $connectionName = $this->stringValue($input, 'connection');
      if ($connectionName === null) {
         return $this->apiError($ctx, 400, 'invalid_input', 'connection is required.');
      }
      $tableName = $this->stringValue($input, 'table');
      if ($tableName === null) {
         return $this->apiError($ctx, 400, 'invalid_input', 'table is required.');
      }

      $artifacts = $this->artifactList($input);
      if ($artifacts === []) {
         return $this->apiError($ctx, 400, 'invalid_input', 'artifacts must include at least one artifact.');
      }

      try {
         [$connection, $driver] = $this->databaseConnectionAndDriver($connectionName);
         $schema = $this->describeTable($connection, $driver, $tableName);
         $files = $this->buildScaffoldPreview($tableName, $schema, $artifacts);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'tooling_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         'table' => $tableName,
         'artifacts' => $artifacts,
         'schema' => $schema,
         'files' => $files,
      ]);
   }

   private function apiScaffoldApplyResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $connectionName = $this->stringValue($input, 'connection');
      if ($connectionName === null) {
         return $this->apiError($ctx, 400, 'invalid_input', 'connection is required.');
      }
      $tableName = $this->stringValue($input, 'table');
      if ($tableName === null) {
         return $this->apiError($ctx, 400, 'invalid_input', 'table is required.');
      }

      $artifacts = $this->artifactList($input);
      if ($artifacts === []) {
         return $this->apiError($ctx, 400, 'invalid_input', 'artifacts must include at least one artifact.');
      }

      try {
         [$connection, $driver] = $this->databaseConnectionAndDriver($connectionName);
         $schema = $this->describeTable($connection, $driver, $tableName);
         $files = $this->buildScaffoldPreview($tableName, $schema, $artifacts);
         [$written, $skipped] = $this->writeScaffoldFiles($files);
         $this->auditScaffoldApply($ctx, $request, $connectionName, $tableName, $artifacts, $written, $skipped, null);
      } catch (Throwable $exception) {
         $this->auditScaffoldApply($ctx, $request, $connectionName, $tableName ?? '', $artifacts ?? [], [], [], $exception->getMessage());
         return $this->apiError($ctx, 422, 'tooling_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         'table' => $tableName,
         'artifacts' => $artifacts,
         'written' => $written,
         'skipped' => $skipped,
      ]);
   }

   private function apiCompatibilityBreakingChangesResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $connectionName = $this->stringValue($input, 'connection');
      $tableName = $this->stringValue($input, 'table');
      if ($connectionName === null || $tableName === null) {
         return $this->apiError($ctx, 400, 'invalid_input', 'connection and table are required.');
      }

      try {
         [$connection, $driver] = $this->databaseConnectionAndDriver($connectionName);
         $currentSchema = $this->describeTable($connection, $driver, $tableName);
         $baselinePath = $this->compatibilityBaselinePath($connectionName, $tableName);
         if (!is_file($baselinePath)) {
            return $this->apiOk($ctx, [
               'connection' => $connectionName,
               'table' => $tableName,
               'has_baseline' => false,
               'breaking_changes' => [],
               'message' => 'No baseline found. Save a baseline first.',
            ]);
         }

         $raw = @file_get_contents($baselinePath);
         if (!is_string($raw) || $raw === '') {
            throw new ToolingException('Baseline file is unreadable or empty.');
         }
         $decoded = json_decode($raw, true);
         if (!is_array($decoded)) {
            throw new ToolingException('Baseline file contains invalid JSON.');
         }

         $baselineSchema = is_array($decoded['schema'] ?? null) ? $decoded['schema'] : [];
         $baselineContract = is_array($decoded['api_contract'] ?? null) ? $decoded['api_contract'] : [];
         $currentContract = $this->apiContractFromSchema($currentSchema);
         $breaking = [
            ...$this->schemaBreakingChanges($baselineSchema, $currentSchema),
            ...$this->apiBreakingChanges($baselineContract, $currentContract),
         ];

         return $this->apiOk($ctx, [
            'connection' => $connectionName,
            'table' => $tableName,
            'has_baseline' => true,
            'baseline_path' => $this->relativeToProject($baselinePath),
            'breaking_changes' => $breaking,
         ]);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'compatibility_error', $exception->getMessage());
      }
   }

   private function apiCompatibilitySaveBaselineResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $connectionName = $this->stringValue($input, 'connection');
      $tableName = $this->stringValue($input, 'table');
      if ($connectionName === null || $tableName === null) {
         return $this->apiError($ctx, 400, 'invalid_input', 'connection and table are required.');
      }

      try {
         [$connection, $driver] = $this->databaseConnectionAndDriver($connectionName);
         $schema = $this->describeTable($connection, $driver, $tableName);
         $contract = $this->apiContractFromSchema($schema);
         $path = $this->compatibilityBaselinePath($connectionName, $tableName);
         $dir = dirname($path);
         if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new ToolingException(sprintf('Unable to create baseline directory "%s".', $dir));
         }

         $payload = [
            'connection' => $connectionName,
            'table' => $tableName,
            'saved_at' => gmdate('c'),
            'schema' => $schema,
            'api_contract' => $contract,
         ];
         $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
         if (!is_string($json) || @file_put_contents($path, $json . "\n") === false) {
            throw new ToolingException(sprintf('Unable to write baseline "%s".', $path));
         }

         return $this->apiOk($ctx, [
            'connection' => $connectionName,
            'table' => $tableName,
            'baseline_path' => $this->relativeToProject($path),
            'saved_at' => $payload['saved_at'],
         ]);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'compatibility_error', $exception->getMessage());
      }
   }

   /**
    * @param array<string, mixed> $input
    * @return array<int, string>
    */
   private function artifactList(array $input): array
   {
      $value = $input['artifacts'] ?? null;
      $raw = [];
      if (is_string($value)) {
         $raw = explode(',', $value);
      } elseif (is_array($value)) {
         foreach ($value as $item) {
            if (is_string($item)) {
               $raw[] = $item;
            }
         }
      }

      if ($raw === []) {
         $raw = [
            'model',
            'repository',
            'service',
            'controller',
            'dto.request',
            'dto.response',
            'factory',
            'seed',
            'test.unit.repository',
            'test.unit.service',
            'test.integration.controller',
         ];
      }

      $normalized = [];
      foreach ($raw as $item) {
         $clean = strtolower(trim($item));
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      $normalized = array_values(array_unique($normalized));
      $allowed = [
         'model',
         'repository',
         'service',
         'controller',
         'dto.request',
         'dto.response',
         'factory',
         'seed',
         'test.unit.repository',
         'test.unit.service',
         'test.integration.controller',
      ];
      return array_values(array_intersect($normalized, $allowed));
   }

   private function config(): ConfigRepository
   {
      if ($this->config instanceof ConfigRepository) {
         return $this->config;
      }

      $base = rtrim($this->projectRoot, '/\\');
      $loader = new ConfigLoader(
         $base . '/config',
         new EnvironmentLoader(
            is_file($base . '/.env') ? $base . '/.env' : null,
            is_dir($base . '/secrets') ? $base . '/secrets' : null,
            false,
            true,
         ),
      );

      $snapshot = $loader->snapshot();
      $this->config = new ConfigRepository(
         $snapshot->getItems(),
         $snapshot->getEnvironment(),
         $snapshot->getSecrets(),
         $snapshot->getFingerprint(),
         $snapshot->getLoadedAt(),
      );

      return $this->config;
   }

   /**
    * @return array<string, DatabaseConfig>
    */
   private function databaseConfigs(): array
   {
      $connectionsSpec = $this->config()->get('database.connections', []);
      $configs = [];
      if (!is_array($connectionsSpec)) {
         return $configs;
      }

      foreach ($connectionsSpec as $name => $spec) {
         if (!is_array($spec)) {
            continue;
         }
         $configs[(string) $name] = DatabaseConfig::fromArray((string) $name, $spec);
      }

      ksort($configs);
      return $configs;
   }

   private function defaultConnectionName(): string
   {
      $default = $this->config()->get('database.default', 'default');
      if (!is_string($default) || trim($default) === '') {
         return 'default';
      }

      return trim($default);
   }

   /**
    * @return array{0: ConnectionInterface, 1: DatabaseDriver}
    */
   private function databaseConnectionAndDriver(string $connectionName): array
   {
      $configs = $this->databaseConfigs();
      $resolvedName = trim($connectionName);
      if ($resolvedName === '') {
         $resolvedName = $this->defaultConnectionName();
      }

      $config = $configs[$resolvedName] ?? null;
      if (!$config instanceof DatabaseConfig) {
         throw new ToolingException(sprintf('Unknown database connection "%s".', $resolvedName));
      }

      $pool = DatabaseBootstrap::poolFromConfig($this->config());
      return [$pool->get($resolvedName), $config->driver()];
   }

   /**
    * @return array<int, string>
    */
   private function listTables(ConnectionInterface $connection, DatabaseDriver $driver): array
   {
      $items = match ($driver) {
         DatabaseDriver::SQLite => array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $connection->fetchAll(
               "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
            )
         ),
         DatabaseDriver::MySQL, DatabaseDriver::MariaDB => array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $connection->fetchAll(
               "SELECT table_name AS name
                FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
                ORDER BY table_name"
            )
         ),
         DatabaseDriver::PostgreSQL => array_map(
            static function (array $row): string {
               $schema = (string) ($row['schema'] ?? '');
               $name = (string) ($row['name'] ?? '');
               if ($schema !== '' && $schema !== 'public') {
                  return $schema . '.' . $name;
               }

               return $name;
            },
            $connection->fetchAll(
               "SELECT table_schema AS schema, table_name AS name
                FROM information_schema.tables
                WHERE table_type = 'BASE TABLE'
                  AND table_schema NOT IN ('pg_catalog', 'information_schema')
                ORDER BY table_schema, table_name"
            )
         ),
         default => throw new ToolingException(
            sprintf('Schema introspection is not implemented yet for driver "%s".', $driver->value)
         ),
      };

      return array_values(array_filter($items, static fn (string $name): bool => trim($name) !== ''));
   }

   /**
    * @return array{table:string,schema:string,columns:array<int,array<string,mixed>>,primary_key:array<int,string>,relationships:array<int,array<string,mixed>>}
    */
   private function describeTable(ConnectionInterface $connection, DatabaseDriver $driver, string $tableName): array
   {
      return match ($driver) {
         DatabaseDriver::SQLite => $this->describeSqliteTable($connection, $tableName),
         DatabaseDriver::MySQL, DatabaseDriver::MariaDB => $this->describeMysqlTable($connection, $tableName),
         DatabaseDriver::PostgreSQL => $this->describePostgresTable($connection, $tableName),
         default => throw new ToolingException(
            sprintf('Table describe is not implemented yet for driver "%s".', $driver->value)
         ),
      };
   }

   /**
    * @return array{schema:string,table:string}
    */
   private function splitSchemaAndTable(string $tableName, string $defaultSchema = 'public'): array
   {
      $clean = trim($tableName);
      if ($clean === '') {
         throw new ToolingException('Table name cannot be empty.');
      }

      if (!preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)?$/', $clean)) {
         throw new ToolingException('Table name contains unsupported characters.');
      }

      if (!str_contains($clean, '.')) {
         return ['schema' => $defaultSchema, 'table' => $clean];
      }

      [$schema, $table] = explode('.', $clean, 2);
      return ['schema' => $schema, 'table' => $table];
   }

   /**
    * @return array{table:string,schema:string,columns:array<int,array<string,mixed>>,primary_key:array<int,string>,relationships:array<int,array<string,mixed>>}
    */
   private function describeSqliteTable(ConnectionInterface $connection, string $tableName): array
   {
      $parsed = $this->splitSchemaAndTable($tableName, 'main');
      $table = $parsed['table'];
      $quoted = "'" . str_replace("'", "''", $table) . "'";

      $rows = $connection->fetchAll('PRAGMA table_info(' . $quoted . ')');
      if ($rows === []) {
         throw new ToolingException(sprintf('Table "%s" was not found.', $tableName));
      }

      $columns = [];
      $primary = [];
      foreach ($rows as $row) {
         $name = (string) ($row['name'] ?? '');
         $pkOrder = isset($row['pk']) ? (int) $row['pk'] : 0;
         if ($pkOrder > 0) {
            $primary[$pkOrder] = $name;
         }

         $columns[] = [
            'name' => $name,
            'type' => (string) ($row['type'] ?? ''),
            'nullable' => ((int) ($row['notnull'] ?? 0)) === 0,
            'default' => $row['dflt_value'] ?? null,
            'primary' => $pkOrder > 0,
         ];
      }
      ksort($primary);

      $fkRows = $connection->fetchAll('PRAGMA foreign_key_list(' . $quoted . ')');
      $relationships = array_map(static fn (array $row): array => [
         'column' => (string) ($row['from'] ?? ''),
         'referenced_table' => (string) ($row['table'] ?? ''),
         'referenced_column' => (string) ($row['to'] ?? ''),
      ], $fkRows);

      return [
         'table' => $table,
         'schema' => $parsed['schema'],
         'columns' => $columns,
         'primary_key' => array_values($primary),
         'relationships' => $relationships,
      ];
   }

   /**
    * @return array{table:string,schema:string,columns:array<int,array<string,mixed>>,primary_key:array<int,string>,relationships:array<int,array<string,mixed>>}
    */
   private function describeMysqlTable(ConnectionInterface $connection, string $tableName): array
   {
      $parsed = $this->splitSchemaAndTable($tableName, '');
      $table = $parsed['table'];

      $rows = $connection->fetchAll(
         "SELECT column_name, data_type, is_nullable, column_default, column_key
          FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name = :table
          ORDER BY ordinal_position",
         ['table' => $table]
      );
      if ($rows === []) {
         throw new ToolingException(sprintf('Table "%s" was not found.', $tableName));
      }

      $columns = [];
      $primary = [];
      foreach ($rows as $row) {
         $name = (string) ($row['column_name'] ?? '');
         $isPrimary = strtoupper((string) ($row['column_key'] ?? '')) === 'PRI';
         if ($isPrimary) {
            $primary[] = $name;
         }

         $columns[] = [
            'name' => $name,
            'type' => (string) ($row['data_type'] ?? ''),
            'nullable' => strtoupper((string) ($row['is_nullable'] ?? 'NO')) === 'YES',
            'default' => $row['column_default'] ?? null,
            'primary' => $isPrimary,
         ];
      }

      $fkRows = $connection->fetchAll(
         "SELECT column_name, referenced_table_name, referenced_column_name
          FROM information_schema.key_column_usage
          WHERE table_schema = DATABASE()
            AND table_name = :table
            AND referenced_table_name IS NOT NULL",
         ['table' => $table]
      );
      $relationships = array_map(static fn (array $row): array => [
         'column' => (string) ($row['column_name'] ?? ''),
         'referenced_table' => (string) ($row['referenced_table_name'] ?? ''),
         'referenced_column' => (string) ($row['referenced_column_name'] ?? ''),
      ], $fkRows);

      return [
         'table' => $table,
         'schema' => '',
         'columns' => $columns,
         'primary_key' => $primary,
         'relationships' => $relationships,
      ];
   }

   /**
    * @return array{table:string,schema:string,columns:array<int,array<string,mixed>>,primary_key:array<int,string>,relationships:array<int,array<string,mixed>>}
    */
   private function describePostgresTable(ConnectionInterface $connection, string $tableName): array
   {
      $parsed = $this->splitSchemaAndTable($tableName, 'public');
      $schema = $parsed['schema'];
      $table = $parsed['table'];

      $rows = $connection->fetchAll(
         "SELECT column_name, data_type, is_nullable, column_default
          FROM information_schema.columns
          WHERE table_schema = :schema
            AND table_name = :table
          ORDER BY ordinal_position",
         ['schema' => $schema, 'table' => $table]
      );
      if ($rows === []) {
         throw new ToolingException(sprintf('Table "%s" was not found.', $tableName));
      }

      $pkRows = $connection->fetchAll(
         "SELECT kcu.column_name
          FROM information_schema.table_constraints tc
          JOIN information_schema.key_column_usage kcu
            ON tc.constraint_name = kcu.constraint_name
           AND tc.table_schema = kcu.table_schema
          WHERE tc.constraint_type = 'PRIMARY KEY'
            AND tc.table_schema = :schema
            AND tc.table_name = :table
          ORDER BY kcu.ordinal_position",
         ['schema' => $schema, 'table' => $table]
      );
      $primary = array_map(static fn (array $row): string => (string) ($row['column_name'] ?? ''), $pkRows);
      $primaryLookup = array_fill_keys($primary, true);

      $columns = [];
      foreach ($rows as $row) {
         $name = (string) ($row['column_name'] ?? '');
         $columns[] = [
            'name' => $name,
            'type' => (string) ($row['data_type'] ?? ''),
            'nullable' => strtoupper((string) ($row['is_nullable'] ?? 'NO')) === 'YES',
            'default' => $row['column_default'] ?? null,
            'primary' => isset($primaryLookup[$name]),
         ];
      }

      $fkRows = $connection->fetchAll(
         "SELECT
             kcu.column_name,
             ccu.table_schema AS referenced_schema,
             ccu.table_name AS referenced_table,
             ccu.column_name AS referenced_column
          FROM information_schema.table_constraints tc
          JOIN information_schema.key_column_usage kcu
            ON tc.constraint_name = kcu.constraint_name
           AND tc.table_schema = kcu.table_schema
          JOIN information_schema.constraint_column_usage ccu
            ON ccu.constraint_name = tc.constraint_name
           AND ccu.table_schema = tc.table_schema
          WHERE tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_schema = :schema
            AND tc.table_name = :table",
         ['schema' => $schema, 'table' => $table]
      );
      $relationships = array_map(static function (array $row): array {
         $referencedTable = (string) ($row['referenced_table'] ?? '');
         $referencedSchema = (string) ($row['referenced_schema'] ?? '');
         if ($referencedSchema !== '' && $referencedSchema !== 'public') {
            $referencedTable = $referencedSchema . '.' . $referencedTable;
         }

         return [
            'column' => (string) ($row['column_name'] ?? ''),
            'referenced_table' => $referencedTable,
            'referenced_column' => (string) ($row['referenced_column'] ?? ''),
         ];
      }, $fkRows);

      return [
         'table' => $table,
         'schema' => $schema,
         'columns' => $columns,
         'primary_key' => $primary,
         'relationships' => $relationships,
      ];
   }

   /**
    * @param array{table:string,schema:string,columns:array<int,array<string,mixed>>,primary_key:array<int,string>,relationships:array<int,array<string,mixed>>} $schema
    * @param array<int, string> $artifacts
    * @return array<int, array<string, mixed>>
    */
   private function buildScaffoldPreview(string $tableName, array $schema, array $artifacts): array
   {
      $entity = $this->entityNameFromTable($tableName);
      $columns = $schema['columns'];
      $primary = $schema['primary_key'];
      $defaults = $this->defaultLiteralMap($columns);
      $required = $this->requiredColumns($columns);
      $rows = [];

      $modelPath = 'app/Models/' . $entity . '.php';
      $modelBasePath = 'app/Models/Base/' . $entity . 'Base.php';
      $repositoryPath = 'app/Repositories/' . $entity . 'Repository.php';
      $repositoryBasePath = 'app/Repositories/Base/' . $entity . 'RepositoryBase.php';
      $servicePath = 'app/Services/' . $entity . 'Service.php';
      $serviceBasePath = 'app/Services/Base/' . $entity . 'ServiceBase.php';
      $controllerPath = 'app/Http/Controllers/' . $entity . 'Controller.php';
      $controllerBasePath = 'app/Http/Controllers/Base/' . $entity . 'ControllerBase.php';

      $tableLiteral = $this->escapeSingleQuoted($tableName);
      $entityLower = strtolower($entity);

      if (in_array('model', $artifacts, true)) {
         $modelBaseContents = <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Models\\Base;

/**
 * @generated by Celeris Tooling. Do not edit this file directly.
 * Source table: {$tableLiteral}
 */
class {$entity}Base
{
   public const TABLE = '{$tableLiteral}';
   public const COLUMNS = [{$this->quotedCsvFromColumns($columns)}];
   public const PRIMARY_KEY = [{$this->quotedCsv($primary)}];
   public const DEFAULTS = [{$this->quotedAssoc($defaults)}];
   public const REQUIRED = [{$this->quotedCsv($required)}];
}
PHP;
         $rows[] = $this->previewFileRow($modelBasePath, $modelBaseContents . "\n");
         $rows = [...$rows, ...$this->previewWrapperFile($modelPath, <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Models;

use {$this->namespaceRoot}\\Models\\Base\\{$entity}Base;

final class {$entity} extends {$entity}Base
{
}
PHP
)];
      }

      if (in_array('repository', $artifacts, true)) {
         $repositoryBaseContents = <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Repositories\\Base;

/**
 * @generated by Celeris Tooling. Do not edit this file directly.
 * Source table: {$tableLiteral}
 */
class {$entity}RepositoryBase
{
   public function table(): string
   {
      return '{$tableLiteral}';
   }

   /**
    * @return array<int, string>
    */
   public function columns(): array
   {
      return [{$this->quotedCsvFromColumns($columns)}];
   }
}
PHP;
         $rows[] = $this->previewFileRow($repositoryBasePath, $repositoryBaseContents . "\n");
         $rows = [...$rows, ...$this->previewWrapperFile($repositoryPath, <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Repositories;

use {$this->namespaceRoot}\\Repositories\\Base\\{$entity}RepositoryBase;

final class {$entity}Repository extends {$entity}RepositoryBase
{
}
PHP
)];
      }

      if (in_array('service', $artifacts, true)) {
         $serviceBaseContents = <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Services\\Base;

use {$this->namespaceRoot}\\Repositories\\{$entity}Repository;
use {$this->namespaceRoot}\\Models\\Base\\{$entity}Base;

/**
 * @generated by Celeris Tooling. Do not edit this file directly.
 */
class {$entity}ServiceBase
{
   public function __construct(
      protected {$entity}Repository \$repository,
   ) {}

   /**
    * @param array<string, mixed> \$payload
    * @return array<string, mixed>
    */
   protected function validateForCreate(array \$payload): array
   {
      \$normalized = \$payload;
      foreach ({$entity}Base::DEFAULTS as \$field => \$defaultValue) {
         if (!array_key_exists(\$field, \$normalized) || \$normalized[\$field] === null || \$normalized[\$field] === '') {
            \$normalized[\$field] = \$defaultValue;
         }
      }

      foreach ({$entity}Base::REQUIRED as \$field) {
         if (!array_key_exists(\$field, \$normalized) || \$normalized[\$field] === null || \$normalized[\$field] === '') {
            throw new \\InvalidArgumentException(sprintf('Field "%s" is required.', \$field));
         }
      }

      return \$normalized;
   }
}
PHP;
         $rows[] = $this->previewFileRow($serviceBasePath, $serviceBaseContents . "\n");
         $rows = [...$rows, ...$this->previewWrapperFile($servicePath, <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Services;

use {$this->namespaceRoot}\\Services\\Base\\{$entity}ServiceBase;

final class {$entity}Service extends {$entity}ServiceBase
{
}
PHP
)];
      }

      if (in_array('controller', $artifacts, true)) {
         $controllerBaseContents = <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Http\\Controllers\\Base;

use Celeris\\Framework\\Http\\Request;
use Celeris\\Framework\\Http\\RequestContext;
use Celeris\\Framework\\Http\\Response;
use Celeris\\Framework\\Routing\\Attribute\\Route;

/**
 * @generated by Celeris Tooling. Do not edit this file directly.
 * Source table: {$tableLiteral}
 */
class {$entity}ControllerBase
{
   #[Route(methods: ['GET'], path: '/api/{$entityLower}', summary: '{$entity} collection')]
   public function index(RequestContext \$ctx, Request \$request): Response
   {
      return new Response(200, ['content-type' => 'application/json; charset=utf-8'], '{"resource":"{$entityLower}","status":"ok"}');
   }
}
PHP;
         $rows[] = $this->previewFileRow($controllerBasePath, $controllerBaseContents . "\n");
         $rows = [...$rows, ...$this->previewWrapperFile($controllerPath, <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Http\\Controllers;

use {$this->namespaceRoot}\\Http\\Controllers\\Base\\{$entity}ControllerBase;

final class {$entity}Controller extends {$entity}ControllerBase
{
}
PHP
)];
      }

      if (in_array('dto.request', $artifacts, true)) {
         $requestProperties = $this->dtoPropertyLines($columns);
         $rows[] = $this->previewFileRow(
            'app/DTO/' . $entity . 'CreateRequest.php',
            <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\DTO;

final class {$entity}CreateRequest
{
{$requestProperties}
}
PHP
            . "\n"
         );
      }

      if (in_array('dto.response', $artifacts, true)) {
         $responseProperties = $this->dtoPropertyLines($columns);
         $rows[] = $this->previewFileRow(
            'app/DTO/' . $entity . 'Response.php',
            <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\DTO;

final class {$entity}Response
{
{$responseProperties}
}
PHP
            . "\n"
         );
      }

      if (in_array('factory', $artifacts, true)) {
         $rows[] = $this->previewFileRow(
            'database/factories/' . $entity . 'Factory.php',
            $this->factoryContents($entity, $tableName, $columns) . "\n"
         );
      }

      if (in_array('seed', $artifacts, true)) {
         $rows[] = $this->previewFileRow(
            'database/seeders/' . $entity . 'Seeder.php',
            $this->seederContents($entity, $tableName, $columns) . "\n"
         );
      }

      if (in_array('test.unit.repository', $artifacts, true)) {
         $rows[] = $this->previewFileRow(
            'tests/Unit/Repositories/' . $entity . 'RepositoryTest.php',
            $this->repositoryTestContents($entity) . "\n"
         );
      }

      if (in_array('test.unit.service', $artifacts, true)) {
         $rows[] = $this->previewFileRow(
            'tests/Unit/Services/' . $entity . 'ServiceTest.php',
            $this->serviceTestContents($entity) . "\n"
         );
      }

      if (in_array('test.integration.controller', $artifacts, true)) {
         $rows[] = $this->previewFileRow(
            'tests/Integration/Http/' . $entity . 'ControllerTest.php',
            $this->controllerIntegrationTestContents($entity) . "\n"
         );
      }

      return $rows;
   }

   /**
    * @param array<int, array<string, mixed>> $columns
    */
   private function factoryContents(string $entity, string $tableName, array $columns): string
   {
      $row = $this->sampleDataMap($columns, 1);
      $pairs = [];
      foreach ($row as $key => $value) {
         $pairs[] = sprintf("         '%s' => %s,", $this->escapeSingleQuoted($key), $value);
      }
      $body = implode("\n", $pairs);
      $tableLiteral = $this->escapeSingleQuoted($tableName);

      return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Database\\Factories;

final class {$entity}Factory
{
   /**
    * @return array<string, mixed>
    */
   public static function make(array \$overrides = []): array
   {
      \$base = [
{$body}
      ];

      return array_replace(\$base, \$overrides);
   }

   /**
    * @return array<int, array<string, mixed>>
    */
   public static function makeMany(int \$count): array
   {
      \$rows = [];
      for (\$i = 0; \$i < \$count; \$i++) {
         \$rows[] = self::make();
      }

      return \$rows;
   }

   public static function table(): string
   {
      return '{$tableLiteral}';
   }
}
PHP;
   }

   /**
    * @param array<int, array<string, mixed>> $columns
    */
   private function seederContents(string $entity, string $tableName, array $columns): string
   {
      $rows = [];
      for ($i = 1; $i <= 3; $i++) {
         $sample = $this->sampleDataMap($columns, $i);
         $pairs = [];
         foreach ($sample as $key => $value) {
            $pairs[] = sprintf("            '%s' => %s,", $this->escapeSingleQuoted($key), $value);
         }
         $rows[] = "         [\n" . implode("\n", $pairs) . "\n         ],";
      }
      $rowsBlock = implode("\n", $rows);
      $tableLiteral = $this->escapeSingleQuoted($tableName);

      return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Database\\Seeders;

final class {$entity}Seeder
{
   /**
    * @return array<int, array<string, mixed>>
    */
   public function records(): array
   {
      return [
{$rowsBlock}
      ];
   }

   public function table(): string
   {
      return '{$tableLiteral}';
   }
}
PHP;
   }

   private function repositoryTestContents(string $entity): string
   {
      return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\\Unit\\Repositories;

use {$this->namespaceRoot}\\Repositories\\{$entity}Repository;
use PHPUnit\\Framework\\TestCase;

final class {$entity}RepositoryTest extends TestCase
{
   public function test_can_be_constructed(): void
   {
      \$repository = new {$entity}Repository();
      self::assertInstanceOf({$entity}Repository::class, \$repository);
   }
}
PHP;
   }

   private function serviceTestContents(string $entity): string
   {
      return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\\Unit\\Services;

use {$this->namespaceRoot}\\Repositories\\{$entity}Repository;
use {$this->namespaceRoot}\\Services\\{$entity}Service;
use PHPUnit\\Framework\\TestCase;

final class {$entity}ServiceTest extends TestCase
{
   public function test_validation_rejects_missing_required_fields(): void
   {
      \$service = new {$entity}Service(new {$entity}Repository());
      self::assertInstanceOf({$entity}Service::class, \$service);
   }
}
PHP;
   }

   private function controllerIntegrationTestContents(string $entity): string
   {
      return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\\Integration\\Http;

use {$this->namespaceRoot}\\Http\\Controllers\\{$entity}Controller;
use PHPUnit\\Framework\\TestCase;

final class {$entity}ControllerTest extends TestCase
{
   public function test_controller_class_exists(): void
   {
      self::assertTrue(class_exists({$entity}Controller::class));
   }
}
PHP;
   }

   /**
    * @param array<int, array<string, mixed>> $columns
    * @return array<string, string>
    */
   private function sampleDataMap(array $columns, int $index): array
   {
      $out = [];
      foreach ($columns as $column) {
         $name = (string) ($column['name'] ?? '');
         if ($name === '') {
            continue;
         }
         if ((bool) ($column['primary'] ?? false)) {
            continue;
         }

         $literal = $this->defaultLiteral($column['default'] ?? null);
         if ($literal !== null) {
            $out[$name] = $literal;
            continue;
         }

         $type = strtolower((string) ($column['type'] ?? ''));
         $out[$name] = $this->sampleLiteralForType($name, $type, $index);
      }

      return $out;
   }

   private function sampleLiteralForType(string $column, string $type, int $index): string
   {
      if (str_contains($type, 'int') || str_contains($type, 'numeric') || str_contains($type, 'decimal')) {
         return (string) (100 + $index);
      }
      if (str_contains($type, 'bool')) {
         return $index % 2 === 0 ? 'true' : 'false';
      }
      if (str_contains($type, 'date')) {
         return "'" . gmdate('Y-m-d') . "'";
      }
      if (str_contains($type, 'time')) {
         return "'" . gmdate('c') . "'";
      }
      if (str_contains($type, 'json')) {
         return "'{}'";
      }

      return "'" . $this->escapeSingleQuoted($column . '_' . $index) . "'";
   }

   private function compatibilityBaselinePath(string $connection, string $table): string
   {
      $safeConnection = preg_replace('/[^A-Za-z0-9_]+/', '_', strtolower($connection)) ?? 'connection';
      $safeTable = preg_replace('/[^A-Za-z0-9_]+/', '_', strtolower($table)) ?? 'table';
      return rtrim($this->projectRoot, '/\\') . '/var/tooling/baselines/' . $safeConnection . '__' . $safeTable . '.json';
   }

   private function relativeToProject(string $absolutePath): string
   {
      $root = rtrim($this->projectRoot, '/\\') . '/';
      if (str_starts_with($absolutePath, $root)) {
         return substr($absolutePath, strlen($root));
      }

      return $absolutePath;
   }

   /**
    * @param array{table:string,schema:string,columns:array<int,array<string,mixed>>,primary_key:array<int,string>,relationships:array<int,array<string,mixed>>} $schema
    * @return array<string, mixed>
    */
   private function apiContractFromSchema(array $schema): array
   {
      $responseFields = [];
      $requestRequired = [];
      foreach ($schema['columns'] as $column) {
         if (!is_array($column)) {
            continue;
         }

         $name = (string) ($column['name'] ?? '');
         if ($name === '') {
            continue;
         }

         $responseFields[] = $name;
         $isPrimary = (bool) ($column['primary'] ?? false);
         $nullable = (bool) ($column['nullable'] ?? false);
         $hasDefault = $this->defaultLiteral($column['default'] ?? null) !== null;
         if (!$isPrimary && !$nullable && !$hasDefault) {
            $requestRequired[] = $name;
         }
      }

      return [
         'response_fields' => array_values(array_unique($responseFields)),
         'request_required_fields' => array_values(array_unique($requestRequired)),
      ];
   }

   /**
    * @param array<string, mixed> $baseline
    * @param array{table:string,schema:string,columns:array<int,array<string,mixed>>,primary_key:array<int,string>,relationships:array<int,array<string,mixed>>} $current
    * @return array<int, array<string, string>>
    */
   private function schemaBreakingChanges(array $baseline, array $current): array
   {
      $changes = [];
      $baselineColumns = [];
      $currentColumns = [];

      $baselineRows = $baseline['columns'] ?? [];
      if (is_array($baselineRows)) {
         foreach ($baselineRows as $row) {
            if (!is_array($row)) {
               continue;
            }
            $name = is_string($row['name'] ?? null) ? $row['name'] : '';
            if ($name !== '') {
               $baselineColumns[$name] = $row;
            }
         }
      }

      foreach ($current['columns'] as $row) {
         if (!is_array($row)) {
            continue;
         }
         $name = is_string($row['name'] ?? null) ? $row['name'] : '';
         if ($name !== '') {
            $currentColumns[$name] = $row;
         }
      }

      foreach ($baselineColumns as $name => $old) {
         if (!isset($currentColumns[$name])) {
            $changes[] = [
               'type' => 'schema.column_removed',
               'message' => sprintf('Column "%s" was removed.', $name),
            ];
            continue;
         }

         $new = $currentColumns[$name];
         $oldType = strtolower((string) ($old['type'] ?? ''));
         $newType = strtolower((string) ($new['type'] ?? ''));
         if ($oldType !== '' && $newType !== '' && $oldType !== $newType) {
            $changes[] = [
               'type' => 'schema.column_type_changed',
               'message' => sprintf('Column "%s" type changed from "%s" to "%s".', $name, $oldType, $newType),
            ];
         }

         $oldNullable = (bool) ($old['nullable'] ?? false);
         $newNullable = (bool) ($new['nullable'] ?? false);
         if ($oldNullable && !$newNullable) {
            $changes[] = [
               'type' => 'schema.nullability_tightened',
               'message' => sprintf('Column "%s" changed from nullable to non-nullable.', $name),
            ];
         }
      }

      return $changes;
   }

   /**
    * @param array<string, mixed> $baseline
    * @param array<string, mixed> $current
    * @return array<int, array<string, string>>
    */
   private function apiBreakingChanges(array $baseline, array $current): array
   {
      $changes = [];
      $baselineResponse = is_array($baseline['response_fields'] ?? null) ? $baseline['response_fields'] : [];
      $currentResponse = is_array($current['response_fields'] ?? null) ? $current['response_fields'] : [];
      $baselineRequired = is_array($baseline['request_required_fields'] ?? null) ? $baseline['request_required_fields'] : [];
      $currentRequired = is_array($current['request_required_fields'] ?? null) ? $current['request_required_fields'] : [];

      $baselineResponseSet = [];
      foreach ($baselineResponse as $field) {
         if (is_string($field) && $field !== '') {
            $baselineResponseSet[$field] = true;
         }
      }
      $currentResponseSet = [];
      foreach ($currentResponse as $field) {
         if (is_string($field) && $field !== '') {
            $currentResponseSet[$field] = true;
         }
      }
      foreach (array_keys($baselineResponseSet) as $field) {
         if (!isset($currentResponseSet[$field])) {
            $changes[] = [
               'type' => 'api.response_field_removed',
               'message' => sprintf('Response field "%s" was removed.', $field),
            ];
         }
      }

      $baselineRequiredSet = [];
      foreach ($baselineRequired as $field) {
         if (is_string($field) && $field !== '') {
            $baselineRequiredSet[$field] = true;
         }
      }
      $currentRequiredSet = [];
      foreach ($currentRequired as $field) {
         if (is_string($field) && $field !== '') {
            $currentRequiredSet[$field] = true;
         }
      }
      foreach (array_keys($currentRequiredSet) as $field) {
         if (!isset($baselineRequiredSet[$field])) {
            $changes[] = [
               'type' => 'api.request_field_now_required',
               'message' => sprintf('Request field "%s" became required.', $field),
            ];
         }
      }

      return $changes;
   }

   private function entityNameFromTable(string $tableName): string
   {
      $parsed = $this->splitSchemaAndTable($tableName, 'public');
      $table = $parsed['table'];
      $singular = preg_replace('/s$/', '', $table) ?? $table;
      $parts = preg_split('/[^A-Za-z0-9]+/', $singular) ?: [];
      $studly = '';
      foreach ($parts as $part) {
         if ($part === '') {
            continue;
         }
         $studly .= ucfirst(strtolower($part));
      }

      return $studly !== '' ? $studly : 'Entity';
   }

   /**
    * @param array<int, array<string, mixed>> $columns
    */
   private function quotedCsvFromColumns(array $columns): string
   {
      $names = [];
      foreach ($columns as $column) {
         $name = (string) ($column['name'] ?? '');
         if ($name !== '') {
            $names[] = $name;
         }
      }

      return $this->quotedCsv($names);
   }

   /**
    * @param array<int, string> $items
    */
   private function quotedCsv(array $items): string
   {
      $quoted = [];
      foreach ($items as $item) {
         $clean = trim($item);
         if ($clean !== '') {
            $quoted[] = "'" . $this->escapeSingleQuoted($clean) . "'";
         }
      }

      return implode(', ', $quoted);
   }

   /**
    * @param array<string, string> $items
    */
   private function quotedAssoc(array $items): string
   {
      $pairs = [];
      foreach ($items as $key => $valueLiteral) {
         $cleanKey = trim($key);
         if ($cleanKey === '' || trim($valueLiteral) === '') {
            continue;
         }
         $pairs[] = sprintf("'%s' => %s", $this->escapeSingleQuoted($cleanKey), $valueLiteral);
      }

      return implode(', ', $pairs);
   }

   /**
    * @param array<int, array<string, mixed>> $columns
    * @return array<string, string>
    */
   private function defaultLiteralMap(array $columns): array
   {
      $defaults = [];
      foreach ($columns as $column) {
         $name = (string) ($column['name'] ?? '');
         if ($name === '') {
            continue;
         }

         $literal = $this->defaultLiteral($column['default'] ?? null);
         if ($literal !== null) {
            $defaults[$name] = $literal;
         }
      }

      return $defaults;
   }

   /**
    * @param array<int, array<string, mixed>> $columns
    * @return array<int, string>
    */
   private function requiredColumns(array $columns): array
   {
      $required = [];
      foreach ($columns as $column) {
         $name = (string) ($column['name'] ?? '');
         if ($name === '') {
            continue;
         }

         $nullable = (bool) ($column['nullable'] ?? false);
         $primary = (bool) ($column['primary'] ?? false);
         $hasDefault = $this->defaultLiteral($column['default'] ?? null) !== null;
         if (!$nullable && !$primary && !$hasDefault) {
            $required[] = $name;
         }
      }

      return array_values(array_unique($required));
   }

   private function defaultLiteral(mixed $value): ?string
   {
      if ($value === null) {
         return null;
      }
      if (is_int($value) || is_float($value)) {
         return (string) $value;
      }
      if (is_bool($value)) {
         return $value ? 'true' : 'false';
      }
      if (!is_string($value)) {
         return null;
      }

      $raw = trim($value);
      if ($raw === '') {
         return null;
      }

      if (preg_match('/^[-+]?\d+$/', $raw) === 1 || preg_match('/^[-+]?\d+\.\d+$/', $raw) === 1) {
         return $raw;
      }

      $lower = strtolower($raw);
      if (in_array($lower, ['true', 'false'], true)) {
         return $lower;
      }
      if ($lower === 'null') {
         return 'null';
      }

      if ((str_starts_with($raw, "'") && str_ends_with($raw, "'")) || (str_starts_with($raw, '"') && str_ends_with($raw, '"'))) {
         $unquoted = substr($raw, 1, -1);
         return "'" . $this->escapeSingleQuoted($unquoted) . "'";
      }

      if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\(\))?$/', $raw) === 1) {
         return null;
      }

      return "'" . $this->escapeSingleQuoted($raw) . "'";
   }

   /**
    * @param array<int, array<string, mixed>> $columns
    */
   private function dtoPropertyLines(array $columns): string
   {
      $lines = [];
      foreach ($columns as $column) {
         $name = (string) ($column['name'] ?? '');
         if ($name === '') {
            continue;
         }

         $property = $this->camel($name);
         $nullable = (bool) ($column['nullable'] ?? false);
         $type = $nullable ? '?string' : 'string';
         $lines[] = sprintf('   public %s $%s;', $type, $property);
      }

      if ($lines === []) {
         return '   // No columns discovered.';
      }

      return implode("\n", $lines);
   }

   private function camel(string $value): string
   {
      $parts = preg_split('/[^A-Za-z0-9]+/', strtolower($value)) ?: [];
      if ($parts === []) {
         return 'field';
      }

      $first = array_shift($parts);
      $rest = array_map(static fn (string $part): string => ucfirst($part), $parts);
      return ($first !== null ? $first : 'field') . implode('', $rest);
   }

   private function escapeSingleQuoted(string $value): string
   {
      return str_replace("'", "\\'", $value);
   }

   /**
    * @return array<int, array<string, mixed>>
    */
   private function previewWrapperFile(string $path, string $contents): array
   {
      return [$this->previewFileRow($path, $contents . "\n")];
   }

   /**
    * @return array<string, mixed>
    */
   private function previewFileRow(string $path, string $contents): array
   {
      $fullPath = rtrim($this->projectRoot, '/\\') . '/' . ltrim($path, '/\\');
      $exists = is_file($fullPath);
      $current = '';
      if ($exists) {
         $read = @file_get_contents($fullPath);
         if (is_string($read)) {
            $current = $read;
         }
      }

      $diff = (new UnifiedDiffBuilder())->build($current, $contents, 'a/' . $path, 'b/' . $path);
      return [
         'path' => $path,
         'exists' => $exists,
         'diff' => $diff,
         'contents' => $contents,
      ];
   }

   /**
    * @param array<int, array<string, mixed>> $files
    * @return array{0: array<int, string>, 1: array<int, string>}
    */
   private function writeScaffoldFiles(array $files): array
   {
      $written = [];
      $skipped = [];

      foreach ($files as $file) {
         $path = isset($file['path']) && is_string($file['path']) ? trim($file['path']) : '';
         $contents = isset($file['contents']) && is_string($file['contents']) ? $file['contents'] : null;
         if ($path === '' || $contents === null) {
            continue;
         }

         if (str_contains($path, '..')) {
            throw new ToolingException(sprintf('Refusing to write unsafe path "%s".', $path));
         }

         $target = rtrim($this->projectRoot, '/\\') . '/' . ltrim($path, '/\\');

         $dir = dirname($target);
         if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new ToolingException(sprintf('Unable to create directory "%s".', $dir));
         }

         $result = @file_put_contents($target, $contents);
         if ($result === false) {
            throw new ToolingException(sprintf('Unable to write file "%s".', $path));
         }

         $written[] = $path;
      }

      return [$written, $skipped];
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

   /**
    * @param array<int, string> $artifacts
    * @param array<int, string> $written
    * @param array<int, string> $skipped
    */
   private function auditScaffoldApply(
      RequestContext $ctx,
      Request $request,
      string $connection,
      string $table,
      array $artifacts,
      array $written,
      array $skipped,
      ?string $error,
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
         'channel' => 'api',
         'method' => $request->getMethod(),
         'path' => $request->getPath(),
         'request_id' => $ctx->getRequestId(),
         'remote_addr' => $this->serverParam($ctx, 'REMOTE_ADDR'),
         'user_agent' => $request->getHeader('user-agent', ''),
         'generator' => 'scaffold',
         'connection' => $connection,
         'table' => $table,
         'artifacts' => $artifacts,
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
