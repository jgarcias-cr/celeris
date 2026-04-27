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
use Celeris\Framework\Database\Migration\DatabaseMigrationRepository;
use Celeris\Framework\Database\Migration\MigrationInterface;
use Celeris\Framework\Database\Migration\MigrationRunner;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Route;
use Celeris\Framework\Tooling\Architecture\ArchitectureDecisionValidator;
use Celeris\Framework\Tooling\Diff\UnifiedDiffBuilder;
use Celeris\Framework\Tooling\Generator\GenerationRequest;
use Celeris\Framework\Tooling\Generator\GeneratorEngine;
use Celeris\Framework\Tooling\Graph\DependencyGraphBuilder;
use Celeris\Framework\Tooling\Routing\ProjectRouteInspector;
use Celeris\Framework\Tooling\Security\AppKeyManager;
use Celeris\Framework\Tooling\ToolingException;
use Throwable;

/**
 * Implement developer ui controller behavior for the Tooling subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class DeveloperUiController
{
   private const API_VERSION = 'v1';
   private const ENV_KEY = 'APP_ENV';
   private const WEB_ENABLED_KEY = 'TOOLING_WEB_ENABLED';
   private const LEGACY_ENABLED_KEY = 'TOOLING_ENABLED';
   private const ALLOWED_ENVS_KEY = 'TOOLING_ALLOWED_ENVS';
   private const AUDIT_ENABLED_KEY = 'TOOLING_AUDIT_ENABLED';
   private const AUDIT_PATH_KEY = 'TOOLING_AUDIT_PATH';
   private ?ConfigRepository $config = null;
   private ?AppKeyManager $appKeyManager = null;
   private ?ProjectRouteInspector $routeInspector = null;

   /**
    * Create a new instance.
    *
    * @param GeneratorEngine $generatorEngine
    * @param DependencyGraphBuilder $dependencyGraphBuilder
    * @param ArchitectureDecisionValidator $architectureValidator
    * @param string $projectRoot
    * @param string $routePrefix
    * @param string $namespaceRoot
    * @param ?callable(): array<int, \Celeris\Framework\Routing\RouteDefinition> $routeProvider
    * @return mixed
    */
   public function __construct(
      private GeneratorEngine $generatorEngine,
      private DependencyGraphBuilder $dependencyGraphBuilder,
      private ArchitectureDecisionValidator $architectureValidator,
      private string $projectRoot,
      private string $routePrefix = '/__dev/tooling',
      private string $namespaceRoot = 'App',
      private $routeProvider = null,
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
}
.artifact-checks-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.7rem;
}
.artifact-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 1rem;
  background: #fffef8;
}
.artifact-column {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.artifact-column-title {
  margin: 0 0 0.5rem 0;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--ink);
  padding-bottom: 0.3rem;
  border-bottom: 2px solid var(--accent);
}
.artifact-items {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.artifact-item {
  display: flex;
  align-items: flex-start;
  gap: 0.45rem;
  color: var(--ink);
  font-size: 0.82rem;
  padding: 0.35rem 0.4rem;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.15s ease;
}
.artifact-item:hover {
  background: rgba(0, 0, 0, 0.02);
}
.artifact-item.artifact-core {
  font-weight: 500;
}
.artifact-item.artifact-optional {
  opacity: 0.85;
}
.artifact-item.artifact-test {
  padding-left: 1.5rem;
  border-left: 2px solid var(--accent);
}
.artifact-item.disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: rgba(0, 0, 0, 0.03);
}
.artifact-item.disabled::before {
  content: "🔒";
  display: inline-block;
  margin-right: 0.3rem;
  font-size: 0.75rem;
}
.artifact-spacer {
  height: 0.7rem;
}
.artifact-label {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.artifact-item input[type="checkbox"] {
  width: auto;
  margin-top: 0.15rem;
  flex-shrink: 0;
}
.artifact-item.disabled input[type="checkbox"] {
  cursor: not-allowed;
}
.hint {
  display: block;
  color: var(--muted);
  font-size: 0.73rem;
  line-height: 1.3;
}
.schema-snapshot {
  margin-top: 0.7rem;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: #fffef8;
  padding: 0.55rem 0.65rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  flex-wrap: wrap;
}
.schema-snapshot .summary {
  font-size: 0.8rem;
  color: var(--muted);
}
.schema-modal {
  position: fixed;
  inset: 0;
  background: rgba(16, 23, 37, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  z-index: 60;
}
.schema-modal[hidden] {
  display: none;
}
.schema-modal-panel {
  width: min(980px, 100%);
  max-height: 86vh;
  overflow: auto;
  border-radius: 12px;
  background: #fffef9;
  border: 1px solid var(--line);
  box-shadow: 0 20px 44px rgba(20, 27, 44, 0.26);
  padding: 0.8rem;
}
.schema-modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.8rem;
}
.schema-modal-title {
  margin: 0;
  font-size: 1rem;
}
.schema-kpis {
  margin-top: 0.5rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.38rem;
}
.schema-kpi {
  border: 1px solid var(--line);
  border-radius: 999px;
  padding: 0.2rem 0.5rem;
  background: #fff;
  font-size: 0.74rem;
  color: var(--muted);
}
.schema-table-wrap {
  margin-top: 0.6rem;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: #fff;
  overflow: auto;
  max-height: 48vh;
}
.schema-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8rem;
}
.schema-table th, .schema-table td {
  border-bottom: 1px solid var(--line);
  text-align: left;
  padding: 0.35rem 0.4rem;
  vertical-align: top;
}
.schema-table th {
  color: var(--muted);
  font-weight: 600;
  position: sticky;
  top: 0;
  background: #fffef9;
}
.pill {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--line);
  border-radius: 999px;
  padding: 0.15rem 0.45rem;
  font-size: 0.72rem;
  background: #f8f7f1;
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
.preview-workspace {
  margin-top: 0.8rem;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #fffef9;
  padding: 0.7rem;
}
.preview-meta {
  font-size: 0.8rem;
  color: var(--muted);
}
.preview-layout {
  margin-top: 0.55rem;
  display: grid;
  grid-template-columns: minmax(220px, 320px) 1fr;
  gap: 0.7rem;
}
@media (max-width: 840px) {
  .preview-layout {
    grid-template-columns: 1fr;
  }
}
.preview-tabs {
  display: grid;
  gap: 0.4rem;
  max-height: 18rem;
  overflow-y: auto;
  padding-right: 0.2rem;
}
.preview-tab {
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: #fff;
  color: var(--ink);
  text-align: left;
  padding: 0.5rem 0.55rem;
  cursor: pointer;
}
.preview-tab:hover {
  border-color: #c9bbaa;
}
.preview-tab.active {
  border-color: var(--accent);
  box-shadow: 0 0 0 1px rgba(225, 87, 63, 0.2);
  background: #fff6f2;
}
.preview-tab .path {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.25;
  overflow-wrap: anywhere;
}
.preview-tab .meta {
  display: block;
  margin-top: 0.2rem;
  font-size: 0.73rem;
  color: var(--muted);
}
.tabbar {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.tab-panel {
  display: none;
}
.tab-panel.active {
  display: block;
}
.tab-button.active {
  background: var(--accent);
}
.routes-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}
.routes-table th, .routes-table td {
  border-bottom: 1px solid var(--line);
  text-align: left;
  padding: 0.4rem 0.3rem;
  vertical-align: top;
}
.routes-table td code {
  font-family: "JetBrains Mono", "Cascadia Code", monospace;
}
.routes-scroll {
  margin-top: 0.7rem;
  max-height: 27rem;
  overflow: auto;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: #fff;
}
.env-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.6rem;
  margin-top: 0.7rem;
}
@media (max-width: 900px) {
  .env-grid { grid-template-columns: 1fr; }
}
.env-section {
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 0.65rem;
  background: #fffef9;
}
.env-section h3 {
  font-size: 0.9rem;
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
    <div class="tabbar">
      <button id="tabScaffoldBtn" class="tab-button primary active" type="button">Schema &amp; Scaffold</button>
      <button id="tabDatabaseBtn" class="tab-button" type="button">Database Ops</button>
      <button id="tabCacheBtn" class="tab-button" type="button">Cache Ops</button>
      <button id="tabRoutesBtn" class="tab-button" type="button">Routes</button>
      <button id="tabEnvBtn" class="tab-button" type="button">Environment</button>
      <button id="tabAppKeyBtn" class="tab-button" type="button">Security</button>
    </div>
  </section>
  <div id="scaffoldTab" class="tab-panel active">
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
        <label for="routingType">Routing Type</label>
        <select id="routingType">
          <option value="attribute" selected>Attribute routes</option>
          <option value="php">PHP routes</option>
        </select>
      </div>
      <div>
        <label>&nbsp;</label>
        <button id="dbReloadBtn">Reload Tables</button>
      </div>
    </div>
    <div id="artifactChecks" class="artifact-checks-grid">
      <div class="artifact-section">
        <div class="artifact-column artifact-main">
          <h4 class="artifact-column-title">Main Classes</h4>
          <div class="artifact-items">
            <label class="artifact-item artifact-core"><input type="checkbox" value="model" checked data-artifact="model"><span class="artifact-label">Model<span class="hint">Entity constants and table schema metadata.</span></span></label>
            <label class="artifact-item artifact-core"><input type="checkbox" value="repository" checked data-artifact="repository"><span class="artifact-label">Repository<span class="hint">Data-access abstraction for table operations.</span></span></label>
            <label class="artifact-item artifact-core"><input type="checkbox" value="service" checked data-artifact="service"><span class="artifact-label">Service<span class="hint">Business logic with required/default validation.</span></span></label>
            <label class="artifact-item artifact-core"><input type="checkbox" value="controller" checked data-artifact="controller"><span class="artifact-label">Controller<span class="hint">HTTP endpoints for the generated resource.</span></span></label>
            <div class="artifact-spacer"></div>
            <label class="artifact-item artifact-optional"><input type="checkbox" value="dto.request" checked data-artifact="dto.request"><span class="artifact-label">DTO Request<span class="hint">Typed input contract for create/update payloads.</span></span></label>
            <label class="artifact-item artifact-optional"><input type="checkbox" value="dto.response" checked data-artifact="dto.response"><span class="artifact-label">DTO Response<span class="hint">Typed output contract returned to clients.</span></span></label>
            <label class="artifact-item artifact-optional"><input type="checkbox" value="factory" data-artifact="factory"><span class="artifact-label">Factory<span class="hint">Realistic test-data builder for this table.</span></span></label>
            <label class="artifact-item artifact-optional"><input type="checkbox" value="seed" data-artifact="seed"><span class="artifact-label">Seeder<span class="hint">Seed records for local/dev environments.</span></span></label>
          </div>
        </div>
        <div class="artifact-column artifact-tests">
          <h4 class="artifact-column-title">Test Coverage</h4>
          <div class="artifact-items">
            <label class="artifact-item artifact-test artifact-test-repository" data-test-for="repository"><input type="checkbox" value="test.unit.repository" data-artifact="test.unit.repository"><span class="artifact-label">Repo Test<span class="hint">Unit scaffold for repository behavior.</span></span></label>
            <label class="artifact-item artifact-test artifact-test-service" data-test-for="service"><input type="checkbox" value="test.unit.service" data-artifact="test.unit.service"><span class="artifact-label">Service Test<span class="hint">Unit scaffold for service rules/defaults.</span></span></label>
            <label class="artifact-item artifact-test artifact-test-controller" data-test-for="controller"><input type="checkbox" value="test.integration.controller" data-artifact="test.integration.controller"><span class="artifact-label">Controller Test<span class="hint">Integration scaffold for HTTP contract checks.</span></span></label>
          </div>
        </div>
      </div>
    </div>
    <div class="actions">
      <button id="dbPreviewBtn" class="primary">DB Preview</button>
      <button id="dbApplyBtn">DB Apply</button>
    </div>
    <div id="schemaSnapshot" class="schema-snapshot">
      <div id="schemaSnapshotText" class="summary">Schema snapshot unavailable until a table is selected.</div>
      <button id="schemaInspectBtn" type="button" disabled>Inspect Schema</button>
    </div>
    <div id="previewStatus" class="status" style="margin-top:0.7rem;">Idle</div>
    <section class="preview-workspace">
      <div id="previewMeta" class="preview-meta">No preview generated yet.</div>
      <div class="preview-layout">
        <div id="previewTabs" class="preview-tabs" role="tablist" aria-label="Preview files"></div>
        <pre id="diffPanel">(no diff selected)</pre>
      </div>
    </section>
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
</div>
  <div id="schemaModal" class="schema-modal" hidden>
    <section class="schema-modal-panel" role="dialog" aria-modal="true" aria-labelledby="schemaModalTitle">
      <div class="schema-modal-head">
        <div>
          <h3 id="schemaModalTitle" class="schema-modal-title">Schema Inspector</h3>
          <p id="schemaModalSummary" class="muted" style="margin-top:0.2rem;">No schema loaded.</p>
        </div>
        <button id="schemaModalCloseBtn" type="button">Close</button>
      </div>
      <div id="schemaModalStatus" class="status" style="margin-top:0.6rem;">Idle</div>
      <div id="schemaModalKpis" class="schema-kpis"></div>
      <div class="schema-table-wrap">
        <table class="schema-table">
          <thead>
            <tr>
              <th>Column</th>
              <th>Type</th>
              <th>Null</th>
              <th>Default</th>
              <th>PK</th>
              <th>FK</th>
            </tr>
          </thead>
          <tbody id="schemaModalRows">
            <tr><td colspan="6" class="muted">No schema loaded.</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
  <div id="databaseTab" class="tab-panel">
    <section class="card">
      <h2>Database Operations</h2>
      <p class="muted" style="margin-top:0.25rem;">Run migrations and seeding with explicit targets.</p>
      <p class="muted" style="margin-top:0.35rem; color:#8b1e1e;"><strong>Warning:</strong> These operations change schema/data. Run in development/staging first and confirm your target connection.</p>
      <div class="row">
        <div>
          <label for="opsConnection">Connection</label>
          <select id="opsConnection"></select>
        </div>
        <div>
          <label for="migrationTarget">Migration Target</label>
          <input id="migrationTarget" type="text" placeholder="all or CreateUsersTableMigration.php">
        </div>
        <div>
          <label for="seedTarget">Seed Target</label>
          <input id="seedTarget" type="text" placeholder="all or table name">
        </div>
      </div>
      <div class="actions">
        <button id="migrateRunBtn" class="primary">Migrate</button>
        <button id="migrateRollbackBtn">Rollback</button>
        <button id="migrateFreshBtn">Fresh</button>
        <button id="migrateStatusBtn">Status</button>
        <button id="seedRunBtn">Seed</button>
      </div>
      <div id="dbOpsStatus" class="status" style="margin-top:0.7rem;">Idle</div>
      <pre id="dbOpsOutput">(no database operation run yet)</pre>
    </section>
  </div>
  <div id="cacheTab" class="tab-panel">
    <section class="card">
      <h2>Cache Operations</h2>
      <p class="muted" style="margin-top:0.25rem;">Clear app cache directories and route bindings.</p>
      <p class="muted" style="margin-top:0.35rem; color:#8b1e1e;"><strong>Warning:</strong> Clearing cache can invalidate route bindings and warm state. Use in maintenance windows for production-like environments.</p>
      <div class="actions">
        <button id="cacheClearAllBtn" class="primary">Clear All Cache</button>
        <button id="cacheClearRouteBtn">Route Clear</button>
        <button id="cacheClearHttpBtn">HTTP Cache Clear</button>
      </div>
      <div id="cacheStatus" class="status" style="margin-top:0.7rem;">Idle</div>
      <pre id="cacheOutput">(no cache clear run yet)</pre>
    </section>
  </div>
  <div id="routesTab" class="tab-panel">
    <section class="card">
      <h2>Routes Explorer</h2>
      <p class="muted" style="margin-top:0.25rem;">Inspect registered routes with method, URI, action, and middleware.</p>
      <div class="actions">
        <button id="routesRefreshBtn" class="primary">Refresh Routes</button>
      </div>
      <div id="routesStatus" class="status" style="margin-top:0.7rem;">Idle</div>
      <div class="routes-scroll">
        <table class="routes-table">
          <thead>
            <tr>
              <th>Method</th>
              <th>URI</th>
              <th>Action</th>
              <th>Middleware</th>
            </tr>
          </thead>
          <tbody id="routesRows">
            <tr><td colspan="4" class="muted">No routes loaded.</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
  <div id="environmentTab" class="tab-panel">
    <section class="card">
      <h2>Environment</h2>
      <p class="muted" style="margin-top:0.25rem;">Manage core app settings safely. Only supported scaffold keys are editable.</p>
      <p class="muted" style="margin-top:0.35rem;">Note: saving requires a writable <code>.env</code> file for the app runtime user (recommended: <code>www-data:www-data</code>, mode <code>664</code>).</p>
      <div class="actions">
        <button id="envReloadBtn">Reload</button>
        <button id="envSaveBtn" class="primary">Save Environment</button>
      </div>
      <div id="envStatus" class="status" style="margin-top:0.7rem;">Idle</div>
      <div class="env-grid">
        <section class="env-section">
          <h3>Application</h3>
          <label for="envAppName">APP_NAME</label>
          <input id="envAppName" type="text">
          <label for="envAppUrl">APP_URL</label>
          <input id="envAppUrl" type="text">
          <label for="envAppTimezone">APP_TIMEZONE</label>
          <input id="envAppTimezone" type="text">
        </section>
        <section class="env-section">
          <h3>Database</h3>
          <label for="envDbDefault">DB_DEFAULT</label>
          <input id="envDbDefault" type="text">
          <label for="envPgHost">PGSQL_HOST</label>
          <input id="envPgHost" type="text">
          <label for="envPgPort">PGSQL_PORT</label>
          <input id="envPgPort" type="text">
          <label for="envPgDatabase">PGSQL_DATABASE</label>
          <input id="envPgDatabase" type="text">
          <label for="envPgUsername">PGSQL_USERNAME</label>
          <input id="envPgUsername" type="text">
          <label for="envPgPassword">PGSQL_PASSWORD (leave blank to keep current)</label>
          <input id="envPgPassword" type="password">
        </section>
        <section id="envViewSection" class="env-section">
          <h3>MVC View</h3>
          <label for="envViewEngine">VIEW_ENGINE</label>
          <select id="envViewEngine">
            <option value="php">php</option>
            <option value="twig">twig</option>
            <option value="plates">plates</option>
            <option value="latte">latte</option>
          </select>
        </section>
      </div>
    </section>
  </div>
  <div id="appKeyTab" class="tab-panel">
    <section class="card">
      <h2>Application Key</h2>
      <p class="muted" style="margin-top:0.25rem;">Generate and persist APP_KEY into your env file.</p>
      <div class="actions">
        <button id="appKeyGenerateBtn" class="primary">Generate APP_KEY</button>
        <label style="display:flex; align-items:center; gap:0.45rem;">
          <input id="appKeyForce" type="checkbox" style="width:auto;"> Overwrite existing key
        </label>
      </div>
      <div id="appKeyStatus" class="status" style="margin-top:0.7rem;">Idle</div>
      <pre id="appKeyOutput">(APP_KEY has not been generated in this session)</pre>
    </section>
  </div>
</main>
<script>
const API_BASE = {$apiBaseJson};

const elements = {
  dbConnection: document.getElementById('dbConnection'),
  dbTable: document.getElementById('dbTable'),
  routingType: document.getElementById('routingType'),
  dbReloadBtn: document.getElementById('dbReloadBtn'),
  dbPreviewBtn: document.getElementById('dbPreviewBtn'),
  dbApplyBtn: document.getElementById('dbApplyBtn'),
  compatCheckBtn: document.getElementById('compatCheckBtn'),
  compatSaveBtn: document.getElementById('compatSaveBtn'),
  compatPanel: document.getElementById('compatPanel'),
  tabScaffoldBtn: document.getElementById('tabScaffoldBtn'),
  tabDatabaseBtn: document.getElementById('tabDatabaseBtn'),
  tabCacheBtn: document.getElementById('tabCacheBtn'),
  tabRoutesBtn: document.getElementById('tabRoutesBtn'),
  tabEnvBtn: document.getElementById('tabEnvBtn'),
  tabAppKeyBtn: document.getElementById('tabAppKeyBtn'),
  scaffoldTab: document.getElementById('scaffoldTab'),
  databaseTab: document.getElementById('databaseTab'),
  cacheTab: document.getElementById('cacheTab'),
  routesTab: document.getElementById('routesTab'),
  environmentTab: document.getElementById('environmentTab'),
  appKeyTab: document.getElementById('appKeyTab'),
  opsConnection: document.getElementById('opsConnection'),
  migrationTarget: document.getElementById('migrationTarget'),
  seedTarget: document.getElementById('seedTarget'),
  migrateRunBtn: document.getElementById('migrateRunBtn'),
  migrateRollbackBtn: document.getElementById('migrateRollbackBtn'),
  migrateFreshBtn: document.getElementById('migrateFreshBtn'),
  migrateStatusBtn: document.getElementById('migrateStatusBtn'),
  seedRunBtn: document.getElementById('seedRunBtn'),
  dbOpsStatus: document.getElementById('dbOpsStatus'),
  dbOpsOutput: document.getElementById('dbOpsOutput'),
  routesRefreshBtn: document.getElementById('routesRefreshBtn'),
  routesStatus: document.getElementById('routesStatus'),
  routesRows: document.getElementById('routesRows'),
  cacheClearAllBtn: document.getElementById('cacheClearAllBtn'),
  cacheClearRouteBtn: document.getElementById('cacheClearRouteBtn'),
  cacheClearHttpBtn: document.getElementById('cacheClearHttpBtn'),
  cacheStatus: document.getElementById('cacheStatus'),
  cacheOutput: document.getElementById('cacheOutput'),
  appKeyGenerateBtn: document.getElementById('appKeyGenerateBtn'),
  appKeyForce: document.getElementById('appKeyForce'),
  appKeyStatus: document.getElementById('appKeyStatus'),
  appKeyOutput: document.getElementById('appKeyOutput'),
  envReloadBtn: document.getElementById('envReloadBtn'),
  envSaveBtn: document.getElementById('envSaveBtn'),
  envStatus: document.getElementById('envStatus'),
  envViewSection: document.getElementById('envViewSection'),
  envAppName: document.getElementById('envAppName'),
  envAppUrl: document.getElementById('envAppUrl'),
  envAppTimezone: document.getElementById('envAppTimezone'),
  envDbDefault: document.getElementById('envDbDefault'),
  envPgHost: document.getElementById('envPgHost'),
  envPgPort: document.getElementById('envPgPort'),
  envPgDatabase: document.getElementById('envPgDatabase'),
  envPgUsername: document.getElementById('envPgUsername'),
  envPgPassword: document.getElementById('envPgPassword'),
  envViewEngine: document.getElementById('envViewEngine'),
  schemaSnapshotText: document.getElementById('schemaSnapshotText'),
  schemaInspectBtn: document.getElementById('schemaInspectBtn'),
  schemaModal: document.getElementById('schemaModal'),
  schemaModalCloseBtn: document.getElementById('schemaModalCloseBtn'),
  schemaModalTitle: document.getElementById('schemaModalTitle'),
  schemaModalSummary: document.getElementById('schemaModalSummary'),
  schemaModalStatus: document.getElementById('schemaModalStatus'),
  schemaModalKpis: document.getElementById('schemaModalKpis'),
  schemaModalRows: document.getElementById('schemaModalRows'),
  artifactChecks: document.getElementById('artifactChecks'),
  previewStatus: document.getElementById('previewStatus'),
  previewMeta: document.getElementById('previewMeta'),
  previewTabs: document.getElementById('previewTabs'),
  diffPanel: document.getElementById('diffPanel'),
};

let envSnapshot = null;
const schemaCache = new Map();

function selectedSchemaKey() {
  const connection = elements.dbConnection ? elements.dbConnection.value : '';
  const table = elements.dbTable ? elements.dbTable.value : '';
  if (!connection || !table) return null;
  return connection + '|' + table;
}

function setSchemaSnapshotMessage(message) {
  if (!elements.schemaSnapshotText) return;
  elements.schemaSnapshotText.textContent = message;
}

function setSchemaModalStatus(message, ok) {
  if (!elements.schemaModalStatus) return;
  elements.schemaModalStatus.textContent = message;
  elements.schemaModalStatus.className = 'status ' + (ok ? 'ok' : 'error');
}

function renderSchemaKpis(schema) {
  if (!elements.schemaModalKpis) return;
  elements.schemaModalKpis.innerHTML = '';
  if (!schema || !Array.isArray(schema.columns)) return;

  const primary = Array.isArray(schema.primary_key) ? schema.primary_key : [];
  const relationships = Array.isArray(schema.relationships) ? schema.relationships : [];
  const nullable = schema.columns.reduce((count, row) => count + (row && row.nullable ? 1 : 0), 0);
  const kpis = [
    'columns: ' + schema.columns.length,
    'pk: ' + (primary.length === 0 ? '-' : primary.join(', ')),
    'fk: ' + relationships.length,
    'nullable: ' + nullable,
  ];

  kpis.forEach((item) => {
    const chip = document.createElement('div');
    chip.className = 'schema-kpi';
    chip.textContent = item;
    elements.schemaModalKpis.appendChild(chip);
  });
}

function renderSchemaRows(schema) {
  if (!elements.schemaModalRows) return;
  elements.schemaModalRows.innerHTML = '';
  if (!schema || !Array.isArray(schema.columns) || schema.columns.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td colspan="6" class="muted">No columns detected.</td>';
    elements.schemaModalRows.appendChild(tr);
    return;
  }

  const primary = new Set(Array.isArray(schema.primary_key) ? schema.primary_key : []);
  const relationships = Array.isArray(schema.relationships) ? schema.relationships : [];
  const relationshipMap = new Map();
  relationships.forEach((row) => {
    if (!row || !row.column) return;
    const refSchema = row.referenced_schema ? String(row.referenced_schema) + '.' : '';
    relationshipMap.set(String(row.column), refSchema + String(row.referenced_table || '') + '.' + String(row.referenced_column || ''));
  });

  schema.columns.forEach((column) => {
    const name = String(column && column.name ? column.name : '');
    const type = String(column && column.type ? column.type : '');
    const nullable = !!(column && column.nullable);
    const defaultValue = column && Object.prototype.hasOwnProperty.call(column, 'default') ? column.default : null;
    const pk = primary.has(name);
    const fkRef = relationshipMap.get(name);
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td><code>' + escapeHtml(name) + '</code></td>' +
      '<td>' + escapeHtml(type) + '</td>' +
      '<td>' + (nullable ? '<span class="pill">yes</span>' : '<span class="pill">no</span>') + '</td>' +
      '<td><code>' + escapeHtml(defaultValue === null ? '-' : String(defaultValue)) + '</code></td>' +
      '<td>' + (pk ? '<span class="pill">yes</span>' : '<span class="muted">-</span>') + '</td>' +
      '<td>' + (fkRef ? '<code>' + escapeHtml(fkRef) + '</code>' : '<span class="muted">-</span>') + '</td>';
    elements.schemaModalRows.appendChild(tr);
  });
}

function applySchemaSnapshot(data) {
  const schema = data && data.schema ? data.schema : null;
  if (!schema || !Array.isArray(schema.columns)) {
    setSchemaSnapshotMessage('Schema snapshot unavailable for current selection.');
    if (elements.schemaInspectBtn) elements.schemaInspectBtn.disabled = true;
    return;
  }

  const primary = Array.isArray(schema.primary_key) ? schema.primary_key : [];
  const relationships = Array.isArray(schema.relationships) ? schema.relationships : [];
  const nullable = schema.columns.reduce((count, row) => count + (row && row.nullable ? 1 : 0), 0);
  setSchemaSnapshotMessage(
    'columns: ' + schema.columns.length +
    ' | pk: ' + (primary.length === 0 ? '-' : primary.join(', ')) +
    ' | fk: ' + relationships.length +
    ' | nullable: ' + nullable
  );
  if (elements.schemaInspectBtn) elements.schemaInspectBtn.disabled = false;
}

function setSchemaModalOpen(open) {
  if (!elements.schemaModal) return;
  if (open) {
    elements.schemaModal.removeAttribute('hidden');
  } else {
    elements.schemaModal.setAttribute('hidden', 'hidden');
  }
}

function loadTableSchema(force) {
  const connection = elements.dbConnection ? elements.dbConnection.value : '';
  const table = elements.dbTable ? elements.dbTable.value : '';
  if (!connection || !table) {
    setSchemaSnapshotMessage('Schema snapshot unavailable until a table is selected.');
    if (elements.schemaInspectBtn) elements.schemaInspectBtn.disabled = true;
    return Promise.resolve(null);
  }

  const key = selectedSchemaKey();
  if (!force && key && schemaCache.has(key)) {
    const cached = schemaCache.get(key);
    applySchemaSnapshot(cached);
    return Promise.resolve(cached);
  }

  const query = '?connection=' + encodeURIComponent(connection);
  const path = '/schema/tables/' + encodeURIComponent(table) + query;
  return request(path).then((data) => {
    if (key) schemaCache.set(key, data);
    applySchemaSnapshot(data);
    return data;
  }).catch((error) => {
    setSchemaSnapshotMessage('Schema snapshot error: ' + error.message);
    if (elements.schemaInspectBtn) elements.schemaInspectBtn.disabled = true;
    throw error;
  });
}

function openSchemaInspector() {
  if (!selectedSchemaKey()) {
    setPreviewStatus('Select a connection and table first.', false);
    return;
  }
  setSchemaModalOpen(true);
  setSchemaModalStatus('Loading schema...', true);
  loadTableSchema(false)
    .then((data) => {
      const schema = data && data.schema ? data.schema : null;
      const tableName = data && data.table ? data.table : (elements.dbTable ? elements.dbTable.value : '');
      if (elements.schemaModalTitle) {
        elements.schemaModalTitle.textContent = 'Schema Inspector: ' + tableName;
      }
      if (elements.schemaModalSummary) {
        elements.schemaModalSummary.textContent = 'Connection: ' + (elements.dbConnection ? elements.dbConnection.value : '-');
      }
      renderSchemaKpis(schema);
      renderSchemaRows(schema);
      setSchemaModalStatus('Schema loaded.', true);
    })
    .catch((error) => {
      if (elements.schemaModalSummary) {
        elements.schemaModalSummary.textContent = 'Unable to load schema.';
      }
      renderSchemaKpis(null);
      renderSchemaRows(null);
      setSchemaModalStatus(error.message, false);
    });
}

function setLoading(active) {
  if (elements.dbReloadBtn) elements.dbReloadBtn.disabled = active;
  if (elements.dbPreviewBtn) elements.dbPreviewBtn.disabled = active;
  if (elements.dbApplyBtn) elements.dbApplyBtn.disabled = active;
  if (elements.compatCheckBtn) elements.compatCheckBtn.disabled = active;
  if (elements.compatSaveBtn) elements.compatSaveBtn.disabled = active;
  if (elements.migrateRunBtn) elements.migrateRunBtn.disabled = active;
  if (elements.migrateRollbackBtn) elements.migrateRollbackBtn.disabled = active;
  if (elements.migrateFreshBtn) elements.migrateFreshBtn.disabled = active;
  if (elements.migrateStatusBtn) elements.migrateStatusBtn.disabled = active;
  if (elements.seedRunBtn) elements.seedRunBtn.disabled = active;
  if (elements.appKeyGenerateBtn) elements.appKeyGenerateBtn.disabled = active;
  if (elements.routesRefreshBtn) elements.routesRefreshBtn.disabled = active;
  if (elements.cacheClearAllBtn) elements.cacheClearAllBtn.disabled = active;
  if (elements.cacheClearRouteBtn) elements.cacheClearRouteBtn.disabled = active;
  if (elements.cacheClearHttpBtn) elements.cacheClearHttpBtn.disabled = active;
  if (elements.envReloadBtn) elements.envReloadBtn.disabled = active;
  if (elements.envSaveBtn) elements.envSaveBtn.disabled = active;
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
    routing_type: selected.includes('controller') && elements.routingType ? elements.routingType.value : 'attribute',
    artifacts: selected,
  };
}

