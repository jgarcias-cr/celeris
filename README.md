# Celeris

Celeris is a PHP 8.4 framework for building backend software with explicit architecture, deterministic runtime behavior, and one programming model for both API and MVC applications.

It is designed for teams that want clear composition, strict typing, and runtime safety without giving up performance or long-lived worker support.

## What Celeris Includes

- `celeris/framework` — the reusable core framework package
- `celeris/api` — an API starter project installed with `composer create-project`
- `celeris/mvc` — an MVC starter project installed with `composer create-project`
- optional first-party packages for queues, notifications, realtime delivery, and worker tooling

## Core Ideas

- Explicit dependency injection through a container and service providers
- Deterministic request handling with clear middleware, routing, and response finalization order
- Worker-safe lifecycle management through a shared `Kernel + WorkerRunner` model
- One framework model for `FPM`, native worker mode, and adapter-based runtimes such as RoadRunner or Swoole
- Strong package boundaries so optional integrations do not bloat the core

## Features

- API-first architecture with support for pure APIs, MVC apps, or hybrid applications
- Strict typing and framework-native contracts
- Routing, middleware, controllers, and service provider composition
- Configuration loading and environment-based setup
- Security pipeline with pre-checks and finalizers
- Validation and serialization support
- Database stack with DBAL, ORM, migrations, and optional Active Record compatibility
- View layer support for MVC applications without forcing a template engine into core
- Native worker runtime support for long-lived PHP processes
- CLI tooling through `vendor/bin/celeris`
- Optional queue, outbox, SMTP, in-app notification, and realtime gateway packages

## Advantages

- Safer long-lived process execution because request cleanup and lifecycle boundaries are explicit
- Less hidden framework magic, which makes behavior easier to reason about and debug
- Easier scaling from simple CRUD apps to larger modular systems
- Same mental model for API and MVC projects, reducing team context switching
- Optional package model keeps the core lightweight while allowing richer capabilities when needed

## Quick Start

Create an API project:

```bash
composer create-project celeris/api my-api
```

Create an MVC project:

```bash
composer create-project celeris/mvc my-mvc
```

In both cases, Composer installs `celeris/framework` into `vendor/celeris/framework`.

## Package Layout

- `packages/framework` — core framework
- `packages/api-stub` — API scaffold
- `packages/mvc-stub` — MVC scaffold
- `packages/queue-manager` — queue and scheduling support
- `packages/notification-*` — notification, outbox, dispatch, SMTP, and realtime integration packages

## When Celeris Fits Well

- Internal APIs and service backends
- MVC applications that want explicit architecture instead of heavy convention
- Hybrid systems serving both HTML and JSON
- Teams that care about worker-mode correctness and predictable runtime behavior

## Documentation

- [User manual](docs/user-manual.md)
- [Architecture blueprint](docs/architecture-blueprint.md)
- [Project layout](docs/project-layout.md)
- [Local project install](docs/local-project-install.md)

## Repository Model

This repository is the development workspace for Celeris. The publishable packages are split into independent repositories for Packagist:

- `celeris/framework`
- `celeris/api`
- `celeris/mvc`

The root package exists for local development and package orchestration.
