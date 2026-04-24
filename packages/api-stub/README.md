# Celeris API Stub

`celeris/api` is a base application scaffold for building REST APIs and service-oriented backends on top of `celeris/framework`.

It gives you a practical starting point instead of an empty shell: a bootstrapped HTTP entrypoint, API controllers, DTOs, services, repositories, config files, migrations, seeded example data, and optional notification worker hooks.

## What It Is For

Use this package when you want to start a new API quickly without rebuilding the same foundation each time.

It is a good fit for:

- internal business APIs
- service-oriented backends
- CRUD-heavy applications
- authenticated JSON APIs
- projects that may later grow into event-driven or notification-enabled services

## Included Features

- API-first bootstrap with a JSON root endpoint and `/health` style service readiness flow
- Example auth endpoints under `/api/auth` for login and identity inspection
- Example resource endpoints under `/api/contacts`
- Attribute-based routing through Celeris controllers
- DTO-driven request input mapping for create and update flows
- Service and repository layers already separated for easier maintenance
- Database configuration, migrations, and seed data out of the box
- Environment-based security toggles for JWT, opaque tokens, cookie sessions, API tokens, and mTLS
- Built-in rate-limit configuration
- Optional notification integrations for SMTP, in-app notifications, transactional outbox, realtime delivery, and dispatch workers
- CLI wrappers for migrations and worker scripts

## Advantages

- Faster project setup: you can begin with working application structure immediately
- Clear boundaries: controllers, services, repositories, DTOs, config, and bootstrap are already organized
- Safer customization: generated base classes and extension points make it easier to evolve scaffolded code cleanly
- Production-friendly direction: auth, rate limiting, migrations, and notification workflows are already anticipated
- Monorepo-friendly local development: the stub can run against the local framework package during development

## Default API Surface

The scaffold currently exposes these starter endpoints:

- `GET /` returns basic API and framework metadata
- `POST /api/auth/login` authenticates the demo user and returns a token payload
- `GET /api/auth/me` returns the authenticated identity
- `GET /api/contacts` lists contacts
- `GET /api/contacts/{id}` fetches one contact
- `POST /api/contacts` creates a contact
- `PUT /api/contacts/{id}` updates a contact
- `DELETE /api/contacts/{id}` deletes a contact

Treat these as a starting point. They are intentionally simple so you can replace, extend, or remove them as your real domain takes shape.

## Quick Start

Create a project from the package:

```bash
composer create-project celeris/api my-api
cd my-api
cp .env.example .env
composer install
php celeris app-key
php bin/migrate.php up
php -S 127.0.0.1:8000 -t public
```

If you are working inside the Celeris monorepo, the scaffold can also fall back to the local framework bootstrap and CLI binary during development.

## Project Structure

- `public/index.php` boots the HTTP kernel and registers API controllers
- `app/Http/Controllers/Api` contains your API controllers
- `app/Http/DTOs` contains request DTOs
- `app/Services` holds application use cases
- `app/Repositories` contains persistence-facing code
- `database/migrations` contains schema changes
- `database/seeds` contains sample seed data
- `config` centralizes environment-driven app behavior
- `bin` contains migration and notification worker helpers

## Notifications and Background Work

The stub is ready for optional notification packages, but keeps them disabled by default. That means you can start lean and only enable the pieces you actually need.

When those packages are installed, the scaffold already includes entrypoints such as:

- `php bin/notifications-dispatch-worker.php`
- `php bin/notifications-replay-dead-letter.php`

This makes the package a nice fit for APIs that may grow from simple synchronous CRUD into more resilient event-driven workflows.

## Recommendations

- Replace the sample `Contact` domain early with your real bounded context
- Copy `.env.example` to `.env` and explicitly review all security flags before deploying
- Set `APP_KEY` and `JWT_SECRET` before enabling JWT authentication
- Keep auth strategies opt-in; only enable the ones your service truly needs
- Treat the demo credentials as development-only scaffolding
- Add your domain-specific tests as soon as you start replacing the example endpoints
- If you adopt the outbox flow, run the dispatch worker separately from the HTTP process

## Tip

The best way to use this stub is not to preserve every sample file, but to preserve the structure. Replace the demo domain quickly, keep the layering, and let the scaffold accelerate your first real feature instead of becoming long-lived placeholder code.
