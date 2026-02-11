# ADR 001 — RequestContext design

Status: Proposed

Context
  - API-first, stateless-by-default demands an explicit request container for per-request state.

Decision
  - Introduce an immutable `RequestContext` value-object created at the start of request handling.
  - `RequestContext` contains: `request_id`, `timestamp`, `server_params` (immutable), `auth` (nullable), `route_metadata` (immutable), `deadline` (optional), and a typed `attributes` bag for small, explicit extensions.
  - Do NOT allow global mutation of `RequestContext`. Extensions must declare the keys they use and accept a new context when mutation is needed (functional update).

Consequences
  - Promotes explicit passing of context via DI or function args; avoids hidden globals and facilitates testing.
  - Small attribute bag keeps framework extensible without permitting arbitrary global state.
