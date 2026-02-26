# ADR 008 — DB-first tooling scaffolding workflow

Status: Proposed

Context
  - Current tooling generators are template-driven (`generator`, `name`, `module`) and are not database-introspective.
  - Teams frequently start from existing relational schemas and need accelerated code generation aligned with table constraints and relationships.
  - Existing tooling already provides a safe preview/apply model, audit logging, and wrapper/base split conventions; new capabilities should reuse these safety guarantees.
  - The web tooling UX should remain fast and deterministic while supporting multi-file generation decisions.

Decision
  - Introduce a DB-first scaffolding workflow in tooling web/API:
    - Select connection.
    - Select table.
    - Inspect schema metadata (columns, PK, FK, nullability, defaults, uniqueness, indexes).
    - Select artifacts from a checkbox list (for example: model, repository, service, controller, request/response DTOs).
    - Preview generated files per file tab.
    - Apply selected files with overwrite policy.
  - Add schema-introspection support through DBAL-backed metadata readers, keyed by configured connection names.
  - Extend generator contracts to support table-aware requests while preserving backward compatibility for existing generators.
  - Keep generation safety model unchanged:
    - Base/generated files are always regenerable.
    - User wrapper files are created once when possible and not overwritten unless explicitly requested.
  - Keep apply operations auditable through existing tooling audit pipeline.

Planned API shape (web tooling v1 extension)
  - `GET /__dev/tooling/api/v1/schema/connections`
  - `GET /__dev/tooling/api/v1/schema/tables?connection=<name>`
  - `GET /__dev/tooling/api/v1/schema/tables/{table}?connection=<name>`
  - `POST /__dev/tooling/api/v1/scaffold/preview`
  - `POST /__dev/tooling/api/v1/scaffold/apply`

  Request payload (preview/apply, indicative):
  - `connection`: string
  - `table`: string
  - `artifacts`: string[] (`model`, `repository`, `service`, `controller`, `dto.request`, `dto.response`, ...)
  - `module`: string (optional override)
  - `name`: string (optional override)
  - `overwrite`: bool

Compatibility and migration
  - Existing CLI and web generator endpoints remain operational.
  - DB-first scaffolding is additive and does not replace current `generate` commands in initial phases.
  - Current generator implementations continue to work without schema metadata.

Non-goals
  - Full database migration authoring and DDL editing from tooling UI.
  - ORM-specific behavior generation beyond agreed scaffold templates.
  - Arbitrary code transformation/refactor of existing handwritten classes.

Consequences
  - Faster onboarding for schema-first projects and legacy database adoption.
  - Higher implementation complexity around cross-engine schema normalization.
  - Additional validation and test requirements for foreign keys, composite keys, and naming strategies.

Acceptance criteria
  - User can select connection and table from tooling UI.
  - Tooling preview returns per-file drafts for selected artifacts based on schema metadata.
  - Preview UI supports tabbed per-file inspection before apply.
  - Apply writes only selected files and honors overwrite policy.
  - Audit log includes connection/table/artifacts metadata for apply operations.
  - Existing non-schema generators continue to behave unchanged.