function syncRoutingTypeState() {
  if (!elements.artifactChecks || !elements.routingType) return;
  const controllerInput = elements.artifactChecks.querySelector('input[type="checkbox"][value="controller"]');
  const enabled = !!(controllerInput && controllerInput.checked);
  elements.routingType.disabled = !enabled;
  if (!enabled) {
    elements.routingType.value = 'attribute';
  }
}

// Artifact dependency definitions
const ARTIFACT_DEPENDENCIES = {
  model: { requires: [], disables: ['repository', 'service', 'test.unit.repository', 'test.unit.service', 'controller'] },
  repository: { requires: ['model'], disables: ['service', 'test.unit.service', 'test.unit.repository'] },
  service: { requires: ['model', 'repository'], disables: ['test.unit.service'] },
  controller: { requires: [], disables: ['test.integration.controller'] },
  'test.unit.repository': { requires: ['repository'], disables: [] },
  'test.unit.service': { requires: ['service', 'repository'], disables: [] },
  'test.integration.controller': { requires: ['controller'], disables: [] },
  'dto.request': { requires: [], disables: [] },
  'dto.response': { requires: [], disables: [] },
  factory: { requires: [], disables: [] },
  seed: { requires: [], disables: [] },
};

function getArtifactInput(value) {
  if (!elements.artifactChecks) return null;
  return elements.artifactChecks.querySelector('input[type="checkbox"][value="' + value + '"]');
}

function getArtifactLabel(value) {
  const input = getArtifactInput(value);
  return input ? input.closest('label') : null;
}

function enforceDependencies() {
  if (!elements.artifactChecks) return;
  
  const allInputs = elements.artifactChecks.querySelectorAll('input[type="checkbox"]');
  const unchecked = [];
  
  // First pass: uncheck dependent artifacts
  allInputs.forEach((input) => {
    if (!input.checked) return;
    
    const value = input.value;
    const deps = ARTIFACT_DEPENDENCIES[value];
    if (!deps) return;
    
    // Check if this artifact's requirements are met
    const requirementsMet = deps.requires.every((req) => {
      const reqInput = getArtifactInput(req);
      return reqInput && reqInput.checked;
    });
    
    if (!requirementsMet) {
      input.checked = false;
      unchecked.push(value);
    }
  });
  
  // Second pass: uncheck artifacts that depend on unchecked items
  let changes = true;
  while (changes) {
    changes = false;
    allInputs.forEach((input) => {
      if (!input.checked) return;
      
      const value = input.value;
      const deps = ARTIFACT_DEPENDENCIES[value];
      if (!deps) return;
      
      const requirementsMet = deps.requires.every((req) => {
        const reqInput = getArtifactInput(req);
        return reqInput && reqInput.checked;
      });
      
      if (!requirementsMet) {
        input.checked = false;
        unchecked.push(value);
        changes = true;
      }
    });
  }
  
  // Disable artifacts based on unchecked items
  allInputs.forEach((input) => {
    const value = input.value;
    const deps = ARTIFACT_DEPENDENCIES[value];
    if (!deps) return;
    
    const canCheck = deps.requires.every((req) => {
      const reqInput = getArtifactInput(req);
      return reqInput && reqInput.checked;
    });
    
    const label = getArtifactLabel(value);
    if (!canCheck && !input.checked) {
      input.disabled = true;
      if (label) label.classList.add('disabled');
    } else {
      input.disabled = false;
      if (label) label.classList.remove('disabled');
    }
  });
  
  // Show notification if artifacts were unchecked
  if (unchecked.length > 0) {
    const names = unchecked.map((v) => {
      const label = getArtifactLabel(v);
      if (label) {
        const span = label.querySelector('span:not(.hint)');
        return span ? span.textContent : v;
      }
      return v;
    }).join(', ');
    showNotification('Dependencies enforced: ' + names + ' unchecked');
  }
}

function showNotification(message) {
  // Create a simple toast/notification
  const existing = document.getElementById('dependency-notification');
  if (existing) existing.remove();
  
  const notification = document.createElement('div');
  notification.id = 'dependency-notification';
  notification.style.cssText = 'position: fixed; top: 1rem; right: 1rem; background: #2196F3; color: white; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.85rem; z-index: 50; max-width: 300px;';
  notification.textContent = message;
  document.body.appendChild(notification);
  
  setTimeout(() => {
    if (notification.parentNode) {
      notification.remove();
    }
  }, 6000);
}

function initArtifactDependencies() {
  if (!elements.artifactChecks) return;
  
  const checkboxes = elements.artifactChecks.querySelectorAll('input[type="checkbox"]');
  checkboxes.forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
      enforceDependencies();
      syncRoutingTypeState();
    });
  });
  
  // Initial enforcement
  enforceDependencies();
}

function validateArtifactDependencies(artifacts) {
  if (!Array.isArray(artifacts)) {
    return 'Invalid artifacts list';
  }

  // Check each artifact's requirements
  for (let i = 0; i < artifacts.length; i++) {
    const artifact = artifacts[i];
    const deps = ARTIFACT_DEPENDENCIES[artifact];
    
    if (!deps) {
      continue; // Unknown artifact, let server validate
    }
    
    // Check if all required artifacts are in the selection
    for (let j = 0; j < deps.requires.length; j++) {
      const required = deps.requires[j];
      if (!artifacts.includes(required)) {
        return 'Invalid selection: "' + artifact + '" requires "' + required + '" to be selected';
      }
    }
  }
  
  return null; // Valid
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
  elements.previewTabs.innerHTML = '';
  elements.diffPanel.textContent = '(no diff selected)';
  if (elements.previewMeta) {
    elements.previewMeta.textContent = 'No preview generated yet.';
  }

  if (!Array.isArray(files) || files.length === 0) {
    if (elements.previewMeta) {
      elements.previewMeta.textContent = 'No files produced.';
    }
    return;
  }

  const changedCount = files.reduce((count, file) => {
    return count + (file && file.diff && file.diff !== '' ? 1 : 0);
  }, 0);
  if (elements.previewMeta) {
    elements.previewMeta.textContent = 'Files: ' + files.length + ' | Changed: ' + changedCount;
  }

  let activeTab = null;
  files.forEach((file, index) => {
    const tab = document.createElement('button');
    tab.type = 'button';
    tab.className = 'preview-tab';
    tab.setAttribute('role', 'tab');
    const changed = file.diff && file.diff !== '';
    tab.innerHTML =
      '<span class="path">' + escapeHtml(file.path || '') + '</span>' +
      '<span class="meta">exists=' + escapeHtml(String(file.exists === true)) + ' changed=' + escapeHtml(String(changed)) + '</span>';
    tab.addEventListener('click', () => {
      elements.diffPanel.textContent = file.diff && file.diff !== '' ? file.diff : '(no diff)';
      Array.from(elements.previewTabs.children).forEach((item) => {
        item.classList.remove('active');
        item.setAttribute('aria-selected', 'false');
      });
      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      activeTab = tab;
    });

    tab.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
      event.preventDefault();
      const tabs = Array.from(elements.previewTabs.children);
      const currentIndex = tabs.indexOf(tab);
      const nextIndex = event.key === 'ArrowDown'
        ? Math.min(tabs.length - 1, currentIndex + 1)
        : Math.max(0, currentIndex - 1);
      const nextTab = tabs[nextIndex];
      if (nextTab) nextTab.focus();
    });

    elements.previewTabs.appendChild(tab);
    if (index === 0) {
      tab.click();
    }
  });

  if (!activeTab && elements.previewTabs.firstChild) {
    elements.previewTabs.firstChild.click();
  }
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
    setSchemaSnapshotMessage('Schema snapshot unavailable until a table is selected.');
    if (elements.schemaInspectBtn) elements.schemaInspectBtn.disabled = true;
    return Promise.resolve();
  }
  const query = connection ? ('?connection=' + encodeURIComponent(connection)) : '';
  return request('/schema/tables' + query).then((data) => {
    renderTableOptions(data.items || []);
    return loadTableSchema(false).catch(() => null);
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

  const validationError = validateArtifactDependencies(payload.artifacts);
  if (validationError) {
    setPreviewStatus(validationError, false);
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

  const validationError = validateArtifactDependencies(payload.artifacts);
  if (validationError) {
    setPreviewStatus(validationError, false);
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

function setAppKeyStatus(message, ok) {
  if (!elements.appKeyStatus) return;
  elements.appKeyStatus.textContent = message;
  elements.appKeyStatus.className = 'status ' + (ok ? 'ok' : 'error');
}

function setRoutesStatus(message, ok) {
  if (!elements.routesStatus) return;
  elements.routesStatus.textContent = message;
  elements.routesStatus.className = 'status ' + (ok ? 'ok' : 'error');
}

function setCacheStatus(message, ok) {
  if (!elements.cacheStatus) return;
  elements.cacheStatus.textContent = message;
  elements.cacheStatus.className = 'status ' + (ok ? 'ok' : 'error');
}

function setDbOpsStatus(message, ok) {
  if (!elements.dbOpsStatus) return;
  elements.dbOpsStatus.textContent = message;
  elements.dbOpsStatus.className = 'status ' + (ok ? 'ok' : 'error');
}

function setEnvStatus(message, ok) {
  if (!elements.envStatus) return;
  elements.envStatus.textContent = message;
  elements.envStatus.className = 'status ' + (ok ? 'ok' : 'error');
}

function setInputValue(el, value) {
  if (!el) return;
  el.value = typeof value === 'string' ? value : '';
}

function populateEnvironmentForm(data) {
  envSnapshot = data || {};
  const app = data && data.app ? data.app : {};
  const database = data && data.database ? data.database : {};
  const pgsql = database && database.pgsql ? database.pgsql : {};
  const view = data && data.view ? data.view : {};

  setInputValue(elements.envAppName, app.name || '');
  setInputValue(elements.envAppUrl, app.url || '');
  setInputValue(elements.envAppTimezone, app.timezone || '');
  setInputValue(elements.envDbDefault, database.default || '');
  setInputValue(elements.envPgHost, pgsql.host || '');
  setInputValue(elements.envPgPort, pgsql.port || '');
  setInputValue(elements.envPgDatabase, pgsql.database || '');
  setInputValue(elements.envPgUsername, pgsql.username || '');
  if (elements.envPgPassword) {
    elements.envPgPassword.value = '';
  }
  setInputValue(elements.envViewEngine, view.engine || 'php');

  const hasView = !!(view && view.enabled);
  if (elements.envViewSection) {
    elements.envViewSection.style.display = hasView ? '' : 'none';
  }
}

function readEnvironmentPayload() {
  const payload = {
    app: {
      name: elements.envAppName ? elements.envAppName.value : '',
      url: elements.envAppUrl ? elements.envAppUrl.value : '',
      timezone: elements.envAppTimezone ? elements.envAppTimezone.value : '',
    },
    database: {
      default: elements.envDbDefault ? elements.envDbDefault.value : '',
      pgsql: {
        host: elements.envPgHost ? elements.envPgHost.value : '',
        port: elements.envPgPort ? elements.envPgPort.value : '',
        database: elements.envPgDatabase ? elements.envPgDatabase.value : '',
        username: elements.envPgUsername ? elements.envPgUsername.value : '',
      },
    },
  };

  if (elements.envPgPassword && elements.envPgPassword.value !== '') {
    payload.database.pgsql.password = elements.envPgPassword.value;
  }

  const hasView = !!(envSnapshot && envSnapshot.view && envSnapshot.view.enabled);
  if (hasView) {
    payload.view = {
      engine: elements.envViewEngine ? elements.envViewEngine.value : 'php',
    };
  }

  return payload;
}

function loadEnvironment() {
  setLoading(true);
  request('/environment')
    .then((data) => {
      populateEnvironmentForm(data);
      const appType = data && data.app_type ? data.app_type : 'app';
      setEnvStatus('Loaded environment settings (' + appType + ').', true);
    })
    .catch((error) => {
      setEnvStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function saveEnvironment() {
  setLoading(true);
  request('/environment', { method: 'POST', body: readEnvironmentPayload() })
    .then((data) => {
      populateEnvironmentForm(data.current || envSnapshot || {});
      setEnvStatus('Environment updated in ' + (data.env_file || '.env') + '.', true);
    })
    .catch((error) => {
      setEnvStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function showTab(tabName) {
  const scaffoldActive = tabName === 'scaffold';
  const databaseActive = tabName === 'database';
  const cacheActive = tabName === 'cache';
  const routesActive = tabName === 'routes';
  const envActive = tabName === 'environment';
  const appKeyActive = tabName === 'security';

  if (elements.scaffoldTab) elements.scaffoldTab.classList.toggle('active', scaffoldActive);
  if (elements.databaseTab) elements.databaseTab.classList.toggle('active', databaseActive);
  if (elements.cacheTab) elements.cacheTab.classList.toggle('active', cacheActive);
  if (elements.routesTab) elements.routesTab.classList.toggle('active', routesActive);
  if (elements.environmentTab) elements.environmentTab.classList.toggle('active', envActive);
  if (elements.appKeyTab) elements.appKeyTab.classList.toggle('active', appKeyActive);
  if (elements.tabScaffoldBtn) elements.tabScaffoldBtn.classList.toggle('active', scaffoldActive);
  if (elements.tabDatabaseBtn) elements.tabDatabaseBtn.classList.toggle('active', databaseActive);
  if (elements.tabCacheBtn) elements.tabCacheBtn.classList.toggle('active', cacheActive);
  if (elements.tabRoutesBtn) elements.tabRoutesBtn.classList.toggle('active', routesActive);
  if (elements.tabEnvBtn) elements.tabEnvBtn.classList.toggle('active', envActive);
  if (elements.tabAppKeyBtn) elements.tabAppKeyBtn.classList.toggle('active', appKeyActive);
}

function renderRoutes(items) {
  if (!elements.routesRows) return;
  elements.routesRows.innerHTML = '';

  if (!Array.isArray(items) || items.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td colspan="4" class="muted">No routes found.</td>';
    elements.routesRows.appendChild(tr);
    return;
  }

  items.forEach((row) => {
    const middleware = Array.isArray(row.middleware) && row.middleware.length > 0
      ? row.middleware.join(', ')
      : '-';
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td><code>' + escapeHtml(row.method || '') + '</code></td>' +
      '<td><code>' + escapeHtml(row.uri || '') + '</code></td>' +
      '<td><code>' + escapeHtml(row.action || '') + '</code></td>' +
      '<td>' + escapeHtml(middleware) + '</td>';
    elements.routesRows.appendChild(tr);
  });
}

function loadRoutes() {
  setLoading(true);
  request('/routes')
    .then((data) => {
      const items = Array.isArray(data.items) ? data.items : [];
      renderRoutes(items);
      setRoutesStatus('Loaded ' + items.length + ' route(s).', true);
    })
    .catch((error) => {
      renderRoutes([]);
      setRoutesStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function selectedOpsConnection() {
  if (elements.opsConnection && elements.opsConnection.value) return elements.opsConnection.value;
  if (elements.dbConnection && elements.dbConnection.value) return elements.dbConnection.value;
  return '';
}

function initDatabaseOpsPanel() {
  request('/schema/connections')
    .then((data) => {
      if (!elements.opsConnection) return;
      elements.opsConnection.innerHTML = '';
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Select a connection';
      placeholder.selected = true;
      elements.opsConnection.appendChild(placeholder);
      (Array.isArray(data.items) ? data.items : []).forEach((row) => {
        const option = document.createElement('option');
        option.value = row.name;
        const suffix = row.default ? ' (default)' : '';
        option.textContent = row.name + ' [' + (row.driver || 'unknown') + ']' + suffix;
        elements.opsConnection.appendChild(option);
      });
    })
    .catch((error) => {
      setDbOpsStatus('Connection init failed: ' + error.message, false);
    });
}

function runMigration(target, action) {
  const connection = selectedOpsConnection();
  if (!connection) {
    setDbOpsStatus('Select a database connection first.', false);
    return;
  }

  const safeTarget = target && String(target).trim() !== '' ? String(target).trim() : 'all';
  const actionPath = action === 'rollback' ? '/migrations/rollback' : '/migrations/run';
  const verb = action === 'rollback' ? 'rollback' : 'migrate';
  const warning = 'This operation changes database schema/data. Continue?';
  if (!window.confirm('[Warning] ' + warning + '\\nAction: ' + verb + '\\nTarget: ' + safeTarget + '\\nConnection: ' + connection)) {
    return;
  }

  setLoading(true);
  request(actionPath, { method: 'POST', body: { connection: connection, target: safeTarget } })
    .then((data) => {
      setDbOpsStatus('Database operation completed: ' + verb + ' ' + safeTarget + '.', true);
      if (elements.dbOpsOutput) {
        elements.dbOpsOutput.textContent = JSON.stringify(data, null, 2);
      }
    })
    .catch((error) => {
      setDbOpsStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function runMigrationFresh() {
  const connection = selectedOpsConnection();
  if (!connection) {
    setDbOpsStatus('Select a database connection first.', false);
    return;
  }
  if (!window.confirm('[Warning] migrate:fresh will rollback all known migrations and re-run them.\\nConnection: ' + connection + '\\nContinue?')) {
    return;
  }

  setLoading(true);
  request('/migrations/fresh', { method: 'POST', body: { connection: connection } })
    .then((data) => {
      setDbOpsStatus('Fresh migration completed.', true);
      if (elements.dbOpsOutput) {
        elements.dbOpsOutput.textContent = JSON.stringify(data, null, 2);
      }
    })
    .catch((error) => {
      setDbOpsStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function runMigrationStatus() {
  const connection = selectedOpsConnection();
  if (!connection) {
    setDbOpsStatus('Select a database connection first.', false);
    return;
  }

  setLoading(true);
  request('/migrations/status?connection=' + encodeURIComponent(connection))
    .then((data) => {
      setDbOpsStatus('Migration status loaded.', true);
      if (elements.dbOpsOutput) {
        elements.dbOpsOutput.textContent = JSON.stringify(data, null, 2);
      }
    })
    .catch((error) => {
      setDbOpsStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function runSeedTarget(target) {
  const connection = selectedOpsConnection();
  if (!connection) {
    setDbOpsStatus('Select a database connection first.', false);
    return;
  }

  const safeTarget = target && String(target).trim() !== '' ? String(target).trim() : 'all';
  if (!window.confirm('[Warning] Seeding inserts records and can duplicate data depending on script design.\\nTarget: ' + safeTarget + '\\nConnection: ' + connection + '\\nContinue?')) {
    return;
  }

  setLoading(true);
  request('/seed/run', { method: 'POST', body: { connection: connection, target: safeTarget } })
    .then((data) => {
      setDbOpsStatus('Seeding completed for target ' + safeTarget + '.', true);
      if (elements.dbOpsOutput) {
        elements.dbOpsOutput.textContent = JSON.stringify(data, null, 2);
      }
    })
    .catch((error) => {
      setDbOpsStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function generateAppKey() {
  const force = !!(elements.appKeyForce && elements.appKeyForce.checked);
  setLoading(true);
  request('/app-key/generate', { method: 'POST', body: { force: force, show: true } })
    .then((data) => {
      const createdEnv = data.created_env ? 'yes' : 'no';
      const updated = data.updated ? 'yes' : 'no';
      setAppKeyStatus('APP_KEY operation complete. updated=' + updated + ' created_env=' + createdEnv, true);
      if (elements.appKeyOutput) {
        elements.appKeyOutput.textContent = 'env_file: ' + (data.env_file || '') + '\\nAPP_KEY=' + (data.key || '');
      }
    })
    .catch((error) => {
      setAppKeyStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}

function clearCache(scope) {
  const warningMap = {
    all: 'Clears all local cache directories and route bindings.',
    route: 'Clears route cache and static route bindings.',
    http: 'Clears HTTP response cache.'
  };
  const warning = warningMap[scope] || 'Clears cache data.';
  if (!window.confirm('[Warning] ' + warning + '\\nScope: ' + scope + '\\nContinue?')) {
    return;
  }

  setLoading(true);
  request('/cache/clear', { method: 'POST', body: { scope: scope } })
    .then((data) => {
      const deletedFiles = Number(data.deleted_files || 0);
      const deletedDirs = Number(data.deleted_dirs || 0);
      const missingPaths = Number(data.missing_paths || 0);
      setCacheStatus(
        'Cache clear complete. scope=' + (data.scope || scope) + ' deleted_files=' + deletedFiles + ' deleted_dirs=' + deletedDirs + ' missing_paths=' + missingPaths,
        true
      );
      if (elements.cacheOutput) {
        elements.cacheOutput.textContent = JSON.stringify(data, null, 2);
      }
    })
    .catch((error) => {
      setCacheStatus(error.message, false);
    })
    .finally(() => {
      setLoading(false);
    });
}
if (elements.dbConnection) elements.dbConnection.addEventListener('change', () => {
  loadTables().catch(() => null);
});
if (elements.dbTable) elements.dbTable.addEventListener('change', () => {
  loadTableSchema(false).catch(() => null);
});
if (elements.dbReloadBtn) elements.dbReloadBtn.addEventListener('click', loadTables);
if (elements.dbPreviewBtn) elements.dbPreviewBtn.addEventListener('click', scaffoldPreview);
if (elements.dbApplyBtn) elements.dbApplyBtn.addEventListener('click', scaffoldApply);
if (elements.schemaInspectBtn) elements.schemaInspectBtn.addEventListener('click', openSchemaInspector);
if (elements.schemaModalCloseBtn) elements.schemaModalCloseBtn.addEventListener('click', () => setSchemaModalOpen(false));
if (elements.schemaModal) elements.schemaModal.addEventListener('click', (event) => {
  if (event.target === elements.schemaModal) {
    setSchemaModalOpen(false);
  }
});
window.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    setSchemaModalOpen(false);
  }
});
if (elements.compatCheckBtn) elements.compatCheckBtn.addEventListener('click', compatibilityCheck);
if (elements.compatSaveBtn) elements.compatSaveBtn.addEventListener('click', compatibilitySaveBaseline);
if (elements.migrateRunBtn) elements.migrateRunBtn.addEventListener('click', () => runMigration(elements.migrationTarget ? elements.migrationTarget.value : 'all', 'run'));
if (elements.migrateRollbackBtn) elements.migrateRollbackBtn.addEventListener('click', () => runMigration(elements.migrationTarget ? elements.migrationTarget.value : 'all', 'rollback'));
if (elements.migrateFreshBtn) elements.migrateFreshBtn.addEventListener('click', runMigrationFresh);
if (elements.migrateStatusBtn) elements.migrateStatusBtn.addEventListener('click', runMigrationStatus);
if (elements.seedRunBtn) elements.seedRunBtn.addEventListener('click', () => runSeedTarget(elements.seedTarget ? elements.seedTarget.value : 'all'));
if (elements.appKeyGenerateBtn) elements.appKeyGenerateBtn.addEventListener('click', generateAppKey);
if (elements.routesRefreshBtn) elements.routesRefreshBtn.addEventListener('click', loadRoutes);
if (elements.cacheClearAllBtn) elements.cacheClearAllBtn.addEventListener('click', () => clearCache('all'));
if (elements.cacheClearRouteBtn) elements.cacheClearRouteBtn.addEventListener('click', () => clearCache('route'));
if (elements.cacheClearHttpBtn) elements.cacheClearHttpBtn.addEventListener('click', () => clearCache('http'));
if (elements.tabScaffoldBtn) elements.tabScaffoldBtn.addEventListener('click', () => showTab('scaffold'));
if (elements.tabDatabaseBtn) elements.tabDatabaseBtn.addEventListener('click', () => {
  showTab('database');
  runMigrationStatus();
});
if (elements.tabCacheBtn) elements.tabCacheBtn.addEventListener('click', () => showTab('cache'));
if (elements.tabRoutesBtn) elements.tabRoutesBtn.addEventListener('click', () => {
  showTab('routes');
  loadRoutes();
});
if (elements.tabEnvBtn) elements.tabEnvBtn.addEventListener('click', () => {
  showTab('environment');
  loadEnvironment();
});
if (elements.tabAppKeyBtn) elements.tabAppKeyBtn.addEventListener('click', () => showTab('security'));
if (elements.envReloadBtn) elements.envReloadBtn.addEventListener('click', loadEnvironment);
if (elements.envSaveBtn) elements.envSaveBtn.addEventListener('click', saveEnvironment);

initScaffoldPanel();
initArtifactDependencies();
initDatabaseOpsPanel();
syncRoutingTypeState();
showTab('scaffold');
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

      if ($apiPath === '/environment') {
         if ($request->getMethod() === 'GET') {
            return $this->apiEnvironmentResponse($ctx);
         }
         if ($request->getMethod() === 'POST') {
            return $this->apiSaveEnvironmentResponse($ctx, $request);
         }
         return $this->methodNotAllowed($ctx, ['GET', 'POST']);
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

      if ($apiPath === '/migrations/status') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         return $this->apiMigrationsStatusResponse($ctx, $request);
      }

      if ($apiPath === '/routes') {
         if ($request->getMethod() !== 'GET') {
            return $this->methodNotAllowed($ctx, ['GET']);
         }
         return $this->apiRoutesResponse($ctx);
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

      if ($apiPath === '/migrations/run') {
         if ($request->getMethod() !== 'POST') {
            return $this->methodNotAllowed($ctx, ['POST']);
         }
         return $this->apiMigrationsRunResponse($ctx, $request);
      }

      if ($apiPath === '/migrations/rollback') {
         if ($request->getMethod() !== 'POST') {
            return $this->methodNotAllowed($ctx, ['POST']);
         }
         return $this->apiMigrationsRollbackResponse($ctx, $request);
      }

      if ($apiPath === '/migrations/fresh') {
         if ($request->getMethod() !== 'POST') {
            return $this->methodNotAllowed($ctx, ['POST']);
         }
         return $this->apiMigrationsFreshResponse($ctx, $request);
      }

      if ($apiPath === '/seed/run') {
         if ($request->getMethod() !== 'POST') {
            return $this->methodNotAllowed($ctx, ['POST']);
         }
         return $this->apiSeedRunResponse($ctx, $request);
      }

      if ($apiPath === '/cache/clear') {
         if ($request->getMethod() !== 'POST') {
            return $this->methodNotAllowed($ctx, ['POST']);
         }
         return $this->apiCacheClearResponse($ctx, $request);
      }

      if ($apiPath === '/app-key/generate') {
         if ($request->getMethod() !== 'POST') {
            return $this->methodNotAllowed($ctx, ['POST']);
         }
         return $this->apiGenerateAppKeyResponse($ctx, $request);
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
               options: ['routing_type' => $args['routing_type']],
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
         'routing_type' => $args['routing_type'],
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
               options: ['routing_type' => $args['routing_type']],
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
         'routing_type' => $args['routing_type'],
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
               options: ['routing_type' => $args['routing_type']],
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
         'routing_type' => $args['routing_type'],
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
               options: ['routing_type' => $args['routing_type']],
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
         'routing_type' => $args['routing_type'],
         'written' => $result->written(),
         'skipped' => $result->skipped(),
      ]);
   }

   /**
    * @param array<string, mixed> $input
    * @return array{generator:string,name:string,module:string,routing_type:string,overwrite:bool}|Response
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
         'routing_type' => $this->routingTypeValue($input),
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
         $names = array_map(static fn(array $item): string => (string) ($item['name'] ?? ''), $items);
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

   private function apiRoutesResponse(RequestContext $ctx): Response
   {
      try {
         $definitions = null;
         if (is_callable($this->routeProvider)) {
            $provided = call_user_func($this->routeProvider);
            if (is_array($provided)) {
               $definitions = $provided;
            }
         }

         $items = $this->routeInspector()->inspect($this->projectRoot, $definitions);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'routes_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'items' => $items,
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

   private function apiMigrationsStatusResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $connectionName = $this->stringValue($input, 'connection') ?? $this->defaultConnectionName();

      try {
         [$connection, ] = $this->databaseConnectionAndDriver($connectionName);
         $status = $this->migrationsStatus($connection);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'migrations_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         ...$status,
      ]);
   }

   private function apiMigrationsRunResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $connectionName = $this->stringValue($input, 'connection') ?? $this->defaultConnectionName();
      $target = strtolower($this->stringValue($input, 'target') ?? 'all');

      try {
         [$connection, ] = $this->databaseConnectionAndDriver($connectionName);
         $result = $this->migrationsRun($connection, $target);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'migrations_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         'target' => $target,
         ...$result,
      ]);
   }

   private function apiMigrationsRollbackResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $connectionName = $this->stringValue($input, 'connection') ?? $this->defaultConnectionName();
      $target = strtolower($this->stringValue($input, 'target') ?? 'all');

      try {
         [$connection, ] = $this->databaseConnectionAndDriver($connectionName);
         $result = $this->migrationsRollback($connection, $target);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'migrations_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         'target' => $target,
         ...$result,
      ]);
   }

   private function apiMigrationsFreshResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $connectionName = $this->stringValue($input, 'connection') ?? $this->defaultConnectionName();

      try {
         [$connection, ] = $this->databaseConnectionAndDriver($connectionName);
         $result = $this->migrationsFresh($connection);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'migrations_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         ...$result,
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
      $routingType = $this->effectiveRoutingType($input, $artifacts);

      try {
         [$connection, $driver] = $this->databaseConnectionAndDriver($connectionName);
         $schema = $this->describeTable($connection, $driver, $tableName);
         $files = $this->buildScaffoldPreview($tableName, $schema, $artifacts, $routingType);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'tooling_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         'table' => $tableName,
         'routing_type' => $routingType,
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
      $routingType = $this->effectiveRoutingType($input, $artifacts);

      try {
         [$connection, $driver] = $this->databaseConnectionAndDriver($connectionName);
         $schema = $this->describeTable($connection, $driver, $tableName);
         $files = $this->buildScaffoldPreview($tableName, $schema, $artifacts, $routingType);
         [$written, $skipped] = $this->writeScaffoldFiles($files);
         $this->auditScaffoldApply($ctx, $request, $connectionName, $tableName, $artifacts, $routingType, $written, $skipped, null);
      } catch (Throwable $exception) {
         $this->auditScaffoldApply($ctx, $request, $connectionName, $tableName ?? '', $artifacts ?? [], $routingType ?? 'attribute', [], [], $exception->getMessage());
         return $this->apiError($ctx, 422, 'tooling_error', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         'table' => $tableName,
         'routing_type' => $routingType,
         'artifacts' => $artifacts,
         'written' => $written,
         'skipped' => $skipped,
      ]);
   }

   private function apiSeedRunResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $target = strtolower($this->stringValue($input, 'target') ?? 'all');
      $connectionName = $this->stringValue($input, 'connection') ?? $this->defaultConnectionName();
      if ($target === '') {
         $target = 'all';
      }

      try {
         [$connection, $driver] = $this->databaseConnectionAndDriver($connectionName);
         $result = $this->runSeeds($connection, $driver, $target);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'seed_failed', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'connection' => $connectionName,
         'target' => $target,
         ...$result,
      ]);
   }

   private function apiCacheClearResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $scope = strtolower($this->stringValue($input, 'scope') ?? 'all');
      if (!in_array($scope, ['all', 'route', 'http', 'view'], true)) {
         return $this->apiError($ctx, 400, 'invalid_input', 'scope must be one of: all, route, http, view.');
      }

      try {
         $result = $this->clearCacheScope($scope);
      } catch (Throwable $exception) {
         return $this->apiError($ctx, 422, 'cache_clear_failed', $exception->getMessage());
      }

      return $this->apiOk($ctx, $result);
   }

   private function apiEnvironmentResponse(RequestContext $ctx): Response
   {
      try {
         $payload = $this->environmentPayload();
      } catch (ToolingException $exception) {
         return $this->apiError($ctx, 422, 'environment_read_failed', $exception->getMessage());
      }

      return $this->apiOk($ctx, $payload);
   }

   private function apiSaveEnvironmentResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $isMvc = $this->environmentHasViewEngine();
      $allowedKeys = $this->envAllowedKeys($isMvc);
      $updates = [];

      $app = $input['app'] ?? null;
      if (is_array($app)) {
         foreach (['name' => 'APP_NAME', 'url' => 'APP_URL', 'timezone' => 'APP_TIMEZONE'] as $source => $target) {
            $value = $this->environmentInputString($app, $source);
            if ($value !== null) {
               $updates[$target] = $value;
            }
         }
      }

      $database = $input['database'] ?? null;
      if (is_array($database)) {
         $default = $this->environmentInputString($database, 'default');
         if ($default !== null) {
            $updates['DB_DEFAULT'] = $default;
         }

         $pgsql = $database['pgsql'] ?? null;
         if (is_array($pgsql)) {
            foreach ([
               'host' => 'PGSQL_HOST',
               'port' => 'PGSQL_PORT',
               'database' => 'PGSQL_DATABASE',
               'username' => 'PGSQL_USERNAME',
               'password' => 'PGSQL_PASSWORD',
            ] as $source => $target) {
               $value = $this->environmentInputString($pgsql, $source);
               if ($value !== null) {
                  $updates[$target] = $value;
               }
            }
         }
      }

      if ($isMvc) {
         $view = $input['view'] ?? null;
         if (is_array($view)) {
            $engine = $this->environmentInputString($view, 'engine');
            if ($engine !== null) {
               $engine = strtolower($engine);
               if (!in_array($engine, ['php', 'twig', 'plates', 'latte'], true)) {
                  return $this->apiError($ctx, 400, 'invalid_input', 'Unsupported VIEW_ENGINE value.');
               }
               $updates['VIEW_ENGINE'] = $engine;
            }
         }
      }

      if ($updates === []) {
         return $this->apiError($ctx, 400, 'invalid_input', 'No environment values were provided.');
      }

      foreach (array_keys($updates) as $key) {
         if (!in_array($key, $allowedKeys, true)) {
            return $this->apiError($ctx, 400, 'invalid_input', sprintf('Key "%s" is not writable.', $key));
         }
      }

      try {
         $envMeta = $this->readEnvironmentMap();
         $current = $envMeta['map'];
         foreach ($updates as $key => $value) {
            $current[$key] = $value;
         }
         $savedPath = $this->persistEnvironmentMap($envMeta, $current, $allowedKeys);
      } catch (ToolingException $exception) {
         return $this->apiError($ctx, 422, 'environment_write_failed', $exception->getMessage());
      }

      try {
         $currentPayload = $this->environmentPayload();
      } catch (ToolingException $exception) {
         return $this->apiError($ctx, 422, 'environment_read_failed', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'env_file' => $this->relativeToProject($savedPath),
         'current' => $currentPayload,
      ]);
   }

   private function apiGenerateAppKeyResponse(RequestContext $ctx, Request $request): Response
   {
      $input = $this->requestInput($request);
      $force = $this->boolValue($input, 'force');
      $show = $this->boolValue($input, 'show');
      $envFile = $this->stringValue($input, 'env_file') ?? '.env';

      try {
         $key = $this->appKeyManager()->generate();
         $result = $this->appKeyManager()->write($this->projectRoot, $key, $envFile, $force);
      } catch (ToolingException $exception) {
         return $this->apiError($ctx, 422, 'app_key_generate_failed', $exception->getMessage());
      }

      return $this->apiOk($ctx, [
         'env_file' => $result['env_file'],
         'created_env' => $result['created_env'],
         'updated' => $result['updated'],
         'existing_key' => $result['existing_key'],
         'key' => $show ? $key : null,
      ]);
   }

   /**
    * @return array<string, mixed>
    */
   private function environmentPayload(): array
   {
      $meta = $this->readEnvironmentMap();
      $map = $meta['map'];
      $isMvc = $this->environmentHasViewEngine();
      $appType = $isMvc ? 'mvc' : 'api';

      $appName = $this->environmentMapValue($map, 'APP_NAME', (string) $this->config()->get('app.name', ''));
      $appUrl = $this->environmentMapValue($map, 'APP_URL', (string) $this->config()->get('app.url', ''));
      $appTimezone = $this->environmentMapValue($map, 'APP_TIMEZONE', (string) $this->config()->get('app.timezone', 'UTC'));
      $dbDefault = $this->environmentMapValue($map, 'DB_DEFAULT', (string) $this->config()->get('database.default', ''));

      $defaultPgPort = (string) $this->config()->get('database.connections.pgsql.port', '5432');
      if ($defaultPgPort === '') {
         $defaultPgPort = '5432';
      }

      return [
         'app_type' => $appType,
         'app' => [
            'name' => $appName,
            'url' => $appUrl,
            'timezone' => $appTimezone,
         ],
         'database' => [
            'default' => $dbDefault,
            'pgsql' => [
               'host' => $this->environmentMapValue($map, 'PGSQL_HOST', (string) $this->config()->get('database.connections.pgsql.host', '127.0.0.1')),
               'port' => $this->environmentMapValue($map, 'PGSQL_PORT', $defaultPgPort),
               'database' => $this->environmentMapValue($map, 'PGSQL_DATABASE', (string) $this->config()->get('database.connections.pgsql.database', 'app')),
               'username' => $this->environmentMapValue($map, 'PGSQL_USERNAME', (string) $this->config()->get('database.connections.pgsql.username', 'postgres')),
               'password' => '',
            ],
         ],
         'view' => [
            'enabled' => $isMvc,
            'engine' => $isMvc
               ? $this->environmentMapValue($map, 'VIEW_ENGINE', (string) $this->config()->get('app.view.engine', 'php'))
               : '',
         ],
      ];
   }

   private function environmentHasViewEngine(): bool
   {
      $viewsPath = rtrim($this->projectRoot, '/\\') . '/app/Views';
      if (is_dir($viewsPath)) {
         return true;
      }

      $value = $this->config()->get('app.view.engine');
      return is_string($value) && trim($value) !== '';
   }

   /**
    * @return array{target:string,map:array<string,string>,lines:array<int,string>,line_ending:string}
    */
   private function readEnvironmentMap(): array
   {
      $root = rtrim($this->projectRoot, '/\\');
      $target = $root . '/.env';
      $template = $target . '.example';

      $contents = '';
      if (is_file($target)) {
         $read = @file_get_contents($target);
         if (!is_string($read)) {
            throw new ToolingException(sprintf('Unable to read env file "%s".', $target));
         }
         $contents = $read;
      } elseif (is_file($template)) {
         $read = @file_get_contents($template);
         if (!is_string($read)) {
            throw new ToolingException(sprintf('Unable to read env template "%s".', $template));
         }
         $contents = $read;
      }

      $lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";
      $normalized = str_replace(["\r\n", "\r"], "\n", $contents);
      $lines = $normalized === '' ? [] : explode("\n", $normalized);
      if ($lines !== [] && $lines[count($lines) - 1] === '') {
         array_pop($lines);
      }

      return [
         'target' => $target,
         'map' => $this->parseEnvironmentLines($lines),
         'lines' => $lines,
         'line_ending' => $lineEnding,
      ];
   }

   /**
    * @param array<int, string> $lines
    * @return array<string, string>
    */
   private function parseEnvironmentLines(array $lines): array
   {
      $map = [];
      foreach ($lines as $line) {
         if (!preg_match('/^\s*([A-Z0-9_]+)\s*=(.*)$/', $line, $matches)) {
            continue;
         }

         $key = trim((string) ($matches[1] ?? ''));
         if ($key === '') {
            continue;
         }

         $map[$key] = $this->decodeEnvironmentValue((string) ($matches[2] ?? ''));
      }

      return $map;
   }

   private function decodeEnvironmentValue(string $value): string
   {
      $trimmed = trim($value);
      if ($trimmed === '') {
         return '';
      }

      if (strlen($trimmed) >= 2) {
         $first = $trimmed[0];
         $last = $trimmed[strlen($trimmed) - 1];
         if ($first === '"' && $last === '"') {
            $inner = substr($trimmed, 1, -1);
            return str_replace(['\\"', '\\\\'], ['"', '\\'], $inner);
         }
         if ($first === "'" && $last === "'") {
            $inner = substr($trimmed, 1, -1);
            return str_replace(["\\'", '\\\\'], ["'", '\\'], $inner);
         }
      }

      return $trimmed;
   }

   /**
    * @param array{target:string,map:array<string,string>,lines:array<int,string>,line_ending:string} $meta
    * @param array<string, string> $map
    * @param array<int, string> $allowedKeys
    */
   private function persistEnvironmentMap(array $meta, array $map, array $allowedKeys): string
   {
      $target = $meta['target'];
      $root = rtrim($this->projectRoot, '/\\');
      $allowedRoot = $root . '/';
      if (!str_starts_with($target, $allowedRoot)) {
         throw new ToolingException('Refusing to write environment file outside project root.');
      }

      $lines = $meta['lines'];
      foreach ($allowedKeys as $key) {
         if (!array_key_exists($key, $map)) {
            continue;
         }

         $updated = false;
         foreach ($lines as $index => $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line) === 1) {
               $lines[$index] = $key . '=' . $this->envValueForWrite($map[$key]);
               $updated = true;
               break;
            }
         }

         if (!$updated) {
            $lines[] = $key . '=' . $this->envValueForWrite($map[$key]);
         }
      }

      $lineEnding = $meta['line_ending'] === "\r\n" ? "\r\n" : "\n";
      $contents = implode($lineEnding, $lines);
      if ($contents !== '') {
         $contents .= $lineEnding;
      }

      $dir = dirname($target);
      if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
         throw new ToolingException(sprintf('Unable to create directory "%s".', $dir));
      }

      if (@file_put_contents($target, $contents) === false) {
         throw new ToolingException(sprintf('Unable to write env file "%s".', $target));
      }

      return $target;
   }

   /**
    * @return array<int, string>
    */
   private function envAllowedKeys(bool $isMvc): array
   {
      $keys = [
         'APP_NAME',
         'APP_URL',
         'APP_TIMEZONE',
         'DB_DEFAULT',
         'PGSQL_HOST',
         'PGSQL_PORT',
         'PGSQL_DATABASE',
         'PGSQL_USERNAME',
         'PGSQL_PASSWORD',
      ];

      if ($isMvc) {
         $keys[] = 'VIEW_ENGINE';
      }

      return $keys;
   }

   private function envValueForWrite(string $value): string
   {
      if ($value === '') {
         return '';
      }

      if (preg_match('/[\s#"\']/', $value) !== 1) {
         return $value;
      }

      return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
   }

   /**
    * @param array<string, mixed> $source
    */
   private function environmentInputString(array $source, string $key): ?string
   {
      if (!array_key_exists($key, $source)) {
         return null;
      }

      $value = $source[$key];
      if (!is_scalar($value) && $value !== null) {
         return null;
      }

      return trim((string) $value);
   }

   /**
    * @param array<string, string> $map
    */
   private function environmentMapValue(array $map, string $key, string $fallback = ''): string
   {
      if (!array_key_exists($key, $map)) {
         return $fallback;
      }

      return (string) $map[$key];
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

   /**
    * @param array<string, mixed> $input
    */
   private function routingTypeValue(array $input): string
   {
      $routingType = strtolower((string) ($this->stringValue($input, 'routing_type') ?? 'attribute'));
      return $routingType === 'php' ? 'php' : 'attribute';
   }

   /**
    * @param array<string, mixed> $input
    * @param array<int, string> $artifacts
    */
   private function effectiveRoutingType(array $input, array $artifacts): string
   {
      if (!in_array('controller', $artifacts, true)) {
         return 'attribute';
      }

      return $this->routingTypeValue($input);
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

   private function migrationsDirectory(): string
   {
      return rtrim($this->projectRoot, '/\\') . '/database/migrations';
   }

   /**
    * @return array<int, array{file:string,migration:MigrationInterface}>
    */
   private function discoverMigrationEntries(): array
   {
      $dir = $this->migrationsDirectory();
      if (!is_dir($dir)) {
         throw new ToolingException(sprintf('Migrations directory not found: %s', $this->relativeToProject($dir)));
      }

      $entries = scandir($dir);
      if (!is_array($entries)) {
         throw new ToolingException(sprintf('Unable to read migrations directory: %s', $this->relativeToProject($dir)));
      }

      $files = [];
      foreach ($entries as $entry) {
         if ($entry === '.' || $entry === '..') {
            continue;
         }
         $path = $dir . '/' . $entry;
         if (is_file($path) && str_ends_with(strtolower($entry), '.php')) {
            $files[] = $path;
         }
      }

      sort($files, SORT_STRING);
      $rows = [];
      foreach ($files as $path) {
         $className = $this->migrationClassFromFile($path);
         require_once $path;
         if (!class_exists($className)) {
            throw new ToolingException(sprintf('Migration class "%s" was not found in %s', $className, $this->relativeToProject($path)));
         }
         if (!is_subclass_of($className, MigrationInterface::class)) {
            throw new ToolingException(sprintf('Migration class "%s" must implement %s', $className, MigrationInterface::class));
         }

         /** @var MigrationInterface $migration */
         $migration = new $className();
         $rows[] = [
            'file' => $path,
            'migration' => $migration,
         ];
      }

      return $rows;
   }

   private function migrationClassFromFile(string $path): string
   {
      $source = (string) file_get_contents($path);
      $namespace = '';
      $class = '';

      if (preg_match('/^\s*namespace\s+([^;]+);/m', $source, $match) === 1) {
         $namespace = trim((string) ($match[1] ?? ''));
      }
      if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $source, $match) === 1) {
         $class = trim((string) ($match[1] ?? ''));
      }

      if ($class === '') {
         throw new ToolingException(sprintf('Migration class declaration was not found in %s', $this->relativeToProject($path)));
      }

      return $namespace === '' ? $class : $namespace . '\\' . $class;
   }

   /**
    * @param array<int, array{file:string,migration:MigrationInterface}> $entries
    * @return array<int, array{file:string,migration:MigrationInterface}>
    */
   private function selectMigrationEntries(array $entries, string $target): array
   {
      $normalized = strtolower(trim($target));
      if ($normalized === '' || $normalized === 'all') {
         return $entries;
      }

      foreach ($entries as $entry) {
         $basename = strtolower(basename($entry['file']));
         $withoutExt = strtolower(pathinfo($entry['file'], PATHINFO_FILENAME));
         if ($normalized === $basename || $normalized === $withoutExt) {
            return [$entry];
         }
      }

      throw new ToolingException(sprintf(
         'Migration file "%s" was not found in %s.',
         $target,
         $this->relativeToProject($this->migrationsDirectory())
      ));
   }

   /**
    * @return array<string, mixed>
    */
   private function migrationsStatus(ConnectionInterface $connection): array
   {
      $repo = new DatabaseMigrationRepository($connection);
      $repo->ensureStorage();
      $entries = $this->discoverMigrationEntries();
      $applied = array_flip($repo->appliedVersions());

      $items = [];
      $knownVersions = [];
      foreach ($entries as $entry) {
         $version = $entry['migration']->version();
         $knownVersions[] = $version;
         $items[] = [
            'version' => $version,
            'description' => $entry['migration']->description(),
            'file' => $this->relativeToProject($entry['file']),
            'applied' => isset($applied[$version]),
            'pending' => !isset($applied[$version]),
         ];
      }

      $orphans = [];
      foreach (array_keys($applied) as $version) {
         if (!in_array($version, $knownVersions, true)) {
            $orphans[] = $version;
         }
      }

      return [
         'directory' => $this->relativeToProject($this->migrationsDirectory()),
         'items' => $items,
         'pending' => array_values(array_filter($items, static fn (array $row): bool => (bool) ($row['pending'] ?? false))),
         'applied_count' => count($applied),
         'pending_count' => count(array_filter($items, static fn (array $row): bool => (bool) ($row['pending'] ?? false))),
         'orphan_applied_versions' => $orphans,
      ];
   }

   /**
    * @return array<string, mixed>
    */
   private function migrationsRun(ConnectionInterface $connection, string $target): array
   {
      $repo = new DatabaseMigrationRepository($connection);
      $entries = $this->selectMigrationEntries($this->discoverMigrationEntries(), $target);
      $migrations = array_map(static fn (array $entry): MigrationInterface => $entry['migration'], $entries);

      $runner = new MigrationRunner($connection, $repo);
      $result = $runner->migrate($migrations);
      return [
         'applied' => $result->applied(),
         'count' => count($result->applied()),
      ];
   }

   /**
    * @return array<string, mixed>
    */
   private function migrationsRollback(ConnectionInterface $connection, string $target): array
   {
      $repo = new DatabaseMigrationRepository($connection);
      $repo->ensureStorage();
      $entries = $this->discoverMigrationEntries();
      $selected = $this->selectMigrationEntries($entries, $target);

      if ($target === 'all' || $target === '') {
         $all = array_map(static fn (array $entry): MigrationInterface => $entry['migration'], $entries);
         $applied = $repo->appliedVersions();
         $knownVersions = array_map(static fn (array $entry): string => $entry['migration']->version(), $entries);
         $orphans = array_values(array_filter($applied, static fn (string $version): bool => !in_array($version, $knownVersions, true)));
         if ($orphans !== []) {
            throw new ToolingException('Cannot rollback all because applied versions are missing migration files: ' . implode(', ', $orphans));
         }

         $runner = new MigrationRunner($connection, $repo);
         $result = $runner->rollback($all, count($applied));
         return [
            'rolled_back' => $result->rolledBack(),
            'count' => count($result->rolledBack()),
         ];
      }

      $entry = $selected[0];
      $migration = $entry['migration'];
      $version = $migration->version();
      $applied = $repo->appliedVersions();
      if (!in_array($version, $applied, true)) {
         return [
            'rolled_back' => [],
            'count' => 0,
         ];
      }

      $connection->transactional(function (ConnectionInterface $tx) use ($migration, $repo, $version): void {
         $migration->down($tx);
         $repo->markRolledBack($version);
      });

      return [
         'rolled_back' => [$version],
         'count' => 1,
      ];
   }

   /**
    * @return array<string, mixed>
    */
   private function migrationsFresh(ConnectionInterface $connection): array
   {
      $rollback = $this->migrationsRollback($connection, 'all');
      $migrate = $this->migrationsRun($connection, 'all');

      return [
         'rolled_back' => $rollback['rolled_back'] ?? [],
         'rolled_back_count' => (int) ($rollback['count'] ?? 0),
         'applied' => $migrate['applied'] ?? [],
         'applied_count' => (int) ($migrate['count'] ?? 0),
      ];
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
            static fn(array $row): string => (string) ($row['name'] ?? ''),
            $connection->fetchAll(
               "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
            )
         ),
         DatabaseDriver::MySQL, DatabaseDriver::MariaDB => array_map(
            static fn(array $row): string => (string) ($row['name'] ?? ''),
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

      return array_values(array_filter($items, static fn(string $name): bool => trim($name) !== ''));
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
      $relationships = array_map(static fn(array $row): array => [
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
      $relationships = array_map(static fn(array $row): array => [
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
      $primary = array_map(static fn(array $row): string => (string) ($row['column_name'] ?? ''), $pkRows);
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
    * @param string $routingType
    * @return array<int, array<string, mixed>>
    */
   private function buildScaffoldPreview(string $tableName, array $schema, array $artifacts, string $routingType = 'attribute'): array
   {
      $entity = $this->entityNameFromTable($tableName);
      $columns = $schema['columns'];
      $primary = $schema['primary_key'];
      $defaults = $this->defaultLiteralMap($columns);
      $required = $this->requiredColumns($columns);
      $rows = [];
      $resolvedRoutingType = $routingType === 'php' ? 'php' : 'attribute';

      $modelPath = 'app/Models/' . $entity . '.php';
      $modelBasePath = 'app/Models/Base/' . $entity . 'Base.php';
      $repositoryPath = 'app/Repositories/' . $entity . 'Repository.php';
      $repositoryBasePath = 'app/Repositories/Base/' . $entity . 'RepositoryBase.php';
      $servicePath = 'app/Services/' . $entity . 'Service.php';
      $serviceBasePath = 'app/Services/Base/' . $entity . 'ServiceBase.php';
      $controllerPath = 'app/Http/Controllers/' . $entity . 'Controller.php';
      $controllerBasePath = 'app/Http/Controllers/Base/' . $entity . 'ControllerBase.php';
      $routesPath = 'app/Http/Routes/' . $entity . 'Routes.php';
      $routesBasePath = 'app/Http/Routes/Base/' . $entity . 'RoutesBase.php';

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
         $rows = [...$rows, ...$this->previewWrapperFile(
            $modelPath,
            <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Models;

use {$this->namespaceRoot}\\Models\\Base\\{$entity}Base;

/**
 * User-editable model wrapper.
 * Inherits generated metadata constants from {$entity}Base.
 */
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
         $rows = [...$rows, ...$this->previewWrapperFile(
            $repositoryPath,
            <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Repositories;

use {$this->namespaceRoot}\\Repositories\\Base\\{$entity}RepositoryBase;

/**
 * User-editable repository wrapper.
 *
 * @method string table()
 * @method array<int, string> columns()
 */
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
         $rows = [...$rows, ...$this->previewWrapperFile(
            $servicePath,
            <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Services;

use {$this->namespaceRoot}\\Services\\Base\\{$entity}ServiceBase;

/**
 * User-editable service wrapper.
 *
 * Inherited from base:
 * - validateForCreate(array<string, mixed> \$payload): array<string, mixed> (protected)
 */
final class {$entity}Service extends {$entity}ServiceBase
{
}
PHP
         )];
      }

      if (in_array('controller', $artifacts, true)) {
         $controllerBaseContents = $resolvedRoutingType === 'attribute'
            ? <<<PHP
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
PHP
            : <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Http\\Controllers\\Base;

use Celeris\\Framework\\Http\\Request;
use Celeris\\Framework\\Http\\RequestContext;
use Celeris\\Framework\\Http\\Response;

/**
 * @generated by Celeris Tooling. Do not edit this file directly.
 * Source table: {$tableLiteral}
 */
class {$entity}ControllerBase
{
   public function index(RequestContext \$ctx, Request \$request): Response
   {
      return new Response(200, ['content-type' => 'application/json; charset=utf-8'], '{"resource":"{$entityLower}","status":"ok"}');
   }
}
PHP;
         $rows[] = $this->previewFileRow($controllerBasePath, $controllerBaseContents . "\n");
         $rows = [...$rows, ...$this->previewWrapperFile(
            $controllerPath,
            <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Http\\Controllers;

use {$this->namespaceRoot}\\Http\\Controllers\\Base\\{$entity}ControllerBase;

/**
 * User-editable controller wrapper.
 *
 * @method \\Celeris\\Framework\\Http\\Response index(\\Celeris\\Framework\\Http\\RequestContext \$ctx, \\Celeris\\Framework\\Http\\Request \$request)
 */
final class {$entity}Controller extends {$entity}ControllerBase
{
}
PHP
         )];

         if ($resolvedRoutingType === 'php') {
            $routesBaseContents = <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Http\\Routes\\Base;

use {$this->namespaceRoot}\\Http\\Controllers\\{$entity}Controller;
use Celeris\\Framework\\Routing\\RouteCollector;

/**
 * @generated by Celeris Tooling. Do not edit this file directly.
 * Register from bootstrap: {$this->namespaceRoot}\\Http\\Routes\\{$entity}Routes::register(\$kernel->routes());
 */
class {$entity}RoutesBase
{
   public static function register(RouteCollector \$routes): void
   {
      \$routes
         ->controller({$entity}Controller::class)
         ->get('/api/{$entityLower}', 'index');
   }
}
PHP;
            $rows[] = $this->previewFileRow($routesBasePath, $routesBaseContents . "\n");
            $rows = [...$rows, ...$this->previewWrapperFile(
               $routesPath,
               <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespaceRoot}\\Http\\Routes;

use {$this->namespaceRoot}\\Http\\Routes\\Base\\{$entity}RoutesBase;

/**
 * User-editable routes wrapper.
 *
 * @method static void register(\\Celeris\\Framework\\Routing\\RouteCollector \$routes)
 */
final class {$entity}Routes extends {$entity}RoutesBase
{
}
PHP
            )];
         }
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
         $seedFile = $this->seedFileNameForTable($tableName);
         $rows[] = $this->previewFileRow(
            'database/seeds/' . $seedFile . '.php',
            $this->seederContents($tableName, $columns) . "\n"
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
   private function seederContents(string $tableName, array $columns): string
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

return [
   'table' => '{$tableLiteral}',
   'records' => [
{$rowsBlock}
   ],
];
PHP;
   }

   private function seedFileNameForTable(string $tableName): string
   {
      $normalized = strtolower(trim($tableName));
      if ($normalized === '') {
         return 'table';
      }

      $safe = preg_replace('/[^a-z0-9_.-]+/', '_', $normalized);
      if (!is_string($safe) || trim($safe, '._-') === '') {
         return 'table';
      }

      return trim($safe, '._-');
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
    * @return array<string, mixed>
    */
   private function runSeeds(ConnectionInterface $connection, DatabaseDriver $driver, string $target): array
   {
      $seedFiles = $this->resolveSeedFiles($target);
      if ($seedFiles === []) {
         throw new ToolingException('No seed files were found for the requested target.');
      }

      $appliedFiles = [];
      $appliedTables = [];
      $insertedRows = 0;

      $connection->transactional(function (ConnectionInterface $tx) use ($driver, $seedFiles, &$appliedFiles, &$appliedTables, &$insertedRows): void {
         foreach ($seedFiles as $path) {
            $seed = $this->loadSeedFile($path);
            $table = $seed['table'];
            $records = $seed['records'];
            $rows = $this->insertSeedRecords($tx, $driver, $table, $records);
            $insertedRows += $rows;
            $appliedFiles[] = $this->relativeToProject($path);
            $appliedTables[] = $table;
         }
      });

      return [
         'files' => $appliedFiles,
         'tables' => array_values(array_unique($appliedTables)),
         'inserted_rows' => $insertedRows,
      ];
   }

   /**
    * @return array<int, string>
    */
   private function resolveSeedFiles(string $target): array
   {
      $dir = rtrim($this->projectRoot, '/\\') . '/database/seeds';
      if (!is_dir($dir)) {
         return [];
      }

      $entries = scandir($dir);
      if (!is_array($entries)) {
         return [];
      }

      $files = [];
      foreach ($entries as $entry) {
         if ($entry === '.' || $entry === '..') {
            continue;
         }

         $path = $dir . '/' . $entry;
         if (is_file($path) && str_ends_with(strtolower($entry), '.php')) {
            $files[] = $path;
         }
      }

      sort($files, SORT_STRING);
      if ($target === 'all') {
         return $files;
      }

      $requestedFile = $this->seedFileNameForTable($target) . '.php';
      $requestedPath = $dir . '/' . $requestedFile;
      if (is_file($requestedPath)) {
         return [$requestedPath];
      }

      foreach ($files as $path) {
         $seed = $this->loadSeedFile($path);
         if (strtolower($seed['table']) === $target) {
            return [$path];
         }
      }

      return [];
   }

   /**
    * @param string $path
    * @return array{table:string,records:array<int,array<string,mixed>>}
    */
   private function loadSeedFile(string $path): array
   {
      $payload = include $path;
      if (!is_array($payload)) {
         throw new ToolingException(sprintf('Seed file must return an array: %s', $this->relativeToProject($path)));
      }

      $table = $payload['table'] ?? null;
      $records = $payload['records'] ?? null;
      if (!is_string($table) || trim($table) === '') {
         throw new ToolingException(sprintf('Seed file table is missing: %s', $this->relativeToProject($path)));
      }
      if (!is_array($records)) {
         throw new ToolingException(sprintf('Seed file records must be an array: %s', $this->relativeToProject($path)));
      }

      $normalizedRecords = [];
      foreach ($records as $index => $record) {
         if (!is_array($record)) {
            throw new ToolingException(sprintf('Seed record #%d must be an array in %s', $index, $this->relativeToProject($path)));
         }

         $normalized = [];
         foreach ($record as $column => $value) {
            if (!is_string($column) || trim($column) === '') {
               throw new ToolingException(sprintf('Seed record has invalid column key in %s', $this->relativeToProject($path)));
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {
               throw new ToolingException(sprintf('Seed record column has unsupported characters ("%s") in %s', $column, $this->relativeToProject($path)));
            }
            $normalized[$column] = $value;
         }

         $normalizedRecords[] = $normalized;
      }

      return [
         'table' => trim($table),
         'records' => $normalizedRecords,
      ];
   }

   /**
    * @param array<int, array<string, mixed>> $records
    */
   private function insertSeedRecords(ConnectionInterface $connection, DatabaseDriver $driver, string $table, array $records): int
   {
      $count = 0;
      foreach ($records as $record) {
         if ($record === []) {
            continue;
         }

         $columns = [];
         $placeholders = [];
         $params = [];
         $index = 0;
         foreach ($record as $column => $value) {
            $columns[] = $this->quoteIdentifier($column, $driver);
            $param = 'p' . $index;
            $placeholders[] = ':' . $param;
            $params[$param] = $value;
            $index++;
         }

         $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->qualifiedTableName($table, $driver),
            implode(', ', $columns),
            implode(', ', $placeholders)
         );
         $connection->execute($sql, $params);
         $count++;
      }

      return $count;
   }

   private function qualifiedTableName(string $table, DatabaseDriver $driver): string
   {
      $parts = $this->splitSchemaAndTable($table);
      if ($parts['schema'] === '' || $parts['schema'] === 'public') {
         return $this->quoteIdentifier($parts['table'], $driver);
      }

      return $this->quoteIdentifier($parts['schema'], $driver) . '.' . $this->quoteIdentifier($parts['table'], $driver);
   }

   private function quoteIdentifier(string $identifier, DatabaseDriver $driver): string
   {
      $clean = trim($identifier);
      if ($clean === '' || preg_match('/^[A-Za-z0-9_]+$/', $clean) !== 1) {
         throw new ToolingException(sprintf('Unsupported SQL identifier "%s".', $identifier));
      }

      if (in_array($driver, [DatabaseDriver::MySQL, DatabaseDriver::MariaDB], true)) {
         return '`' . $clean . '`';
      }

      return '"' . $clean . '"';
   }

   /**
    * @return array<string, mixed>
    */
   private function clearCacheScope(string $scope): array
   {
      $targets = $this->cacheTargetsForScope($scope);
      $deletedFiles = 0;
      $deletedDirs = 0;
      $missing = 0;
      $cleared = [];
      $skippedOutsideProject = [];

      foreach ($targets as $target) {
         if ($target === '') {
            continue;
         }

         if (!file_exists($target)) {
            $missing++;
            continue;
         }

         if (!$this->isPathWithinProject($target)) {
            $skippedOutsideProject[] = $this->relativeToProject($target);
            continue;
         }

         if (is_dir($target) && !is_link($target)) {
            [$files, $dirs] = $this->clearDirectoryContents($target);
            $deletedFiles += $files;
            $deletedDirs += $dirs;
            $cleared[] = $this->relativeToProject($target);
            continue;
         }

         if (@unlink($target) === false) {
            throw new ToolingException(sprintf('Failed to remove cache file: %s', $target));
         }
         $deletedFiles++;
         $cleared[] = $this->relativeToProject($target);
      }

      if (in_array($scope, ['all', 'route'], true)) {
         Route::clear();
      }

      return [
         'scope' => $scope,
         'cleared' => array_values(array_unique($cleared)),
         'skipped_outside_project' => array_values(array_unique($skippedOutsideProject)),
         'deleted_files' => $deletedFiles,
         'deleted_dirs' => $deletedDirs,
         'missing_paths' => $missing,
      ];
   }

   /**
    * @return array<int, string>
    */
   private function cacheTargetsForScope(string $scope): array
   {
      $root = rtrim($this->projectRoot, '/\\');
      $routeTargets = [
         $root . '/var/cache/routes',
         $root . '/var/cache/routes.php',
         $root . '/var/cache/routes.json',
      ];
      $httpTargets = [$root . '/var/cache/http'];
      $viewTargets = [
         $this->envPath('VIEW_TWIG_CACHE_PATH', $root . '/var/cache/twig'),
         $this->envPath('VIEW_LATTE_TEMP_PATH', $root . '/var/cache/latte'),
      ];

      return match ($scope) {
         'route' => $routeTargets,
         'http' => $httpTargets,
         'view' => $viewTargets,
         default => [$root . '/var/cache'],
      };
   }

   private function envPath(string $envKey, string $default): string
   {
      $raw = $this->envString($envKey);
      if ($raw === '') {
         return $default;
      }

      if (str_starts_with($raw, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $raw) === 1) {
         return $raw;
      }

      return rtrim($this->projectRoot, '/\\') . '/' . ltrim($raw, '/\\');
   }

   private function isPathWithinProject(string $path): bool
   {
      $root = realpath($this->projectRoot);
      $absolute = realpath($path);
      if (!is_string($root) || !is_string($absolute)) {
         return false;
      }

      $root = rtrim($root, '/\\');
      return $absolute === $root || str_starts_with($absolute, $root . DIRECTORY_SEPARATOR);
   }

   /**
    * @return array{0:int,1:int}
    */
   private function clearDirectoryContents(string $dir): array
   {
      $files = 0;
      $dirs = 0;
      $entries = scandir($dir);
      if (!is_array($entries)) {
         throw new ToolingException(sprintf('Failed to scan cache directory: %s', $dir));
      }

      foreach ($entries as $entry) {
         if ($entry === '.' || $entry === '..') {
            continue;
         }

         $path = $dir . '/' . $entry;
         if (is_dir($path) && !is_link($path)) {
            [$childFiles, $childDirs] = $this->clearDirectoryContents($path);
            $files += $childFiles;
            $dirs += $childDirs;
            if (@rmdir($path) === false) {
               throw new ToolingException(sprintf('Failed to remove cache directory: %s', $path));
            }
            $dirs++;
            continue;
         }

         if (@unlink($path) === false) {
            throw new ToolingException(sprintf('Failed to remove cache file: %s', $path));
         }
         $files++;
      }

      return [$files, $dirs];
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
      $rest = array_map(static fn(string $part): string => ucfirst($part), $parts);
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
      $explicit = $this->envFlag(self::WEB_ENABLED_KEY);
      if ($explicit !== null) {
         return $explicit;
      }

      $explicit = $this->envFlag(self::LEGACY_ENABLED_KEY);
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
    * @param array{generator:string,name:string,module:string,routing_type:string,overwrite:bool} $args
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
         'routing_type' => $args['routing_type'],
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
    * @param string $routingType
    * @param array<int, string> $written
    * @param array<int, string> $skipped
    */
   private function auditScaffoldApply(
      RequestContext $ctx,
      Request $request,
      string $connection,
      string $table,
      array $artifacts,
      string $routingType,
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
         'routing_type' => $routingType,
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

   private function appKeyManager(): AppKeyManager
   {
      if ($this->appKeyManager === null) {
         $this->appKeyManager = new AppKeyManager();
      }

      return $this->appKeyManager;
   }

   private function routeInspector(): ProjectRouteInspector
   {
      if ($this->routeInspector === null) {
         $this->routeInspector = new ProjectRouteInspector();
      }

      return $this->routeInspector;
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

      $parts = array_map(static fn(string $item): string => strtolower(trim($item)), explode(',', $raw));
      return array_values(array_filter($parts, static fn(string $item): bool => $item !== ''));
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
